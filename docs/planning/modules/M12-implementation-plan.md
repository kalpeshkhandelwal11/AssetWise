# M12 — Notifications (Implementation Plan)

## Context

M12 is the last module with no blocking dependency: its Phase 1 stub shipped inside M08
(`NotificationService`, `GenericNotification`, the `notifications` table, and a bell icon in
`layouts/app.blade.php`). Since then **six modules have become producers** without M12 ever
being completed, so the stub is now feeding real traffic through a single database channel
into a dropdown that renders a hard-coded "No new notifications".

This plan is for review only — implementation happens in a separate session. Do not start
coding from this plan.

**The defining constraint:** `NotificationService::send($user, $type, $data)` is a
cross-module contract (CLAUDE.md + `docs/decisions-log.md`) with **13 call sites across 8
files**. M12 must add channels, preferences and UI *behind that signature*. Not one caller
changes.

### Four pre-implementation decisions resolved with the owner

- **D12.1 — Type dispatch:** a **central type catalog** (`config/notifications.php`) maps each
  type string to label/icon/title-template/default channels. `GenericNotification` stays the
  single notification class and reads the catalog. Rejected: 11 per-type `Notification`
  subclasses — that is 11 files and a `match()` factory to express what is currently 11 rows
  of static metadata, and every future type would cost another class.
