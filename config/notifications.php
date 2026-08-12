<?php

/*
|--------------------------------------------------------------------------
| Notification Type Catalog (M12)
|--------------------------------------------------------------------------
|
| Every app-level notification type string that reaches NotificationService::send()
| must have an entry here. This is the single source of truth for how a type
| renders — the bell dropdown, the notifications index, and outbound mail all
| read through App\Support\NotificationCatalog rather than touching this file
| or the payload shape directly.
|
| 'title' / 'body' are ":placeholder" templates resolved against the payload
| array — a missing placeholder renders as an empty string, never an exception —
| OR a closure fn (array $data): string for a type whose payload shape varies
| too much for one template (see 'import_completed').
|
| 'link' names the payload key holding the deep link, OR a closure
| fn (array $data): ?string for types whose payload has no single URL key.
| A type with no meaningful destination (e.g. an error payload) may omit 'link'.
|
| 'channels' lists the defaults; a user's notification_preferences row can
| drop 'mail' but never adds a channel the catalog doesn't declare, and
| 'database' is always sent regardless of what's listed here.
|
*/

return [

    'types' => [

        'approval.pending' => [
            'label'    => 'Approval required',
            'icon'     => 'clock',
            'title'    => ':workflow — :subject',
            'body'     => 'Your approval is needed at step :step_level.',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'approval.approved' => [
            'label'    => 'Approval approved',
            'icon'     => 'check-circle',
            'title'    => ':workflow — :subject',
            'body'     => 'Your request has been approved.',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'approval.rejected' => [
            'label'    => 'Approval rejected',
            'icon'     => 'x-circle',
            'title'    => ':workflow — :subject',
            'body'     => 'Your request was rejected: :reason',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'approval.escalated' => [
            'label'    => 'Approval escalated',
            'icon'     => 'arrow-up-circle',
            'title'    => ':workflow — :subject',
            'body'     => 'This request was escalated at step :step_level and now needs your attention.',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'audit.campaign_activated' => [
            'label'    => 'Audit campaign assigned',
            'icon'     => 'clipboard-check',
            'title'    => 'Audit campaign: :campaign',
            'body'     => ':item_count assets are ready for verification.',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'audit.campaign_closed' => [
            'label'    => 'Audit campaign closed',
            'icon'     => 'clipboard-check',
            'title'    => 'Audit campaign closed: :campaign',
            'body'     => 'The compliance report is ready.',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'movement.completed' => [
            'label'    => 'Asset movement completed',
            'icon'     => 'arrow-right-circle',
            'title'    => ':movement_type — :asset',
            'body'     => 'You are now the custodian of :asset.',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'disposal.completed' => [
            'label'    => 'Disposal completed',
            'icon'     => 'trash',
            'title'    => 'Disposal completed — :asset',
            'body'     => 'The disposal you requested has been completed.',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'expiry_alert' => [
            'label'    => 'Warranty / AMC expiring',
            'icon'     => 'exclamation-triangle',
            'title'    => ':asset — :type expiring in :days_remaining day(s)',
            'body'     => 'Expires on :expires_on.',
            'channels' => ['database', 'mail'],
            'link'     => 'url',
        ],

        'export_ready' => [
            'label'    => 'Export ready',
            'icon'     => 'download',
            'title'    => 'Export ready: :file_name',
            'body'     => ':row_count rows exported.',
            'channels' => ['database', 'mail'],
            'link'     => 'download_url',
        ],

        'import_completed' => [
            'label'    => 'Import completed',
            'icon'     => 'upload',
            'title'    => 'Import completed',
            // Closure, not a placeholder string: ProcessAssetImport sends two payload
            // shapes under this type — a success shape (success_count/error_count) and
            // an unreadable-file shape (error only) — and each needs its own wording
            // rather than an empty-placeholder mash-up of both.
            'body'     => fn (array $data) => isset($data['error'])
                ? $data['error']
                : sprintf('%d succeeded, %d failed.', $data['success_count'] ?? 0, $data['error_count'] ?? 0),
            'channels' => ['database', 'mail'],
            'link'     => fn (array $data) => isset($data['batch_id'])
                ? route('assets.import.show', $data['batch_id'])
                : null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback
    |--------------------------------------------------------------------------
    |
    | Used when a notification row's type has no catalog entry (an older
    | deploy, a type since renamed/removed). Must never throw — this is what
    | keeps a stale row from 500ing the bell for every user.
    |
    */
    'fallback' => [
        'icon'     => 'bell',
        'channels' => ['database'],
    ],

];
