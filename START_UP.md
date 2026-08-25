# Getting Started - Local Server Setup

Quick-start guide for running AssetWise on your own machine and logging in to explore it.

> **Assumes dependencies are already installed** — `composer install` and `npm install` have been run. If they haven't, see [`docs/developer-setup.md`](docs/developer-setup.md) for the full walkthrough.
>
> **PHP 8.3 or newer is required.** Laravel 13 will not boot on 8.2 — every `php artisan` command fails with *"Composer detected issues in your platform"* before any application code runs. Check with `php -v` first.

---

## Steps to Start the Server Locally

### 1. Confirm your PHP version

```powershell
php -v
```

Expected: `PHP 8.3.x` or newer. If you see 8.2, open a **new** terminal (a PATH change won't reach an already-open one) and check again.

### 2. Prepare the environment file — *first run only*

```powershell
copy .env.example .env
php artisan key:generate
```

Skip this if `.env` already exists.

### 3. Create and seed the database — *first run only*

```powershell
php artisan migrate --seed
php artisan storage:link
```

This creates every table and seeds roles, permissions, shared masters, the admin account, and the two default approval workflows.

To wipe and start over at any point:

```powershell
php artisan migrate:fresh --seed
```

> ⚠️ `migrate:fresh` destroys all local data, including any demo users you created in the [Access Credentials](#access-credentials) section. Re-run the snippet there afterwards.

### 4. Build the frontend assets

Pick **one**:

```powershell
npm run build     # one-off production build — simplest
```

```powershell
npm run dev       # hot-reload; leave running in its own terminal
```

> **If every page renders unstyled**, a stale `public/hot` file is left over from a killed `npm run dev`. Delete it and re-run `npm run build` — Vite only falls back to the built assets when that file is absent.

### 5. Start the server

**This project runs on Laragon** — there is no `php artisan serve` step normally. Open the Laragon app and click **Start All** (Apache/Nginx + MySQL). With the project under Laragon's `www` folder, it auto-serves at `http://assetwise.test` via its virtual host — nothing else to run.

If you're not using Laragon, `php artisan serve` works as a fallback:

```powershell
php artisan serve
```

Leave this running. The server stays in the foreground; stop it with `Ctrl+C`. If the port is already in use, pick another: `php artisan serve --port=8123`.

### 6. Verify it is running

| Check | How | Expected |
|-------|-----|----------|
| Homepage | Open `http://assetwise.test` (or `http://127.0.0.1:8000` if using `serve`) | Redirects via `/dashboard` to the login screen |
| Routes registered | `php artisan route:list` | A table of routes, no errors |
| Test suite | `php artisan test` | **591 passed** |

> **Frontend assets not loading / page unstyled?** Run `npm run dev` in its own terminal (hot-reload, leave it running) — this is the normal dev-loop flow and is required for Vite-served CSS/JS to work with Laragon. `npm run build` is a one-off alternative if you don't need HMR.

---

## Access Credentials

### Seeded by default

This account is created by `php artisan migrate --seed`:

| Username (email)        | Password     | Role        |
|-------------------------|--------------|-------------|
| `admin@assetwise.test`  | `Admin@1234` | Super Admin |

> **Expect a forced password change.** This account ships with `must_change_password = true`, so your first login redirects to `/password/change` before you can reach the dashboard. Either set a new password (must be 8+ characters with mixed case, a number, and a symbol — e.g. `NewPass@9876`), or clear the flag:
>
> ```powershell
> php artisan tinker --execute="App\Models\User::where('email','admin@assetwise.test')->update(['must_change_password'=>false]);"
> ```

> **Login fails with "these credentials do not match"?** Check whether the database is actually empty first — `migrate:fresh` run *without* `--seed` (easy to do by accident) drops all data but leaves the schema in place, so the app looks fine until you try to log in:
>
> ```powershell
> php artisan tinker --execute="echo App\Models\User::count() . ' users, ' . App\Models\Asset::count() . ' assets';"
> ```
>
> `0 users, 0 assets` means the seeders never ran. Fix it with:
>
> ```powershell
> php artisan db:seed
> ```
>
> This recreates the admin account above along with roles, masters, workflows, and demo assets — safe to run against empty tables.

### Additional demo accounts — one per role

AssetWise has six roles, but only the Super Admin above is seeded. As of M01 there's a full user-management UI at **Administration → Users** (create a user, assign a role, done) — the fastest way to add one demo account at a time.

To seed all five in one shot, the Tinker snippet below is still the quickest path:

Open an interactive Tinker session:

```powershell
php artisan tinker
```

Then paste this and press Enter:

```php
collect([
    ['Ava Manager',   'manager@assetwise.test',  'Asset Manager'],
    ['Dana Dept',     'dept@assetwise.test',     'Department User'],
    ['Alex Approver', 'approver@assetwise.test', 'Approver'],
    ['Ivan Auditor',  'auditor@assetwise.test',  'Auditor'],
    ['Vera Viewer',   'viewer@assetwise.test',   'Viewer'],
])->each(function ($r) {
    $u = App\Models\User::updateOrCreate(
        ['email' => $r[1]],
        ['name' => $r[0], 'password' => 'Demo@1234', 'is_active' => true,
         'must_change_password' => false, 'email_verified_at' => now()]
    );
    $u->syncRoles([$r[2]]);
});
```

Type `exit` to leave Tinker.

> **Paste it into the REPL — don't wrap it in `tinker --execute="…"`.** PowerShell interpolates `$r` and `$u` before PHP ever sees them, and PowerShell 5.1 mangles the quoting either way. Pasting into the interactive session sidesteps the shell entirely.

The snippet is safe to re-run — existing accounts are updated rather than duplicated. Afterwards these all work, with **no** forced password change:

| Username (email)          | Password    | Role            |
|---------------------------|-------------|-----------------|
| `manager@assetwise.test`  | `Demo@1234` | Asset Manager   |
| `dept@assetwise.test`     | `Demo@1234` | Department User |
| `approver@assetwise.test` | `Demo@1234` | Approver        |
| `auditor@assetwise.test`  | `Demo@1234` | Auditor         |
| `viewer@assetwise.test`   | `Demo@1234` | Viewer          |

> Log in with the **email address**, not the display name — the login form's field is labelled "Email".
>
> These are throwaway local credentials. Never reuse them anywhere real, and never commit real passwords to this repo.

### What each role can see

The sidebar changes per role — that is the quickest way to feel the permission model:

| Role            | Can do |
|-----------------|--------|
| Super Admin     | Everything, including Administration → Users, Roles & Permissions, Workflow Config, and Activity Log |
| Asset Manager   | Assets, masters (incl. departments/branches/designations), imports/exports, and approvals |
| Approver        | Approvals inbox, read-only assets |
| Auditor         | Audits, read-only assets, and Activity Log |
| Department User | Read-only assets, request movements |
| Viewer          | Read-only assets and reports |

---

## Next Steps

Once the server is running and you are logged in:

1. **Land on the dashboard** at `http://127.0.0.1:8000/dashboard`. Note which sidebar groups appear — they are gated by your role's permissions.
2. **Create your first asset** — *Assets → All Assets → Add Asset*. A freshly seeded database contains 1 company and 4 categories but **zero assets**, so this is the natural starting point. The form shows the cascading Location → Building → Floor → Room selects. Step 4 below needs at least one asset to exist.
3. **Try dynamic fields (M04).** Go to *Assets → Categories*, open a category, and add a custom field. Then create an asset in that category — your field appears on the form automatically, and child categories inherit it.
4. **Exercise the approval workflow (M08)** — the most interesting flow to explore end to end:
   - As Super Admin, visit *Administration → Workflow Config* to see the two seeded chains (Transfer, Tag Replacement) and their escalation settings.
   - Open a request for approval. Requires at least one asset (step 2) — run `php artisan tinker`, then paste:
     ```php
     app(App\Services\WorkflowService::class)->submit(
         App\Models\Asset::firstOrFail(),
         'transfer',
         App\Models\User::where('email', 'dept@assetwise.test')->firstOrFail()
     );
     ```
   - Log in as `approver@assetwise.test` — the request is waiting in **Approvals**, with a badge in the sidebar. Approve it.
   - Log in as `manager@assetwise.test` — it has advanced to level 2 and is now in that inbox. Approve again to complete it.
   - Open the request's detail page to see the append-only history.
5. **Watch escalation fire.** Approvals escalate after 48 hours, so trigger the sweep manually rather than waiting:
   ```powershell
   php artisan approvals:escalate
   ```
   It is idempotent — running it twice never double-escalates.
6. **Run the test suite** (`php artisan test`) before and after any change. It uses in-memory SQLite and never touches your local database.

### Where to go next

| I want to… | Read |
|------------|------|
| Set up from scratch, or troubleshoot | [`docs/developer-setup.md`](docs/developer-setup.md) |
| See what is built and what is next | [`docs/planning/MODULES_INDEX.md`](docs/planning/MODULES_INDEX.md) |
| Understand why something works the way it does | [`docs/decisions-log.md`](docs/decisions-log.md) |
| Pick up the next module | [`docs/planning/modules/`](docs/planning/modules/) |
| Follow project conventions when contributing | [`CLAUDE.md`](CLAUDE.md) |
