<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\GenericMailNotification;
use App\Notifications\GenericNotification;
use App\Support\NotificationCatalog;
use Illuminate\Support\Collection;

/**
 * Cross-module contract per CLAUDE.md: send($user, $type, $data). Callers (M08, M09, M10,
 * M11, M13) never change — M12 adds channels and per-user preferences entirely behind this
 * signature. The database notification always goes out inline; mail goes out only when the
 * catalog (config/notifications.php) allows it for this type AND the recipient hasn't
 * opted out via notification_preferences, and it's queued (see GenericMailNotification).
 */
class NotificationService
{
    public function __construct(private readonly NotificationCatalog $catalog)
    {
    }

    public function send(User $user, string $type, array $data = []): void
    {
        $user->notify(new GenericNotification($type, $data));

        if ($this->shouldMail($user, $type)) {
            $user->notify(new GenericMailNotification($type, $data));
        }
    }

    /**
     * @param  Collection<int, User>|iterable<User>  $users
     */
    public function sendMany(iterable $users, string $type, array $data = []): void
    {
        $users = collect($users)->values();

        // One preload query for the whole batch — sendMany() is called with full role
        // collections (e.g. every approver for a workflow step), and without this,
        // wantsEmailFor() would run one notification_preferences query per recipient.
        // Built as a keyed lookup rather than $users->loadMissing() because $users may be
        // a plain array or a base Support\Collection, not always an Eloquent collection.
        $preferences = NotificationPreference::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id');

        foreach ($users as $user) {
            if (! $user->relationLoaded('notificationPreferences')) {
                $user->setRelation('notificationPreferences', $preferences->get($user->id, collect()));
            }

            $this->send($user, $type, $data);
        }
    }

    private function shouldMail(User $user, string $type): bool
    {
        return $user->email
            && in_array('mail', $this->catalog->channels($type), true)
            && $user->wantsEmailFor($type);
    }
}
