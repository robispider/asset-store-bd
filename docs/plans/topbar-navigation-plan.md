# Gov-Store Top Bar & User Menu — Investigation & Mitigation Plan

> **Scope:** Snipe-IT's navbar (`header.main-header`) and the top-right user menu, as rendered with the gov-store packages attached.
> **Status:** Phases 0–3 delivered; license receipt (D2) and Phase 4 search open · **Date:** 2026-10-13
> **Follows:** `docs/plans/navigation-menu-plan.md` (the sidebar work, Phases 0–3 delivered). The same rules apply here: zero core edits, attach through `NavigationHook` and theming, translate at render time, and protect every anchor with a contract test.

---

## 1. Summary

The sidebar is now task-based, but the top bar is still **stock Snipe-IT** with two gov items bolted on (the basket, and a membership block in the user menu). Five problems stand out:

1. **"Create New" bypasses the store ledger.** The navbar offers direct creation of Asset, License, Accessory, Consumable, Component and User. In gov-store, stock enters through a **Goods Receipt (GRN)**. Hiding the menu alone is not enough: storekeepers hold `assets.create`, `consumables.create` and similar grants, so the same forms are reachable from the dashboard, list toolbars, clone buttons, Import and the API.
2. **The super admin sees every office.** The working-office switcher lists every location that has a profile. That is **57 offices** in the current database and would be thousands nationally. It is rendered as one POST form per office, on **every page**.
3. **The navbar shows Snipe-IT nouns, not the user's work.** It has six icon-only shortcuts (Assets, Licenses, …, Users), an asset-tag box and a Snipe-IT alert bell. It shows no current office, no "waiting for me" count, and no gov actions.
4. **The user menu is long and mixed.** It holds up to 16 entries, including Snipe-IT's requested-assets flow (which D2 replaced), *Manage API keys* for **every employee**, and two separate appearance controls (Dark mode and Appearance).
5. **The bar scrolls away.** On long registers and queues, users lose the basket, the switcher and the menu.

---

## 2. What renders today (audit)

`resources/views/layouts/default.blade.php` lines 1196–1499 (core, untouched), plus the gov zones injected by `NavigationHook`:

| # | Item | Gate | Source | Super admin | Storekeeper | Employee |
|---|---|---|---|---|---|---|
| 1 | Sidebar toggle, logo / site name | — | core | ✓ | ✓ | ✓ |
| 2 | Icon shortcuts: Assets, Licenses, Accessories, Consumables, Components, Users | `index`/`view` per model | core :1248–1296 | 6 | 5 | — |
| 3 | Asset-tag lookup box | `index` Asset | core :1298 | ✓ | ✓ | — |
| 4 | **Create New ▾** (6 entries) | `admin` + `create` per model | core :1318–1377 | ✓ | —¹ | — |
| 5 | Alert bell (`<x-alert-menu />`) | `admin` | core :1379 | ✓ | — | — |
| 6 | Request basket | — | gov `topbar` zone (`custom-requests`) | ✓ | ✓ | ✓ |
| 7 | User menu (see below) | — | core + gov `user` zone | | | |
| 8 | Settings cog | `superadmin` | core :1486 | ✓ | — | — |

¹ Storekeepers do not see the navbar dropdown (it needs `admin`), but they **do** see the same create links on the dashboard and in list toolbars, because `inventory_operator` grants `assets.create`, `consumables.create`, `accessories.create`, `components.create` and `licenses.create` (`tenant-scope/src/config/permissions.php:41-73`).

**User menu, in render order:** View my assets · Requested assets (super users only, since D2) · Accept assets · Edit profile · Change password · Dark mode · Manage API keys · *[gov `user` zone]* My access · Appearance · ─ · My memberships · "Choose working office" header · Global overview · **one form per office** · ─ · Logout.

### How GRN intake actually works (relevant to §3.4)

- **Assets** are created *by* GRN posting: `CreateAssetsCapability` builds `Asset` rows from the receipt line's `AssetModel`. Posting is gated by `gov.can:storeops.documents.post` and **does not need** `assets.create`.
- **Consumables, accessories and components** are *posted to an existing stock record*. `LedgerPostingService` rejects a record that does not belong to the posting office, and `ProductResolver` searches only existing records. **Someone still has to create the stock record (with quantity 0) before the first GRN.** Today that is the native "Create Consumable" form. `LedgerStockGuard` already blocks native quantity changes in ledger offices, but it allows a qty-0 create.
- **Licenses** are not a `StockableType`, so GRN cannot receive them.
- **Users** come from onboarding, the government directory import and staff management, not from stock intake.

