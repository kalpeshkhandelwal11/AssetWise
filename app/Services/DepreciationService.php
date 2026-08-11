<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDepreciationSetting;
use App\Models\DepreciationScheduleLine;
use App\Models\DepreciationSettingRequest;
use App\Models\MaintenanceRecord;
use App\Models\Setting;
use App\Services\Depreciation\DepreciationInput;
use App\Services\Depreciation\ScheduleLineDTO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asset depreciation (M16). Straight-line only in the MVP, Companies Act daily proration.
 *
 * Configuration changes (method/life/salvage) are approval-gated: submitSettingChange() opens
 * a 'depreciation' workflow request, and applySettingChange() — driven by the
 * ApplyDepreciationSettingChange listener — writes the new active settings row and rebuilds
 * the schedule. Monthly posting is automatic and idempotent (postDuePeriods()).
 */
class DepreciationService
{
    public function __construct(private WorkflowService $workflows)
    {
    }

    // ------------------------------------------------------------------ resolution

    /**
     * Proposed settings for an asset that has no active depreciation yet, inherited from its
     * category default. Returns null when the category has no default configured — the
     * "no depreciation until configured" acceptance criterion.
     *
     * @return array<string, mixed>|null
     */
    public function resolveDefaults(Asset $asset): ?array
    {
        $default = $asset->category?->depreciationDefault;

        if (! $default) {
            return null;
        }

        $costBasis = (float) $asset->purchase_cost;
        $startDate = $default->start_basis === 'commission_date'
            ? ($asset->purchase_date ?? now())
            : ($asset->purchase_date ?? now());

        return [
            'depreciation_method_id' => $default->depreciation_method_id,
            'useful_life_months'     => $default->useful_life_months,
            'salvage_value'          => $default->salvage_value !== null ? (float) $default->salvage_value : null,
            'salvage_percent'        => $default->salvage_percent !== null ? (float) $default->salvage_percent : null,
            'start_date'             => Carbon::parse($startDate)->toDateString(),
            'cost_basis'             => $costBasis,
        ];
    }

    /** Resolve fixed-or-percent salvage to an absolute amount, defaulting to the 5% setting. */
    public function computeSalvage(float $costBasis, ?float $salvageValue, ?float $salvagePercent): float
    {
        if ($salvageValue !== null) {
            return round($salvageValue, 2);
        }

        $percent = $salvagePercent ?? (float) Setting::get('depreciation_default_salvage_percent', '5');

        return round($costBasis * $percent / 100, 2);
    }

    // ------------------------------------------------------------------ approval flow

