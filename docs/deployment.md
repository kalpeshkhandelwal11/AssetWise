# Deployment (cPanel shared hosting)

How AssetWise is built locally and deployed to a cPanel host. The live install is
**https://assetwise.mandeepa.com** (cPanel user `mandeepa`, server
`cloud101lnx.cgsinfotech.com`), but nothing here is site-specific except the config
block at the top of the scripts.

> **Model:** build the app **locally** (Composer + Vite), ship a tarball of the built
> artifact, and run only migrations/caching on the server. The server needs **no**
> Composer or Node — only PHP 8.3 + MySQL. This suits shared hosts with limited tooling.

Secrets (`APP_KEY`, DB password, mailbox password) live **only** in the server's
`.env`. They are never committed — `server-deploy.sh` generates them.

---

## 0. Prerequisites

**Local (Windows / Laragon):**
- PHP 8.3 (`C:\laragon\bin\php\php-8.3.*\php.exe`), Composer (`composer.phar`), Node 20+ / npm
- `tar` (ships with Windows 10/11), `curl` (ditto)

**Server (cPanel):**
- PHP 8.3 available (`/opt/cpanel/ea-php83/root/usr/bin/php`)
- MySQL 8, SSH/Terminal access, FTP access
- A subdomain already created (docroot under `public_html/`)

---

## 1. Build the deployable artifact (local)

```powershell
# From the repo root
powershell -File tools/build-deploy.ps1
```