- **D12.2 — Channel control:** **per-user preferences**. A new `notification_preferences`
  table plus a Notifications tab on the profile page. In-app is always on (it is the
  system's record of what happened); email is opt-out per type, seeded from the catalog
  defaults. Admin-global-only was rejected because approvers are the heaviest recipients and
  cannot currently mute anything.
- **D12.3 — Scope:** **wire channels only.** M12 adds no new trigger points. Maintenance
  events (M11), a movement-submitted acknowledgement (M09), and depreciation approval
  outcomes (M16) all remain the responsibility of their owning modules. M12's job is the
  delivery layer, not the event inventory.
- **D12.4 — Mail delivery:** **queued**, on the existing `database` queue driver, with
  Mailpit documented for local dev. `MAIL_MAILER=log` stays the default in `.env.example`.

---

## The producer inventory (established by reading every call site — this is the real spec)

Eleven type strings are live. The M12 module doc's "Events to wire" table is aspirational
prose; **this** is what actually reaches `NotificationService` today:

| Type | Producer | Recipients | Payload keys |
|------|----------|-----------|--------------|
| `approval.pending` | `WorkflowService` (submit + each step advance) | approvers for the step | `request_id, module, workflow, subject, url, step_level` |
| `approval.approved` | `WorkflowService::approve()` terminal | submitter | `request_id, module, workflow, subject, url` |
| `approval.rejected` | `WorkflowService::reject()` | submitter | above + rejection reason |
| `approval.escalated` | `approvals:escalate` command | widened eligibility set | above + `step_level` |
| `audit.campaign_activated` | `AuditService::activate()` | campaign auditors | `campaign_id, campaign, item_count, url` |
| `audit.campaign_closed` | `AuditService::close()` | recipients | `campaign_id, campaign, url` |
| `movement.completed` | `MovementService` | `toCustodian` | `asset, movement_type, url` |
| `disposal.completed` | `DisposalService` | `requestedBy` | `asset, url` |
| `expiry_alert` | `ExpiryAlertService` (30/7/1-day) | `maintenance.manage` + custodian | `asset_id, asset, type, expires_on, days_remaining, url` |
| `export_ready` | `GenerateAssetExport`, `GenerateReportExport` | requesting user | `download_url, file_name, row_count` |
| `import_completed` | `ProcessAssetImport` (×2 — success and unreadable-file) | uploader | `batch_id, success_count, error_count` *or* `batch_id, error` |

Three things this inventory settles:

1. **`url` is present on 9 of 11 types but absent on `export_ready` and `import_completed`.**
   Those two carry `download_url` and `batch_id` instead. The bell's "click through to the
   record" acceptance criterion therefore cannot be a bare `data.url` read — the catalog
   needs a per-type link resolver. Fixing this by editing the two producers would be the
   wrong call: it puts M12's concerns into M06's job classes.
2. **`expiry_alert`'s payload has its own `type` key** (`warranty` / `amc`), which shadows the
   notification type in any naive `$data['type']` template lookup. The renderer must never
   pull the notification type out of the payload array.
3. **`import_completed` has two shapes** — a success shape and an error shape distinguished
   by the presence of `error`. The catalog title template must tolerate a missing key rather
   than throwing.

---

## Schema

**`notification_preferences`** — one row per user per type that deviates from the catalog
default. Sparse by design: a user who never opens the screen has zero rows and inherits
defaults, so seeding is unnecessary and adding a new type in the catalog does not require a
backfill.

| Column | Type | Notes |
|--------|------|-------|
| `user_id` | FK users, cascade | |
| `type` | string | the app-level type string, not an FK — the catalog is the source of truth and lives in config |
| `email_enabled` | boolean | in-app is not stored; it is always on |
| timestamps | | |

`unique(user_id, type)`. Migration named `2026_08_16_100001_create_notification_preferences_table.php`
(after M16's `..._08_15_100007_...`).

**`notifications`** — already migrated (Laravel's default: uuid pk, `type`, morphs, `data`
text, `read_at`). **No change.** Adding an index on `(notifiable_type, notifiable_id, read_at)`
is tempting for the unread-count query, but `morphs()` already indexed the first two columns
and the leading-prefix rule makes that index serve the count fine at this data volume.

---

## Build order

### 1. `config/notifications.php` — the type catalog

Follows `config/pwa.php`'s established shape: a plain config file with a header comment
explaining why Laravel owns it, readable in tests without any build step.

```php
'types' => [
    'approval.pending' => [
        'label'    => 'Approval required',
        'icon'     => 'clock',
        'title'    => ':workflow — :subject',
        'body'     => 'Your approval is needed at step :step_level.',
        'channels' => ['database', 'mail'],   // defaults; user prefs may drop 'mail'
        'link'     => 'url',                  // payload key holding the deep link
    ],
    'export_ready' => [
        ...
        'link'     => 'download_url',
    ],
    ...
],
```

- `title` / `body` are `:placeholder` templates resolved against the payload via
  `Str::swap()`, with **missing keys rendering as empty string, never throwing** — this is
  what absorbs `import_completed`'s two shapes.
- `link` names the payload key, which is how `export_ready` / `import_completed` are handled
  without touching M06. `import_completed` resolves via a small closure to
  `route('imports.show', $data['batch_id'])` rather than a bare key, so the catalog value
  accepts `string|Closure`.
- An **unknown type must degrade, not crash**. `catalogFor($type)` returns a fallback entry
  (label = the type string humanised, no mail, no link). A notification row written by an
  older deploy must never 500 the bell for every user.

### 2. `App\Support\NotificationCatalog`

A thin read-only wrapper over the config so views and the service never touch `config()`
directly and the fallback logic lives in exactly one place: `label($type)`, `icon($type)`,
`title($type, $data)`, `body($type, $data)`, `link($type, $data)`, `channels($type)`,
`all()` (drives the preferences screen). Placed in `app/Support/` — it is neither a service
with business rules nor a model.

### 3. `App\Models\NotificationPreference` + `User::notificationPreferences()`

Plus `User::wantsEmailFor(string $type): bool` — checks the loaded relation first, falls back
to the catalog default. Reading the **loaded relation** matters for step 5's N+1.

### 4. Split the notification class — the non-obvious part

`GenericNotification implements ShouldQueue` is the obvious move and it is **wrong here**.
`ShouldQueue` queues *every* channel, including `database` — so the notification row would
be written by the queue worker, not inline. The bell would not update until `queue:work`
picked the job up, and in a dev environment with no worker running the in-app notification
would never appear at all. That is a visible regression against today's behaviour.

So M12 splits by channel:

- **`GenericNotification`** — unchanged: `via() => ['database']`, **not** `ShouldQueue`,
  still overriding `databaseType()`. Writes inline, exactly as today.
- **`GenericMailNotification`** (new) — `via() => ['mail']`, **`implements ShouldQueue`**,
  renders `toMail()` from the catalog.

`NotificationService::send()` then becomes:

```php
public function send(User $user, string $type, array $data = []): void
{
    $user->notify(new GenericNotification($type, $data));          // inline, always

    if (in_array('mail', $this->catalog->channels($type), true)
        && $user->wantsEmailFor($type)
        && $user->email) {
        $user->notify(new GenericMailNotification($type, $data));  // queued
    }
}
```

Signature unchanged. Thirteen call sites unchanged.

### 5. `sendMany()` — the N+1 that will otherwise ship

`sendMany()` currently loops `send()`. With preferences added, that becomes one
`notification_preferences` query **per user per notification** — and `sendMany()` is called
with whole role collections (`WorkflowService::notifyStepApprovers`, `notifyEscalationTargets`,
`AuditService` auditors). A 20-approver workflow step would fire 20 preference queries inside
a request that is already inside a `DB::transaction()`.

Fix: `sendMany()` eager-loads `notificationPreferences` on the incoming collection once
before looping. Because `wantsEmailFor()` reads the loaded relation, `send()` needs no
special-casing and stays correct when called standalone.

`ExpiryAlertService` is a second offender: it calls `send()` in a nested loop inside
`Asset::each()`. Its recipient set is `User::permission('maintenance.manage')` re-queried per
asset. **In scope to fix** (it is a delivery-layer performance bug M12 creates), and the fix
is `->with('notificationPreferences')` on that user query — not a restructure of M11's
service.

### 6. Mail rendering

One shared Markdown mail view, `resources/views/notifications/mail.blade.php`, driven by the
catalog: subject = catalog title, body = catalog body, single CTA button to the resolved link
(omitted when the type has no link). No `app/Mail/` directory and no Mailable classes — a
`Notification::toMail()` returning `MailMessage` is the idiomatic path and Mailables would add
a parallel hierarchy for no gain.

**Absolute URLs:** queue workers run outside an HTTP request, so `APP_URL` must be correct or
every emailed link points at `localhost`. The payloads already store fully-resolved absolute
`route()` strings at *send* time (verified across all 11 producers), so the link is baked in
before it ever reaches the queue. This is worth an explicit test — it is the classic way this
breaks in production.

### 7. `NotificationController` + routes

New `app/Http/Controllers/NotificationController.php` (top-level, not namespaced — it is
per-user, not a module, matching `ProfileController`):

| Route | Name | Action |
|-------|------|--------|
| `GET /notifications` | `notifications.index` | paginated list, unread-first, filter by read/unread and type |
| `PATCH /notifications/{id}/read` | `notifications.read` | mark one read, then redirect to the resolved link |
| `PATCH /notifications/read-all` | `notifications.read-all` | mark all read, back |

**No permission gate.** Every authenticated user has notifications; the resource is scoped by
`auth()->user()->notifications()` rather than by an RBAC check, so **no new permissions are
seeded and `RolePermissionSeeder` is untouched**. This deliberately breaks from the
`{module}.{action}` convention because there is no admin-vs-user split to express.

The `{id}` is the notification **uuid**, and the query must be scoped to the authenticated
user's own notifications — a bare `DatabaseNotification::find($id)` would let any user mark
another's notification read. Route-model-binding is not used for exactly this reason.

Deep-link behaviour: `notifications.read` marks read **and then redirects** to the resolved
link, so one click both clears the badge and opens the record. The bell dropdown's rows are
therefore small `PATCH` forms, not `<a>` tags.

### 8. Bell dropdown + index view

Replace lines 133–145 of `layouts/app.blade.php`. Rows render catalog label + title + relative
timestamp + icon.

**The layout currently runs `auth()->user()->unreadNotifications->count()` on every page
render** — that hydrates every unread notification model just to count them, on every request,
site-wide. Fix while here: `unreadNotifications()->count()` (a `COUNT(*)`), and have the
dropdown separately take `->latest()->limit(5)->get()`. Cache both on the `User` instance so
the bell's two uses do not double-query.

Wire "Mark all read" and "View all notifications" — currently both `href="#"`.

Per **Alpine gotchas in CLAUDE.md**: the dropdown is server-rendered Blade inside the existing
`x-data="{ open: false }"`. No notification payload is passed through an Alpine attribute, so
the `@json`/`@js` trap does not arise. Keep it that way — do not "improve" this into an
Alpine-fetched dropdown.

### 9. Preferences screen

New partial `resources/views/profile/partials/notification-preferences-form.blade.php`,
included in the existing `profile/edit.blade.php` alongside the other three Breeze partials —
not a separate page, matching how profile settings already compose.

`ProfileController::updateNotifications()` (or a small `NotificationPreferenceController` if
`ProfileController` grows past comfort) writes only rows that **differ from the catalog
default**, deleting rows that return to default. Keeps the table sparse as designed.

The screen iterates `NotificationCatalog::all()`, so a new type appears automatically with no
migration and no seeder.

### 10. Developer setup

Append a Mailpit section to `docs/developer-setup.md`: Laragon ships Mailpit; set
`MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, UI on `:8025`.

**Document the failure mode prominently:** with `QUEUE_CONNECTION=database` and no
`php artisan queue:work` running, in-app notifications still appear (they are inline by
design, step 4) but **no email is ever sent** — the job sits in `jobs` indefinitely. This is
the M15 `npm run build` situation repeating: a required out-of-band step whose absence is
silent. It belongs in `docs/developer-setup.md` and in CLAUDE.md's M12 row.

---

## Testing

New `tests/Feature/Notifications/`, matching the existing per-module test directory
convention. In-memory SQLite as always.

| Test | Asserts |
|------|---------|
| `NotificationCatalogTest` | every one of the 11 live types has a catalog entry (guards against a producer being added without a catalog row); unknown type returns the fallback and does not throw; `import_completed`'s error shape renders without a missing-key error; `expiry_alert`'s payload `type` key does not shadow the notification type |
| `NotificationChannelTest` | `Notification::fake()` — database notification always sent; mail sent when catalog allows and user has not opted out; **not** sent when opted out; not sent when catalog omits `mail` |
| `NotificationQueueTest` | `GenericMailNotification` is `ShouldQueue` and `GenericNotification` is **not** — this is the step-4 decision, and it is the kind of thing a later refactor silently reverses |
| `NotificationPreferenceTest` | defaults apply with zero rows; saving a non-default writes a row; reverting to default deletes it |
| `NotificationBellTest` | dropdown renders real rows and a working link (asserts on **markup**, per the integration-pass lesson that DB-only assertions missed the `@json` defect); unread badge count |
| `NotificationControllerTest` | index paginates and scopes to own user; **marking another user's notification read 404s**; read-all clears; read redirects to the resolved deep link |
| `NotificationMailTest` | rendered mail contains the absolute URL from the payload, proving queue-context links survive |

Existing tests that touch notifications must stay green unchanged — that is the proof the
contract held. Expect the current **528 passing** to become roughly **555–565**.

---

## What this plan deliberately does not do

- **No new triggers** (D12.3). Maintenance events, movement-submitted acks and depreciation
  outcomes stay with M11/M09/M16.
- **No digest or batching.** A user watching 200 assets expire on the same day gets 200
  emails. Real, but it needs a scheduled aggregator and a "since last digest" watermark —
  a module of its own, not a subsection of this one. Flagged, not built.
- **No broadcast/websocket channel.** No Redis on shared hosting (CLAUDE.md), and the bell
  updating on page load is sufficient.
- **No SMS/push.** M15's PWA has no push subscription infrastructure and adding Web Push
  means VAPID keys, a subscription table and a service-worker `push` handler — which would
  also mean touching M15's fetch handler, explicitly fenced off in CLAUDE.md.
- **No notification retention/pruning.** `notifications` grows unbounded. Laravel's
  `model:prune` on `DatabaseNotification` would be a one-line follow-up; deferred rather than
  quietly chosen for the user.

## Acceptance criteria (from the module doc, mapped)

| Criterion | Met by |
|-----------|--------|
| Users see unread count in nav | Step 8 — already partly working, plus the `count()` fix |
| Click notification opens related record | Steps 1 (`link` resolver, incl. the two link-less types), 7 (read-then-redirect), 8 |
| Emails sent for configured events, testable with Mailpit | Steps 4, 6, 10 |
| Mark as read | Step 7 |
| Mailable classes per event type | Step 6 — satisfied by catalog-driven `toMail()`; the doc's wording assumed per-type classes, superseded by D12.1 |
| Queue mail on database driver | Step 4 (`GenericMailNotification implements ShouldQueue`) |

---

## Docs to update on completion

- `CLAUDE.md` — M12 row to ✅, note the inline-database / queued-mail split and the
  `queue:work` requirement, update the test count
- `docs/planning/MODULES_INDEX.md` — status table (line 102), the contract table (line 158),
  and the "no blocking dependency" note (line 182)
- `docs/planning/modules/M12-notifications.md` — tick the four open task boxes
- `docs/decisions-log.md` — D12.1–D12.4, plus the `ShouldQueue`-splits-all-channels finding,
  which is the sort of thing that gets rediscovered painfully
- `docs/developer-setup.md` — Mailpit + the silent no-worker failure mode
