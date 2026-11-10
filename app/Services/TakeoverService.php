<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetStatusHistory;
use App\Models\Company;
use App\Models\PlazaTakeover;
use App\Models\PlazaTakeoverLine;
use App\Models\Setting;
use App\Models\User;
use App\Support\CompanyVisibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Toll-plaza takeover (Phase 3).
 *
 *  kind = nhai      NHAI hands over its assets: company = NHAI, operating company = ours,
 *                   no price, never depreciated. Lines mirror the NHAI checklist (TP-02).
 *  kind = purchase  We buy assets from the previous agency / another party: company = ours,
 *                   priced per unit, depreciated from the takeover date.
 *
 * Draft (lines edited by hand or imported) → submit() expands every line into DRAFT assets
 * (quantity 17 → 17 assets) and opens ONE `takeover` approval → apply() puts them live;
 * markRejected() deletes the never-live drafts so the takeover can be fixed and resubmitted.
 */
class TakeoverService
{
    /** Safety cap — a checklist this size is a mistake (e.g. a quantity typo), not a plaza. */
    public const MAX_ASSETS = 3000;

    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly AssetService $assets,
        private readonly DepreciationService $depreciation,
    ) {
    }

    public function createDraft(array $data, User $actor): PlazaTakeover
    {
        $this->assertParties($data);

        return DB::transaction(fn () => PlazaTakeover::create($data + [
            'reference_no' => $this->nextReference('TKO'),
            'status'       => 'draft',
            'requested_by' => $actor->id,
        ]));
    }

    public function updateDraft(PlazaTakeover $takeover, array $data): PlazaTakeover
    {
        $this->assertDraft($takeover);
        $this->assertParties($data + ['kind' => $takeover->kind]);
        $takeover->update($data);

        return $takeover;
    }

    public function addLine(PlazaTakeover $takeover, array $line): PlazaTakeoverLine
    {
        $this->assertDraft($takeover);
        $line = $this->normaliseLine($takeover, $line);

        return $takeover->lines()->create($line + ['line_no' => (int) $takeover->lines()->max('line_no') + 1]);
    }

    public function removeLine(PlazaTakeover $takeover, PlazaTakeoverLine $line): void
    {
        $this->assertDraft($takeover);
        abort_unless($line->takeover_id === $takeover->id, 404);
        $line->delete();
    }

    /**
     * Replace nothing — append every validated row. All-or-nothing: one bad row and no row is
     * added, so a half-imported checklist never reaches the approver.
     *
     * @param array<int, array<string, mixed>> $rows already resolved by TakeoverLinesImport
     */
    public function addLines(PlazaTakeover $takeover, array $rows): int
    {
        $this->assertDraft($takeover);

        return DB::transaction(function () use ($takeover, $rows) {
            foreach ($rows as $row) {
                $this->addLine($takeover, $row);
            }

            return count($rows);
        });
    }

    /** Expand lines into DRAFT assets and submit the whole takeover for one approval. */
    public function submit(PlazaTakeover $takeover, User $actor): PlazaTakeover
    {
        $this->assertDraft($takeover);
        $takeover->load('lines', 'location');

        if ($takeover->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line before submitting.']);
        }

        if ($takeover->totalUnits() > self::MAX_ASSETS) {
            throw ValidationException::withMessages([
                'lines' => 'This takeover would create '.$takeover->totalUnits().' assets (limit '.self::MAX_ASSETS.'). Check the quantities.',
            ]);
        }

        $draftId = AssetStatus::firstOrCreate(['code' => 'DRAFT'], ['name' => 'Draft', 'color' => '#9ca3af', 'is_system' => true, 'is_active' => true])->id;

        return DB::transaction(function () use ($takeover, $actor, $draftId) {
            foreach ($takeover->lines as $line) {
                $this->expandLine($takeover, $line, $actor, $draftId);
            }

            $approval = $this->workflows->submit($takeover, 'takeover', $actor);
            $takeover->update(['status' => 'pending_approval', 'approval_request_id' => $approval->id]);

            return $takeover;
        });
    }

    /** ApplyTakeover (terminal approval): put every draft live; depreciate purchased ones. */
    public function apply(PlazaTakeover $takeover, User $approver): void
    {
        CompanyVisibility::unrestricted(function () use ($takeover, $approver) {
            DB::transaction(function () use ($takeover, $approver) {
                $draftId = AssetStatus::where('code', 'DRAFT')->value('id');
                $assignedId = AssetStatus::where('code', 'ASSIGNED')->value('id');
                $availableId = AssetStatus::where('code', 'AVAILABLE')->value('id');

                foreach ($takeover->assets()->get() as $asset) {
                    if ((int) $asset->status_id !== (int) $draftId) {
                        continue;
                    }

                    $live = $asset->custodian_id ? ($assignedId ?? $availableId) : $availableId;

                    AssetStatusHistory::create([
                        'asset_id'       => $asset->id,
                        'from_status_id' => $asset->status_id,
                        'to_status_id'   => $live,
                        'changed_by'     => $approver->id,
                        'reason'         => "Plaza takeover {$takeover->reference_no} approved",
                        'created_at'     => now(),
                    ]);
                    $asset->update(['status_id' => $live]);

                    if (! $takeover->isNhai()) {
                        $this->depreciation->applyCategoryDefault($asset->fresh());
                    }
                }

                $takeover->update(['status' => 'approved', 'approved_at' => now()]);
            });
        });
    }

    /** MarkTakeoverRejected: the drafts were never live — remove them (frees their serials). */
    public function markRejected(PlazaTakeover $takeover): void
    {
        CompanyVisibility::unrestricted(function () use ($takeover) {
            DB::transaction(function () use ($takeover) {
                foreach ($takeover->assets()->get() as $asset) {
                    $asset->fieldValues()->delete();
                    $asset->statusHistories()->delete();
                    $asset->forceDelete();
                }

                $takeover->update(['status' => 'rejected']);
            });
        });
    }

    /** A rejected takeover goes back to draft for correction and resubmission. */
    public function reopen(PlazaTakeover $takeover): void
    {
        if ($takeover->status !== 'rejected') {
            throw ValidationException::withMessages(['status' => 'Only a rejected takeover can be reopened.']);
        }

        $takeover->update(['status' => 'draft', 'approval_request_id' => null]);
    }

    // ------------------------------------------------------------------ internals

    private function expandLine(PlazaTakeover $takeover, PlazaTakeoverLine $line, User $actor, int $draftId): void
    {
        $isNhai = $takeover->isNhai();
        $warrantyExpiry = $line->warranty_years
            ? Carbon::parse($takeover->takeover_date)->addMonths((int) round((float) $line->warranty_years * 12))->toDateString()
            : null;

        for ($i = 1; $i <= max(1, $line->quantity); $i++) {
            $notFunctional = $i <= $line->not_functional_qty;
            $name = trim($line->item_name.($line->position_label ? ' — '.$line->position_label : '')
                .($line->quantity > 1 ? " #{$i}" : ''));

            $asset = $this->assets->create([
                'name'                 => $name,
                'serial_number'        => $line->quantity === 1 ? $line->serial_number : null,
                'model'                => $line->model,
                'manufacturer'         => $line->manufacturer,
                'company_id'           => $isNhai ? $takeover->from_company_id : $takeover->company_id,
                'operating_company_id' => $isNhai ? $takeover->company_id : null,
                'category_id'          => $line->category_id,
                'asset_type_id'        => $line->asset_type_id ?? $this->defaultTypeId(),
                'status_id'            => $draftId,
                'location_id'          => $takeover->location_id,
                'building_id'          => $line->building_id,
                'floor_id'             => $line->floor_id,
                'room_id'              => $line->room_id,
                'custodian_id'         => $takeover->custodian_id,
                'purchase_date'        => $takeover->takeover_date,
                'purchase_cost'        => $isNhai ? null : $line->unit_price,
                'vendor'               => $isNhai ? null : $takeover->fromCompany?->name,
                'warranty_expiry'      => $warrantyExpiry,
                'condition'            => $notFunctional ? 'not_functional' : $line->condition,
                'condition_remarks'    => $line->remarks,
                'notes'                => trim(implode(' · ', array_filter([$line->section, $line->location_note]))) ?: null,
                'takeover_line_id'     => $line->id,
            ], $actor);
        }
    }

    /** Checklist rows rarely name an asset type — file them as Equipment. */
    private function defaultTypeId(): int
    {
        return \App\Models\AssetType::where('code', 'EQUIPMENT')->value('id')
            ?? \App\Models\AssetType::firstOrCreate(['code' => 'EQUIPMENT'], ['name' => 'Equipment', 'is_active' => true])->id;
    }

    private function normaliseLine(PlazaTakeover $takeover, array $line): array
    {
        $line['quantity'] = max(1, (int) ($line['quantity'] ?? 1));
        $line['not_functional_qty'] = min($line['quantity'], max(0, (int) ($line['not_functional_qty'] ?? 0)));
        $line['condition'] = $line['condition'] ?? 'functional';

        if (! empty($line['serial_number']) && $line['quantity'] > 1) {
            throw ValidationException::withMessages(['serial_number' => 'A serial number belongs to a single unit — use quantity 1 per serial.']);
        }

        // NHAI is never monetary; a purchase always is.
        if ($takeover->isNhai()) {
            $line['unit_price'] = null;
        } elseif (! isset($line['unit_price']) || $line['unit_price'] === '' || $line['unit_price'] === null) {
            throw ValidationException::withMessages(['unit_price' => "Enter the purchase price for \"{$line['item_name']}\"."]);
        }

        if (! empty($line['serial_number'])) {
            (new \App\Rules\UniqueSerialNumber)->validate('serial_number', $line['serial_number'], function ($message) {
                throw ValidationException::withMessages(['serial_number' => $message]);
            });

            $dupe = PlazaTakeoverLine::where('takeover_id', $takeover->id)
                ->whereRaw('LOWER(serial_number) = ?', [mb_strtolower(trim($line['serial_number']))])->exists();
            if ($dupe) {
                throw ValidationException::withMessages(['serial_number' => "Serial {$line['serial_number']} is already on this takeover."]);
            }
        }

        return $line;
    }

    private function assertParties(array $data): void
    {
        $from = Company::find($data['from_company_id'] ?? null);
        $ours = Company::find($data['company_id'] ?? null);

        if ($ours && $ours->isExternal()) {
            throw ValidationException::withMessages(['company_id' => 'The operating company must be one of our own companies.']);
        }

        if (($data['kind'] ?? null) === 'nhai' && $from && ! $from->isAuthority()) {
            throw ValidationException::withMessages(['from_company_id' => 'A takeover from NHAI must name the authority (NHAI) as the party handing over.']);
        }
    }

    private function assertDraft(PlazaTakeover $takeover): void
    {
        if (! $takeover->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only a draft takeover can be changed.']);
        }
    }

    /** Row-locked counter (TKO-0001 / HNO-0001), same pattern as AssetNamingService. */
    public function nextReference(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $key = 'plaza_ref_next_'.strtolower($prefix);
            Setting::firstOrCreate(['key' => $key], ['value' => '1']);
            $row = Setting::where('key', $key)->lockForUpdate()->first();
            $n = (int) $row->value;
            $row->update(['value' => (string) ($n + 1)]);

            return sprintf('%s-%04d', $prefix, $n);
        });
    }
}
