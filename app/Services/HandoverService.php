<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStatus;
use App\Models\AssetStatusHistory;
use App\Models\Company;
use App\Models\MovementType;
use App\Models\PlazaHandover;
use App\Models\PlazaHandoverLine;
use App\Models\User;
use App\Support\CompanyVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Contract-end handover of a toll plaza (Phase 3).
 *
 * Draft lists every asset at the plaza that our company owns or operates:
 *   NHAI-owned → return_to_nhai (fixed; no money).
 *   ours       → give_to_nhai (default suggestion: written off at zero proceeds),
 *                sell (price + buyer, never NHAI — NHAI is never monetary),
 *                or move (to another plaza — an approved movement that goes In Transit).
 * One `handover` approval covers the whole plaza; apply() then runs line by line, each line
 * idempotent (applied_at), so a failure part-way can be retried without double-applying.
 */
class HandoverService
{
    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly DisposalService $disposals,
        private readonly DepreciationService $depreciation,
        private readonly NotificationService $notifications,
    ) {
    }

    public function createDraft(array $data, User $actor): PlazaHandover
    {
        $ours = Company::findOrFail($data['company_id']);
        if ($ours->isExternal()) {
            throw ValidationException::withMessages(['company_id' => 'Pick the company of ours that is handing the plaza over.']);
        }

        $data['authority_company_id'] ??= Company::where('party_type', 'external_authority')->value('id');
        if (! $data['authority_company_id']) {
            throw ValidationException::withMessages(['authority_company_id' => 'No authority (NHAI) is configured in Companies.']);
        }

        return DB::transaction(function () use ($data, $actor) {
            $handover = PlazaHandover::create($data + [
                'reference_no' => app(TakeoverService::class)->nextReference('HNO'),
                'status'       => 'draft',
                'requested_by' => $actor->id,
            ]);

            $this->refreshLines($handover);

            return $handover;
        });
    }

    /** (Re)build the line list from what is at the plaza now, keeping choices already made. */
    public function refreshLines(PlazaHandover $handover): void
    {
        $assets = CompanyVisibility::unrestricted(fn () => Asset::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('location_id', $handover->location_id)
            ->where(fn ($q) => $q->where('company_id', $handover->company_id)->orWhere('operating_company_id', $handover->company_id))
            ->whereDoesntHave('status', fn ($q) => $q->whereIn('code', AssetStatus::TERMINAL))
            ->with('company')
            ->get());

        $existing = $handover->lines()->get()->keyBy('asset_id');

        foreach ($assets as $asset) {
            if ($existing->has($asset->id)) {
                continue;
            }

            $setting = $asset->activeDepreciationSetting();

            $handover->lines()->create([
                'asset_id'     => $asset->id,
                'action'       => $asset->isExternallyOwned() ? 'return_to_nhai' : 'give_to_nhai',
                'condition'    => $asset->condition,
                'nbv_snapshot' => $setting ? $this->depreciation->netBookValue($setting, now()) : null,
            ]);
        }

        // Assets that left the plaza since the draft was made drop off the list.
        $handover->lines()->whereNotIn('asset_id', $assets->pluck('id'))->whereNull('applied_at')->delete();
    }

    /**
     * Save per-line choices from the draft screen.
     *
     * @param array<int|string, array<string, mixed>> $input keyed by line id
     */
    public function updateLines(PlazaHandover $handover, array $input): void
    {
        $this->assertDraft($handover);

        DB::transaction(function () use ($handover, $input) {
            foreach ($handover->lines()->with('asset.company')->get() as $line) {
                if (! isset($input[$line->id])) {
                    continue;
                }

                $row = $input[$line->id];
                $line->fill([
                    'action'           => $row['action'] ?? $line->action,
                    'sale_price'       => ($row['sale_price'] ?? '') === '' ? null : $row['sale_price'],
                    'buyer_company_id' => $row['buyer_company_id'] ?? null ?: null,
                    'buyer_name'       => $row['buyer_name'] ?? null ?: null,
                    'to_location_id'   => $row['to_location_id'] ?? null ?: null,
                    'to_custodian_id'  => $row['to_custodian_id'] ?? null ?: null,
                    'condition'        => $row['condition'] ?? $line->condition,
                    'remarks'          => $row['remarks'] ?? $line->remarks,
                ]);

                $this->assertLine($handover, $line, strict: false);
                $line->save();
            }
        });
    }

    /** @return array<int, string> asset id => why it can't be handed over yet */
    public function blockers(PlazaHandover $handover): array
    {
        $blockers = [];

        foreach ($handover->lines()->with('asset.status')->get() as $line) {
            $asset = $line->asset;
            $reason = match (true) {
                ! $asset                       => 'asset no longer exists',
                $asset->isDraft()              => 'still a draft (creation approval)',
                $asset->isInTransit()          => 'in transit — receive it first',
                $asset->hasPendingMovement()   => 'has a movement pending approval',
                $asset->hasPendingDisposal()   => 'has a disposal in progress',
                $asset->hasPendingLoss()       => 'has a loss write-off pending',
                default                        => null,
            };

            if ($reason) {
                $blockers[$line->asset_id] = $reason;
            }
        }

        return $blockers;
    }

    public function submit(PlazaHandover $handover, User $actor): PlazaHandover
    {
        $this->assertDraft($handover);
        $handover->load('lines.asset.company');

        if ($handover->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'There are no assets at this plaza to hand over.']);
        }

        if ($blockers = $this->blockers($handover)) {
            throw ValidationException::withMessages(['lines' => count($blockers).' asset(s) cannot be handed over yet: '
                .collect($blockers)->map(fn ($why, $id) => Asset::labelFor(Asset::withoutGlobalScopes()->find($id)).' ('.$why.')')->take(5)->implode('; ')]);
        }

        foreach ($handover->lines as $line) {
            $this->assertLine($handover, $line, strict: true);

            $elsewhere = PlazaHandoverLine::where('asset_id', $line->asset_id)
                ->where('handover_id', '!=', $handover->id)
                ->whereHas('handover', fn ($q) => $q->where('status', 'pending_approval'))->exists();
            if ($elsewhere) {
                throw ValidationException::withMessages(['lines' => Asset::labelFor($line->asset).' is already in another pending handover.']);
            }
        }

        return DB::transaction(function () use ($handover, $actor) {
            $approval = $this->workflows->submit($handover, 'handover', $actor);
            $handover->update(['status' => 'pending_approval', 'approval_request_id' => $approval->id, 'apply_error' => null]);

            return $handover;
        });
    }

    /**
     * ApplyHandover (terminal approval). Each line in its own transaction and idempotent, so a
     * failure leaves the handover `apply_failed` with the error, ready for retry().
     */
    public function apply(PlazaHandover $handover, User $approver): void
    {
        CompanyVisibility::unrestricted(function () use ($handover, $approver) {
            try {
                foreach ($handover->lines()->whereNull('applied_at')->with('asset.company')->get() as $line) {
                    DB::transaction(fn () => $this->applyLine($handover, $line, $approver));
                }

                $handover->update(['status' => 'approved', 'approved_at' => now(), 'apply_error' => null]);
            } catch (\Throwable $e) {
                Log::error('Plaza handover apply failed', ['handover' => $handover->id, 'error' => $e->getMessage()]);
                $handover->update(['status' => 'apply_failed', 'apply_error' => $e->getMessage()]);

                $recipients = collect([$handover->requestedBy])->filter();
                $this->notifications->sendMany($recipients, 'handover.apply_failed', [
                    'reference' => $handover->reference_no,
                    'error'     => $e->getMessage(),
                    'url'       => route('nhai.handovers.show', $handover),
                ]);
            }
        });
    }

    public function retry(PlazaHandover $handover, User $actor): void
    {
        if ($handover->status !== 'apply_failed') {
            throw ValidationException::withMessages(['status' => 'Only a handover whose apply failed can be retried.']);
        }

        $this->apply($handover, $actor);
    }

    public function markRejected(PlazaHandover $handover): void
    {
        $handover->update(['status' => 'rejected']);
    }

    public function reopen(PlazaHandover $handover): void
    {
        if ($handover->status !== 'rejected') {
            throw ValidationException::withMessages(['status' => 'Only a rejected handover can be reopened.']);
        }

        $handover->update(['status' => 'draft', 'approval_request_id' => null]);
        $this->refreshLines($handover);
    }

    // ------------------------------------------------------------------ internals

    private function applyLine(PlazaHandover $handover, PlazaHandoverLine $line, User $approver): void
    {
        $asset = Asset::withoutGlobalScopes()->findOrFail($line->asset_id);
        $requester = $handover->requestedBy ?? $approver;
        $ref = $handover->reference_no;

        switch ($line->action) {
            case 'return_to_nhai':
                $this->setStatus($asset, 'RETURNED', $approver, "Returned to NHAI at handover {$ref}");
                $asset->update(['custodian_id' => null, 'operating_company_id' => null, 'is_active' => false]);
                break;

            case 'sell':
                $buyer = $line->buyer?->name ?? $line->buyer_name ?? 'buyer';
                $disposal = $this->disposals->createApproved($asset, 'SELL', "Sold to {$buyer} at plaza handover {$ref}", $requester, $handover->approval_request_id);
                $this->disposals->writeOff($disposal, ['disposal_value' => (float) $line->sale_price], $approver);
                $this->disposals->finalize($disposal, $approver, 'SOLD_EXT', "Sold to {$buyer} at handover {$ref}");
                $asset->update(['custodian_id' => null, 'is_active' => false]);
                $line->disposal_request_id = $disposal->id;
                break;

            case 'give_to_nhai':
                $disposal = $this->disposals->createApproved($asset, 'WRITE_OFF', "Handed over to NHAI at plaza handover {$ref}", $requester, $handover->approval_request_id);
                $this->disposals->writeOff($disposal, ['disposal_value' => 0], $approver);
                $this->disposals->finalize($disposal, $approver, 'HANDED_OVER', "Handed over to NHAI at {$ref}");
                $asset->update(['custodian_id' => null, 'is_active' => false]);
                $line->disposal_request_id = $disposal->id;
                break;

            case 'move':
                $type = MovementType::firstOrCreate(['code' => 'TRANSFER'], ['name' => 'Transfer', 'is_active' => true]);
                $movement = AssetMovement::create([
                    'asset_id'           => $asset->id,
                    'movement_type_id'   => $type->id,
                    'from_company_id'    => $asset->company_id,
                    'from_location_id'   => $asset->location_id,
                    'to_location_id'     => $line->to_location_id,
                    'from_custodian_id'  => $asset->custodian_id,
                    'to_custodian_id'    => $line->to_custodian_id,
                    'from_department_id' => $asset->department_id,
                    'status'             => 'pending_approval',
                    'notes'              => "Moved at plaza handover {$ref}",
                    'approval_request_id'=> $handover->approval_request_id,
                    'requested_by'       => $requester->id,
                ]);
                // The handover approval is the movement's approval: dispatch → In Transit,
                // received at the destination plaza through the normal receipt flow.
                app(MovementService::class)->apply($movement);
                $line->movement_id = $movement->id;
                break;

            default:
                throw new \RuntimeException("Line for asset {$asset->id} has no action.");
        }

        $line->applied_at = now();
        $line->save();
    }

    private function assertLine(PlazaHandover $handover, PlazaHandoverLine $line, bool $strict): void
    {
        $asset = $line->asset;
        $label = Asset::labelFor($asset);
        $external = (bool) $asset?->isExternallyOwned();

        if (! array_key_exists((string) $line->action, PlazaHandover::ACTIONS)) {
            throw ValidationException::withMessages(['lines' => "Choose what happens to {$label}."]);
        }

        if ($external && $line->action !== 'return_to_nhai') {
            throw ValidationException::withMessages(['lines' => "{$label} belongs to NHAI and can only be returned."]);
        }

        if (! $external && $line->action === 'return_to_nhai') {
            throw ValidationException::withMessages(['lines' => "{$label} is ours — sell it, give it to NHAI, or move it."]);
        }

        if ($line->action === 'sell') {
            $buyer = $line->buyer_company_id ? Company::find($line->buyer_company_id) : null;

            if ($buyer?->isAuthority()) {
                throw ValidationException::withMessages(['lines' => "{$label}: nothing is ever sold to NHAI — use \"Give to NHAI\" instead."]);
            }

            if ($strict && ($line->sale_price === null || (float) $line->sale_price < 0)) {
                throw ValidationException::withMessages(['lines' => "{$label}: enter the sale price."]);
            }

            if ($strict && ! $buyer && blank($line->buyer_name)) {
                throw ValidationException::withMessages(['lines' => "{$label}: name the buyer."]);
            }
        } elseif ($line->sale_price !== null && (float) $line->sale_price !== 0.0) {
            throw ValidationException::withMessages(['lines' => "{$label}: a price is only recorded for a sale."]);
        }

        if ($line->action === 'move' && $strict
            && (! $line->to_location_id || (int) $line->to_location_id === (int) $handover->location_id)) {
            throw ValidationException::withMessages(['lines' => "{$label}: pick the other plaza it moves to."]);
        }
    }

    private function setStatus(Asset $asset, string $code, User $actor, string $reason): void
    {
        $status = AssetStatus::where('code', $code)->first();

        if (! $status || $status->id === $asset->status_id) {
            return;
        }

        AssetStatusHistory::create([
            'asset_id'       => $asset->id,
            'from_status_id' => $asset->status_id,
            'to_status_id'   => $status->id,
            'changed_by'     => $actor->id,
            'reason'         => $reason,
            'created_at'     => now(),
        ]);

        $asset->update(['status_id' => $status->id]);
    }

    private function assertDraft(PlazaHandover $handover): void
    {
        if (! $handover->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only a draft handover can be changed.']);
        }
    }
}
