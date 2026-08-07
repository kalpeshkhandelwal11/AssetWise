---
name: feedback-testing-conventions
description: "AssetWise test suite conventions — factory password, session expiry pattern, event listener auto-discovery pitfall"
metadata:
  type: feedback
---

## Factory password is `Admin@1234`, not `password`

The UserFactory uses `Admin@1234` (satisfies the global strong-password policy in AppServiceProvider). All Breeze-generated tests that used `'password'` as the current or new password were broken and had to be fixed.

**Why:** AppServiceProvider sets `Password::defaults()` to min 8, mixed case, numbers, symbols. `'password'` fails this policy.

**How to apply:** Always use `Admin@1234` as the current-password in tests. Use `NewPass@9876` (or similar strong password) as the replacement password in update flows.

## Session expiry tests need a seed request before time travel

`CheckSessionLifetime` sets `_last_activity_at` on the **first authenticated GET**, not on the login POST (because at login time the user is not yet authenticated when the middleware runs). Expiry tests must follow this pattern:

```php
$this->post('/login', [...]);           // login
$this->get('/dashboard');               // seeds _last_activity_at
$this->travel(9)->hours();
$this->get('/dashboard')->assertRedirect('/login');
$this->travelBack();
```

**Why:** `withSession(['_last_activity_at' => ...])` does NOT reliably persist to the request session (StartSession may start a fresh session with a new ID). Real login + real request is the reliable approach.

## `diffInMinutes` sign changed in Carbon 3

Carbon 3 changed the default for `$absolute` from `true` to `false`. `now()->diffInMinutes($pastDate)` returns a **negative** value. The middleware bug was `-540 > 480 = false` (no expiry). Fix: use `$pastDate->diffInMinutes(now())` which is positive.

## Event listeners auto-discovered — never register manually too

Laravel auto-discovers listeners via `handle()` type-hints in `app/Listeners/`. If you ALSO register with `Event::listen()` in `AppServiceProvider::boot()`, the listener fires TWICE per event. `LogSuccessfulLogin` and `LogFailedLogin` were double-registered, causing 2 history records per login.

**How to apply:** Do NOT add `Event::listen(...)` calls for any listener class in `app/Listeners/` — rely on auto-discovery only.

## Use partial `Event::fake([X::class])`, never a bare `Event::fake()`

A bare `Event::fake()` intercepts *all* events, including the Eloquent model events (`created`, `updated`, …) that Spatie's `LogsActivity` trait relies on. Tests that assert on domain events while also touching `Asset` (or any `LogsActivity` model) must scope the fake:

```php
Event::fake([ApprovalRequestApproved::class]);   // not Event::fake()
```

**Why:** silently disabling activity logging makes an unrelated assertion fail later, and the cause is not obvious from the failure message.

## Time-travel tests must always `travelBack()`

Escalation tests (`WorkflowServiceTest`, `EscalateApprovalsCommandTest`) follow: create → `$this->travel(49)->hours()` → act/assert → `$this->travelBack()`. Leaving time advanced leaks into later tests in the same class.
