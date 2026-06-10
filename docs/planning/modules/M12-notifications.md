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

- [ ] **Phase 1 stub:** `NotificationService` interface + `notifications` table + bell icon placeholder
- [ ] Notification bell in layout with unread count
- [ ] Mark as read; deep links to records
- [ ] Mailable classes per event type
- [ ] Queue mail on database driver

## Acceptance criteria

- Users see unread count in nav
- Click notification opens related record
- Emails sent for configured events (test with Mailpit/Laragon)
