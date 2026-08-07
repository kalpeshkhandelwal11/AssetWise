<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Minimal database-channel notification backing NotificationService::send().
 *
 * M12 will replace/extend this with per-type notification classes and channel
 * preferences; until then a single generic class keeps the `notifications` table
 * (already migrated, already read by the sidebar bell) fed with a stable shape:
 * the `type` column holds the app-level type string, not this PHP class name.
 */
class GenericNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $notificationType,
        public array $payload = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Overrides what Laravel writes into notifications.type. */
    public function databaseType(object $notifiable): string
    {
        return $this->notificationType;
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