`tools/build-deploy.ps1`:
1. `git archive HEAD` → clean, tracked-only source into `G:\_awbuild` (no `.env`, `.git`, uploads),
2. copies `vendor/` + `node_modules/` in to speed the build,
3. `composer install --no-dev --optimize-autoloader`,
4. `npm run build` (**required** — the PWA + `public/build` won't exist otherwise).

Then package it (excludes dev-only trees):

```powershell
tar -czf G:\assetwise-deploy.tar.gz -C G:\_awbuild `
  --exclude=./node_modules --exclude=./.git --exclude=./.env --exclude=./tests `
  --exclude=./storage/logs/*.log .
```

Result: a ~14 MB `assetwise-deploy.tar.gz` containing `app/`, `vendor/`, `public/build`, etc.

---

## 2. Upload to the server (FTP)

```powershell
$u='<cpanel_user>'; $p='<ftp_password>'
curl.exe -sS --ftp-pasv -T G:\assetwise-deploy.tar.gz --user "${u}:${p}" ftp://ftp.<domain>/assetwise-deploy.tar.gz
curl.exe -sS --ftp-pasv -T tools/deploy/server-deploy.sh --user "${u}:${p}" ftp://ftp.<domain>/server-deploy.sh
```

FTP root for the main cPanel user **is the home directory** (which contains `public_html`),
so the files land in `~`. Normalize the `.sh` to LF before upload or CRLF `\r` will corrupt
`.env` values and break `bash`:

```powershell
$sh='tools/deploy/server-deploy.sh'; [IO.File]::WriteAllText($sh, ([IO.File]::ReadAllText($sh) -replace "`r`n","`n"))
```

---

## 3. First-time deploy (server, over SSH)

```bash
cd ~
sed -i 's/\r$//' server-deploy.sh          # belt-and-braces CR strip
bash server-deploy.sh 2>&1 | tee deploy.log
```

`tools/deploy/server-deploy.sh` (idempotent-ish) does:
1. create DB `<user>_assetwise` + user `<user>_awuser` + grant (via `uapi Mysql …`),
2. extract the app to `~/assetwise` (**private, outside the web root**),
3. write `.env` (generates DB password; runs `artisan key:generate`),
4. `storage:link`, `migrate --force --seed`,
5. `config:clear` + `route:cache` + `view:cache` — **never `config:cache`** (see Gotchas),
6. attempt to point the docroot at `public/`.

### 3a. Document root (the cPanel gotcha)

cPanel requires the subdomain docroot to **begin with `public_html/`**, so you cannot point
it at `~/assetwise/public` and `uapi SubDomain changedocroot` will refuse. Instead, make the
existing docroot a **symlink** to the app's `public/`:

```bash
cd ~/public_html
mv <subdomain_dir> _old_<subdomain_dir>            # set the default docroot aside
ln -s /home/<user>/assetwise/public <subdomain_dir>
ls -la <subdomain_dir>                              # confirm the symlink
```

Apache follows it because owner matches (`SymLinksIfOwnerMatch`). The cPanel docroot path is
unchanged, so no `changedocroot` call is needed.

### 3b. Cron (scheduler + queue worker)

No queue daemon on shared hosting — drive both from cron (`MAILTO=""` silences mail):

```bash
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
( crontab -l 2>/dev/null | grep -v 'assetwise/artisan'
  echo "* * * * * $PHP /home/<user>/assetwise/artisan schedule:run >/dev/null 2>&1"
  echo "* * * * * cd /home/<user>/assetwise && flock -n storage/queue.lock $PHP artisan queue:work --stop-when-empty --max-time=50 >/dev/null 2>&1"
) | crontab -
```

- `schedule:run` → `approvals:escalate`, `alerts:expiry` (M11), `depreciation:post-monthly` (M16).
- `queue:work` → exports (M06/M14), imports, queued mail (`GenericMailNotification`). Without a
  worker these sit in the `jobs` table forever. `flock` prevents overlapping runs.

### 3c. First login

Seeded Super Admin: `admin@assetwise.test` / `Admin@1234` (forces a password change on first login).

---

## 4. Updating an existing install

Rebuild + upload the new tarball (steps 1–2), also upload `tools/deploy/deploy-update.sh`, then:

```bash
bash ~/deploy-update.sh 2>&1 | tee ~/deploy-update.log
```

`deploy-update.sh` differs from the fresh install: it does **not** touch the DB/`.env`/seed. It
backs up `.env`, drops to maintenance mode, extracts the new build over `~/assetwise`
(`--keep-directory-symlink` preserves `public/storage`), runs `migrate --force` (no seed),
refreshes route/view caches, `queue:restart`, and lifts maintenance mode.

---

## 5. Mail (SMTP + deliverability)

Configured in `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=mandeepa.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=assetwise@mandeepa.com
MAIL_PASSWORD="********"
MAIL_FROM_ADDRESS="assetwise@mandeepa.com"
```

Test (bypasses the queue, shows errors immediately):

```bash
cd ~/assetwise
/opt/cpanel/ea-php83/root/usr/bin/php artisan tinker --execute='Mail::raw("test", fn($m)=>$m->to("you@example.com")->subject("test")); echo "SENT";'
```

**Deliverability:** Gmail rejects unauthenticated senders (`550-5.7.26 … SPF/DKIM`). Two facts
that made it pass here:
- The sender is the **root** domain `mandeepa.com`, whose existing SPF
  `v=spf1 mx a include:… ~all` authorizes the server's send IP via the `a` mechanism (the
  subdomain `assetwise.mandeepa.com` had **no** SPF/DKIM and was rejected).
- If the domain's SPF doesn't cover the send IP, add a TXT record on the sending host
  (`v=spf1 +mx +a +ip4:<server_ip> ~all`) and the DKIM record from **cPanel → Email
  Deliverability**. DNS here is **external** (not on the cPanel server), so those records must be
  added at the DNS provider, not via cPanel.

---

## Gotchas

- **Never run `php artisan config:cache`.** `config/notifications.php` (M12 catalog) contains
  closures → the command aborts with `Call to undefined method Closure::__set_state()`. Use
  `config:clear`; `route:cache`/`view:cache` are fine.
- **Docroot must start with `public_html/`** → use the symlink workaround (3a).
- **Normalize `.sh` to LF** before FTP upload, or `\r` corrupts `.env` values and `bash`.
- **The cPanel API over MCP (`webpros-mcp`) was unreliable** here (executions against port 2083
  timed out); provisioning was done over SSH + FTP instead. Don't block on MCP.
- **Build on PHP 8.3**; running the built `vendor/` on 8.3 or 8.4 is fine (forward compatible),
  not the reverse.