---

## 3. Problems & gaps

Severity: **H** = visibly wrong or a control gap now · **M** = friction or cost · **L** = polish.

### 3.1 Top bar content

| # | Sev | Problem |
|---|---|---|
| T1 | **H** | **"Create New" contradicts the GRN-only intake rule.** It is the most prominent action in the bar and sends admins around the ledger, the committee and the audit trail. |
| T2 | M | **The icon shortcuts are Snipe-IT's object list**, are icon-only (`aria-hidden`, tooltip only), and duplicate the sidebar. They do not match gov tasks (receive, issue, approve, request). |
| T3 | M | **The current working office is invisible.** Every gov page is scoped by `TenantContext`, but the only place that shows the office is inside the user dropdown. Users acting for the wrong office is the most expensive mistake in a multi-office store. |
| T4 | M | **No "waiting for me" signal in the bar.** The sidebar has Approvals and To-fulfil badges, but they are hidden whenever the sidebar is collapsed, which is the default (`sidebar-collapse` unless `menu_state=open`). |
| T5 | L | **The asset-tag box** is labelled "Lookup by tag" and searches only hardware. For a storekeeper, the useful lookup is "item, tag or document number". |
| T6 | L | **The Settings cog and the alert bell** are Snipe-IT admin tools. They are fine for super admins, but have no label (icon only). |

### 3.2 User menu & office switcher

| # | Sev | Problem | Evidence |
|---|---|---|---|
| O1 | **H** | **Super admin switcher lists every office.** For super users the list is `Location::whereHas('profile')`, not their memberships (57 offices now). A super admin has no memberships, so the switcher becomes a directory of the whole country. | `office-membership/…/hooks/membership-menu.blade.php:7-9` |
| O2 | **H** | **Cost on every page:** one query for all offices, plus N `<form>` elements with N CSRF tokens in the HTML of every page, for a control that is used rarely. This grows linearly with the number of offices. | same |
| O3 | M | **A dropdown is the wrong control** for choosing among hundreds of offices. The type-to-filter box (Phase 4 of the sidebar plan) helps only after the full list has already loaded. It offers no recent offices, no grouping by ministry or district, and no way to see "which office am I in" without opening it. | |
| O4 | M | **The switcher is the last thing in a 16-item menu**, below personal settings. It is a work-context control, not a profile setting. | |
| U1 | M | **"Manage API keys" is shown to every employee**, because the `employee` profile grants `self.api`. Field staff have no use for it, and it is a needless credential surface. | `permissions.php` (employee → `self.api`) |
| U2 | M | **Two appearance controls:** core "Dark mode" and gov "Appearance" (theming). | |
| U3 | L | **"View my assets" and "Accept assets"** use Snipe-IT wording. In gov terms these are "Items issued to me" and "Confirm receipt of issued items". | |
| U4 | L | **Requested assets** still shows for super users (D2 hid it from everyone else). It is the legacy flow. | |

### 3.3 Floating (sticky) bar

| # | Sev | Problem | Evidence |
|---|---|---|---|
| S1 | M | The bar is `position: relative` and scrolls away. The basket, the switcher and logout are lost on long pages. | `all.css`: `.main-header{position:relative;z-index:1030}` |
| S2 | — | **AdminLTE's built-in `.fixed` layout is not suitable.** It fixes the header **and** the sidebar, sets `.wrapper{overflow:hidden}`, and assumes a 50px header (`.content-wrapper{padding-top:50px}`). Snipe-IT's header wraps to about 100px on narrow screens (`max-height:150px`), so content would be hidden under it. | `all.css` `.fixed …` rules |
| S3 | M | **Bootstrap-table sticky headers** are enabled (`stickyHeader: true`) with **no Y offset**. Under a floating bar, every list's column header would stick *behind* the navbar. | `partials/bootstrap-table.blade.php:683` |
| S4 | M | **Dropdowns inside a floating bar can no longer scroll with the page.** The user menu, and anything long in it, must cap its height to the viewport. | |
| S5 | L | **In-page anchors and the skip link** (`.skip-main` → `#main`) would land under the bar without `scroll-padding-top`. | |

### 3.4 GRN-only intake (enforcement, not just menu)

