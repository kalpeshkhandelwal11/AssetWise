<?php

namespace App\Notifications;

use App\Support\NotificationCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The mail half of every notification (M12). ShouldQueue on purpose — sendMany() can
 * fan out to a whole role's worth of approvers, and a slow SMTP host must not stall the
 * request. Requires `php artisan queue:work` to be running (QUEUE_CONNECTION=database,
 * no Redis assumed) or mail silently sits in the `jobs` table forever; see
 * docs/developer-setup.md.
 *
 * NotificationService only ever dispatches this after confirming the catalog allows a
 * mail channel for this type AND the recipient hasn't opted out — this class doesn't
 * re-check either, it just renders.
 */
class GenericMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $notificationType,
        public array $payload = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $catalog = app(NotificationCatalog::class);
        $link = $catalog->link($this->notificationType, $this->payload);

        $message = (new MailMessage())
            ->subject($catalog->title($this->notificationType, $this->payload))
            ->line($catalog->body($this->notificationType, $this->payload));

        if ($link) {
            $message->action('View in AssetWise', $link);
        }

        return $message;
    }
}
