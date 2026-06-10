# M15 — PWA (Full Site)

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 3 |
| **Depends on** | M00 UI, all major screens exist |

## Scope

Installable full-site PWA, service worker, mobile-responsive polish, camera QR scanning.

## Packages

- `vite-plugin-pwa`
- `html5-qrcode` (npm, for camera scan in M10/M05 views)

## Tasks

- [ ] Configure `vite-plugin-pwa` manifest (name, icons, `start_url: /dashboard`, `display: standalone`)
- [ ] Service worker: cache static assets; network-first for pages/API
- [ ] Offline fallback page
- [ ] Install prompt banner component
- [ ] `theme-color`, splash screens, app icons (192, 512)
- [ ] Mobile-responsive pass on audit + scan + movement screens
- [ ] Camera QR component reusable in audit verify + scan page
- [ ] HTTPS note for production; localhost OK for dev

## Acceptance criteria

- App installable on Chrome Android / Edge desktop
- Installed app opens to dashboard after login
- Camera scan works on mobile HTTPS (or localhost)
- Offline shows fallback, not broken blank page

## User flow

See parent plan UF-18.

## Not in scope

- Offline data sync or offline asset edits