| # | Sev | Problem |
|---|---|---|
| G1 | **H** | **Hiding the menu is cosmetic.** Direct create stays reachable from the dashboard quick buttons (`dashboard.blade.php:175-201`), list toolbars, clone actions, the model page ("create asset of this model"), `/import`, and `POST /api/v1/hardware|consumables|accessories|components`. |
| G2 | **H** | **Super users bypass every permission.** Core `App\Providers\AuthServiceProvider:112` registers a `Gate::before` that returns `true` for super users. It boots before every gov provider (`config/app.php:428` vs `442+`), and the first non-null `before` result wins. Neither removing profile grants nor adding a gov `Gate::before` can stop a super user. |
| G3 | M | **Stock records for consumables, accessories and components must exist before a GRN can receive them** (§2). Blocking their native create outright would leave no way to add a new item to an office. |

---

## 4. Target design

### 4.1 Principles

1. **The bar answers three questions:** *Where am I working?* *What is waiting for me?* *What do I do next?*
2. **Use gov verbs, not Snipe-IT nouns.** Receive, issue, request and approve replace Asset, License and Component.
3. **Intake has one front door.** New stock enters only through a posted GRN. The rule is enforced on the routes, not just hidden in the UI.
4. **Make no core edits.** Attach through `NavigationHook` and theming, as the sidebar does, behind a contract test.
5. **Label everything.** No icon-only controls without a visible label at desktop widths.

### 4.2 Proposed top bar (left to right)

```
[☰] [Logo · Site]  [📍 Dhaka DC Office ▾]        [🔎 Item, tag or document no.]  [＋ New ▾]  [📥 3]  [🧺 2]  [👤 Name ▾]  [⚙]
                    office chip (T3/O*)            gov search (T5)             gov actions   inbox  basket   user menu    super only
```

| Slot | Content | Who | Replaces |
|---|---|---|---|
| **Office chip** (new `context` zone, after the logo) | The current working office name (or "All offices" for a super admin in global overview). Clicking opens the **office picker** (§4.3). | everyone with a membership; super admin | the switcher block inside the user menu |
| **Search** | Keep the core tag box at first. Later (Phase 4), point it at a gov search across items, tags and document numbers. | `index` Asset / `storeops.documents.view` | — |
| **＋ New ▾** (gov `actions` zone, **in place of** core Create) | New goods receipt · New issue · New adjustment *(storekeeper, `storeops.documents.draft`)* · New request *(employee)* · Add staff member *(office admin, `gov.membership.admin`)* | gated per entry; hidden when empty | **core "Create New"** (T1) |
| **Inbox 📥 n** | One count: approvals + to-fulfil + onboarding + access requests, from the existing `badge` closures. Opens a small dropdown with each queue and its count. | approvers, storekeepers, office admins | — (T4) |
| **Basket** | Unchanged (`custom-requests` `topbar` item). | everyone | — |
| **User menu** | §4.4 | everyone | — |
| **Settings cog / alert bell** | Unchanged, super admin only. | super admin | — |
| *Core icon shortcuts* | **Hidden** for gov deployments (config flag). The sidebar already lists them under "Asset management". | — | T2 |

### 4.3 Office picker (fixes O1–O4)

- **Members** (almost everyone): render their active memberships directly. There are usually 1–3, so no search is needed and the cost stays tiny.
- **Super admins / multi-office users with more than 8 offices:**
  - The page renders **no office list**. The chip opens a small panel containing:
    - **Global overview** (super admin only)
    - **Recent offices**: the last 5 switched to, stored per user
    - A **search box** backed by a new `GET gov/membership/offices?q=` endpoint (select2-style, paged, 20 per page). Results show *office · upazila/district · ministry*, so offices with similar names can be told apart.
  - **One** switch form, submitted with the chosen `location_id`, instead of N forms.
- The endpoint applies the same rule as today: members see only their memberships, super users see offices with a profile. It reuses `gov.membership.switch` for the actual switch, so no authorization logic changes.
- A visible office name in the bar also closes the sidebar plan's last open item ("current-office indicator").

### 4.4 User menu (fixes U1–U4)

| Section | Entries |
|---|---|
| **Me** | Items issued to me (`view-assets`) · Confirm receipt (`account.accept`) · My office memberships · My access |
| **Account** | Edit profile · Change password · Appearance (theming; the core Dark-mode toggle is folded in here or hidden) · API keys *(only with an explicit `self.api` grant)* |
| ─ | Logout |

