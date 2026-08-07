# M12 — Notifications

| | |
|--|--|
| **Developer** | Both (stub Dev 1, complete Dev 2) |
| **Phase** | 1 stub → 2 complete |
| **Depends on** | M00 |

## Scope

In-app alerts + email for lifecycle events.

## DB Tables

- `notifications` (Laravel default or custom: user_id, type, data json, read_at)

## Events to wire (Phase 2+)

| Event | Channels |
|-------|----------|
| Movement submitted | In-app to approvers |
| Movement approved/rejected | In-app + email to requester/custodian |
| Audit campaign assigned | In-app + email to auditors |
| Warranty/AMC expiry | In-app + email to asset manager |
| Disposal approval needed | In-app + email to approvers |
| Approval escalated | In-app + email to next level |

## Tasks

- [x] **Phase 1 stub:** `NotificationService` + `notifications` table + bell icon — **shipped in M08**
- [x] Notification bell in layout with unread count — `layouts/app.blade.php` reads `auth()->user()->unreadNotifications`
- [ ] Mark as read; deep links to records — the bell dropdown still renders a hard-coded "No new notifications" and both its links are `#`
- [ ] Mailable classes per event type
- [ ] Queue mail on database driver

## What M08 already built (Phase 1 stub — extend, don't replace)

`App\Services\NotificationService` implements the cross-module contract `send($user, $type, $data)` plus a `sendMany($users, $type, $data)` convenience. It writes through Laravel's **database channel only**, via `App\Notifications\GenericNotification`, which overrides `databaseType()` so the `notifications.type` column holds the app-level type string (`approval.pending`, `approval.approved`, `approval.rejected`, `approval.escalated`) rather than the PHP class name. Each payload carries `request_id`, `module`, `workflow`, `subject`, `url` and `step_level`.

**M12's job is to add channels behind that same signature** — callers in M08 (and later M09/M10/M11/M13) must not have to change. Concretely:

- Add a mail channel to `GenericNotification::via()`, or dispatch per-type notification classes keyed off `$type`
- Make the bell dropdown render real rows from `unreadNotifications` and link through to `data.url`
- Add a mark-as-read route and a "view all" index
- The `Approval escalated` row in the events table below is already emitted by M08's `approvals:escalate` command — it needs the email channel, not the trigger

## Acceptance criteria

- Users see unread count in nav
- Click notification opens related record
- Emails sent for configured events (test with Mailpit/Laragon)
