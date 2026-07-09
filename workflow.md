# Asset-Store-BD — Complete Business & Functional Workflow

> **What this document is.** A single, end-to-end map of how the whole system works — every module, what it does, how it links to the others, and the exact sequence of actions from a user click to a database change. It is written to be read two ways:
>
> - **Non-technical readers** — read the plain-language "In plain words" boxes and the numbered step lists. Skip the code paths.
> - **Technical readers** — the file/class/method references (`ClassName::method()`), tables, routes, and state machines give you the implementation-level truth.
>
> **The system in one sentence:** It is a **Snipe-IT IT-asset-management platform** (track hardware, licenses, accessories, consumables, who has what) with a **custom "Gov-Store" procurement layer bolted on top** that lets ordinary staff browse a catalog, drop items in a basket, submit a formal Service Request, get it approved line-by-line by an admin, and have a storekeeper physically issue the goods — with every issue flowing straight back into Snipe-IT's real inventory.

---

## Table of Contents

1. [The Big Picture (start here)](#1-the-big-picture-start-here)
2. [Technical Architecture Overview](#2-technical-architecture-overview)
3. [Actors & Roles](#3-actors--roles)
4. [Part A — Core Snipe-IT Inventory Platform](#part-a--core-snipe-it-inventory-platform)
   - [A0. Foundation / Reference Data](#a0-foundation--reference-data-the-lookup-modules)
   - [A1. Users, Groups & Authentication](#a1-users-groups--authentication)
   - [A2. Assets & Asset Models](#a2-assets--asset-models-the-heart)
   - [A3. Licenses](#a3-licenses)
   - [A4. Accessories](#a4-accessories)
   - [A5. Consumables](#a5-consumables)
   - [A6. Components](#a6-components)
   - [A7. Predefined Kits](#a7-predefined-kits)
   - [A8. Maintenances](#a8-maintenances)
   - [A9. Native Asset Requests & Acceptance](#a9-native-asset-requests--acceptance)
   - [A10. Reporting, Action Log & Audit](#a10-reporting-action-log--audit)
   - [A11. Settings & Administration](#a11-settings--administration)
   - [A12. Import](#a12-import)
   - [A13. Dashboard](#a13-dashboard)
5. [Part B — Gov-Store Custom Requests (the procurement layer)](#part-b--gov-store-custom-requests-the-procurement-layer)
   - [B0. Why it exists & how it plugs in](#b0-why-it-exists--how-it-plugs-in)
   - [B1. Data Model](#b1-data-model)
   - [B2. The Catalog module](#b2-the-catalog-module)
   - [B3. The Basket module](#b3-the-basket-module)
   - [B4. Submission — the Service Request document](#b4-submission--the-service-request-document)
   - [B5. Approval module](#b5-approval-module-line-by-line)
   - [B6. Fulfillment module](#b6-fulfillment-module-progressive-issue)
   - [B7. The Adapter/Factory bridge to Snipe-IT](#b7-the-adapterfactory-bridge-to-snipe-it)
   - [B8. Timeline / event sourcing](#b8-timeline--event-sourcing)
   - [B9. Status state machines](#b9-status-state-machines)
   - [B10. Legacy single-item request flow](#b10-legacy-single-item-request-flow-itemrequest)
6. [End-to-End Cross-Module Scenarios](#6-end-to-end-cross-module-scenarios)
7. [Module Dependency Map](#7-module-dependency-map)
8. [Status Reference Tables](#8-status-reference-tables)
9. [Known Gaps, Quirks & Risks](#9-known-gaps-quirks--risks)
10. [Glossary](#10-glossary)

---

## 1. The Big Picture (start here)

The application has **two layers stacked on the same database and login**:

```
┌─────────────────────────────────────────────────────────────────────┐
│  LAYER 2 — GOV-STORE PROCUREMENT (custom package)                    │
│  "Shop → Basket → Service Request → Approve → Fulfill"               │
│  Staff self-service catalog + admin approval + storekeeper issuing.  │
│  Lives in: packages/gov-store/custom-requests/                       │
└───────────────▲─────────────────────────────────────────────────────┘
                │ calls down through "Adapters" to do the real checkout
                │
┌───────────────┴─────────────────────────────────────────────────────┐
│  LAYER 1 — CORE SNIPE-IT INVENTORY (Laravel app in app/)             │
│  The source of truth: what stock exists, who holds it, its history.  │
│  Assets, Licenses, Accessories, Consumables, Components, Users, ...  │
└─────────────────────────────────────────────────────────────────────┘
```

**Two lifecycles run the business:**

**(1) The Inventory Lifecycle (Layer 1)** — how a physical thing lives in the system:

```
   Procure/Import → Register as stock → CHECK OUT to a person/asset/location
        → (user Accepts) → in-use → maintenance/audit → CHECK IN → back to stock
        → retire/delete
```

**(2) The Request/Procurement Lifecycle (Layer 2)** — how a staff member gets something:

```
   Browse Catalog → Add to Basket → Submit Service Request
        → Admin reviews & Approves/Rejects each line (with quantity)
        → Storekeeper issues goods progressively (Fulfillment)
        → Each issue triggers a real Snipe-IT CHECK OUT (Layer 1)
        → Request auto-closes when everything approved is issued
```

The **join point** between the two layers is the **Adapter** classes: when Gov-Store fulfillment issues an item, it calls `AssetAdapter` / `AccessoryAdapter` / `ConsumableAdapter`, which perform the *exact same* checkout that a Snipe-IT admin would do manually — so inventory counts and item history stay perfectly correct.

---

## 2. Technical Architecture Overview

| Concern | Choice |
|---|---|
| Language / framework | **PHP 8.2+ / Laravel 12** |
| Frontend build | **Laravel Mix (webpack)**; `npm run dev` / `prod` / `watch` |
| UI | **AdminLTE 2 / Bootstrap 3**, Blade templates (no Livewire/Inertia in core UI) |
| Charts | **Chart.js v2.9.4** (`horizontalBar`, v2 API) |
| Dev server | **Laravel Herd** |
| Auth | Session (web) + Laravel Passport OAuth + optional LDAP/SAML/Google |

**Repeating code patterns (learn these once, they apply everywhere):**

1. **Two parallel controller trees.**
   - `app/Http/Controllers/…` → web/UI controllers that return **Blade views**.
   - `app/Http/Controllers/Api/…` → REST controllers that return **JSON**, consumed by the DataTables listings and Select2 dropdowns.

2. **Transformers.** Every API controller returns data through a class in `app/Http/Transformers/` — never raw model attributes. `DatatablesTransformer` wraps paginated lists. *(Rule: API output = transformer output.)*

3. **Policies.** All authorization goes through `app/Policies/`. `CheckoutablePermissionsPolicy` is the shared base for assets/licenses/accessories/consumables; its `checkout()`/`checkin()` accept a null item so `@can('checkout', Asset::class)` works class-wide.

4. **Routes.** `routes/web.php` (UI), `routes/api.php` (JSON), `routes/scim.php` (SCIM provisioning), `routes/console.php`. Breadcrumbs are declared inline per route via `tabuna/breadcrumbs`.

5. **Global settings.** `$snipeSettings` is injected into every Blade view by a service provider; feature flags (like FMCS below) are read from it.

6. **FMCS — Full Multiple Company Support.** When `Setting::getSettings()->full_multiple_companies_support == '1'`, data is scoped by company. Select2 endpoints accept `companyId`; models use `CompanyableTrait` + `CompanyableScope` to auto-filter queries by the current user's company.

7. **Translations.** UI strings live in `resources/lang/en-US/*.php` (`general.php` etc.). New strings are added as keys, not hard-coded.

---

## 3. Actors & Roles

| Actor | Where defined | What they can do |
|---|---|---|
| **Super User** | `User::isSuperUser()` | Everything, including Settings/Admin and Gov-Store approval + fulfillment. |
| **Admin** | permission `hasAccess('admin')` | Manage inventory, approve Gov-Store requests, run fulfillment. |
| **Manager / limited staff** | per-permission flags on `User` / `Group` | Checkout/checkin, view, edit — as granted by group permissions. |
| **End User (requester)** | any authenticated `User` | Browse catalog, build basket, submit Service Requests, view own requests, accept/decline assigned items. |
| **Storekeeper** | in Gov-Store = admin/superuser | Physically issues approved items (Fulfillment Queue). *(Currently gated by the same admin check as approvers.)* |

> **In plain words:** three human roles matter for the day-to-day flow — the **employee** who asks for stuff, the **approver** who says yes/no, and the **storekeeper** who hands it over. In the current build the approver and storekeeper are both "admins."

---

# Part A — Core Snipe-IT Inventory Platform

This is the foundation. Every Gov-Store action ultimately reads or writes these modules.

### A0. Foundation / Reference Data (the "lookup" modules)

These modules hold the **reference data** that everything else points at. They are simple CRUD (Create/Read/Update/Delete) with an API twin for dropdowns. They must exist *before* you can create assets meaningfully.

| Module | Controller (web / API) | Model | Purpose & who depends on it |
|---|---|---|---|
| **Companies** | `CompaniesController` / `Api\CompaniesController` | `Company` | Tenant boundary for FMCS. Assets, users, licenses, etc. carry `company_id`. |
| **Locations** | `LocationsController` / `Api\LocationsController` | `Location` | Physical places. Assets/users have a location; Gov-Store requests have a `delivery_location_id`. Self-referencing tree (parent location). |
| **Categories** | `CategoriesController` / `Api\CategoriesController` | `Category` | Classifies asset models, accessories, consumables, licenses, components. Drives whether acceptance/EULA is required and checkout email is sent. |
| **Manufacturers** | `ManufacturersController` / `Api\…` | `Manufacturer` | Brand of a model/accessory/consumable/license. |
| **Suppliers** | `SuppliersController` / `Api\…` | `Supplier` | Where an item was bought; used in purchase info and reports. |
| **Depreciations** | `DepreciationsController` / `Api\…` | `Depreciation` | Straight-line depreciation schedules applied to asset models. `Depreciable` base model computes current value. |
| **Status Labels** | `StatuslabelsController` / `Api\…` | `Statuslabel` | Asset states. A label has a **type**: `deployable` (can be checked out), `pending`, `archived`, `undeployable`. This is the gatekeeper for whether an asset can be issued. |
| **Departments** | `DepartmentsController` / `Api\…` | `Department` | Sub-grouping of users under a location/company. |
| **Custom Fields / Fieldsets** | `CustomFields(ets)Controller` / `Api\…` | `CustomField`, `CustomFieldset` | Attach extra attributes to asset models (e.g. MAC address). Fieldsets bind to `AssetModel`; values stored per-asset. |
| **Maintenance Types** | `MaintenanceTypesController` / `Api\…` | `MaintenanceType` | Lookup for maintenance records (repair, upgrade, …). |

Each of these has **bulk-delete** controllers (`Bulk*Controller`) for multi-select delete, and a `selectlist()` API method feeding Select2 (respecting `companyId` when FMCS is on).

> **In plain words:** before you can say "this laptop is a Dell in Room 204, category Laptops, status Ready-to-Deploy," someone must first have created the manufacturer *Dell*, the location *Room 204*, the category *Laptops*, and the status *Ready-to-Deploy*. These modules are that setup.

---

### A1. Users, Groups & Authentication

**Purpose:** identity + permission for everyone in the system, and the people/things that assets get checked out *to*.

**Key files:** `Users\UsersController`, `Api\UsersController`, `GroupsController`, `Auth\*Controller`, models `User`, `Group`, `Ldap`, `SCIMUser`.

**Authentication paths (all land on a `User`):**

- **Local login** — `Auth\LoginController` (email/username + password, throttled; login attempts logged, view via admin `login-attempts`).
- **LDAP** — `Ldap` model + `Users\LDAPImportController` sync directory users.
- **SAML SSO** — `Auth\SamlController` + `SamlNonce` (replay protection).
- **Google OAuth** — `GoogleAuthController` (`/google` redirect + `/google/callback`).
- **SCIM 2.0** — `routes/scim.php`, `SCIMUser`, `SnipeSCIMConfig` for automated provisioning from an IdP.
- **Password reset** — `Auth\ForgotPasswordController` + `Auth\ResetPasswordController`.
- **API tokens** — Passport personal access tokens, managed under `account/api` and admin `oauth`.

**Groups** bundle permission flags; a user's effective permissions = own permissions merged with group permissions. This is what the `hasAccess('admin')` / `isSuperUser()` checks read.

**A user is a checkout target.** Assets, accessories, consumables and licenses are assigned *to* a `User` (polymorphic `assigned_type = User::class`). A user's `location_id` is inherited by an asset on checkout (see AssetAdapter).

**Self-service area** (`/account`, `ProfileController` + `ViewAssetsController`):
- `profile`, `password`, `api` (personal tokens), `menu` state.
- `view-assets` — "what do I currently hold."
- `requested` — items I've requested (native Snipe-IT request feature, see A9).
- `accept` — pending acceptance of items checked out to me (see A9).

> **In plain words:** this module answers "who are you, what are you allowed to do, and what do you currently have." Everything checked out has a person on the other end, and that person is a record here.

---

### A2. Assets & Asset Models (the heart)

This is the central module — the physical, individually-tracked hardware.

**Key files:** `Assets\AssetsController` (web), `Api\AssetsController` (JSON), `Assets\AssetCheckoutController`, `Assets\AssetCheckinController`, `Assets\BulkAssetsController`, `AssetModelsController` + API. Models: `Asset` (extends `Depreciable` → `SnipeModel`), `AssetModel`.

**Asset vs. Asset Model — the critical distinction:**
- **`AssetModel`** = the *template* ("Dell Latitude 5540") — has a manufacturer, category, depreciation schedule, custom-fieldset, default image.
- **`Asset`** = one *physical unit* of that model — has a unique `asset_tag`, a serial, a `status_id`, and (when issued) an `assigned_to`.

**What an Asset carries (from `Asset::$rules`/`$casts`):** `asset_tag` (unique, required), `model_id` (required), `status_id` (required), `name`, `serial`, `company_id`, `rtd_company_id`, `location_id`, `supplier_id`, `purchase_date`, `warranty_months`, `assigned_to` + `assigned_type` (polymorphic target), `last_checkout`, `last_checkin`, `expected_checkin`, `last_audit_date`, `next_audit_date`, `requestable`, plus custom-field columns.

**Traits that give Asset its behaviors:**
- `CompanyableTrait` — FMCS auto-scoping.
- `Loggable` — writes to the **Action Log** on every meaningful change (`logCheckout`, `logCheckin`, `logAudit`, etc.).
- `Requestable` — can be requested by users (native request feature).
- `Acceptable` — checkout can require the user to accept/decline (drives `accepted` + `CheckoutAcceptance`).
- `HasUploads` — file attachments.
- `SoftDeletes` — deletes are recoverable; `unique_undeleted` validation ignores trashed rows.

**The Asset lifecycle & the actions that drive it:**

```
   CREATE ──► (status: Ready to Deploy / deployable)
     │
     ▼
   CHECK OUT ──► assigned_to set, status may change, last_checkout stamped,
     │           Action Log entry, optional Acceptance request + email
     ▼
   (user ACCEPTS or DECLINES)  ── decline resets assigned_to = null
     │
     ▼
   IN USE ──► can be AUDITED (last/next_audit_date), maintained (A8)
     │
     ▼
   CHECK IN ──► assigned_to cleared, last_checkin stamped, Action Log entry,
     │           back to deployable stock
     ▼
   RETIRE / DELETE (soft) ──► archived or trashed
```

**Checkout — step by step** (`AssetCheckoutController::store` → `Asset` model):
1. Authorize via policy (`@can('checkout', Asset::class)`).
2. Validate the target: a **user**, another **asset**, or a **location** (`Asset::LOCATION|ASSET|USER`).
3. Confirm the asset's current status label is **deployable** and it isn't already assigned.
4. Set `assigned_to` + `assigned_type`, update `location_id`, `last_checkout`, optionally `status_id`, `expected_checkin`.
5. Fire `CheckoutableCheckedOut` event → notifications (email/Slack) if the category requires it.
6. `logCheckout()` writes an Action Log row (this is what shows in the item's "History" tab).
7. If acceptance required → create a `CheckoutAcceptance`; the user sees it under `account/accept`.
8. Redirect per `Helper::getRedirectOption()` (`redirect_option` = index/item/target).

**Check-in** reverses steps 4–6 via `AssetCheckinController` + `logCheckin()`.

**Bulk operations** (`BulkAssetsController`): multi-edit, bulk checkout, bulk delete, label printing.

**Select2/status wiring:** checkout forms use `Helper::deployableStatusLabelList()`; AJAX dropdowns use `class="js-data-ajax" data-endpoint="hardware"` and forward `companyId` + `statusType`.

> **In plain words:** an *Asset Model* is like a product on a shelf catalog ("Dell laptop, this spec"); an *Asset* is the one actual laptop with a barcode sticker. The whole point of the system is knowing which real laptop is with which person, and keeping a full history of every hand-off. "Check out" = give it to someone; "check in" = take it back.

---

### A3. Licenses

**Purpose:** track software licenses and their **seats** (individual activations), assignable to users or assets.

**Key files:** `Licenses\LicensesController`, `Api\LicensesController`, `Licenses\LicenseCheckoutController`, `Licenses\LicenseCheckinController`, `Licenses\BulkLicensesController`, `Api\LicenseSeatsController`. Models: `License`, `LicenseSeat`.

**Model:** a `License` has `seats` (a quantity); creating/updating it generates that many `LicenseSeat` rows. Each seat is checked out independently to a user **or** an asset. `numRemaining()` = free seats.

**Actions:** create license (with seat count, expiry, maintenance, supplier) → check out a seat → (optional acceptance) → check in a seat. Authorization via `CheckoutablePermissionsPolicy`.

> **In plain words:** buy "20 seats of Photoshop," and the system makes 20 assignable slots. Give a slot to Alice, another to a shared design PC. It always knows how many are left.

---

### A4. Accessories

**Purpose:** low-value, **quantity-tracked, non-serialized** items handed to users and *not* individually barcoded (keyboards, mice, cables).

**Key files:** `Accessories\AccessoriesController`, `Api\AccessoriesController`, `Accessories\AccessoryCheckoutController`, `Accessories\AccessoryCheckinController`. Models: `Accessory`, `AccessoryCheckout` (the pivot recording who holds one).

**Model:** an `Accessory` has a total `qty`; `numRemaining()` = `qty` − checked-out count. Checkout **attaches** the user via the `users()` pivot and logs it; checkin detaches. Unlike assets, the *same* accessory can be out to many users simultaneously (up to qty).

> **In plain words:** you have 50 mice. Hand them out one at a time; the count drops. You don't track each mouse individually, just "who has how many and how many are left."

---

### A5. Consumables

**Purpose:** items that get **used up** and never come back (toner, paper, batteries).

**Key files:** `Consumables\ConsumablesController`, `Api\ConsumablesController`, `Consumables\ConsumableCheckoutController`. Models: `Consumable`, `ConsumableAssignment`.

**Model:** total `qty`, `numRemaining()` = qty − issued. **Checkout only — no check-in** (they're consumed). Checkout attaches the user via `users()` pivot + logs. `min_amt` drives low-stock alerts.

> **In plain words:** hand out a toner cartridge and it's gone for good; the count drops and never comes back. When stock is low the system can alert you.

---

### A6. Components

**Purpose:** internal parts installed **into assets** (RAM stick, SSD), quantity-tracked, checked out to an asset (not usually a person).

**Key files:** `Components\ComponentsController`, `Api\ComponentsController`, `Components\ComponentCheckoutController`, `Components\ComponentCheckinController`. Models: `Component`, `ComponentAssignment`.

**Model:** total `qty`; checkout links a quantity to a specific `Asset` via `ComponentAssignment`; checkin returns it to the pool.

> **In plain words:** you have 100 RAM sticks; install 2 into Laptop #A45. The system records that those 2 are now inside that laptop and 98 remain in the drawer.

---

### A7. Predefined Kits

**Purpose:** a **bundle** of items (assets + licenses + accessories + consumables) that can be checked out to a user in one action — e.g. a "New Developer Kit."

**Key files:** `Kits\PredefinedKitsController`, `Kits\CheckoutKitController`, `Api\PredefinedKitsController`. Model: `PredefinedKit` (has many of each item type via pivots).

**Action:** build the kit once → "checkout kit to user" fans out into individual checkouts of each contained item (each still logged separately).

> **In plain words:** instead of assigning a laptop, a mouse, a copy of Office and a notebook one by one to every new hire, define the bundle once and issue it all at once.

---

### A8. Maintenances

**Purpose:** record repairs/upgrades/servicing performed on an asset, with cost and dates.

**Key files:** `MaintenancesController`, `Api\MaintenancesController`, `MaintenanceTypesController`. Models: `Maintenance`, `MaintenanceType`.

**Action:** open a maintenance against an asset (type, supplier, start/completion date, cost, notes). Feeds the maintenance report and asset TCO. Does not change checkout state.

> **In plain words:** "Laptop #A45 went to the repair shop on the 3rd, cost $120, back on the 5th." Kept against the asset for cost tracking and history.

---

### A9. Native Asset Requests & Acceptance

> **Important:** this is Snipe-IT's *built-in* request feature — **separate from** the custom Gov-Store package in Part B. Both exist in this codebase.

**Purpose (Requests):** a user browses assets flagged `requestable` and asks for one; an admin sees the request and can fulfill it via a normal checkout.

**Key files:** `ViewAssetsController` (`getRequestedAssets`, `store`, `destroy`, `getRequestItem`), model `CheckoutRequest`, trait `Requestable`. Routes under `/account`: `request-asset/{asset}`, `request/{itemType}/{itemId}/…`.

**Purpose (Acceptance):** when an item is checked out and its category requires acceptance, the user must **Accept** (optionally e-signing a EULA) or **Decline**. Declining an asset calls `Asset::declinedCheckout()` which **nulls `assigned_to`**, returning it to stock.

**Key files:** `Account\AcceptanceController` (`index`, `create`, `store`), model `CheckoutAcceptance`, trait `Acceptable`. The `reports/unaccepted_assets` report + reminder emails chase people who haven't accepted.

> **In plain words:** two small built-in flows — "I'd like that monitor please" (request), and "yes, I confirm I received this laptop and agree to the usage policy" (acceptance). The Gov-Store package in Part B is a much bigger, catalog-and-basket version of the request idea.

---

### A10. Reporting, Action Log & Audit

**Purpose:** visibility and compliance.

**Key files:** `ReportsController`, `Reports\CustomComponentReportController`, `ReportTemplatesController`, `ActionlogController`. Models: `Actionlog`, `ReportTemplate`.

**The Action Log (`Actionlog`)** is the spine of history: every checkout/checkin/edit/audit/upload across all item types writes a row (via the `Loggable` trait's `log*()` methods). This is what powers each item's "History" tab and the activity report — and it is exactly what Gov-Store's adapters trigger so their issues appear in native history.

**Reports available** (`/reports/…`): audit, activity, custom asset report (pick columns → CSV), license report, accessory report, maintenance export, custom component report, **unaccepted assets** (route named with slashes: `route('reports/unaccepted_assets')`). Report layouts can be saved as `ReportTemplate`.

> **In plain words:** the system never forgets. Every hand-off is logged forever, and you can pull reports on who has what, what's overdue for acceptance, and asset value/depreciation.

---

### A11. Settings & Administration

**Purpose:** system configuration; superuser-only (`/admin`, middleware `authorize:superuser`).

**Key files:** `SettingsController` (huge — one method pair per section), `SetupController` (first-run install), `HealthController`, `SnipeModel`/`Setting` models.

**Sections:** general settings, branding, security, localization, notifications, Slack, asset-tag format, labels, LDAP, OAuth/API, Google login, SAML, purge (permanent delete of soft-deleted), backups (create/download/restore/upload), login-attempts, groups, phpinfo.

**`Setting::getSettings()`** is the global config singleton; `$snipeSettings` exposes it to all views. The **FMCS flag** and default checkout behaviors live here.

> **In plain words:** the control room. Branding, security rules, how emails/Slack fire, how asset tags are formatted, backups, and the on/off switch for multi-company mode.

---

### A12. Import

**Purpose:** bulk-load assets/users/etc. from CSV.

**Key files:** `Api\ImportController`, `SettingsController` import routes, model `Import`. Upload a CSV → map columns → process rows into the target module, with error reporting.

---

### A13. Dashboard

**Purpose:** the landing page after login — counts and charts (assets by status/category, recent activity) drawn with Chart.js (`horizontalBar`, v2). `DashboardController`. Palette from `Helper::defaultChartColors()`.

---

# Part B — Gov-Store Custom Requests (the procurement layer)

Everything below lives in **`packages/gov-store/custom-requests/`** and is loaded as a Laravel package. It never modifies core Snipe-IT files — it *plugs in* and *calls down*.

### B0. Why it exists & how it plugs in

**Business need:** let ordinary staff **self-serve** — browse everything available, build a basket, submit one formal, numbered **Service Request**, and have it approved and physically issued through a controlled, fully-audited workflow (government-store style). Core Snipe-IT only had the tiny native "request an asset" feature (A9); Gov-Store is the full procurement process.

**How it attaches — `CustomRequestServiceProvider::boot()`:**

1. **Loads its own migrations** (`database/migrations`) — creates 3 custom tables.
2. **Loads its own routes** (`routes/web.php`, prefix `gov-requests`, middleware `web`+`auth`).
3. **Loads its views** under the `govstore::` namespace.
4. **Registers an event listener:** `ItemApproved → ProcessItemCheckout` *(used only by the legacy single-item flow, B10)*.
5. **Zero-touch UI injection:** pushes `InjectGovStoreUi` middleware onto the `web` group. On every HTML response for a logged-in user, it renders `govstore::hooks.menu-injection` and splices the script in right before `</body>`. That script (jQuery) injects: sidebar links (**Gov Approvals**, **Fulfillment Queue**) for admins, user-dropdown links (**Browse Item Catalog**, **My Gov-Requests**), an **"Add to Request Basket"** button on individual item pages, and a **floating basket widget** with a live draft count. *No core Blade file is edited.*
6. **Polymorphic morph-map:** aliases `asset`/`accessory`/`consumable`/`license` → the real `App\Models\*` classes, so the DB stores short type strings, not namespaces.

> **In plain words:** it's a self-contained add-on. It grows its own tables, its own pages, and — cleverly — it injects its menus and buttons into the existing Snipe-IT screens automatically by rewriting the HTML on the way out, without touching Snipe-IT's own templates.

---

### B1. Data Model

Three custom tables (migration `2024_01_02_000000_create_service_requests_tables.php`), plus the morph-mapped links back to core inventory:

**1. `custom_service_requests`** — the **Request document / basket** (model `Models\Request`):

| Column | Meaning |
|---|---|
| `request_number` | Auto `SR-YYYY-000001`, generated in `Request::boot()` on create. |
| `requested_by` → users | The employee. |
| `approved_by` → users | The admin who decided. |
| `request_type` | onboarding / replacement / other … |
| `purpose`, `justification`, `required_by_date`, `delivery_location_id`, `cost_center` | Request metadata. |
| `approval_status` | **State machine A** (see B9): draft → submitted → under_review → approved / partially_approved / rejected → closed. |
| `fulfillment_status` | **State machine B**: unstarted → waiting → partially_issued → issued / closed / cannot_fulfill. |
| `submitted_at`, `approved_at`, `closed_at`, soft-deletes | Timestamps. |

**2. `custom_service_request_items`** — the **line items** (model `Models\RequestItem`):

| Column | Meaning |
|---|---|
| `request_id` → parent | |
| `requested_type` + `requested_id` | **Polymorphic** — what was asked for (asset/accessory/consumable). |
| `fulfilled_type` + `fulfilled_id` | Polymorphic — what was actually given (supports **substitution**). |
| `requested_qty` / `approved_qty` / `reserved_qty` / `issued_qty` | **Separation of quantities** — the core accounting idea. |
| `line_approval_status` | pending → approved / rejected. |
| `line_fulfillment_status` | unstarted → waiting → partially_issued → issued / cancelled. |
| `notes` | Admin note / rejection reason. |

**3. `custom_service_request_events`** — the **immutable timeline** (model `Models\RequestEvent`, no `updated_at`, `details` = JSON). One row per meaningful action; never edited. This is **event sourcing** for audit.

**Plus (legacy, B10):** `ItemRequest` model (single-item requests) — its table was created by the earlier migration `2024_01_01_..._create_custom_item_requests_table.php` but is **dropped** by the newer service-requests migration's cleanup step. Treat B10 as deprecated.

**The quantity-separation idea (why it's powerful):** a single line tracks four numbers independently — *requested* (what you asked), *approved* (what the admin allowed, ≤ requested), *reserved* (earmarked), *issued* (physically handed over so far, ≤ approved). This is what enables **partial approval** and **progressive/partial fulfillment**.

---

### B2. The Catalog module

**Route:** `GET gov-requests/catalog` → `GovRequestController::catalog()` → `CatalogService::getUnifiedCatalog()`.
**View:** `govstore::catalog.index`.

**What it does — `CatalogService::getUnifiedCatalog()`:** builds one merged product list (`Collection<CatalogItem>`) from three core sources:

1. **Assets** — only where `requestable = 1` **and** `assigned_to IS NULL` (i.e. flagged self-service *and* currently free). Available qty is always **1** (a physical unit).
2. **Accessories** — all, filtered to `numRemaining() > 0`. Available qty = remaining.
3. **Consumables** — all, filtered to `numRemaining() > 0`. Available qty = remaining.

Each becomes a **`CatalogItem` DTO** (`type, id, name, category, available_qty, image_url, created_timestamp, details[]`) with a resolved image (item image → model image → default) and detail bullets (model no., brand, tag). List is sorted by name.

The catalog page also shows the current user's request counts (pending / approved / rejected), computed in the controller from `custom_service_requests`.

> **In plain words:** one clean "shop" page pulling live availability from the three inventory types Snipe-IT tracks. If a laptop is already assigned, or a consumable is out of stock, it simply doesn't appear. **Note:** licenses are morph-mapped but *not* surfaced in the catalog today.

---

### B3. The Basket module

**Routes (all `gov-requests/basket/…`) → `BasketController` → `BasketService`:**

| Action | Route | Service method |
|---|---|---|
| View basket | `GET /basket` | `getOrCreateDraftBasket()` |
| Add item | `POST /basket/add` | `addItem()` |
| Update qty | `POST /basket/update` | `updateItemQty()` |
| Remove item | `POST /basket/remove/{id}` | `removeItem()` |
| Submit | `POST /basket/submit` | `submitBasket()` |

**The basket IS a draft Request.** `getOrCreateDraftBasket($userId)` does `Request::firstOrCreate(['requested_by'=>id,'approval_status'=>'draft'], …)`. So each user has at most **one** open draft `custom_service_requests` row at a time; its `RequestItem`s are the basket lines. A `draft_created` timeline event is logged on first creation.

**Add rules (`addItem`):**
- Type is lower-cased. If the line already exists: for accessories/consumables, `requested_qty += qty`; for **assets, never increment past 1** (unique physical item).
- Otherwise create a new `RequestItem` with `line_approval_status='pending'`, `line_fulfillment_status='unstarted'`.

**Update rules (`updateItemQty`):** assets throw `"Hardware assets can only be requested at a quantity of 1 per line item."`; qty ≤ 0 deletes the line.

**Submit (`submitBasket`) — the draft→official transition (DB transaction):**
1. Reject empty basket (`"You cannot submit an empty service request basket."`).
2. Update the parent with the metadata form (`request_type`, `purpose`, `justification`, `required_by_date`, `delivery_location_id`, `cost_center`), set `approval_status='submitted'`, stamp `submitted_at`.
3. Log a `submitted` timeline event (item count + purpose).
4. Redirect to **My Requests** with `"Service Request SR-YYYY-… submitted successfully!"`.

The **"Add to Request Basket"** button (injected on item pages, B0) and the **floating basket** widget drive this UI. Add supports AJAX (returns JSON `{success,count}`) so the basket count updates without reload.

> **In plain words:** a shopping cart. While you shop, it's an invisible "draft" order in the database. Assets are one-per-line (you can't ask for two of the exact same physical laptop); supplies can be any quantity. Hitting **Submit** turns the cart into a real, numbered request and sends it for approval.

---

### B4. Submission — the Service Request document

After submit, the row is a formal document: numbered `SR-YYYY-NNNNNN`, owned by the requester, carrying purpose/justification/needed-by/delivery-location/cost-center, status `submitted`.

**User's own view — `GET gov-requests/my-requests`** → `GovRequestController::index()` → `govstore::user.index`: lists that user's non-draft requests, newest first, with line items and their statuses. This is the requester's tracking screen.

---

### B5. Approval module (line-by-line)

**Admin-only** (`checkAdminAccess()` = superuser or `hasAccess('admin')`, else 403).

**Routes / flow:**

| Step | Route | Controller | Effect |
|---|---|---|---|
| Queue | `GET gov-requests/admin` | `GovApprovalController::index` | Lists `submitted` + `under_review` (pending), plus last 10 processed for reference. |
| Open one | `GET gov-requests/admin/{id}` | `::show` | Loads requester + `items.requested` + `events.user`. **On first open**, if status is `submitted`, auto-transitions to `under_review` and logs an `under_review` event. |
| Decide | `POST gov-requests/admin/{id}/process` | `::process` → `ApprovalService::processDecision()` | Applies per-line decisions. |

**`ApprovalService::processDecision($request, $admin, $itemDecisions)` — inside a DB transaction:**

1. Guard: request must be `submitted`/`under_review`, else `"This service request has already been processed."`.
2. **Every line must have a decision**, else it throws (naming the item). For each line:
   - **Approved:** validate `qty > 0` and `qty ≤ requested_qty` (else throw). If `qty < requested_qty`, log a `quantity_adjusted` event. Set `approved_qty=qty`, `line_approval_status='approved'`, `line_fulfillment_status='waiting'` (⇒ it now enters the Fulfillment Queue), save note.
   - **Rejected:** `approved_qty=0`, `line_approval_status='rejected'`, `line_fulfillment_status='cancelled'`, log a `line_rejected` event with reason.
3. **Roll up the parent status:**
   - 0 approved → parent `rejected`, `fulfillment_status='closed'`, stamp `closed_at`.
   - some rejected but ≥1 approved → `partially_approved`.
   - all approved → `approved`.
   - set `approved_by`, `approved_at`.
4. Log a final parent milestone event (`approved`/`partially_approved`/`rejected`) with approved/rejected line counts.
5. Redirect to the approval queue with a success banner.

> **In plain words:** the approver opens a request and, line by line, says "yes, but only 3 of the 5 you asked" or "no, denied — reason: budget." Approving a line drops it into the storekeeper's to-do list. If nothing is approved, the whole request is closed as rejected. Every decision is time-stamped and reasoned in the permanent timeline.

---

### B6. Fulfillment module (progressive issue)

**Admin/storekeeper-only** (`checkStorekeeperAccess()` — same check as approval today).

**Routes / flow:**

| Step | Route | Controller | Effect |
|---|---|---|---|
| Queue | `GET gov-requests/fulfillment` | `GovFulfillmentController::index` | Lists requests `approved`/`partially_approved` whose fulfillment is not yet `closed`/`issued`, oldest-approved first (FIFO). |
| Open one | `GET gov-requests/fulfillment/{id}` | `::show` | Full detail + timeline. |
| Issue goods | `POST gov-requests/fulfillment/{id}/issue` | `::process` → `FulfillmentService::issueItems()` | Physically checks out the quantities entered. |
| Force close | `POST gov-requests/fulfillment/{id}/close` | `::close` → `FulfillmentService::forceClose()` | Cancels remaining lines, closes request. |

**`FulfillmentService::issueItems($request, $storekeeper, $issueQuantities)` — DB transaction — the money step:**

1. Guard: not already `closed`/`cannot_fulfill`.
2. For each line where `line_approval_status='approved'`:
   - `qtyToIssue` from the form; skip if 0.
   - Guard: `qtyToIssue ≤ approved_qty − issued_qty` (can't over-issue), else throw naming the item.
   - **Handshake with core Snipe-IT** — pick `fulfilled_*` if a substitution was set, else `requested_*`; build the adapter via `RequestableFactory::make($type,$id)` and call `adapter->checkout($request->requester, $storekeeper, $qtyToIssue, "Issued via Service Request {SR-…}")`. If it returns false → throw (whole transaction rolls back).
   - Update cumulative `issued_qty`; set `line_fulfillment_status` = `issued` when `issued_qty === approved_qty`, else `partially_issued`.
   - Log an `item_issued` event (issued qty, running total, approved qty).
3. **Roll up parent:** if every approved line is fully issued → `fulfillment_status='issued'`, `approval_status='closed'`, stamp `closed_at`, log a `closed` event. Otherwise `partially_issued`.

**`forceClose()`:** cancels every not-yet-`issued` approved line, sets request `closed`/`closed`, logs a `closed` event with the reason. Used when stock can't be sourced.

> **In plain words:** the storekeeper hands out what's approved — all at once or in batches over days. The instant they record an issue, the system performs the *real* Snipe-IT checkout, so official inventory and the item's history update automatically. When the last approved item is handed over, the request closes itself. If something can never be supplied, they force-close it with a note.

---

### B7. The Adapter/Factory bridge to Snipe-IT

This is the **seam** between the two layers — how Gov-Store performs a real checkout without duplicating Snipe-IT logic.

- **Contract:** `Contracts\RequestableInterface` — `getModel`, `getDisplayName`, `getType`, `getAvailableQuantity`, `checkout(User $target, User $admin, int $qty, string $notes): bool`.
- **Factory:** `RequestableFactory::make($type, $id)` — normalizes the type string (`class_basename` + lower-case), then returns `AssetAdapter` / `AccessoryAdapter` / `ConsumableAdapter` (or throws `"Unsupported requestable type"`). *(License adapter not implemented.)*
- **Adapters — each wraps a core model and does the native checkout, then logs it into core history:**
  - `AssetAdapter::checkout()` → sets `assigned_to`/`assigned_type=User`, inherits the target user's `location_id`, saves, then `Asset::logCheckout()` → appears in the asset's native History tab.
  - `AccessoryAdapter::checkout()` → `accessory->users()->attach($userId,[…])` + `logCheckout()`.
  - `ConsumableAdapter::checkout()` → `consumable->users()->attach($userId,[…])` + `logCheckout()`.

> **In plain words:** rather than reinventing "checkout," Gov-Store politely asks Snipe-IT to do it its own way. That's why an item issued through a Service Request looks identical in history to one an admin issued by hand — same inventory math, same audit trail.

---

### B8. Timeline / event sourcing

Every state change across B3–B6 writes an immutable `custom_service_request_events` row (`event_type` + JSON `details` + actor + timestamp). Event types seen in the code: `draft_created`, `submitted`, `under_review`, `quantity_adjusted`, `line_rejected`, `approved`, `partially_approved`, `rejected`, `item_issued`, `closed`. The admin/fulfillment detail views render this as a chronological audit trail. Rows are never updated (`RequestEvent::$timestamps = false`, only `created_at`).

> **In plain words:** a black-box flight recorder for each request — who did what, when, and why — that can't be altered after the fact.

---

### B9. Status state machines

**Parent — `approval_status`:**
```
draft ──submit──► submitted ──open──► under_review ──decide──►
        ├─ all lines approved ─────────► approved
        ├─ some approved, some rejected► partially_approved
        └─ none approved ──────────────► rejected (→ fulfillment closed)
approved / partially_approved ──all approved issued──► closed
(any active) ──force close──► closed
```

**Parent — `fulfillment_status`:** `unstarted → waiting (on approval) → partially_issued → issued`; or `closed` / `cannot_fulfill`.

**Line — `line_approval_status`:** `pending → approved | rejected`.
**Line — `line_fulfillment_status`:** `unstarted → waiting (once approved) → partially_issued → issued`; or `cancelled` (rejected or force-closed).

See [§8](#8-status-reference-tables) for value tables.

---

### B10. Legacy single-item request flow (`ItemRequest`)

An **earlier, simpler** design still partly present:

- `RequestService::submitRequest($type,$id,$user,$notes)` — creates one `ItemRequest` (guards against a duplicate pending request for the same item+user).
- Route `POST gov-requests/submit` → `GovRequestController::store()` builds the model class from the type string and calls the service.
- Intended completion path: dispatch `Events\ItemApproved` → listener `ProcessItemCheckout` → `RequestableFactory` → adapter checkout (registered in the provider).

**Status:** effectively **deprecated / dormant.** The `custom_item_requests` table it used is **dropped** by the current migration's cleanup, and the current basket→approval→fulfillment path (B3–B6) does **not** dispatch `ItemApproved`. Keep for context; the live flow is the Service Request document.

---

## 6. End-to-End Cross-Module Scenarios

**Scenario 1 — New hire needs a laptop + mouse + toner (the full Gov-Store path):**

```
1. IT sets the spare laptop's `requestable = 1` (A2). Mouse (A4) & toner (A5) have stock.
2. New hire logs in (A1) → user-dropdown "Browse Item Catalog" (injected, B0).
3. CatalogService merges free assets + in-stock accessories/consumables (B2).
4. Hire clicks "Add to Request Basket" on each (B3) → draft Request + 3 RequestItems.
5. Floating basket → Submit with purpose="Onboarding", needed-by date (B4)
   → SR-2026-000123, status `submitted`.
6. Admin: sidebar "Gov Approvals" → opens it (→ `under_review`) (B5).
   Approves laptop x1, mouse x1, toner x1 (or trims qty) → parent `approved`,
   each line `waiting`.
7. Storekeeper: "Fulfillment Queue" → issues all three (B6).
   → AssetAdapter checks the laptop out to the hire (inherits their location) + logs;
     Accessory/Consumable adapters attach + log (B7).
8. All approved lines issued → request auto-`closed`; timeline shows every step (B8).
9. The laptop now appears under the hire's `account/view-assets` (A1); if its category
   requires acceptance, they Accept it under `account/accept` (A9).
```

**Scenario 2 — Direct admin checkout (no Gov-Store):** admin opens the asset (A2) → Checkout → picks the user → asset assigned, logged, optional acceptance/email. Same end state as step 7 above, minus the request paperwork. *(Gov-Store simply automates this checkout at the end of an approved request.)*

**Scenario 3 — Partial approval + partial fulfillment:** hire asks for 10 reams of paper; admin approves 6 (`quantity_adjusted` logged, `partially_approved`); storekeeper issues 4 now (`partially_issued`), 2 next week (`issued` → line done). When all approved lines are done, request `closed`.

---

## 7. Module Dependency Map

```
                         ┌──────────────┐
                         │  SETTINGS /  │ (FMCS flag, defaults, branding)
                         │    ADMIN     │
                         └──────┬───────┘
   Reference data (A0):        │ configures everything
   Companies, Locations,       ▼
   Categories, Manufacturers, Suppliers, Depreciations,
   Status Labels, Departments, Custom Fields
        │            │            │            │
        ▼            ▼            ▼            ▼
     ┌──────┐   ┌─────────┐  ┌──────────┐  ┌───────────┐
     │USERS │◄──│ ASSETS  │  │ LICENSES │  │ACCESSORIES│  CONSUMABLES  COMPONENTS
     │GROUPS│   │ +MODELS │  │  +SEATS  │  │           │  (A5)         (A6→into Assets)
     └──┬───┘   └────┬────┘  └────┬─────┘  └────┬──────┘
        │            │ checkout target = User/Asset/Location
        │            ▼
        │      ┌───────────────┐   every change ──► ACTION LOG (A10) ──► Reports
        │      │  CHECKOUT /   │
        │      │  CHECKIN      │──► Acceptance (A9), Maintenance (A8), Kits (A7)
        │      └──────▲────────┘
        │             │ Adapters call this exact logic (B7)
        │             │
   ┌────┴─────────────┴───────────────────────────────────────┐
   │  GOV-STORE (Part B)                                        │
   │  Catalog(B2) → Basket(B3) → Submit(B4) → Approve(B5)      │
   │            → Fulfill(B6) ──Adapters(B7)──► core checkout   │
   │  Timeline(B8) records all · Statuses(B9) · morph-map      │
   │  UI injected via middleware (B0), no core files touched    │
   └───────────────────────────────────────────────────────────┘
```

**Hard dependencies (A depends on B = A needs B to exist first):**

| Module | Depends on |
|---|---|
| Assets | Asset Models, Status Labels, Categories, Companies, Locations (+ optionally Suppliers, Depreciations, Custom Fields) |
| Asset Models | Manufacturers, Categories, Depreciations, Custom Fieldsets |
| Licenses / Accessories / Consumables / Components | Categories, Manufacturers, Companies (+ Suppliers) |
| Any checkout | Users (targets) + Action Log (history) + Policies (authz) |
| Kits | Assets/Licenses/Accessories/Consumables |
| Gov-Store Catalog | Assets (`requestable`), Accessories, Consumables |
| Gov-Store Fulfillment | Adapters → core checkout → Action Log; Users; Locations (delivery) |
| Gov-Store everything | Users/auth, Settings (`web` middleware), the 3 custom tables |

---

## 8. Status Reference Tables

**Asset (core) — `Statuslabel` type gate:** `deployable` (checkout-able), `pending`, `undeployable`, `archived`.

**Gov-Store parent `approval_status`:**
| Value | Meaning |
|---|---|
| `draft` | Basket, not submitted. |
| `submitted` | Sent for approval, untouched. |
| `under_review` | An admin has opened it. |
| `approved` | All lines approved. |
| `partially_approved` | Some approved, some rejected. |
| `rejected` | Nothing approved. |
| `closed` | Fully issued or force-closed. |

**Gov-Store parent `fulfillment_status`:** `unstarted` · `waiting` · `partially_issued` · `issued` · `closed` · `cannot_fulfill`.

**Gov-Store `line_approval_status`:** `pending` · `approved` · `rejected`.
**Gov-Store `line_fulfillment_status`:** `unstarted` · `waiting` · `partially_issued` · `issued` · `cancelled`.

---

## 9. Known Gaps, Quirks & Risks

> Flagged during code review — useful for maintainers.
>
> **✅ Update:** items 1–7 below (plus the broken `approve`/`reject` routes) have since been **fixed**. See [`issue_fixed.md`](issue_fixed.md) for the cause/effect/solution of each. Item 8 is intentional by design. The descriptions are kept here for context.

1. **Broken sidebar link → hard 500.** `menu-injection.blade.php` renders a **"Test Subject"** item pointing at `route('gov.requests.test-subject.index')`, but **no such route exists** in `packages/gov-store/custom-requests/src/routes/web.php`. Laravel's `route()` throws on an undefined name, so this can fatal-error the injected script / page for admins. **Fix:** remove the Test Subject block or add the route.
2. **Dead event path.** `ItemApproved`/`ProcessItemCheckout` are registered but the live Service-Request flow never dispatches `ItemApproved`; the whole `ItemRequest` single-item flow (B10) is superseded and its table is dropped by the newer migration. Candidate for removal.
3. **Storekeeper == approver.** Fulfillment and Approval share the *same* admin check, so one person can both approve and issue — no separation of duties. Intended? If not, add a distinct storekeeper permission/role.
4. **Licenses half-wired.** Licenses are in the morph-map but have **no adapter** and are **not** in the catalog, so they can't actually be requested/fulfilled. Either finish (`LicenseAdapter` + catalog source) or drop from the map.
5. **Stray helper.** `ApprovalService.php` ends with an unused free function `mountaineer_in_array()` — dead code.
6. **No stock reservation.** Approving sets `approved_qty` but doesn't decrement/reserve core stock until fulfillment; two approved requests can race for the last unit (the actual checkout at issue time is the only real guard). `reserved_qty` exists but is unused.
7. **Asset availability at issue time isn't re-checked** in `AssetAdapter::checkout()` — if the asset got assigned between approval and issue, it will overwrite `assigned_to`. Consider re-guarding on `assigned_to`.
8. **UI injection is content-rewriting.** `InjectGovStoreUi` string-splices before `</body>` on every HTML response — robust but fragile to non-standard responses; keep in mind when debugging odd pages.

---

## 10. Glossary

| Term | Meaning |
|---|---|
| **Asset** | One individually-tracked physical unit (unique tag). |
| **Asset Model** | The template/spec an asset is an instance of. |
| **Checkout / Checkin** | Assign an item to a user/asset/location / return it to stock. |
| **Status Label** | An asset's state; `deployable` type = eligible for checkout. |
| **Accessory / Consumable / Component** | Qty-tracked returnable / used-up / installed-into-asset items. |
| **Acceptance** | User confirming (optionally e-signing) receipt of a checked-out item. |
| **Action Log** | The append-only history table behind every "History" tab. |
| **FMCS** | Full Multiple Company Support — company-scoped data isolation. |
| **Transformer** | Class that shapes model data into API JSON. |
| **Policy** | Laravel authorization class deciding who may do an action. |
| **Service Request** | A submitted Gov-Store request document, `SR-YYYY-NNNNNN`. |
| **Basket** | A user's single draft Service Request (the cart). |
| **Line item** | One requested product row inside a Service Request. |
| **Approval / Fulfillment** | Deciding what/how-much is granted / physically issuing it. |
| **Adapter** | Bridge class that performs the real Snipe-IT checkout for Gov-Store. |
| **Timeline / Event** | Immutable audit row per action on a Service Request. |
| **Quantity separation** | Tracking requested vs approved vs reserved vs issued per line. |

---

*Generated from source: `app/` (core Snipe-IT) and `packages/gov-store/custom-requests/` (custom procurement layer). For implementation detail, follow the `Class::method()` references cited in each section.*