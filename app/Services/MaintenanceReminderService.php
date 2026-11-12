<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Backs `maintenance:remind`. A scheduled date used to be just a field — nothing told anyone
 * the work was coming up or had slipped. Fires exactly at 7 days before, 1 day before and
 * 1 day after the scheduled date (same exact-day pattern as ExpiryAlertService, so a daily run
 * never repeats a reminder) for records still in `scheduled`.
 */
class MaintenanceReminderService
{
    /** Days relative to scheduled_date: positive = still ahead, negative = overdue. */
    public const OFFSETS = [7, 1, -1];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function run(): int
    {
        $sent = 0;

        foreach (self::OFFSETS as $days) {
            MaintenanceRecord::withoutGlobalScopes()
                ->where('status', 'scheduled')
                ->whereDate('scheduled_date', now()->addDays($days)->toDateString())
                ->get()
                ->each(function (MaintenanceRecord $record) use (&$sent, $days) {
                    $asset = Asset::withoutGlobalScopes()->with('custodian.user')->find($record->asset_id);
                    if (! $asset || $asset->isRetired()) {
                        return;
                    }

                    $work = \App\Models\MaintenanceType::whereKey($record->maintenance_type_id)->value('name') ?? 'Maintenance';

                    foreach ($this->recipientsFor($asset) as $user) {
                        $this->notifications->send($user, 'maintenance_due', [
                            'asset_id'     => $asset->id,
                            'asset'        => $asset->name,
                            'work'         => $work,
                            'scheduled_on' => $record->scheduled_date->toDateString(),
                            'when'         => $days > 0 ? "due in {$days} day(s)" : 'overdue',
                            'url'          => route('assets.show', $asset),
                        ]);
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    /** @return Collection<int, User> */
    private function recipientsFor(Asset $asset): Collection
    {
        $recipients = User::permission('maintenance.manage')
            ->where('is_active', true)
            ->with('notificationPreferences')
            ->get();

        // The custodian is an Employee; reminders go to its linked login, if any and active.
        $custodianUser = $asset->custodian?->user;
        if ($custodianUser && $custodianUser->is_active) {
            $recipients->push($custodianUser->loadMissing('notificationPreferences'));
        }

        return $recipients->unique('id');
    }
}
