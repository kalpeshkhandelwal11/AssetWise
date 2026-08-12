<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Backs the `alerts:expiry` scheduled command. Fires exactly on the 30/7/1-day
 * thresholds (not "within 30 days" every day) so recipients get three alerts per
 * expiring record, not one every day of the window. Reads warranty_expiry/amc_expiry
 * off Asset directly — AmcService/WarrantyService keep those in sync with the furthest
 * end_date across each asset's full history, so this stays a single source per asset.
 */
class ExpiryAlertService
{
    /** Public: ReportService::expiryStatus() (M14) reads the 30-day tier for its "expiring" bucket. */
    public const THRESHOLD_DAYS = [30, 7, 1];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function run(): int
    {
        $sent = 0;

        foreach (['warranty_expiry' => 'warranty', 'amc_expiry' => 'amc'] as $column => $type) {
            foreach (self::THRESHOLD_DAYS as $days) {
                $targetDate = now()->addDays($days)->toDateString();

                Asset::whereDate($column, $targetDate)
                    ->with('custodian')
                    ->each(function (Asset $asset) use (&$sent, $type, $column, $days) {
                        foreach ($this->recipientsFor($asset) as $user) {
                            $this->notifications->send($user, 'expiry_alert', [
                                'asset_id'       => $asset->id,
                                'asset'          => $asset->name,
                                'type'           => $type,
                                'expires_on'     => $asset->{$column}->toDateString(),
                                'days_remaining' => $days,
                                'url'            => route('assets.show', $asset),
                            ]);
                            $sent++;
                        }
                    });
            }
        }

        return $sent;
    }

    /** @return Collection<int, User> */
    private function recipientsFor(Asset $asset): Collection
    {
        // Eager-loaded (M12): run() calls send() per recipient rather than sendMany(), so
        // without this each user's wantsEmailFor() would re-query notification_preferences —
        // and this whole method already runs once per matching asset per threshold.
        $recipients = User::permission('maintenance.manage')
            ->where('is_active', true)
            ->with('notificationPreferences')
            ->get();

        if ($asset->custodian && $asset->custodian->is_active) {
            $recipients->push($asset->custodian->loadMissing('notificationPreferences'));
        }

        return $recipients->unique('id');
    }
}