- Remove `self.api` from the `employee` profile and grant it only to ICT and admin profiles (U1).
- Hide core "Requested assets" for super users too when `custom-requests` is enabled (U4). Optional; see D5.
- The office switcher **leaves** the user menu (it moves to the chip).

### 4.5 Floating top bar (fixes S1–S5)

Use **`position: sticky`**, not AdminLTE `.fixed` (S2). Sticky keeps the bar in normal flow, so a header that wraps to two lines still pushes the content down correctly, and the sidebar is unaffected.

```css
/* theming adapter (or tenant-scope nav CSS), desktop and tablet only */
@media (min-width: 768px) {
  .main-header { position: sticky; top: 0; z-index: 1030; }
  html { scroll-padding-top: var(--gs-header-h, 50px); }                        /* S5 */
  .main-header .navbar-nav > li > .dropdown-menu {                              /* S4 */
    max-height: calc(100vh - var(--gs-header-h, 50px) - 16px); overflow-y: auto;
  }
}
```

- **Measure the height once** (`ResizeObserver` on `.main-header`) into `--gs-header-h`, so wrapping and theme density changes stay correct.
- **Bootstrap-table offset (S3):** set `$.fn.bootstrapTable.defaults.stickyHeaderOffsetY` to the measured height in the `scripts` zone. It runs before `@stack('js')`, so before tables initialise. Every list's sticky column header then sits under the bar instead of behind it.
- **Phones (< 768px), per D4:** the bar is sticky but **hides on scroll-down and comes back on scroll-up**, as in the X/Twitter app. A passive `scroll` listener toggles `data-gs-header-hidden`, and CSS applies `transform: translateY(-100%)` with a 200ms transition (no transition under `prefers-reduced-motion`). The bar always shows at the top of the page, while a navbar dropdown is open, and while focus is inside it (keyboard users). It ignores scroll changes under 8px so it doesn't flicker. The bootstrap-table offset follows the bar: 0 when hidden, the bar's height when shown.
- Theming owns colour. A subtle shadow (`box-shadow` token) appears only once the page has scrolled, via a `data-gs-scrolled` attribute set by the same script.
- The sidebar keeps scrolling with the page (D4).

### 4.6 GRN-only intake enforcement (fixes G1–G3)

A new `govstore.intake` config in `store-operations`, with **`mode` = `grn_only`** for gov deployments.

| Layer | Mechanism | Covers |
|---|---|---|
| **Route guard** (the real control) | Middleware `govstore.intake`, pushed onto the `web` and `api` groups by `StoreOperationsServiceProvider` (`pushMiddlewareToGroup`, no core edit). It rejects a fixed list of route **names** when `mode = grn_only`: asset and license create, store and clone, plus `api.assets.store` and `api.licenses.store` (D1, D2). Web requests are redirected to "New goods receipt" with an explanatory flash. API requests get a 403 JSON error. Being route-level, it **applies to super users too** (G2), which a gate cannot do. | every create path in G1, for every user |
| **Profiles** | Drop `assets.create` and `licenses.create` from `inventory_operator` (`licenses.create` only once license receipt ships), so non-super users no longer *see* the dashboard buttons, toolbar "Create New", clone or model-page links. | UI for everyone except super users |
| **Super-user UI** | Super users still pass `@can('create', …)`, so the dashboard quick buttons and list toolbars still show for them, and the guard catches the click. The navbar Create menu is replaced (below). Hiding the remaining buttons would need more precompiler anchors on core views; decide after Phase 1 whether that's worth it. | residual, low risk |
| **Stock records** | Consumable, accessory and component records stay creatable, but **quantity is forced to 0** for every office, not just ledger-opened ones. `LedgerStockGuard` already does this for ledger offices, so the change widens its scope. In the UI, the entry becomes "Add item to office catalogue", reached from the GRN line ("Item not listed? Add it"), not from a global Create menu. | G3 |
| **Import** (D3) | Stock imports (asset, license, consumable, accessory, component) are allowed **only for an office whose store ledger is not opened yet** (no `gov_store_ledger_openings` row) and only for super admins. After the office's cut-over (`govstore:ledger-open`), stock imports into it are rejected. User and location imports are unaffected. The guard checks the import's target location on each row, not the page. | G1 |
| **License receipt** (D2) | Licenses become GRN-receivable: a `LICENSE` `StockableType`, with a receipt line that creates the `License` and its seats. **The license create block switches on in the same release**, so there is never a window with no way to add a license. | D2 |
| **Menu** | The core "Create New" dropdown is **replaced** by the gov `actions` zone (§4.2). | T1 |