    /**
     * Open a depreciation-settings change request and submit it to the 'depreciation' workflow.
     * Wrapped in a transaction so a missing/deactivated workflow never leaves an orphaned
     * pending request that would trip hasPendingDepreciationRequest() forever (CLAUDE.md rule).
     *
     * @param array<string, mixed> $data
     */
    public function submitSettingChange(
        Asset $asset,
        array $data,
        User $actor,
        string $changeType = 'revision',
        ?MaintenanceRecord $source = null,
    ): DepreciationSettingRequest {
        if ($asset->hasPendingDepreciationRequest()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" already has a depreciation change pending approval.",
            ]);
        }

        return DB::transaction(function () use ($asset, $data, $actor, $changeType, $source) {
            $request = DepreciationSettingRequest::create([
                'asset_id'               => $asset->id,
                'depreciation_method_id' => $data['depreciation_method_id'],
                'useful_life_months'     => $data['useful_life_months'],
                'salvage_value'          => $data['salvage_value'] ?? null,
                'salvage_percent'        => $data['salvage_percent'] ?? null,
                'start_date'             => $data['start_date'],
                'cost_basis'             => $data['cost_basis'],
                'change_type'            => $changeType,
                'source_maintenance_id'  => $source?->id,
                'status'                 => 'pending_approval',
                'requested_by'           => $actor->id,
            ]);

            $approvalRequest = $this->workflows->submit($request, 'depreciation', $actor);
            $request->update(['approval_request_id' => $approvalRequest->id]);

            return $request;
        });
    }

    /**
     * Apply an approved change: supersede the current active settings row, create the new one,
     * regenerate the schedule and post any already-due periods. Called by the listener when
     * ApprovalRequestApproved fires for module 'depreciation'.
     */
    public function applySettingChange(DepreciationSettingRequest $request): AssetDepreciationSetting
    {
        return DB::transaction(function () use ($request) {
            $costBasis = (float) $request->cost_basis;
            $salvage = $this->computeSalvage(
                $costBasis,
                $request->salvage_value !== null ? (float) $request->salvage_value : null,
                $request->salvage_percent !== null ? (float) $request->salvage_percent : null,
            );

            $setting = AssetDepreciationSetting::create([
                'asset_id'                 => $request->asset_id,
                'depreciation_method_id'   => $request->depreciation_method_id,
                'useful_life_months'       => $request->useful_life_months,
                'salvage_value'            => $salvage,
                'salvage_percent'          => $request->salvage_percent,
                'start_date'               => $request->start_date,
                'cost_basis'               => $costBasis,
                'accumulated_depreciation' => 0,
                'current_book_value'       => $costBasis,
                'is_active'                => true,
            ]);

            $this->supersedePrevious($request->asset_id, $setting);
            $this->generateSchedule($setting);
            $this->postSetting($setting, now());

            $request->update(['status' => 'applied']);

            return $setting->refresh();
        });
    }

    private function supersedePrevious(int $assetId, AssetDepreciationSetting $new): void
    {
        AssetDepreciationSetting::where('asset_id', $assetId)
            ->where('is_active', true)
            ->where('id', '!=', $new->id)
            ->get()
            ->each(function (AssetDepreciationSetting $old) use ($new) {
                $old->update([
                    'is_active'     => false,
                    'stopped_at'    => now()->toDateString(),
                    'superseded_by' => $new->id,
                ]);
            });
    }

    // ------------------------------------------------------------------ schedule

    /** Rebuild the (unposted) schedule for a setting from its calculator. Posted lines are left intact. */
    public function generateSchedule(AssetDepreciationSetting $setting): void
    {
        $setting->scheduleLines()->where('status', 'scheduled')->delete();

        $input = new DepreciationInput(
            costBasis: (float) $setting->cost_basis,
            salvageValue: (float) $setting->salvage_value,
            usefulLifeMonths: (int) $setting->useful_life_months,
            startDate: Carbon::parse($setting->start_date),
        );

        $lines = $setting->method->calculator()->generate($input);

        foreach ($lines as $line) {
            /** @var ScheduleLineDTO $line */
            DepreciationScheduleLine::create([
                'asset_id'                      => $setting->asset_id,
                'asset_depreciation_setting_id' => $setting->id,
                'period_year'                   => $line->periodYear,
                'period_month'                  => $line->periodMonth,
                'days_in_period'                => $line->daysInPeriod,
                'opening_book_value'            => $line->openingBookValue,
                'depreciation_amount'           => $line->depreciationAmount,
                'accumulated_depreciation'      => $line->accumulatedDepreciation,
                'closing_book_value'            => $line->closingBookValue,
                'status'                        => 'scheduled',
            ]);
        }
    }

    // ------------------------------------------------------------------ posting

    /** Post every due period across all active settings. Idempotent — safe to run daily/by hand. */
    public function postDuePeriods(?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();
        $posted = 0;

        AssetDepreciationSetting::where('is_active', true)
            ->get()
            ->each(function (AssetDepreciationSetting $setting) use ($asOf, &$posted) {
                $posted += $this->postSetting($setting, $asOf);
            });

        return $posted;
    }

    /**
     * Flip due scheduled lines to posted and refresh the setting's cached book value. A period
     * is "due" only once it has fully elapsed — i.e. strictly before the current month — so an
     * in-progress month is never posted early (the monthly run on the 1st posts the month just
     * ended). Net book value mid-month is still exact via netBookValue()'s day proration.
     */
    private function postSetting(AssetDepreciationSetting $setting, CarbonInterface $asOf): int
    {
        $count = $setting->scheduleLines()
            ->where('status', 'scheduled')
            ->where(function ($q) use ($asOf) {
                $q->where('period_year', '<', $asOf->year)
                    ->orWhere(function ($q2) use ($asOf) {
                        $q2->where('period_year', $asOf->year)
                            ->where('period_month', '<', $asOf->month);
                    });
            })
            ->update(['status' => 'posted', 'posted_at' => now()]);

        $latest = $setting->scheduleLines()
            ->where('status', 'posted')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        $setting->update([
            'accumulated_depreciation' => $latest ? $latest->accumulated_depreciation : 0,
            'current_book_value'       => $latest ? $latest->closing_book_value : (float) $setting->cost_basis,
        ]);

        return $count;
    }

    // ------------------------------------------------------------------ book value / stop

    /** Net book value on a given date, with day-level proration inside the disposal/transfer month. */
    public function netBookValue(AssetDepreciationSetting $setting, CarbonInterface $asOf): float
    {
        $nbv = (float) $setting->cost_basis - $this->accumulatedDepreciationAsOf($setting, $asOf);

        return round(max($nbv, (float) $setting->salvage_value), 2);
    }

    public function accumulatedDepreciationAsOf(AssetDepreciationSetting $setting, CarbonInterface $asOf): float
    {
        $start = Carbon::parse($setting->start_date);
        $accumulated = 0.0;

        foreach ($setting->scheduleLines as $line) {
            $lineAfter = $asOf->year < $line->period_year
                || ($asOf->year === $line->period_year && $asOf->month < $line->period_month);

            if ($lineAfter) {
                break;
            }

            $sameMonth = $asOf->year === $line->period_year && $asOf->month === (int) $line->period_month;

            if (! $sameMonth) {
                $accumulated += (float) $line->depreciation_amount;
                continue;
            }

            // Partial month: prorate by active days elapsed up to $asOf.
            $startDay = ($start->year === $line->period_year && $start->month === (int) $line->period_month)
                ? $start->day
                : 1;
            $elapsed = max(0, min((int) $line->days_in_period, $asOf->day - $startDay + 1));
            $accumulated += $line->days_in_period > 0
                ? round((float) $line->depreciation_amount * $elapsed / $line->days_in_period, 2)
                : 0.0;
            break;
        }

        return round($accumulated, 2);
    }

    /** Halt depreciation (disposal/transfer) and drop strictly-future unposted lines. */
    public function stop(AssetDepreciationSetting $setting, CarbonInterface $date): void
    {
        $setting->scheduleLines()
            ->where('status', 'scheduled')
            ->where(function ($q) use ($date) {
                $q->where('period_year', '>', $date->year)
                    ->orWhere(function ($q2) use ($date) {
                        $q2->where('period_year', $date->year)
                            ->where('period_month', '>', $date->month);
                    });
            })
            ->delete();

        $setting->update([
            'is_active'  => false,
            'stopped_at' => $date->toDateString(),
        ]);
    }

    // ------------------------------------------------------------------ cross-module

    /**
     * Inter-company transfer (M09): companies are separate legal entities, so the seller's
     * schedule stops and the receiver starts a fresh one at net book value over the remaining
     * useful life. Applied directly — the movement itself is already M08-approved.
     */
    public function resetForTransfer(Asset $asset, CarbonInterface $transferDate): ?AssetDepreciationSetting
    {
        $current = $asset->activeDepreciationSetting();

        if (! $current) {
            return null;
        }

        return DB::transaction(function () use ($asset, $current, $transferDate) {
            $nbv = $this->netBookValue($current, $transferDate);
            $remaining = $this->remainingLifeMonths($current, $transferDate);

            $this->stop($current, $transferDate);

            $setting = AssetDepreciationSetting::create([
                'asset_id'                 => $asset->id,
                'depreciation_method_id'   => $current->depreciation_method_id,
                'useful_life_months'       => $remaining,
                'salvage_value'            => (float) $current->salvage_value,
                'salvage_percent'          => $current->salvage_percent,
                'start_date'               => $transferDate->toDateString(),
                'cost_basis'               => $nbv,
                'accumulated_depreciation' => 0,
                'current_book_value'       => $nbv,
                'is_active'                => true,
            ]);

            $current->update(['superseded_by' => $setting->id]);
            $this->generateSchedule($setting);
            $this->postSetting($setting, now());

            return $setting->refresh();
        });
    }

    /**
     * Capitalize major maintenance (M11): add the capitalized amount to the current book value
     * and extend the life, then route the change through the depreciation approval workflow.
     * Returns null when the asset isn't being depreciated (nothing to capitalize onto).
     */
    public function capitalize(MaintenanceRecord $record, User $actor): ?DepreciationSettingRequest
    {
        $asset = $record->asset;
        $current = $asset?->activeDepreciationSetting();

        if (! $asset || ! $current) {
            return null;
        }

        $asOf = $record->performed_date ? Carbon::parse($record->performed_date) : now();
        $nbv = $this->netBookValue($current, $asOf);
        $remaining = $this->remainingLifeMonths($current, $asOf);

        return $this->submitSettingChange(
            $asset,
            [
                'depreciation_method_id' => $current->depreciation_method_id,
                'useful_life_months'     => $remaining + (int) $record->additional_useful_life_months,
                'salvage_value'          => (float) $current->salvage_value,
                'salvage_percent'        => null,
                'start_date'             => $asOf->toDateString(),
                'cost_basis'             => round($nbv + (float) $record->capitalized_amount, 2),
            ],
            $actor,
            'capitalization',
            $record,
        );
    }

    private function remainingLifeMonths(AssetDepreciationSetting $setting, CarbonInterface $asOf): int
    {
        $elapsed = (int) Carbon::parse($setting->start_date)->diffInMonths($asOf);

        return max(1, (int) $setting->useful_life_months - $elapsed);
    }
}
