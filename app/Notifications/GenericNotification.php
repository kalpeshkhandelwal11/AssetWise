<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The in-app half of every notification (M12). Deliberately NOT ShouldQueue: if it were,
 * database writes would go through the queue worker too, and the bell would not update
 * until a worker picked the job up (never, in a dev environment with none running). Mail
 * is the half that queues — see GenericMailNotification.
 *
 * The `type` column holds the app-level type string (looked up in
 * config/notifications.php via NotificationCatalog), not this PHP class name.
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