`*.create` stays granted for consumables, accessories and components (record only, qty 0). The route guard is the real control. The menu change is only the visible part.

### 4.7 Attachment (no core edits)

Extend `NavigationHook` (bump to `gov-nav-hook:v2`):

| Zone / change | Anchor | Action |
|---|---|---|
| `context` (office chip) | after `</div>` closing `.left-navblock` | insert |
| `actions` (＋ New) | the `@can('admin') <li class="dropdown user-menu"…trans('general.create')…</li> @endcan` block | **replace**, behind config `navigation.topbar.replace_core_create` (default `true`) |
| core icon shortcuts | the `@can('index', \App\Models\Asset::class) <li aria-hidden…` … Users `@endcan` run | **wrap** in `@unless(config('govstore-navigation.topbar.hide_core_shortcuts'))` |
| `inbox` | before the `topbar` zone | insert |
| sticky CSS and JS | theming adapter CSS + `scripts` zone | no anchor needed |

Replace and wrap operations are new for this hook. Each one must match **exactly once**, or it is skipped and the core markup is left intact. `SnipeItNavContractTest` gets one assertion per anchor, plus "the page still renders when the anchor is missing".

---

## 5. Implementation phases

Delivered 2026-10-13, except license receipt (see Phase 1). Verified by 337 passing gov-store tests (322 before, plus 15 new) and by real pages rendered through the HTTP kernel as a super admin, storekeeper, approver, ICT officer and plain employee, in en-US and bn-BD. The floating bar was also checked live in the browser at 375, 800, 1024, 1200, 1280 and 1366px. **No Snipe-IT core file changed.** Differences from the plan are marked **Changed**.

### Phase 0 — Quick wins ✅
- [x] **Changed:** the office switcher went straight to the Phase 2 chip and picker, with no interim version (O1, O2).
- [x] `self.api` removed from the `employee` profile and granted to `ict_operations` (U1).
- [x] Floating bar (`tenant-scope/src/resources/css/topbar.css`, `resources/js/topbar.js`, bundled by theming): sticky on desktop, hide-on-scroll on phones, `--gs-header-h`, dropdown max-height, `scroll-padding-top`, and the bootstrap-table sticky-header offset (S1, S3–S5, D4).
  - **Found while testing:** AdminLTE's `.wrapper` is `overflow-x: hidden; overflow-y: auto`. That makes it a scroll container that never scrolls, so `position: sticky` did nothing. The fix is `overflow-x: clip` on the wrapper, which still hides sideways overflow.
  - **Found while testing:** package CSS is bundled inside a cascade layer, so Bootstrap's unlayered `.nav > li > a` and `.btn` rules beat it. The navbar-zone rules use `!important`, as theming's adapter does.
  - **Measured:** the bar is one 50px row at 1024px and above in both languages. Inbox and basket text labels are hidden below 1366px; their icons, counts and `aria-label`s stay. At 800px Snipe-IT's own 281px tag-search box wraps the bar to 100px, as stock Snipe-IT does. On phones the bar is 150px (Snipe-IT's own layout), which is why it hides on scroll-down.

### Phase 1 — GRN-only intake ✅ (license receipt not started)
- [x] `store-operations/src/config/intake.php` (`govstore-intake`), `IntakePolicy`, and the `EnforceGrnIntake` middleware on the `web` and `api` groups. It closes `hardware.create`, `hardware.store`, `clone/hardware` and `api.assets.store` for everyone, super users included. Web requests are redirected to Receipts & issues with a message; the API returns 403 (G1, G2, D1). `assets.create` is removed from `inventory_operator`.
- [x] Consumable and accessory records must be created at qty 0 (`consumables.store`, `accessories.store` and their API routes). The goods-receipt line grid has an "Item not listed? Add it to the office catalogue" menu (asset model, consumable, accessory, component). It opens in a new tab and appears only for items the user may create (G3).
  - **Changed:** components are not included. Snipe-IT requires component `qty >= 1`, so a zero-quantity rule would make them impossible to create. `LedgerStockGuard` already blocks native component quantity in ledger-opened offices.
