# Running the Dev Server (AssetWise)

Run each command below in its **own terminal window** (PowerShell or Laragon's
terminal) so they keep running independently. Background tasks started
through Claude Code do not survive between turns in this environment, so
start these manually in terminals you keep open.

## 1. Laravel server

```powershell
cd D:\PROJECTS\AssetWise-daniels_branch
php artisan serve --host=0.0.0.0 --port=8000
```

`--host=0.0.0.0` binds to all network interfaces (not just `127.0.0.1`), so
other devices on the same Wi-Fi (e.g. a phone) can reach it.

## 2. Vite (assets / hot module reload)

```powershell
cd D:\PROJECTS\AssetWise-daniels_branch
npm run dev
```

`vite.config.js` is already configured with `server.host: '0.0.0.0'` and
`server.hmr.host` set to the machine's LAN IP, so assets and HMR work from
other devices too, not just `localhost`.

## 3. Queue listener

```powershell
cd D:\PROJECTS\AssetWise-daniels_branch
php artisan queue:listen --tries=1 --timeout=0
```

Needed for background jobs — bulk import/export (M06), queued report
exports (M14), notifications, etc.

## Notes

- `composer run dev` (the bundled script that runs server + queue +
  `pail` logs + vite together via `concurrently`) **fails on Windows** —
  `php artisan pail` requires the `pcntl` PHP extension, which isn't
  available on Windows PHP builds, and `--kill-others` tears down the
  whole stack when `pail` crashes. Run the three commands above
  separately instead.
- Local URL: `http://127.0.0.1:8000` or `http://localhost:8000`

## Open on your phone (same Wi-Fi network)

```
http://192.168.29.67:8000
```

Replace `192.168.29.67` if the machine's Wi-Fi IP changes — find the
current one with:

```powershell
Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -eq 'Wi-Fi' } | Select-Object IPAddress
```

If the phone can't connect, check that Windows Firewall allows inbound
connections to `php.exe` and `node.exe` on the Private network profile.
