# AssetWise Planning Documentation

All planning artifacts for the Asset Management System (single-company Laravel deployment).

> **Status (2026-08-07):** M00, M03, M04, M08 complete; M01 and M02 partial (see the gaps table in [`../decisions-log.md`](../decisions-log.md)); **M05 and M06 are next**, both unblocked. Test suite: 273 passing.

## Contents

| File | Description |
|------|-------------|
| [Asset_Management_BRD_v1.txt](Asset_Management_BRD_v1.txt) | Original business requirements document |
| [MASTER_PLAN.md](MASTER_PLAN.md) | Full implementation plan — architecture, database, user flows, phases |
| [MODULES_INDEX.md](MODULES_INDEX.md) | Module map **with live status column**, parallel dev timeline, Laragon setup, cross-module contracts |
| [modules/](modules/) | Per-module specs (M00–M17) for Developer 1 / Developer 2 — task checkboxes reflect what is actually built |
| [../decisions-log.md](../decisions-log.md) | Running record of decisions made, plus open `P#.#` questions to resolve before each module starts |

## Module files

- M00 Foundation → M07 Shared UI (Dev 1)
- M08 Approval Workflow → M17 Kits → M16 Depreciation → M15 PWA (Dev 2)

Start with [MODULES_INDEX.md](MODULES_INDEX.md) for development order and local setup.