- [x] Stock import (asset, license, consumable, accessory, component) is allowed only for super users. It runs with a row observer that rejects any row whose office ledger is open, and the whole import rolls back with an error in the importer's own format (D3). User and location imports are unaffected. Licenses have no office, so a super user can still import them.
- [ ] **License receipt in GRN (D2) — not started.** The license block is wired, with routes listed in `intake.routes.licenses`, but it is off by default (`GOVSTORE_INTAKE_BLOCK_LICENSES=false`). Turning it on now would leave no way to add a license. License receipt is a ledger feature, not navigation work: it needs a new stockable type, a seat model, opening and reconciliation support, and issue and return of seats. It needs its own plan.
- [x] Tests in `tests/Feature/GovStore/Navigation/TopbarIntakeTest.php`: guarded routes exist upstream; super users are denied asset create, store, clone and the API; the license switch and `open` mode work; qty-0 records; super-user-only import before cut-over with rollback after; GRN-style saves are never import-checked; the profile changes.
  - **Changed:** "dashboard quick buttons disappear" follows from the profile change and was checked in the rendered pages, not in a unit test.

### Phase 2 — Top bar zones ✅
- [x] `NavigationHook` v2 adds a `context` anchor and `REWRITES`. "Create New" is **replaced** by the `actions` zone, and the icon shortcuts and "Requested assets" are **wrapped**. Each is switched at render time by `govstore-navigation.topbar.*` and `user_menu.*`. The stock markup stays in the compiled view behind the flag, so turning a flag off restores upstream without recompiling. A rewrite that doesn't match exactly once is skipped.
- [x] Office chip (`govmem::hooks.office-chip`), `OfficePicker`, `GET gov-store/my-memberships/offices` (`gov.membership.offices`, paged 20, LIKE-escaped), and recent offices (last 5 per user, cached 90 days) (O1–O4, T3). Members with 8 or fewer offices pick from a short list; others search. One switch form per page instead of one per office.
- [x] ＋ New: New goods receipt, New issue, New adjustment (only with a working office), Request items, Add staff member. Visibility comes from each route's `gov.can`. A single entry renders as a button, not a one-item dropdown (T1).
- [x] Inbox (`govscope::nav.inbox`) reads the `gov-queues` group from the shared per-request snapshot, so each badge counter still runs once per page (T4).
- [x] Contract tests: every rewrite matches upstream once; stock markup survives behind its flag; a moved block is left alone; zones land in place; 9 zone includes.

### Phase 3 — User menu tidy-up ✅ (U3 not done)
- [x] The office switcher moved out of the user menu, which now ends with "My office memberships". "Requested assets" is hidden for super users too (U4, D5).
- [x] Duplicate "Appearance": theming's script injected a second link next to the one the user zone already renders. It now skips when `#menu-gs-theme-appearance` exists (U2). The core Dark-mode toggle stays as a one-click switch.
- [ ] **U3 not done:** relabelling the core "View Assigned Items" and "Accept Assets" would mean overriding core translation keys. "View Assigned Items" already reads well.

### Phase 4 — Search
- [ ] Gov search across items, tags and document numbers (T5). Not started.

### Deploy
`php artisan view:clear` (the layout recompiles with hook v2) and `php artisan gs-theme:build` (bundles `topbar.css` and `topbar.js`).

Snipe-IT's own tests that create assets through `hardware.store` will now be redirected; run them with `GOVSTORE_INTAKE_MODE=open`. They were not run here: `phpunit.xml` has no database override, so they would refresh the development database.

---

## 6. Decisions (2026-10-13)

| # | Question | Decision |
|---|---|---|
| D1 | Should **super admins** also be blocked from direct asset create? | **Yes.** The route guard applies to every user. An emergency override exists only as a deployment config flag, never per user. |
| D2 | Should **licenses** stay creatable until GRN supports them? | **No.** License receipt is added to GRN, and the license create block ships in the same release (§4.6). The block is wired but off until license receipt exists (§5, Phase 1). |
| D3 | Should stock **Import** be blocked? | **Accepted 2026-10-13:** allow stock import only for super admins, into offices whose ledger is not opened yet, as the one-time migration path. Block it after cut-over. See note below. |
| D4 | Should the bar float **on phones**? | **Yes, hide on scroll-down and show on scroll-up** (X/Twitter style). The sidebar is unchanged. |
| D5 | Should "Requested assets" be hidden for super users too? | **Yes.** |
| D6 | Should "Add staff member" stay under ＋ New? | **Yes**, routed through office-membership, not core `users.create`. |

