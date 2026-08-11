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
    private const THRESHOLD_DAYS = [30, 7, 1];

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
        $recipients = User::permission('maintenance.manage')->where('is_active', true)->get();

        if ($asset->custodian && $asset->custodian->is_active) {
            $recipients->push($asset->custodian);
        }

        return $recipients->unique('id');
    }
}
