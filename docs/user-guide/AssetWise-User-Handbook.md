# AssetWise — User Handbook

*Enterprise Asset Management — end‑user guide*

This handbook walks you through everyday use of AssetWise: registering and tracking assets, organising them with categories and custom fields, tagging, movements and approvals, maintenance, depreciation, disposal, kits, and reporting.

> The screenshots in this guide were captured from a demo environment with sample data. Your screens will show your own company, categories, and assets. Menu items you see depend on your **role** (see [Appendix B](#appendix-b--roles-at-a-glance)).
>
> **Call‑out boxes.** On several screenshots, coloured boxes and short labels point to the specific field or action described in the surrounding text — for example, the category picker and the custom fields it reveals on the New Asset form.

---

## Contents

1. [Getting started](#1-getting-started)
2. [Dashboard](#2-dashboard)
3. [Asset categories & custom fields](#3-asset-categories--custom-fields)
4. [Managing assets](#4-managing-assets)
5. [QR / barcode tagging](#5-qr--barcode-tagging)
6. [Bulk import & export](#6-bulk-import--export)
7. [Asset movement & approvals](#7-asset-movement--approvals)
8. [Maintenance](#8-maintenance)
9. [Depreciation](#9-depreciation)
10. [Disposal & scrap](#10-disposal--scrap)
11. [Asset kits & bundles](#11-asset-kits--bundles)
12. [Reports](#12-reports)
13. [Notifications](#13-notifications)
14. [Administration](#14-administration)
- [Appendix A — screenshot capture list](#appendix-a--screenshot-capture-list)
- [Appendix B — roles at a glance](#appendix-b--roles-at-a-glance)

---

## 1. Getting started

### Logging in

Open AssetWise in your browser and sign in with the email and password provided by your administrator. On first login you may be asked to set a new password.

### Finding your way around

Every screen shares the same layout:

- **Left sidebar** — the main menu: Dashboard, Assets, QR / Tags, Approvals, Movement, Audit, Maintenance, Disposal, Reports, and Administration. Sections expand to show sub‑pages.
- **Top bar** — a global **asset search**, a light/dark theme toggle, the **notifications bell**, and your account menu.
- **Content area** — lists, forms, and detail pages.

Menu items appear only if your role grants access to them.

---

## 2. Dashboard

The dashboard is your landing page. It summarises the assets you own or manage: totals, how many are assigned or in maintenance, pending approvals, tag availability, disposals this year, and upcoming warranty/AMC expiries — plus recent activity.

![Dashboard](screenshots/01-dashboard.png)

Use the dashboard cards as shortcuts into the areas that need attention (for example, expiring warranties or pending approvals).

---

## 3. Asset categories & custom fields

Categories classify your assets (e.g. *IT Equipment*, *Furniture*, *Networking*) and can be nested into a tree. Each category can also carry its own **custom fields**, so a laptop can capture "RAM (GB)" while a vehicle captures "Registration No." — without cluttering every other asset type.

> **Set up categories and their fields before you register assets** so the right fields are ready on the asset form.

### 3.1 Browsing and creating categories

Go to **Assets → Categories** (or **Administration → Categories**).

![Categories](screenshots/06-categories.png)

Click **New Category** to add one. Give it a name and code, optionally choose a parent category (to nest it), a description, and an active flag.

![Create category](screenshots/07-category-create.png)

### 3.2 Defining custom fields for a category

From the category list, open a category's **Fields**. Here you define the extra data captured for every asset in that category.

![Category custom fields](screenshots/08-category-fields.png)

Click **New Field** to add one:

![Create custom field](screenshots/09-category-field-create.png)

Each field has:

- **Label** and a **field key** (the internal name).
- A **type**: text, number, date, dropdown, boolean (yes/no), or textarea.
- **Required** — whether the asset form forces a value.
- **Searchable** — whether the field can be used to filter the asset list.
- **Dropdown options** — for dropdown fields, the list of choices.

**Inheritance & overrides.** Fields defined on a parent category are **inherited** by its child categories. A child can override an inherited field — hide it, relabel it, or change whether it's required — without affecting the parent.

**Category lock.** Once an asset has saved values for custom fields, its category is **locked** (you can't switch it to a different category on the edit form) to protect that data. Only a user with the special override permission can force a change.

**Soft delete.** Removing a field that already has data doesn't erase history — the field is hidden on new asset forms but still shown (read‑only) on existing assets.

---

## 4. Managing assets

### 4.1 The asset list, search & filters

Go to **Assets → All Assets**. This is the master register: every asset with its tag, category, status, custodian, and location. Use the **search box** (name, tag, or serial number) and the **filter bar** (company, category, type, status, location, department, branch) to narrow the list. Filters you apply here also drive the Asset Register report and its exports.

![Asset list](screenshots/02-assets-index.png)

### 4.2 Registering a new asset

Click **Add Asset** (or **Assets → Add Asset**). The form is grouped into sections:

![New asset form](screenshots/03-asset-create.png)

- **Identity** — Asset Name and Company (required). Company can only be changed later through an approved inter‑company transfer.
- **Category / Type / Status** — all required. **Choosing a category loads that category's custom fields right below**, so you fill them in the same form.
- **Identification** — serial number, model, manufacturer, description.
- **Assignment** — custodian, department, branch.
- **Location** — a cascading Location → Building → Floor → Room picker (each list loads based on the one above).
- **Purchase & Warranty** — purchase date and cost, vendor, warranty expiry, AMC expiry, EOL projected date, and an End‑of‑Life flag.
- **Notes.**

Click **Create Asset** to save.

### 4.3 Asset detail

Opening an asset shows its full record across tabs: **Summary**, **Photos**, **Attachments**, **Tags**, **Movements**, **Maintenance**, **Kits**, and **History**.

![Asset detail](screenshots/04-asset-show.png)

From here you can add photos and documents (invoices, warranty cards, manuals), assign a tag, request a movement or disposal, and see the full activity trail. The **Kits** tab lists any kit templates this asset belongs to.

### 4.4 Editing an asset

Click **Edit** on the detail page. The form matches the create form. Remember the **category lock**: if the asset already has custom‑field data, the category is read‑only.

![Edit asset](screenshots/05-asset-edit.png)

---

## 5. QR / barcode tagging

AssetWise uses a **tag pool**: you pre‑generate a batch of QR or barcode labels, print them, and assign them to assets later. Manage the pool under **QR / Tags**.

![Tag pool](screenshots/12-tags.png)

Typical flow:

1. **Generate a batch** of tags into the pool (they start as *available*).
2. **Print** the labels (PDF or Word label sheets) and stick them on the assets.
3. **Assign** a tag to an asset from the asset's **Tags** tab.
4. **Scan** a tag (from a phone via the app's scan screen) to jump straight to that asset. Replacing a damaged tag goes through an approval.

Whether new batches use QR or barcode is controlled under **Administration → Tag Settings**.

---

## 6. Bulk import & export

### Importing

Go to **Assets → Bulk Import**. Download the **per‑category Excel template**, fill in your rows (the template includes that category's custom fields as columns), and upload it. AssetWise validates every row and reports successes and errors per row.

![Bulk import](screenshots/10-bulk-import.png)

### Exporting

Use **Export** to download the current, filtered asset list to Excel. Large exports run in the background and notify you when the file is ready.

![Bulk export](screenshots/11-bulk-export.png)

---

## 7. Asset movement & approvals

Movements record an asset changing hands or location. Every movement is **approval‑gated** — it's submitted, routed to approvers, and only applied once approved.

### 7.1 Creating a movement

Go to **Movement → New Movement**. Pick the asset and a **movement type**:

- **Assignment** / **Custodian Change** — hand the asset to a person.
- **Return** — send it back to the pool.
- **Transfer** — relocate it to a new location/department.
- **Inter‑Company Transfer** — change the owning company (updates ownership and, if the asset is depreciated, restarts its depreciation for the receiving company).

![New movement](screenshots/14-movement-create.png)

### 7.2 Bulk movement

To move several assets at once, use **Movement → Bulk Move**. Select multiple assets and one destination; the whole batch is approved together in a single request.

![Bulk movement](screenshots/15-movement-bulk.png)

### 7.3 Movement history

**Movement → Movements** lists every movement with its status (pending approval, completed, rejected) and lets approvers verify completed moves.

![Movements](screenshots/13-movements.png)

### 7.4 Approvals inbox

Approvers act from **Approvals**. Each pending request shows what's being approved; open it to **approve** or **reject** (a reason is required to reject). Approving the final step applies the change automatically. Requests that sit too long are **escalated**.

![Approvals inbox](screenshots/16-approvals.png)

---

## 8. Maintenance

Track servicing, contracts, and end‑of‑life under **Maintenance**. Maintenance is permission‑gated but doesn't require an approval workflow.

### 8.1 Maintenance records

**Maintenance → Maintenance Records** logs scheduled, in‑progress, completed, or cancelled jobs with vendor and cost. Starting an in‑progress job flips the asset's status to *In Maintenance* and restores it when the job completes or is cancelled. A completed job can optionally be **capitalized** (its cost added to the asset's depreciation basis and its useful life extended — see §9).

![Maintenance records](screenshots/17-maintenance.png)

### 8.2 AMC contracts

**Maintenance → AMC** tracks Annual Maintenance Contracts (vendor, coverage, dates, cost). An asset can hold a full history of contracts; its AMC expiry always reflects the furthest end date.

![AMC contracts](screenshots/18-amc.png)

### 8.3 Warranty

**Maintenance → Warranty** tracks warranty periods the same way.

![Warranty](screenshots/19-warranty.png)

**Expiry alerts.** A daily job notifies maintenance staff and the asset's custodian when a warranty or AMC is 30 / 7 / 1 days from expiring.

---

## 9. Depreciation

AssetWise depreciates fixed assets using the **Companies Act 2013, Schedule II** convention: straight‑line, pro‑rata by day, with a default 5% residual value. You set defaults per category and can override per asset.

### 9.1 Methods

**Administration → Depreciation Methods** lists the available calculation methods. Straight‑line is active; others are shown but not yet selectable.

![Depreciation methods](screenshots/20-depreciation-methods.png)

### 9.2 Configuring an asset's depreciation

On an asset, open its **Depreciation** page. You'll see the current settings (or the category defaults to start from) and a form to request a change — method, useful life, cost basis, start date, and salvage (a fixed amount or a percentage).

![Asset depreciation](screenshots/21-asset-depreciation.png)

Depreciation setting changes are **approval‑gated**: submit the change, and it's applied once approved.

### 9.3 The depreciation schedule

The **Schedule** view shows the period‑by‑period breakdown: monthly depreciation, accumulated depreciation, and closing book value. Completed months are marked *posted*; a daily job posts each month as it closes.

![Depreciation schedule](screenshots/22-depreciation-schedule.png)

**Interactions.** When an asset is **disposed/sold**, depreciation stops and a gain/loss (proceeds − book value) is recorded. On an **inter‑company transfer**, the seller's schedule stops and a fresh one starts for the buyer at the current book value.

---

## 10. Disposal & scrap

Retiring an asset follows a controlled lifecycle: **request → approve → write‑off → scrap**.

### 10.1 Requesting a disposal

Go to **Disposal → New Disposal** (or **Request Disposal** from an asset). Choose the disposal type (scrap, sell, donate, return to vendor) and give a reason.

![New disposal](screenshots/24-disposal-create.png)

### 10.2 Processing disposals

**Disposal → Disposals** lists requests and their status. After approval, a user with completion rights records the **write‑off** (with any sale proceeds — which produces the gain/loss figure), then marks it **scrapped**, which sets the asset's status to *Disposed* and locks it from further movement.

![Disposals](screenshots/23-disposals.png)

---

## 11. Asset kits & bundles

Kits let you assign a group of assets together — either a saved **kit template** (e.g. "Developer Workstation") or an **ad‑hoc bundle** of hand‑picked assets.

### 11.1 Kit templates

**Kits** lists your templates with a readiness indicator (are all slots filled?).

![Kits](screenshots/25-kits.png)

Create a template with **New Kit Template**, then add **slots** (each with an optional required category and a quantity) and link physical assets to those slots.

![Create kit](screenshots/26-kit-create.png)

### 11.2 Assigning a kit or bundle

Use **Assign a Kit / Bundle**. Choose a saved kit *or* multi‑select assets ad‑hoc, pick a movement type (assign to a custodian, relocate, or inter‑company transfer), and set the destination. The whole group moves in one action.

![Assign kit](screenshots/27-kit-assign.png)

Depending on the system setting, the assignment is approved as **one request for the whole kit** or **one request per asset**. Assignment history — including whole‑kit **returns** — is on the **Kit Assignments** page.

![Kit assignments](screenshots/28-kit-assignments.png)

The single‑vs‑per‑asset behaviour is set under **Administration → Kit Settings**.

![Kit settings](screenshots/38-kit-settings.png)

---

## 12. Reports

**Reports** offers company‑scoped, exportable reports. Pick a report, apply filters, and export to **Excel** or **PDF** (large exports run in the background).

![Reports](screenshots/29-reports.png)

Available reports include Asset Register, Movement, Inter‑Company Transfer, Disposal, Maintenance, AMC & Warranty, Asset Aging, Utilization, Audit/Compliance, Audit Campaign, and Depreciation Schedule.

![Asset Register report](screenshots/30-report-asset-register.png)

![Depreciation Schedule report](screenshots/31-report-depreciation.png)

![Maintenance report](screenshots/32-report-maintenance.png)

---

## 13. Notifications

The **bell** in the top bar and the **Notifications** page show approvals awaiting you, completed movements/disposals, expiry alerts, and "export ready" messages. Click a notification to jump to the related record; mark items read individually or all at once. You can choose which notifications also arrive by email under your **Profile → Notifications**.

![Notifications](screenshots/33-notifications.png)

---

## 14. Administration

Administrators manage the reference data and access that the rest of the app relies on, under **Administration**.

**Users** — create users, assign roles, toggle active, reset passwords.

![Users](screenshots/34-admin-users.png)

**Roles & permissions** — define roles and their permission matrix. Six roles are seeded and can't be deleted (see Appendix B).

![Roles](screenshots/35-admin-roles.png)

**Approval workflows** — configure the multi‑level approval chains and escalation for movements, disposals, tag replacement, and kit assignments. (A kit‑assignment workflow must be configured here before single‑approval kit assignments can be used.)

![Workflows](screenshots/36-workflows.png)

**Shared masters** — the pick‑lists used across the app: asset statuses, types, priorities, movement types, audit types, disposal types, maintenance types, plus departments, branches, and designations. Companies and the Location → Building → Floor → Room hierarchy are managed here too.

![Masters](screenshots/37-masters.png)

---

## Appendix A — screenshot capture list

To regenerate these screenshots, sign in as an administrator and capture each page below (desktop width, full page). Paths are relative to the site root.

| # | File | Page | Path |
|---|------|------|------|
| 1 | 01-dashboard | Dashboard | `/dashboard` |
| 2 | 02-assets-index | Asset list | `/assets` |
| 3 | 03-asset-create | New asset form | `/assets/create` |
| 4 | 04-asset-show | Asset detail | `/assets/{id}` |
| 5 | 05-asset-edit | Edit asset | `/assets/{id}/edit` |
| 6 | 06-categories | Categories | `/admin/categories` |
| 7 | 07-category-create | New category | `/admin/categories/create` |
| 8 | 08-category-fields | Category custom fields | `/admin/categories/{id}/fields` |
| 9 | 09-category-field-create | New custom field | `/admin/categories/{id}/fields/create` |
| 10 | 10-bulk-import | Bulk import | `/assets/import` |
| 11 | 11-bulk-export | Bulk export | `/assets/export` |
| 12 | 12-tags | Tag pool | `/admin/tags` |
| 13 | 13-movements | Movements | `/movements` |
| 14 | 14-movement-create | New movement | `/movements/create` |
| 15 | 15-movement-bulk | Bulk movement | `/movements/bulk/create` |
| 16 | 16-approvals | Approvals inbox | `/approvals` |
| 17 | 17-maintenance | Maintenance records | `/maintenance` |
| 18 | 18-amc | AMC contracts | `/amc` |
| 19 | 19-warranty | Warranty | `/warranty` |
| 20 | 20-depreciation-methods | Depreciation methods | `/admin/depreciation-methods` |
| 21 | 21-asset-depreciation | Asset depreciation | `/assets/{id}/depreciation` |
| 22 | 22-depreciation-schedule | Depreciation schedule | `/assets/{id}/depreciation/schedule` |
| 23 | 23-disposals | Disposals | `/disposals` |
| 24 | 24-disposal-create | New disposal | `/disposals/create` |
| 25 | 25-kits | Kit templates | `/kits` |
| 26 | 26-kit-create | New kit | `/kits/create` |
| 27 | 27-kit-assign | Assign kit / bundle | `/kit-assignments/create` |
| 28 | 28-kit-assignments | Kit assignment history | `/kit-assignments` |
| 29 | 29-reports | Reports | `/reports` |
| 30 | 30-report-asset-register | Asset Register report | `/reports/asset_register` |
| 31 | 31-report-depreciation | Depreciation report | `/reports/depreciation_schedule` |
| 32 | 32-report-maintenance | Maintenance report | `/reports/maintenance` |
| 33 | 33-notifications | Notifications | `/notifications` |
| 34 | 34-admin-users | Users | `/admin/users` |
| 35 | 35-admin-roles | Roles | `/admin/roles` |
| 36 | 36-workflows | Workflows | `/admin/workflows` |
| 37 | 37-masters | Shared masters | `/admin/masters` |
| 38 | 38-kit-settings | Kit settings | `/admin/settings/kits` |

---

## Appendix B — roles at a glance

AssetWise ships six roles (which can't be renamed or deleted); administrators can add custom roles.

| Role | Typical use |
|------|-------------|
| **Super Admin** | Full access to everything, including administration. |
| **Asset Manager** | Day‑to‑day owner of assets — create/edit assets, tags, movements, maintenance, depreciation, kits, disposal, reports, and masters. |
| **Department User** | View assets, request assignments, view reports. |
| **Auditor** | View assets, run audit campaigns and verification, view reports and the activity log. |
| **Approver** | Act on approval requests (movements, disposals, kit assignments, etc.). |
| **Viewer** | Read‑only access to assets and reports. |

The exact menu items and buttons you see always reflect the permissions attached to your role.
