# Workflow & Architecture Guide — Asset Store BD

> **Who is this for?** Someone new to this codebase who wants to understand *what the system does*, *how a request flows through it*, and *how the pieces connect* — without reading every file first.
>
> **The most important thing to understand first:** this repository is really **two projects living together**:
>
> | Layer | What it is | Where it lives | Touchable? |
> |-------|-----------|----------------|------------|
> | **MAIN PROJECT** | Snipe-IT — a mature open-source IT Asset Management system (Laravel 12) | Everything *except* `packages/` | Treated as vendor / upstream. We avoid editing it. |
> | **CUSTOM MODULES** | The `gov-store` suite — government multi-tenant "asset store" built by our team | `packages/gov-store/**` | This is *our* code. All new business logic goes here. |
>
> The golden rule of this project: **extend the main project from inside `packages/`, do not modify the main project.** The custom modules "reach into" Snipe-IT using Laravel features (service providers, middleware, global scopes, runtime relations) rather than editing core files. This is called the **Zero-Touch** approach.

---

# TABLE OF CONTENTS

- [Part A — The Main Project (Snipe-IT)](#part-a--the-main-project-snipe-it)
  - [A1. What it does](#a1-what-it-does)
  - [A2. Tech stack](#a2-tech-stack)
  - [A3. The life of a request (HTTP → response)](#a3-the-life-of-a-request-http--response)
  - [A4. Folder map that matters](#a4-folder-map-that-matters)
  - [A5. Core domain entities & how they relate](#a5-core-domain-entities--how-they-relate)
  - [A6. The seven patterns you must know](#a6-the-seven-patterns-you-must-know)
  - [A7. Core workflow: checkout / checkin](#a7-core-workflow-checkout--checkin)
- [Part B — The Custom Modules (`gov-store`)](#part-b--the-custom-modules-gov-store)
  - [B0. The big picture: what problem gov-store solves](#b0-the-big-picture-what-problem-gov-store-solves)
  - [B1. How a package plugs into the main project](#b1-how-a-package-plugs-into-the-main-project)
  - [B2. Module: geo-areas](#b2-module-geo-areas)
  - [B3. Module: organization](#b3-module-organization)
  - [B4. Module: office-membership](#b4-module-office-membership)
  - [B5. Module: tenant-scope](#b5-module-tenant-scope)
  - [B6. Module: custom-requests](#b6-module-custom-requests)
  - [B7. End-to-end story: from empty system to an issued item](#b7-end-to-end-story-from-empty-system-to-an-issued-item)
  - [B8. Security posture (known state)](#b8-security-posture-known-state)
- [Appendix 1 — Database tables reference](#appendix-1--database-tables-reference)
- [Appendix 2 — Glossary](#appendix-2--glossary)

---

# PART A — THE MAIN PROJECT (Snipe-IT)

## A1. What it does

Snipe-IT tracks physical and licensed **inventory** for an organization and records **who has what**. The main object types:

- **Asset** — a serialized, trackable physical thing (a laptop, a monitor). Checked out to *one* holder at a time.
- **Accessory** — non-serialized items handed out and returned (keyboard, mouse). Tracked by quantity.
- **Consumable** — items used up and not returned (toner, paper). Tracked by quantity.
- **Component** — parts installed *into* assets (RAM stick, SSD).
- **License** / **License Seat** — software entitlements; each **seat** can be checked out to a user or asset.
- **Kit** — a predefined bundle of the above, checked out together.

Supporting/reference objects: **User**, **Company**, **Location**, **Category**, **Manufacturer**, **Supplier**, **AssetModel**, **Depreciation**, **Status Label**, **Custom Field**, **Department**.

The two verbs the whole system revolves around are **checkout** (assign to a holder) and **checkin** (return to stock).

## A2. Tech stack

- **PHP 8.2+ / Laravel 12** — framework.
- **Blade + AdminLTE 2 + Bootstrap 3 + jQuery + a little Alpine.js** — server-rendered UI. No Livewire/Inertia for the main flows.
- **Laravel Mix (Webpack)** — compiles `resources/assets/**` → `public/**`. Build with `npm run dev` / `npm run prod`.
- **MySQL / MariaDB** via Eloquent ORM.
- **Laravel Passport (OAuth2)** for the REST API; **SCIM v2** for user provisioning; **Google2FA** + **SAML** + **Socialite** for auth.
- **Chart.js v2.9.4** (v2 API — use `horizontalBar`, not v3).
- Dev server: **Laravel Herd**.

## A3. The life of a request (HTTP → response)

Every page/API call travels the same pipeline. Learn this once and the whole app makes sense:

```
Browser
  │
  ▼
routes/web.php  (UI, returns Blade)      routes/api.php  (JSON, returns Transformer output)
  │                                         │
  ▼                                         ▼
Middleware  (auth, session, and — see Part B — the gov-store middleware)
  │
  ▼
Controller
   app/Http/Controllers/<Entity>/…         app/Http/Controllers/Api/<Entity>Controller
  │
  ▼
Form Request  (app/Http/Requests/*)  ── validates input, runs Rules (app/Rules/*)
  │
  ▼
Action class  (app/Actions/<Entity>/*)  ── one job each (optional but common)
  │
  ▼
Service       (app/Services/*)          ── heavier business logic
  │
  ▼
Eloquent Model (app/Models/*)           ── talks to DB
  │
  ▼
Policy         (app/Policies/*)         ── "is this user allowed?"
  │
  ▼
Response:  Blade view   OR   Transformer → JSON  (app/Http/Transformers/*)
```

Key takeaway: **UI controllers return Blade views; API controllers return data through a Transformer** (never raw model attributes).

## A4. Folder map that matters

```
app/
├── Actions/            Single-responsibility "do one thing" classes, grouped by entity
├── Console/            Artisan commands (scheduled + manual)
├── Enums/              PHP 8 enums
├── Events/ Listeners/  Event-driven hooks
├── Helpers/Helper.php  Global helper methods (see below)
├── Http/
│   ├── Controllers/    UI controllers, grouped by entity (Assets/, Licenses/, Users/, …)
│   │   └── Api/        REST controllers (JSON)
│   ├── Middleware/     Request filters
│   ├── Requests/       Form validation classes
│   ├── Transformers/   Model → API JSON shapers  (DatatablesTransformer wraps pagination)
│   └── Traits/
├── Importer/           CSV import logic
├── Jobs/               Queued background work
├── Models/             Eloquent models (+ Traits/ like Companyable, Checkoutable, Depreciable)
├── Notifications/      Email / Slack / Teams / Google Chat notifications
├── Observers/          Model lifecycle hooks
├── Policies/           Authorization
├── Presenters/         Formatting helpers for views
├── Providers/          Service providers (bootstrapping)
├── Rules/              Custom validation rules
└── Services/           Business logic services

routes/    web.php (UI + inline breadcrumbs) · api.php (JSON) · console.php · scim.php
resources/ views/ (Blade) · lang/en-US/ (translation keys) · assets/ (raw CSS/JS/LESS)
config/    ~30 config files
database/  migrations · factories · seeders
tests/     Feature/ (hit the DB) · Unit/
```

Handy `Helper::` methods (`app/Helpers/Helper.php`):
- `deployableStatusLabelList()` — status labels for checkout forms.
- `defaultChartColors()` — the 10-color chart palette.
- `getRedirectOption($request, $id, $table)` — where to send the user after a checkout.

Global convenience: **`$snipeSettings`** is injected into *every* Blade view by a service provider, so you never have to pass `Setting::getSettings()` manually.

## A5. Core domain entities & how they relate

```
Company ──< Location ──< User
   │           │           │
   │           │           └──< (holds) Assets / Accessories / Consumables / LicenseSeats
   │           │
   │           └──< Assets (rtd_location_id = "return-to-default" home location)
   │
Manufacturer ──< AssetModel ──< Asset
Category ──────< AssetModel        (Category also classifies Accessory / Consumable / Component / License)
Supplier ──────< Asset / License / …
Depreciation ──< AssetModel / License
StatusLabel ──< Asset   (deployable / pending / archived / undeployable)
CustomField ──< CustomFieldset ──< AssetModel   (extra per-model fields on assets)
```

- An **Asset** belongs to an **AssetModel**, which belongs to a **Manufacturer** and a **Category**.
- An **Asset** can be checked out to a **User**, another **Asset**, or a **Location** (polymorphic `assigned_to` / `assigned_type`).
- A **License** owns many **LicenseSeats**; each seat is the checkout unit.
- **Company** enables multi-tenant separation (see FMCS below).

## A6. The seven patterns you must know

1. **Dual controller tree.** `app/Http/Controllers/<Entity>/` = UI (Blade). `app/Http/Controllers/Api/` = JSON. Same domain, two response shapes. Datatables and select2 dropdowns are powered by the API tree.

2. **Transformer pattern.** API controllers *always* return through a Transformer:
   ```php
   return (new AssetsTransformer)->transformAssets($assets, $assets->count());
   ```
   Never leak raw model attributes.

3. **Policy-based authorization.** All "can this user do X?" logic lives in `app/Policies/`. `CheckoutablePermissionsPolicy` is the base for assets/licenses/accessories/consumables; its `checkout()` / `checkin()` accept `$item = null`, so `@can('checkout', \App\Models\Asset::class)` works without an instance.

4. **FMCS (Full Multiple Company Support).** Gated by `Setting::getSettings()->full_multiple_companies_support == '1'`. When on, records are company-scoped. The select2 `selectlist()` API endpoints accept a `companyId` param:
   ```php
   if ((Setting::getSettings()->full_multiple_companies_support == '1') && ($request->filled('companyId'))) {
       $query->where('table.company_id', $request->input('companyId'));
   }
   ```
   Wire it in Blade with `data-company-id="{{ $user->company_id }}"`.

5. **Select2 AJAX dropdowns.** Add `class="js-data-ajax"` + `data-endpoint="hardware|licenses|consumables|…"`. `snipeit.js` auto-initializes them and forwards `data-company-id`→`companyId`, `data-asset-status-type`→`statusType`.

6. **Action classes.** `app/Actions/<Entity>/` hold single-responsibility business steps, keeping controllers thin.

7. **Model traits.** `Companyable` (multi-company scoping via `CompanyableScope`), `Checkoutable` (checkout/checkin ability), `Depreciable` (depreciation math).

Other conventions:
- **Routes** live in `routes/web.php` (UI) and `routes/api.php` (API). Breadcrumbs are declared inline with `->breadcrumbs(fn (Trail $trail) => …)` from `tabuna/breadcrumbs`. (Note the oddball route name `reports/unaccepted_assets` uses slashes, not dots.)
- **Translations**: add UI strings as keys in `resources/lang/en-US/general.php` (and siblings) — never hard-code English.
- **Post-checkout redirect**: to bounce back to the assigned user, the form sends `redirect_option=target`, `checkout_to_type=user`, `assigned_user={{ $user->id }}`, read by `Helper::getRedirectOption()`.

## A7. Core workflow: checkout / checkin

The single most important business flow in the main project:

```
User picks an Asset  →  Checkout form (choose holder: user / asset / location, status, notes)
    → Policy check: @can('checkout', Asset)
    → Asset->checkOut($target, $checkoutBy, $checkoutDate, ...)
        · sets assigned_to / assigned_type / location_id
        · writes a CheckoutAcceptance if the category requires acceptance (EULA / signature)
        · fires CheckoutableCheckedOut event → Notification (email/Slack/etc.)
        · logs to the Activity/Actionlog
    → redirect per Helper::getRedirectOption()

Checkin reverses it: clears the holder, returns the asset to its rtd_location, logs + notifies.
```

Accessories/consumables/components/license-seats follow the same idea but track **quantity** instead of a single holder, using pivot tables (`accessories_checkout`, `components_assets`, `license_seats`, …).

---

# PART B — THE CUSTOM MODULES (`gov-store`)

Everything below is **our team's code**, living entirely under `packages/gov-store/`. None of it edits the main project's files.

## B0. The big picture: what problem gov-store solves

Plain Snipe-IT assumes one organization. Our deployment is a **government** with **many Ministries/Departments**, each owning **many physical Offices**, spread across a **geographic hierarchy** (Division → District → Upazila …). Requirements plain Snipe-IT does not cover:

1. **Strict data isolation** — Office A must not see Office B's inventory; a Ministry must not see another Ministry's.
2. **A "storefront"** — employees browse a catalog and *request* items (like an online shop) instead of admins hand-assigning everything.
3. **Multi-step approvals** — requests need Primary and sometimes Final approver sign-off before an item is issued.
4. **A real staff lifecycle** — people transfer between offices; they must be *cleared* (no held assets, no open roles) before leaving and *claimed* by the receiving office.
5. **Geography-aware provisioning** — offices are pinned to a real place; ICT officers manage everything inside their geographic jurisdiction.

The `gov-store` suite is **five cooperating packages**, each a self-contained bounded context:

| Package | One-line job | Key namespace |
|---------|--------------|---------------|
| `geo-areas` | The map: hierarchical geographic reference data | `GovStore\GeoAreas` |
| `organization` | Offices, their geo-profile, roles, ICT jurisdictions, provisioning | `GovStore\Organization` |
| `office-membership` | Staff ⇄ office lifecycle: join, clear, delegate, transfer, override | `GovStore\OfficeMembership` |
| `tenant-scope` | The isolation engine: who-sees-what, who-can-mutate-what | `GovStore\TenantScope` |
| `custom-requests` | The storefront: basket → request → approval → fulfillment | `GovStore\CustomRequests` |

Dependency direction (arrows = "uses"):

```
custom-requests ─┐
                 ├─► organization ─► geo-areas
office-membership┘        ▲
        │                 │
        └────► tenant-scope ──────► office-membership + organization + geo-areas
```
`tenant-scope` is the hub — it reads membership (office-membership), profiles/jurisdictions (organization), and geography (geo-areas) to decide the active data boundary for every request.

## B1. How a package plugs into the main project

This is the architectural heart of the custom work. Six mechanisms, all standard Laravel, none touching core files:

1. **Composer path repositories.** `composer.json` registers each package as a local path repo and requires it:
   ```jsonc
   "repositories": [ { "type": "path", "url": "./packages/gov-store/custom-requests" }, … ],
   "require":      { "gov-store/custom-requests": "*", … },
   "autoload": { "psr-4": { "GovStore\\CustomRequests\\": "packages/gov-store/custom-requests/src/", … } }
   ```

2. **Service providers**, registered in `config/app.php` under `providers`:
   ```
   GovStore\CustomRequests\Providers\CustomRequestServiceProvider
   GovStore\Organization\Providers\OrganizationServiceProvider
   GovStore\GeoAreas\Providers\GeoAreasServiceProvider
   GovStore\TenantScope\Providers\TenantScopeServiceProvider
   GovStore\OfficeMembership\Providers\OfficeMembershipServiceProvider
   ```
   Each provider `boot()` loads that package's **migrations**, **routes**, and **views** (under a namespace like `govstore::`, `govorg::`, `govmem::`, `govscope::`).

3. **Middleware pushed onto core groups.** Providers call `$router->pushMiddlewareToGroup('web', …)` (and `'api'` for tenant-scope) to slot their middleware into *every* request without editing `Http/Kernel.php`.

4. **Zero-Touch UI injection.** Instead of editing Snipe-IT's Blade menus, each package ships an `Inject…Ui` middleware. After the response is built, it finds `</body>` and injects a small jQuery snippet (rendered from a `hooks/menu-injection.blade.php`) that adds the package's menu items/badges into the existing AdminLTE sidebar:
   ```php
   $script = view('govstore::hooks.menu-injection')->render();
   $pos = strrpos($content, '</body>');
   $content = substr($content, 0, $pos) . $script . substr($content, $pos);
   ```

5. **Runtime relationships on core models.** Providers attach relations to Snipe-IT models *in memory* so we never edit `app/Models/*`:
   ```php
   Location::resolveRelationUsing('profile', fn($l) => $l->hasOne(LocationProfile::class, 'location_id', 'id'));
   Location::resolveRelationUsing('roles',   fn($l) => $l->hasOne(LocationRole::class,   'location_id', 'id'));
   User::resolveRelationUsing('memberships', fn($u) => $u->hasMany(OfficeMembership::class,'user_id','id'));
   ```

6. **Global scopes + observers on core models.** `tenant-scope` calls `Asset::addGlobalScope(new MinistryLocationScope())` etc. at boot, so *every* query on those models is filtered automatically, and `Model::observe(TenantMutationObserver::class)` guards every create/update/delete.

**Naming conventions to recognize gov-store data:** custom tables are prefixed `gov_…` (plus the `custom_service_request*` set), route names are prefixed `gov.…`, and URLs sit under `/gov-store/…` or `/gov-requests/…`.

---

## B2. Module: `geo-areas`

**Purpose:** the reference map. Provides the hierarchical list of Bangladeshi geographic units (Division → District → Upazila → Union …) that offices and jurisdictions are pinned to. Everything geographic depends on this.

**Table** — `gov_geo_areas` (seeded automatically from `src/database/data/geo_areas.csv` during migration):

| Column | Meaning |
|--------|---------|
| `GeoAreaId` (PK) | Stable unique id of the area |
| `hid` | **Hierarchy id** — a materialized path string; a child's `hid` starts with its parent's `hid`. This is what makes "everything under X" queries fast (`WHERE hid LIKE 'parent%'`). |
| `geo_type` | division / district / upazila / … |
| `parent_geo_code`, `geo_code` | numeric codes |
| `bn_name`, `en_name` | Bangla + English names |
| `GeoLevel` | depth in the tree |

**Model:** `GeoArea` (primary key `GeoAreaId`).

**Service:** `GeoAreaService` (singleton) — the key methods used across the suite:
- `search(...)` — powers the geo select2 dropdowns.
- `isWithinBoundary($parentGeoAreaId, $childGeoAreaId)` — "is this target area inside that jurisdiction?" Used to stop an ICT officer provisioning offices outside their turf.

**Controller & route:** `GeoAreaController@search` at `GET /gov-store/api/geo/search` (name `gov.geo.search`).

**The `hid` trick (remember this):** to find "all locations inside a jurisdiction," the code selects geo areas whose `hid LIKE '<jurisdiction hid>%'`, then finds location profiles pointing at those areas. This single idea powers ICT-officer scoping.

---

## B3. Module: `organization`

**Purpose:** turn a bare Snipe-IT `Location` into a fully described government **Office** — pinned to geography, owned by a Ministry, staffed with roles, and walked through a provisioning lifecycle. Also defines **ICT officer jurisdictions**.

**Tables** (`gov_organization_tables` migration):

| Table | Role |
|-------|------|
| `gov_location_profiles` | 1:1 extension of a core `locations` row. Holds `geo_area_id` (mandatory), `office_admin_id`, `lifecycle_status`, geo-verification stamps. |
| `gov_ict_jurisdictions` | Maps a `user_id` → a `geo_area_id`. That user (ICT officer) governs everything inside that geographic subtree. |
| `gov_location_roles` | The **approval/fulfillment role matrix** for an office: `primary_approver_id`, `final_approver_id`, `storekeeper_id`, each with an optional **delegate** + `…_delegate_until` date. |
| `gov_organization_activity_logs` | Immutable audit trail of office events (`office_created`, `admin_assigned`, `roles_configured`, `status_changed`, …). |

**Lifecycle status** on a profile progresses: `provisioned` → `configured` (admin assigned) → `operational` (all readiness checks pass).

**Models:** `LocationProfile`, `IctJurisdiction`, `LocationRole`, `OrganizationActivityLog`. Plus the runtime `Location->profile` and `Location->roles` relations injected by the provider.

**Services:**
- `OfficeProvisioningService`
  - `provisionOffice($data, $executorId)` — the main creation flow. Steps: (1) **geo boundary check** — non-admins must stay inside their ICT jurisdiction (`GeoAreaService::isWithinBoundary`); (2) duplicate pre-check (warns if this Ministry already has an office in that area); (3) create *or* re-use an existing core `Location`; (4) create its `LocationProfile` with the mandatory `geo_area_id`; (5) instantiate an empty `LocationRole`; (6) write an activity log. All inside a DB transaction.
  - `assignOfficeAdmin($locationId, $adminId, $executorId)` — sets `office_admin_id`, bumps lifecycle to `configured`, logs it.
- `OfficeReadinessService@evaluateAndTransition($locationId)` — the **operational gate**. Checks a 4-item checklist: `has_office_admin`, `has_primary_approver`, `has_storekeeper`, `has_users`. If all true → lifecycle becomes `operational`; otherwise `configured`. Logs any transition.
- `OfficeConfigurationService` — backs the local office admin's self-service configuration screen.

**Observer:** `IctJurisdictionObserver` — reacts to jurisdiction changes.

**Middleware:**
- `InjectOrganizationUi` — zero-touch menu injection.
- `EnsureOfficeIsOperational` — the "integration handshake": can block/redirect flows when an office is not yet operational.
- `EnsureUserIsIctOfficer` — guards ICT-only routes.

**Controllers & routes** (prefix `gov-store/admin/organization`, names `gov.org.*`):

| Route | Controller | Purpose |
|-------|-----------|---------|
| `GET /` | `ProvisioningController@index` | Office registry dashboard |
| `GET /create`, `POST /store` | `ProvisioningController@create/provision` | Create a new office |
| `GET /geo-search`, `GET /check-duplicate` | `ProvisioningController` | AJAX geo select2 + duplicate pre-check |
| `GET/POST /jurisdictions*` | `ProvisioningController@jurisdictions*` | Manage ICT officer jurisdictions |
| `GET /onboard`, `POST /onboard/store` | `OnboardLocationController` | Adopt an existing (legacy) core Location into gov-store |
| `GET /{id}/hub` | `OfficeHubController@show` | The central per-office control panel |
| `POST /{id}/update`, `/save-roles`, `/verify-geo` | `OfficeHubController` | Edit office, set role matrix, verify geo |

Plus a **local office-admin** area (prefix `gov-store/office`, `ConfigurationController@index/save`) for an office admin to complete their own activation checklist.

---

## B4. Module: `office-membership`

**Purpose:** manage the **staff lifecycle** across offices *without corrupting Snipe-IT's core user table*. The central idea (from the module's own design notes):

> Separate a user's permanent **Identity** (the core `users` row) from their transient **Membership** (which office they currently work in), their **Responsibilities** (storekeeper/approver duties), and their active **Working Context** (which office they are "acting as" right now).

**Tables:**

| Table | Role |
|-------|------|
| `gov_office_memberships` | A user's link to an office. `is_home_office`, `status` (active / suspended / released / release_requested), optional `valid_until`. Unique on `(user_id, location_id)`. |
| `gov_office_responsibilities` | Who holds which operational duty at which office: `(location_id, user_id, role_slug)`. `role_slug` e.g. `storekeeper`, `primary_approver`. This is the live "who is the storekeeper right now" matrix. |
| `gov_role_handshakes` | Pending peer-to-peer role handovers: `role_type`, `outgoing_user_id`, `incoming_user_id`, `status` (pending/accepted/rejected/cancelled). |
| `gov_override_audit_logs` | Immutable record of superadmin emergency overrides (target, type, mandatory reason, old/new location). |

**Models:** `OfficeMembership`, `OfficeResponsibility`, `RoleAssignment`, `RoleHandshake`, `OverrideAuditLog`. Plus the runtime `User->memberships` relation.

**Services (bound as singletons in the provider):**
- `ClearanceEngine` — runs a **stack of rules** and reports pass/fail. Registered rules:
  - `NoActiveAssetsRule` — user must hold zero checked-out assets at this office.
  - `NoActiveRolesRule` — user must not be storekeeper/approver here.
  - `NoPendingRequestsRule` — user must have no open service requests.
  - `runChecks($user, $locationId)` returns a `ClearanceResult` per rule; `isCleared()` is true only if all pass. Adding a new rule = implement `IClearanceRule` and register it — no other change.
- `RoleHandshakeService` — the delegation state machine:
  - `proposeHandshake($locationId, $roleSlug, $fromUserId, $toUserId)` — validates the proposer actually holds the role and there's no duplicate pending handover, then creates a `pending` row.
  - `acceptHandshake($handshakeId, $userId)` — **atomic transaction**: delete the outgoing user's `OfficeResponsibility`, create the incoming user's, cancel any other pending handshakes for that role, mark this one `accepted`, and log to `gov_organization_activity_logs`.
  - `rejectHandshake` / `cancelHandshake` — flip status.
- `RoleAssignmentService` — manages role swaps / DB transactions.
- `OfficeMembershipService` — general membership helpers.

**Observer:** `MembershipActivityLogObserver` on `OfficeMembership` — compliance logging.

**Console command:** `SyncInitialMemberships` — back-fills memberships for pre-existing users (bootstrapping an already-populated Snipe-IT).

**Middleware:**
- `InjectMembershipUi` — menu injection + the orange "Pending Handshakes" warning box.
- `SetWorkingContext` — on every request, resolves the user's **active working office** into the session key `gov_working_membership_id`: clears stale context on user change, then defaults to the `is_home_office` active membership (falling back to the first active one). This session value is what `tenant-scope` reads to scope everything (see B5).

**Controllers & routes** (prefix `gov-store/my-memberships`, names `gov.membership.*`):

| Route | Controller | Purpose |
|-------|-----------|---------|
| `GET /` | `MembershipController@index` | "My Office Memberships" + live clearance results |
| `POST /{id}/request-release` | `MembershipController@requestRelease` | Start leaving an office (only if cleared) |
| `POST /switch` | `MembershipController@switchContext` | Change active working office |
| `POST /handshake/propose` | `RoleHandshakeController@propose` | Offer a role to a colleague |
| `POST /handshake/{id}/accept · reject · cancel` | `RoleHandshakeController` | Respond to a handover |
| `POST /claim/{locationId}` | `MembershipAdminController@claimEmployee` | Receiving office admin claims a released employee |
| `GET /override/console`, `POST /override/force` | `MembershipAdminController` | Superadmin emergency override console |

### The five office-membership workflows

1. **Self-Release & Clearance.** Employee opens *My Office Memberships*. `ClearanceEngine` runs all rules; each failing rule disables the "Request Release" button and shows why (e.g. *"Blocked: you hold 2 active assets"*). When everything is green, submitting sets membership `status = release_requested`.
2. **Peer-to-peer Role Handshake.** If a rule fails because the user *is* the storekeeper/approver, they **Delegate** to a local colleague (same building). Colleague sees the orange warning box and **Accepts**; `acceptHandshake()` atomically transfers the responsibility and logs it — instantly unblocking the outgoing employee.
3. **Onboarding / "Claim Employee".** Once released, the employee appears in a global floating pool. The **receiving** office admin opens their Office Hub → Local Employees → *Claim Incoming Employee*. Approving runs a transaction: mark old membership `released`, create/activate the new membership (`is_default = true`), and **sync core Snipe-IT** by updating `users.location_id` to the new building.
4. **Emergency Override (superadmin).** For abandoned posts: at `/override/console`, a superadmin picks **Force Release** (bypass clearance) or **Force Strip Roles** (clear all their duties), must type a ≥10-char justification, and the system writes a permanent `gov_override_audit_logs` row.
5. **Multi-Office Context Switching.** A director overseeing several offices `POST /switch`es their working context; the session `gov_working_membership_id` changes; `tenant-scope`'s `InitializeTenantContext` reads it and instantly re-scopes their catalog, stock, and requests — all without touching the core user record.

---

## B5. Module: `tenant-scope`

**Purpose:** the **data isolation engine**. It decides, for the current logged-in user on the current request, *which office/company/geographic slice of the database they may see and mutate*, and enforces it automatically on core Snipe-IT models. This is the most cross-cutting package.

### The request-scoped `TenantContext`

A singleton object (fresh per request) describing the active boundary:

```php
TenantContext {
  bool  isActive;              // scoping engaged?
  bool  isGlobal;              // true = superadmin, no restrictions
  ?array allowedLocationIds;   // pre-computed set of locations this user may view
  ?int  membershipId, companyId, locationId;  // active working office
  ?EffectivePermissionSet effectivePermissions;
  array configs;               // per-reference-type scope strategy
}
```

### `InitializeTenantContext` middleware — the brain (runs on both `web` and `api`)

For every authenticated request it:
1. **Guest?** → skip.
2. **Superadmin, or an admin with no company** → `isActive = true`, `isGlobal = true`, done (global view).
3. Otherwise mark `isActive = true`, `isGlobal = false`, then:
   - **Resolve the working office** from session `gov_working_membership_id` (set by office-membership's `SetWorkingContext`). Falls back to the user's own `location_id`/`company_id`.
   - **Pre-compute `allowedLocationIds`** by role:
     - **Company (Ministry) admin** → every location in their `company_id`.
     - **ICT officer** (has a `gov_ict_jurisdictions` row) → every location whose profile's `geo_area_id` sits under the officer's jurisdiction `hid` (the geo `LIKE` trick).
     - **Standard employee** → just their active working location.
   - **Resolve responsibility → permissions:** `AssignmentResolver::resolveActiveRole()` finds the user's `role_slug` at this office; `CapabilityProfileResolver::resolveSchema()` turns it into an `EffectivePermissionSet`; `SnipePermissionAdapter::adaptAndInject()` writes those permissions onto the in-memory `User` model **for this request only** (`$user->save()` is *never* called).

### The scopes (registered on core models at boot)

| Scope | Applied to | Rule |
|-------|-----------|------|
| `MinistryLocationScope` | `Asset`, `Consumable`, `Accessory`, `Component`, `License` (the operational inventory) | If the table has `company_id`, filter to context company; if it has `location_id`, filter to context location (and if no location is set → `WHERE 1=0`, i.e. see nothing). |
| `UserScope` | `User` | Restrict to `location_id IN allowedLocationIds` (empty set → see no users). |
| `TenantScope('locations')` | `Location` | Restrict `id IN allowedLocationIds`. |
| `TenantScope(<reference>)` | `Category`, `AssetModel`, `Supplier`, `Manufacturer` | Catalog mapping: driven by a configurable **strategy** (`global` / `company` / `office`) via the `gov_tenant_scope_mappings` table. Unmapped reference rows stay globally visible; mapped ones are locked to their owner scope. |

All scopes early-return when `!isActive` or `isGlobal`, so superadmins are unrestricted.

### Configurable scope strategy (`gov_tenant_scopes` + `gov_tenant_scope_mappings`)

- `gov_tenant_scopes` stores one `scope_strategy` per reference type. Seeded defaults: categories/models/manufacturers = `global`; suppliers = `office` (decentralized procurement); fieldsets = `company`; locations = `company`.
- `gov_tenant_scope_mappings` is a polymorphic "who owns this reference row" table: `(scope_type = company|location, scope_id) ↔ (reference_type, reference_id)`. This lets admins lock, say, a specific supplier to a specific office.

### Write/delete enforcement — the observer path

Registered on all scoped models: `TenantMutationObserver` forwards `creating`/`updating`/`deleting` to `TenantBoundaryService::verify($model, $action)`, which:
- **On create**: injects the correct `company_id`/`location_id` onto new transactional records so they're born owned by the right office.
- **On update/delete**: runs a **boundary policy** (`AssetBoundaryPolicy` for inventory, `CategoryBoundaryPolicy` for references) — throws `TenantBoundaryException` if the user's office doesn't own the record.
- **Relationship integrity**: for each foreign key on the model, checks the target both *without* scopes (does it exist at all → 404) and *with* scopes (is it visible to me → 403), so you can't attach an out-of-boundary category/supplier/etc.
- **Asset checkouts**: if an asset's `assigned_to`/`status_id`/`location_id` is changing, verify the actor holds an active `storekeeper` responsibility (via `ResponsibilityRegistry::can($roleSlug, 'checkout_assets')`) — otherwise `ROLE_VIOLATION`.
- **Business rules**: delegates extra checks to `BusinessRuleValidator`.

`HandleBoundaryExceptions` middleware turns thrown `TenantBoundaryException`s into clean 403/404 responses.

**Supporting services:** `BoundaryResolver` (chooses `global`/`company`/`location`/`jurisdiction` strategy), `AssignmentResolver`, `CapabilityProfileResolver`, `EffectivePermissionSet`, `ReferenceOwnershipService`, `SnipePermissionAdapter`. **Validators:** `BusinessRuleValidator`, `ResponsibilityRegistry`. **Config:** `src/config/permissions.php` (merged as `govstore-permissions`).

**Admin UI & routes** (prefix `gov-store/admin/scope`, names `gov.scope.*`): `TenantScopeController` — configure strategies, AJAX reference/tenant search, and create/delete polymorphic mappings.

---

## B6. Module: `custom-requests`

**Purpose:** the **storefront**. Employees browse a catalog, fill a **basket**, submit it as one or more **Service Requests**, which then flow through **approval** and **fulfillment (issuance)** — with fulfillment ultimately calling Snipe-IT's real checkout so inventory stays accurate.

### Data model (two generations — note the migration history)

The `2024_01_01` migration created a simple `custom_item_requests` table (single-item requests, model `ItemRequest`, service `RequestService`). The `2024_01_02` migration **superseded** it with the richer **Service Request** document model (it explicitly drops the old table). The current system is the Service Request model:

| Table | Role |
|-------|------|
| `custom_service_requests` | The request **document**: `request_number`, `requested_by`, `request_type`, `purpose`, `justification`, `required_by_date`, `delivery_location_id`, `cost_center`, `resolved_policy`, `assigned_approver_id`, and two independent state machines `approval_status` + `fulfillment_status`, plus timestamps. |
| `custom_service_request_items` | The **line items**. Polymorphic *requested* item (`requested_type`/`requested_id`) **and** a separate *fulfilled* item (`fulfilled_type`/`fulfilled_id`, for substitutions). Quantity ladder: `requested_qty` → `approved_qty` → `reserved_qty` → `issued_qty`, plus per-line `line_approval_status` + `line_fulfillment_status`. |
| `custom_service_request_events` | **Immutable event-sourced timeline** (`event_type`, JSON `details`) — every state change is logged here. |
| `gov_approval_policies` | Polymorphic policy overrides: `(target_type, target_id)` → `policy_name`. |
| `gov_location_roles` | (Also created here for approver resolution — mirrors the organization role matrix.) |

**Models:** `Request` (aliased `ServiceRequest`, → `custom_service_requests`), `RequestItem`, `RequestEvent`, `ApprovalPolicy`, `LocationRole`, plus the legacy `ItemRequest`.

### The polymorphic Adapter pattern (how it talks to Snipe-IT)

Requests can target Assets, Accessories, or Consumables — three different core models with three different checkout signatures. To hide that, each has an **Adapter** (`AssetAdapter`, `AccessoryAdapter`, `ConsumableAdapter`) implementing `RequestableInterface` (`getDisplayName()`, `checkout($requester, $by, $qty, $note)`, …). `RequestableFactory::make($type, $id)` returns the right adapter. This is the seam where our storefront hands off to Snipe-IT's real inventory logic. The provider also declares a `Relation::morphMap` (`asset`/`accessory`/`consumable`/`license`) to keep DB values clean.

### The three policies (`PolicyService::resolvePolicy`)

Every catalog item resolves to one approval policy, checked in order:
1. **Direct item override** — a `gov_approval_policies` row for this exact item.
2. **Category inheritance** — a policy on the item's category.
3. **Global default** → `PRIMARY_ONLY`.

Policy values: `AUTO_APPROVE` (no human needed), `PRIMARY_ONLY` (one approver), `PRIMARY_AND_FINAL` (two-step).

### Business flow — the four stages

**1. Basket (`BasketService`).** A user's draft basket *is* a `custom_service_requests` row with `approval_status = 'draft'` (`getOrCreateDraftBasket`). `addItem` / `updateItemQty` / `removeItem` manage draft line items. Rule: **assets are quantity-1** (serialized); accessories/consumables can stack.

**2. Submit (`BasketService::submitBasket`).** The clever part — the basket is **split by policy**:
- Group draft items by their resolved policy.
- Validate the office has a configured `LocationRole` if any item needs human approval.
- For each policy group, create a **separate** `ServiceRequest` document so each can flow at its own approval speed:
  - `AUTO_APPROVE` → born `approved`, lines `waiting` for fulfillment.
  - `PRIMARY_ONLY` / `PRIMARY_AND_FINAL` → resolve the **active approver** (honoring delegate + `…_delegate_until` calendar), set `assigned_approver_id`, status `pending_primary` (or straight to `pending_final`/`approved` if the requester *is* the approver — self-approval conflict handling).
- Copy line items into each new request, log a `submitted` event, then delete the draft. Returns the list of created requests.

**3. Approval (`ApprovalService::processDecision`).** An approver opens the request and decides **per line** (approve + quantity, or reject):
- **Primary gate**: `PRIMARY_ONLY` → approved lines go straight to `waiting` fulfillment. `PRIMARY_AND_FINAL` → primary's quantity is recorded and the request advances to `pending_final`, with `assigned_approver_id` set to the active final approver (self-approval short-circuits to approved).
- **Final gate**: final approver can only *reduce* quantity (`min(finalQty, approved_qty)`); approved lines move to `waiting`.
- Rejected lines → `approved_qty = 0`, line `cancelled`, logged.
- Parent document rolls up to `approved` / `partially_approved` / `rejected` accordingly. Everything runs in a transaction and writes timeline events.
- (Legacy single-item path fires an `ItemApproved` event → `ProcessItemCheckout` listener → adapter `checkout()`.)

**4. Fulfillment (`FulfillmentService::issueItems`).** The **storekeeper** physically issues approved items:
- Optional **substitution**: swap the requested item for an alternative of the same general type (records `fulfilled_id`, logs `item_substituted`).
- For each approved line, issue up to the remaining approved quantity (can't over-issue), then call the adapter's `checkout(...)` → **this is where real Snipe-IT inventory gets checked out to the requester.**
- Track `issued_qty`; line becomes `partially_issued` or `issued`. When all approved lines are fully issued, the parent closes (`fulfillment_status = issued`, `approval_status = closed`, `closed_at` set).
- `forceClose()` lets a storekeeper cancel remaining lines with a logged reason.

**Middleware:** `InjectGovStoreUi` (menu injection). **Event/Listener:** `ItemApproved` → `ProcessItemCheckout`.

**Controllers & routes** (prefix `gov-requests`, names `gov.requests.*`):

| Area | Route | Controller |
|------|-------|-----------|
| Catalog | `GET /catalog`, `GET /catalog/search` | `GovRequestController@catalog / search` |
| My requests | `GET /my-requests` | `GovRequestController@index` |
| Basket | `GET /basket`, `POST /basket/add · update · remove/{id} · submit` | `BasketController` |
| Approval | `GET /admin`, `GET /admin/{id}`, `POST /admin/{id}/process` | `GovApprovalController` |
| Fulfillment | `GET /fulfillment`, `GET /fulfillment/{id}`, `POST /fulfillment/{id}/issue · close` | `GovFulfillmentController` |
| Settings | `/admin/settings/locations*`, `/admin/settings/policies*` | `GovApprovalController` |

---

## B7. End-to-end story: from empty system to an issued item

Putting all five modules together, here is the full lifecycle a novice can follow:

```
1. GEOGRAPHY (geo-areas)
   gov_geo_areas is seeded from CSV → the whole country's tree exists, each area has an `hid`.

2. PROVISION AN OFFICE (organization)
   Superadmin/ICT officer creates an Office → core Location + gov_location_profiles (pinned to a geo area)
   + empty gov_location_roles. Lifecycle = 'provisioned'.
   Assign an office admin → 'configured'.

3. STAFF THE OFFICE (office-membership + organization)
   Users get gov_office_memberships (home office). Roles get filled in gov_office_responsibilities
   (storekeeper, primary_approver). OfficeReadinessService sees admin+approver+storekeeper+users
   → lifecycle = 'operational'. The office can now trade.

4. A USER LOGS IN (office-membership → tenant-scope)
   SetWorkingContext puts their home membership id in session (gov_working_membership_id).
   InitializeTenantContext reads it, builds TenantContext (company/location/allowedLocationIds),
   resolves their role → permissions injected in-memory.
   From now on EVERY query on assets/users/locations/etc. is auto-filtered to their office.

5. SHOP (custom-requests)
   User browses the catalog (already scoped to their office), adds items to a basket
   (= a draft custom_service_requests row).

6. SUBMIT (custom-requests)
   submitBasket splits the basket by approval policy into one-or-more Service Requests,
   auto-approving AUTO_APPROVE items and routing the rest to the resolved Primary approver.

7. APPROVE (custom-requests + organization roles)
   Primary (and Final, if PRIMARY_AND_FINAL) approve per line, adjusting quantities.
   Approved lines become 'waiting' for the storekeeper.

8. FULFILL (custom-requests → Snipe-IT core)
   Storekeeper issues items (optionally substituting). Each issue calls the Adapter's checkout(),
   which invokes Snipe-IT's REAL checkout → inventory and holder records update in core tables.
   When all lines are issued, the request closes.
   (tenant-scope's boundary service verifies the storekeeper actually owns this office + role.)

9. STAFF MOVEMENT (office-membership)
   When the user transfers: ClearanceEngine checks no held assets / no roles / no open requests.
   Blocking roles are handed over via a RoleHandshake. Once cleared → release_requested →
   receiving office admin "claims" them → core users.location_id is synced to the new building.
   Emergencies use the superadmin override console (fully audited).
```

## B8. Security posture (known state)

A `packages/multi_tenant_security_audit_report.md` (dated 2026-07-08) documented the multi-tenant isolation. Note that the **current code has since hardened several of the audit's findings** — do not read the audit as the present state. Confirmed in the live code today:

- `InitializeTenantContext` now runs on **both** `web` **and** `api` groups (`pushMiddlewareToGroup('api', …)`), so the REST API is scoped.
- Only true `isSuperUser()` (or an admin with **no** company) gets the global bypass; ordinary office/company admins are now bound to their company/jurisdiction.
- Inventory models (`Consumable`, `Accessory`, `Component`, `License`) **are** now registered under `MinistryLocationScope`, not just `Asset`.
- Write/delete/relationship enforcement runs through `TenantMutationObserver` → `TenantBoundaryService`.

Still worth watching (things scopes can't automatically catch): explicit `withoutGlobalScopes()` calls in core Snipe-IT controllers/importers, raw `DB::table(...)` queries, background jobs/CLI (no HTTP request → context inactive), and the base policy's early `return true` for admins. Treat these as the ongoing hardening surface.

---

# APPENDIX 1 — DATABASE TABLES REFERENCE

**Main project (Snipe-IT core, selected):** `assets`, `accessories`, `consumables`, `components`, `licenses`, `license_seats`, `models` (asset models), `categories`, `manufacturers`, `suppliers`, `locations`, `companies`, `users`, `status_labels`, `depreciations`, `custom_fields`, `custom_fieldsets`, plus checkout pivots `accessories_checkout`, `components_assets`.

**Custom modules (`gov-store`):**

| Table | Module | Purpose |
|-------|--------|---------|
| `gov_geo_areas` | geo-areas | Geographic hierarchy (with `hid` path) |
| `gov_location_profiles` | organization | Office ⇄ geo/admin/lifecycle extension of `locations` |
| `gov_ict_jurisdictions` | organization | ICT officer → geo boundary |
| `gov_location_roles` | organization + custom-requests | Approver/storekeeper matrix (+ delegates) |
| `gov_organization_activity_logs` | organization | Office audit trail |
| `gov_office_memberships` | office-membership | User ⇄ office link (+ home flag, status) |
| `gov_office_responsibilities` | office-membership | Live who-holds-which-role matrix |
| `gov_role_handshakes` | office-membership | Pending role handovers |
| `gov_override_audit_logs` | office-membership | Superadmin emergency override log |
| `gov_tenant_scopes` | tenant-scope | Scope strategy per reference type |
| `gov_tenant_scope_mappings` | tenant-scope | Polymorphic "who owns this reference row" |
| `custom_service_requests` | custom-requests | Request document (basket when draft) |
| `custom_service_request_items` | custom-requests | Request line items (requested vs fulfilled) |
| `custom_service_request_events` | custom-requests | Immutable request timeline |
| `gov_approval_policies` | custom-requests | Per-item/category approval policy overrides |
| `custom_item_requests` | custom-requests (legacy) | Superseded single-item request table |

---

# APPENDIX 2 — GLOSSARY

- **Bounded context** — a self-contained module owning its own tables/logic (each gov-store package).
- **Zero-Touch** — extending Snipe-IT without editing its files (via providers, middleware, global scopes, runtime relations).
- **Tenant / boundary** — the office/company/geographic slice of data a user is confined to.
- **Working context** — the office a user is currently "acting as" (session `gov_working_membership_id`); switchable if they have multiple memberships.
- **`hid`** — a geographic hierarchy path string; `LIKE 'parent%'` finds an entire subtree cheaply.
- **Membership vs Responsibility** — *membership* = which office you belong to; *responsibility* = which duty (storekeeper/approver) you hold there.
- **Clearance** — the pass/fail gate (no held assets, no roles, no open requests) required before leaving an office.
- **Handshake** — a proposed peer-to-peer transfer of a responsibility, needing the recipient's acceptance.
- **Policy (approval)** — `AUTO_APPROVE` / `PRIMARY_ONLY` / `PRIMARY_AND_FINAL`; decides how many humans must sign off.
- **Adapter / Factory** — the seam that lets one storefront request target Assets, Accessories, or Consumables and reach Snipe-IT's real checkout.
- **Service Request** — the document produced when a basket is submitted; carries independent `approval_status` and `fulfillment_status` machines.
- **FMCS** — Full Multiple Company Support; Snipe-IT's built-in per-company scoping toggle (distinct from, and layered under, gov-store's tenant-scope).

---

*Generated from the codebase. Main project = Snipe-IT (everything outside `packages/`). Custom modules = `packages/gov-store/{geo-areas, organization, office-membership, tenant-scope, custom-requests}`.*