**Note on D3 (correction).** The earlier recommendation ("use opening-balance documents instead of Import") was wrong. An *opening balance* is not something users fill in. `govstore:ledger-open {office}` is a one-time **cut-over command**: it snapshots the quantities **already in Snipe-IT** for that office into an immutable `OB-…` document, and opening documents are view-only afterwards (`DocumentPolicy:18`). After cut-over, every GRN, issue and adjustment needs that opening to exist (`LedgerPostingService:58`). So an office's existing physical stock has to be in Snipe-IT *before* cut-over, and Import is the practical way to load it. Hence the proposal is a migration window, not a ban:

| Stage | Stock import | Direct create | GRN |
|---|---|---|---|
| Office onboarded, ledger **not** opened | ✓ super admin only (migration) | ✗ | ✗ (needs the opening) |
| `govstore:ledger-open` run | — snapshot becomes the `OB` document — | | |
| Ledger opened (normal life) | ✗ | ✗ | ✓ the only way in |

The alternative is to block Import completely, open every office with an empty ledger, and enter existing stock as GRNs with source "existing stock". That gives a fuller audit trail, but means manual entry of every item in large offices.

---

## 7. Snipe-IT core: how each change attaches

**No file under `app/`, `resources/views/`, `routes/` or `config/` (Snipe-IT's) is edited by this plan.** Everything lives in `packages/gov-store/*`:

| Change | Mechanism | Core file on disk |
|---|---|---|
| Replace "Create New", hide icon shortcuts, add office chip / inbox / ＋ New zones, hide "Requested assets" for super users | `NavigationHook` Blade precompiler: rewrites the **compiled** copy in `storage/framework/views` | unchanged; `SnipeItNavContractTest` asserts it |
| Sticky / hide-on-scroll bar, bootstrap-table offset | theming adapter CSS + the `scripts` zone | unchanged |
| GRN-only route guard | middleware pushed onto the `web` and `api` groups from `StoreOperationsServiceProvider` | `routes/*` unchanged |
| Profile grant changes (`self.api`, `*.create`) | `tenant-scope/src/config/permissions.php` (gov config) | unchanged |
| Qty-0 rule for stock records | existing `LedgerStockGuard` observer, registered by the package | unchanged |
| Office picker endpoint, license receipt | package routes, controllers and services | unchanged |

**Existing core edits (outside this plan).** `git diff $(git merge-base HEAD upstream/master) HEAD` shows `layouts/default.blade.php` byte-identical to upstream, but about **25 small gov edits elsewhere in core** from earlier work:

- **Ledger guard calls:** 7 checkout/checkin controllers and 3 API controllers.
- **Office scoping:** `UsesOfficeInventoryBuilder` / `newEloquentBuilder` in 5 models.
- **Transformers:** 3 of them.
- **Boundary-error handling:** `Exceptions/Handler.php`.
- **Scheduler and locale:** `Console/Kernel.php` and `Helper.php`.
- **Ledger-managed qty UI:** 6 views (the read-only qty field and checkin/checkout buttons).
- **Wiring:** `config/app.php` provider list and `composer.json` autoload, which are legitimately needed.

These conflict with the "keep core untouched" rule. They should get their own clean-up plan that moves each one behind a hook. This plan does not add to them.

## 8. Risks

| Risk | Mitigation |
|---|---|
| The sticky bar covers content or table headers somewhere unforeseen | Use a CSS-only toggle that is easy to switch off (`data-gs-sticky-header`). Bootstrap-table gets an offset. Run a visual check on the 10 busiest pages. |
| The intake guard blocks something GRN posting relies on | GRN posting is gated by `gov.can:storeops.documents.post` and creates `Asset` models directly, not through the guarded routes (verified). A test pins this behaviour. |
| An upstream Snipe-IT release renames a guarded route | The guard's route list is checked against `Route::has()` in a test, so a rename fails CI instead of silently reopening the path. |
| An upstream Snipe-IT change moves the Create or shortcut block | Replace and wrap skip when there is no exact single match. The contract test fails in CI. |
| Super admins lose the quick "jump to any office" path | The picker search covers it, and recent offices make repeated switches one click. |
