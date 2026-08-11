<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditCampaign;
use App\Models\AuditItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Audit campaigns are plain permission-gated CRUD (audit.manage / audit.verify) — like
 * M11, there is no approval workflow here (M10 depends only on M03/M05). Missing/damaged
 * findings are recorded on the audit item only; they never mutate assets.status_id, so
 * this service stays free of the M09/M11/M13 status-ledger coupling those modules share.
 */
class AuditService
{
    private const SCOPE_FILTERS = [
        'company_id', 'category_id', 'asset_type_id', 'status_id',
        'location_id', 'building_id', 'department_id', 'branch_id',
    ];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function create(array $data, User $actor): AuditCampaign
    {
        return DB::transaction(function () use ($data, $actor) {
            $campaign = AuditCampaign::create([
                'name'          => $data['name'],
                'audit_type_id' => $data['audit_type_id'],
                'description'   => $data['description'] ?? null,
                'start_date'    => $data['start_date'],
                'end_date'      => $data['end_date'] ?? null,
                'scope'         => $this->normalizeScope($data['scope'] ?? []),
                'status'        => 'draft',
                'created_by'    => $actor->id,
            ]);

            $campaign->auditors()->sync($data['auditor_ids'] ?? []);

            return $campaign;
        });
    }

    public function update(AuditCampaign $campaign, array $data): AuditCampaign
    {
        if (! $campaign->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft campaign can be edited — scope and auditors are frozen once activated.',
            ]);
        }

        DB::transaction(function () use ($campaign, $data) {
            $campaign->update([
                'name'          => $data['name'],
                'audit_type_id' => $data['audit_type_id'],
                'description'   => $data['description'] ?? null,
                'start_date'    => $data['start_date'],
                'end_date'      => $data['end_date'] ?? null,
                'scope'         => $this->normalizeScope($data['scope'] ?? []),
            ]);

            $campaign->auditors()->sync($data['auditor_ids'] ?? []);
        });

        return $campaign;
    }

    public function delete(AuditCampaign $campaign): void
    {
        if (! $campaign->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft campaign can be deleted.',
            ]);
        }

        $campaign->delete();
    }

    /** Assets matching the campaign's scope filters, excluding already-disposed assets. */
    public function scopedAssetQuery(AuditCampaign $campaign): Builder
    {
        $query = Asset::query()->whereHas('status', fn (Builder $q) => $q->where('code', '!=', 'DISPOSED'));

        foreach (self::SCOPE_FILTERS as $filter) {
            if (! empty($campaign->scope[$filter])) {
                $query->where($filter, $campaign->scope[$filter]);
            }
        }

        return $query;
    }

    public function activate(AuditCampaign $campaign, User $actor): int
    {
        if (! $campaign->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft campaign can be activated.',
            ]);
        }

        if ($campaign->auditors()->count() === 0) {
            throw ValidationException::withMessages([
                'auditors' => 'Assign at least one auditor before activating the campaign.',
            ]);
        }

        if (empty(array_filter($campaign->scope ?? []))) {
            throw ValidationException::withMessages([
                'scope' => 'Set at least one scope filter before activating the campaign.',
            ]);
        }

        $count = DB::transaction(function () use ($campaign) {
            $count = 0;

            $this->scopedAssetQuery($campaign)->orderBy('id')
                ->chunkById(200, function ($assets) use ($campaign, &$count) {
                    foreach ($assets as $asset) {
                        AuditItem::create([
                            'campaign_id'            => $campaign->id,
                            'asset_id'                => $asset->id,
                            'status'                  => 'pending',
                            'expected_location_id'    => $asset->location_id,
                            'expected_custodian_id'   => $asset->custodian_id,
                        ]);
                        $count++;
                    }
                });

            $campaign->update(['status' => 'active', 'activated_at' => now()]);

            return $count;
        });

        $this->notifications->sendMany($campaign->auditors, 'audit.campaign_activated', [
            'campaign_id' => $campaign->id,
            'campaign'    => $campaign->name,
            'item_count'  => $count,
            'url'         => route('audits.verify', ['campaign' => $campaign->id]),
        ]);

        return $count;
    }

    public function verify(AuditItem $item, array $data, User $actor): AuditItem
    {
        $campaign = $item->campaign;

        if (! $campaign->isActive()) {
            throw ValidationException::withMessages([
                'status' => $campaign->isClosed()
                    ? 'This campaign is closed — verification is locked.'
                    : 'This campaign has not been activated yet.',
            ]);
        }

        if (! $actor->can('audit.manage') && ! $campaign->hasAuditor($actor)) {
            throw ValidationException::withMessages([
                'auditor' => 'You are not assigned as an auditor for this campaign.',
            ]);
        }

        if (in_array($data['status'], ['missing', 'damaged'], true) && empty($data['notes'])) {
            throw ValidationException::withMessages([
                'notes' => 'Notes are required when marking an item missing or damaged.',
            ]);
        }

        $item->update([
            'status'      => $data['status'],
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'notes'       => $data['notes'] ?? null,
            'photo_path'  => $data['photo_path'] ?? $item->photo_path,
        ]);

        return $item;
    }

    public function close(AuditCampaign $campaign, User $actor): AuditCampaign
    {
        if (! $campaign->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Only an active campaign can be closed.',
            ]);
        }

        $campaign->update([
            'status'    => 'closed',
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ]);

        $recipients = $campaign->auditors->push($campaign->creator)->unique('id');
        $this->notifications->sendMany($recipients, 'audit.campaign_closed', [
            'campaign_id' => $campaign->id,
            'campaign'    => $campaign->name,
            'url'         => route('audits.campaigns.show', $campaign),
        ]);

        return $campaign;
    }

    /** @return array{total: int, pending: int, verified: int, missing: int, damaged: int, verified_pct: float} */
    public function progressFor(AuditCampaign $campaign): array
    {
        $counts = $campaign->items()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $verified = (int) ($counts['verified'] ?? 0);
        $missing = (int) ($counts['missing'] ?? 0);
        $damaged = (int) ($counts['damaged'] ?? 0);

        return [
            'total'        => $total,
            'pending'      => (int) ($counts['pending'] ?? 0),
            'verified'     => $verified,
            'missing'      => $missing,
            'damaged'      => $damaged,
            'verified_pct' => $total > 0 ? round(($verified + $missing + $damaged) / $total * 100, 1) : 0.0,
        ];
    }

    /** The scan hook: the oldest pending item on an active campaign this user is assigned to. */
    public function openItemFor(Asset $asset, User $user): ?AuditItem
    {
        return AuditItem::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'pending')
            ->whereHas('campaign', function (Builder $q) use ($user) {
                $q->where('status', 'active');

                if (! $user->can('audit.manage')) {
                    $q->whereHas('auditors', fn (Builder $a) => $a->where('user_id', $user->id));
                }
            })
            ->oldest('id')
            ->first();
    }

    private function normalizeScope(array $scope): array
    {
        return array_filter(
            array_intersect_key($scope, array_flip(self::SCOPE_FILTERS)),
            fn ($v) => $v !== null && $v !== ''
        );
    }
}
