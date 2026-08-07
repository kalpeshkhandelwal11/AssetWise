<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Collection;

/**
 * Minimal stand-in for M12. Contract per CLAUDE.md: send($user, $type, $data).
 * Writes to the database channel only — M12 adds mail/other channels behind the
 * same signature so callers (M08 here, later M09/M10/M11/M13) never change.
 */
class NotificationService
{
    public function send(User $user, string $type, array $data = []): void
    {
        $user->notify(new GenericNotification($type, $data));
    }

    /**
     * @param  Collection<int, User>|iterable<User>  $users
     */
    public function sendMany(iterable $users, string $type, array $data = []): void
    {
        foreach ($users as $user) {
            $this->send($user, $type, $data);
        }
    }
}
