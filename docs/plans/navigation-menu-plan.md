# Gov-Store Navigation — Investigation & Mitigation Plan

> **Scope:** the gov-store sidebar, user-menu and top-bar entries (`GovStore\TenantScope\Navigation\MenuRegistry` and every package that registers into it) and how they attach to Snipe-IT's `layouts/default`.
> **Status:** Phases 0–3 delivered, Phase 4 partly; bn-BD wording review (D3) pending · **Date:** 2026-10-13
> **Reference model:** `gov-store/theming` (Blade precompiler attachment, zero core edits, contract test). See `docs/plans/dynamic-theming-plan.md`.

---

## 1. Summary

The gov-store menu works, but it has three structural problems:

1. **The language is wrong for one of the two languages.** Most packages translate menu titles once, when the service provider boots. That runs before `CheckLocale` sets the user's language. With `app.locale = bn-BD`, an **en-US user sees 34 of 50 gov items in Bangla**, mixed with English ones. Two packages hard-code English.
2. **The structure follows the packages, not the people using it.** One "Government Store" folder holds **24 items for a super admin**. Daily self-service ("Browse catalog", "My committees") sits beside rare configuration ("Committee type studio", "Shadow-mode report", "Membership overrides"). Each package creates its own top-level folder, so the same concept (catalogue, requests, admin) appears in up to three places.
3. **The Snipe-IT attachment relies on hand edits.** The core layout has **9 hand-placed gov edits**: 6 `@includeIf`, 1 hard `@include`, and 2 hard-coded English links. Gov navigation can only be appended after Snipe-IT's Reports. Theming solved the same problem with a Blade precompiler and zero core edits.

The plan has four phases: (0) quick fixes for translations and dead code, (1) a v2 registry with central groups, zones and pruning, (2) the new task-based information architecture with a reviewed en/bn glossary, (3) a `NavigationHook` precompiler that reverts every core-layout edit.

---

## 2. How it worked before (audit, 2026-10-13)

```
ServiceProvider::boot()  (12 packages)
   └─ MenuRegistry::register([... 'title' => __('pkg::file.key') ...])   ← translated HERE, at boot
                                    │
layouts/default.blade.php  (core, hand-edited)
   ├─ line 1385  @includeIf('govstore::hooks.basket-nav')        top bar
   ├─ line 1426  hard-coded "My Gov-Requests" (English)          user dropdown
   ├─ line 1433  hard-coded "Browse Item Catalog" (English)      user dropdown
   ├─ line 1484  @includeIf('govscope::hooks.my-access')         user dropdown
   ├─ line 1485  @includeIf('govmem::hooks.membership-menu')     user dropdown (office switcher)
   ├─ line 1952  @includeIf('govscope::hooks.sidebar')           ← whole gov tree, appended LAST
   ├─ line 2041  @includeIf('govscope::hooks.rollout')
   ├─ line 2185  @includeIf('govscope::hooks.unified-menu')
   └─ line 2641  @include('govstore::hooks.basket-widget')      ← hard include, not includeIf
                                    │
hooks/sidebar.blade.php → MenuRegistry::tree()  (permission-filter, nest, sort)
                        → hooks/menu-items.blade.php  (renders {{ __($item->title) }})
```

### What a super admin sees today (registered order)

| Root folder (order) | Children |
|---|---|
| **Government Store** (10) | Browse Catalog · Track My Requests · Gov Approvals · Stock Register Dashboard · Staff management · Store Documents Hub · Fulfillment Queue · Fulfillment Register · Category Policies Settings · Committees · Committee registry · Transfer members · Committee type studio · Purpose bindings · My committees · Product Rules Studio · Membership overrides · My access · Access requests · Permission matrix · Shadow-mode report · Access and audit log · Appearance · Theme defaults — **24 items** |
| **Programme Tracking** (35) | Active Initiatives · System Configuration |
| *My office onboarding* (36) | — orphan link at root level, no folder |
| **Global Catalog** (40) | Discover Collections · Catalog Explorer · Universal Search · My Organization Catalog · Collection Library · Governance Registry · Catalog Import |
| **Office Provisioning** (50) | Office Registry · ICT Jurisdictions · Company Admins · My Office Setup · Onboarding queue · Government Directory |
| **Multitenant Administration** (60) | Scoping Dashboard · Policy Configurator · Boundary Explorer · Metadata Health Diagnostics · Theme Lab · Experiment data |

All of this renders **below** Snipe-IT's Dashboard, Assets, Licenses, Accessories, Consumables, Components, Kits, People, Import, Settings, Reports and Requestable items.

---

## 3. Problems & gaps

Severity: **H** = visibly wrong or a risk to users now · **M** = friction or maintainability cost · **L** = polish.

### 3.1 Multilingual

| # | Sev | Problem | Evidence |
|---|---|---|---|
| L1 | **H** | Titles are translated at **boot**, before `CheckLocale` runs. The registry stores the resolved string, and `__($item->title)` in the view is then a no-op. Verified by booting the app: boot locale `bn-BD`, so an en-US user gets Bangla for the tenant-scope, organization, committee, custom-requests, store-operations and theming items, and English for membership, tracking and onboarding. With `app.locale = en-US`, Bangla users get the reverse. | `TenantScopeServiceProvider.php:161`, `OrganizationServiceProvider.php:48+`, `CommitteeServiceProvider.php:51`, `CustomRequestServiceProvider.php:71+`, `StoreOperationsServiceProvider.php:120+`, `ThemingServiceProvider.php:93+` |
| L2 | **H** | Hard-coded English titles with no translation keys: the whole **Global Catalog** folder (8 titles) and **Metadata Health Diagnostics**. | `ClassificationServiceProvider.php:68-155`, `MetadataServiceProvider.php:76` |
| L3 | **H** | Hard-coded English in the **core** layout user dropdown: "My Gov-Requests" and "Browse Item Catalog". | `resources/views/layouts/default.blade.php:1426-1438` |
| L4 | M | Two conventions coexist: store a raw key and translate in the view (correct), or call `__()` at boot (broken). Nothing enforces either. | §2 |
| L5 | M | The Bangla labels are mostly **transliterated English jargon** rather than administrative Bangla: ফুলফিলমেন্ট কিউ, বাউন্ডারি এক্সপ্লোরার, স্কোপিং ড্যাশবোর্ড, কার্যালয় প্রোভিশনিং, মাল্টি-টেন্যান্ট অ্যাডমিনিস্ট্রেশন. Field staff will not recognise them. | bn-BD lang files |
| L6 | M | The English labels are inconsistent and use developer jargon: "Gov Approvals", "Category Policies Settings", "Shadow-mode report", "Multitenant Administration", "Boundary Explorer", "Universal Search", "Company Admins" (a ministry is called a *company* in Snipe-IT). | lang files |
| L7 | L | No test checks that every menu key exists in **both** locales. This audit found no missing keys among the keys that are used, so it is only a regression risk. | `tests/Feature/GovStore` |

### 3.2 Information architecture (human-centred)

| # | Sev | Problem |
|---|---|---|
| A1 | **H** | **"Government Store" is a 24-item catch-all.** It mixes three audiences (every employee, operational roles, configuration admins) and two frequencies (daily vs. rarely). Navigation guidance for a single level is about 7 ± 2 items. |
| A2 | **H** | **Each package defines its own top-level folder** (catalog, tracking, provisioning, multitenant, store). The menu shows the code structure instead of the user's work. |
| A3 | **H** | **The same concept appears in several places.** Catalogue: "Browse Catalog" (requests), "Global Catalog" (classification), "My Organization Catalog", the "Browse Item Catalog" top-bar link, and Snipe-IT's own "Requestable items". Requests: "Track My Requests" (sidebar), "My Gov-Requests" (user menu), and Snipe-IT's "Requested assets". |
| A4 | M | **Personal settings sit in the work sidebar.** My access and Appearance are about the user's own account. Snipe-IT keeps those in the top-right user menu, where My Memberships already is. |
| A5 | M | **Configuration is scattered across 5 folders:** Category Policies, Product Rules Studio, Committee types and purposes (Gov Store), Tracking System Configuration (Tracking), Collection Library, Governance and Import (Catalog), Theme Lab and Metadata Health (Multitenant). |
| A6 | M | **Items in the wrong place:** Metadata Health and Theme Lab are under "Multitenant Administration", and "My office onboarding" is a stray root link (`UserOnboardingServiceProvider.php:44`, no `parent`). |
| A7 | M | **No visible work inbox.** Approvals, fulfilment, onboarding and access requests are all "things waiting for me", but they sit in three folders and show no counts. |
| A8 | M | **Gov work comes last.** In this deployment, the government store is the main job, yet it renders after Snipe-IT's Reports and Settings. |
| A9 | L | **Superadmin office switcher** lists every location that has a profile as a plain dropdown (`membership-menu.blade.php:7`). This does not scale to hundreds of offices and has no search. |

### 3.3 Registry & rendering mechanics

| # | Sev | Problem | Evidence |
|---|---|---|---|
| R1 | **H** | **Folder permissions use the legacy role list while children use abilities.** A folder can hide children the user is allowed to see. For example, an `onboarding.manage` grant without `office_admin`, `ict_officer` or `company_admin` loses the Onboarding queue under `gov-org`. A folder can also render **empty**: `gov-catalog-root` gates on `catalog.view`, but a child's ability may not be granted. The tree is never pruned. | `MenuRegistry::tree()`, `OrganizationServiceProvider.php:52`, `TrackingServiceProvider.php:143` |
| R2 | M | **Declared `permission` is silently overridden by the route's `gov.can:` middleware.** Of the 41 items whose route carries `gov.can:`, 24 declare a different value from the one actually used (2 more declare none) (for example `storekeeper` → `storeops.documents.view`). The declaration is dead and misleading. The override itself is correct: `G1AuthorizationTest` relies on menu = gate. | measured via route table |
| R3 | M | **Inconsistent registration lifecycle:** plain `boot()`, `booted()` callbacks (classification, onboarding), and a **per-user `View::composer('*')`** that mutates the singleton (experimentation, `ExperimentationServiceProvider.php:50`). The composer is order-dependent and unsafe under Octane or queue workers. | |
| R4 | M | **Inconsistent duplicate handling:** the registry throws, but theming wraps registration in `catch (Throwable)` and would hide any real error. | `ThemingServiceProvider.php:122` |
| R5 | M | **Icon colours are hard-coded** (`text-aqua`, `text-red`, `text-orange`, `text-green`, `text-purple`, `text-yellow`). The theming adapter neutralises them only on **top-level** links (`adapter/snipeit.css:170-173`), so nested items keep AdminLTE colours that ignore the theme and fail dark-mode contrast. `fa-fw` is applied inconsistently. | |
| R6 | L | **Active-state matching is per item.** Committee detail pages (`committee.show`, etc.) do not highlight their entry. There is no `aria-current` or `aria-expanded`. | `menu-items.blade.php` |
| R7 | L | **Order collisions:** five Access items share `order => 95`, and Rules Studio and Overrides share 90. Order then depends on which provider boots first. | |
| R8 | L | **Per-render cost:** `project_member` qualifiers run a DB query **per item** (tracking root and initiatives = 2 queries on every page). | `GovAccess.php:117` |
| R9 | L | **Dead code:** `hooks/menu-injection.blade.php` appends with jQuery to a route (`gov.scope.index`) that no longer exists and is never included. The `sidebar_tenant_scoping` lang key is used only there. | |

### 3.4 Snipe-IT integration

| # | Sev | Problem |
|---|---|---|
| S1 | **H** | **9 hand edits in core `layouts/default.blade.php`.** Every upstream Snipe-IT merge can conflict with or silently drop them, and no test would notice (theming has `SnipeItContractTest`; navigation has nothing). |
| S2 | **H** | **Hard dependencies:** the core layout calls `route('gov.requests.user.index')`, `route('gov.requests.catalog')` and `@include('govstore::hooks.basket-widget')` (not `includeIf`). Disabling `custom-requests` breaks every page. |
| S3 | M | **Only one insertion point** (the end of the sidebar). The registry has `order` but no *zone*, so a package cannot place items in the user menu, the top bar, or before core Asset management. |
| S4 | M | **Two parallel request flows are both visible:** Snipe-IT's Requestable / Requested assets and gov-store's Catalog / My requests. Users must guess which one to use. *(Product decision, see D2.)* |

---

## 4. Target design

### 4.1 Principles

1. **Organise by task and audience, not by package.** Packages contribute *items*. The *groups* are defined once, centrally.
2. **Order by frequency and audience:** my work, then my queues, then operations, then administration, then system tools.
3. **Use at most 2 levels** (AdminLTE 2 treeview) and **at most 7 items per group**. Separate the operational and administrative zones with `li.header` section labels.
4. **Show what the user can do and nothing else.** Prune empty groups, and render a group with one visible child as a direct link.
5. **Personal settings go in the user menu.** Global actions (basket) go in the top bar.
6. **Translate every label at render time** from a key, with a reviewed en-US and bn-BD glossary in plain administrative language.
7. **Make no core edits.** Attach with a precompiler and protect the anchors with a contract test, as theming does.

### 4.2 Proposed information architecture

The English labels below are proposals. The Bangla labels are **drafts that must be reviewed by domain users** (decision D3).

**Sidebar, work zone (after Snipe-IT Dashboard, before Assets)**

Section header: **Government Store / সরকারি স্টোর**

| Group | Who | Items (current id → new label) |
|---|---|---|
| **My workspace** / আমার কাজ | every employee | `gov-requests-catalog` → Request items · `gov-requests-my-requests` → My requests · `committee-mine` → My committees · `gov-onboard-mine` → My office onboarding |
| **Waiting for me** / অপেক্ষমাণ কাজ *(count badges)* | approver, storekeeper, office admin | `gov-requests-approvals` → Approvals · `gov-requests-fulfillment-queue` → To fulfil · `gov-onboard-queue` → Onboarding requests · `gov-access-requests` → Access requests |
| **Store** / স্টোর পরিচালনা | storekeeper | `storeops-hub` → Receipts & issues · `storeops-register` → Stock register · `gov-requests-fulfillment-register` → Fulfilment register · `storeops-my-catalog` → Office catalogue |
| **Catalogue** / পণ্য ক্যাটালগ | `catalog.view` | `gov-catalog-search` → Search · `gov-catalog-collections` → Collections · `gov-catalog-explorer` → Browse by category |
| **Committees** / কমিটি | `committee.view` | `committee` → Overview · `committee-registry` → Committee register · `committee-transfers` → Member transfers |
| **Programme tracking** / কর্মসূচি পর্যবেক্ষণ | project members, admins | `gov-tracking-initiatives` → Initiatives |

**Sidebar, admin zone (after Snipe-IT Settings and Reports)**

Section header: **Administration / প্রশাসন**

| Group | Who | Items |
|---|---|---|
| **Offices & staff** / কার্যালয় ও জনবল | office, company and ICT admins | `gov-org-setup` → My office setup · `govmem-staff` → Staff · `gov-org-registry` → Office registry · `gov-org-company-admins` → Ministry administrators · `gov-org-jurisdictions` → ICT jurisdictions · `gov-org-directory` → Government directory import |
| **Rules & settings** / নীতিমালা ও সেটিংস | configuration admins | `gov-requests-policies` → Request policies · `storeops-admin-rules` → Product rules · `committee-types` → Committee types · `committee-purposes` → Committee purposes · `gov-tracking-config` → Tracking settings · `gs-theme-assignments` → Theme defaults |
| **Catalogue master data** / ক্যাটালগ মাস্টার ডেটা | `catalog.master.manage` | `gov-catalog-builder` → Collection library · `gov-catalog-governance` → Governance · `gov-catalog-import` → Import |
| **Access & security** / অনুমতি ও নিরাপত্তা | access and tenant admins | `gov-access-matrix` → Permission matrix · `gov-access-audit` → Audit log · `gov-access-shadow` → Enforcement preview · `govmem-override` → Membership overrides · `gov-tenantscope-dashboard` → Tenant boundaries · `gov-tenantscope-config` → Boundary policy · `gov-tenantscope-mappings` → Boundary explorer |
| **System tools** / সিস্টেম টুলস | super admin | `gov-metadata-health` → Metadata health · `gs-theme-lab` → Theme lab · `gov-experiments` → Experiment data |

**User menu zone (top-right dropdown, before Logout)**

`gov-access-my_access` → My access · `gs-theme-appearance` → Appearance · My office memberships · **Switch office** (searchable select2 for super admins, fixing A9)

**Top-bar zone:** request basket (existing `basket-nav`).

**Resulting experience by role** (with pruning and single-child collapse):

| Role | Today | Proposed |
|---|---|---|
| Employee | "Government Store" folder with 3–4 items, plus duplicates in the user menu | "My workspace" with 4 items, no duplicates |
| Storekeeper | One mixed folder of about 10 items plus a separate Catalog folder | My workspace · **To fulfil** (direct link) · Store (4) · Catalogue (3) |
| Office admin | Gov Store (mixed) plus Office Provisioning | Waiting for me · Offices & staff — work and administration clearly separated |
| Super admin | 6 folders, one of them with 24 items | 6 work groups + 5 admin groups, none larger than 7 |

### 4.3 Registry v2 (tenant-scope)

Each package registers **leaves only**. The **group skeleton** is declared once in `tenant-scope/config/govstore-navigation.php`:

```php
'groups' => [
    'gov-workspace' => ['zone' => 'work',  'order' => 10, 'label' => 'tenantops::nav.workspace', 'icon' => 'fas fa-user-check'],
    'gov-queues'    => ['zone' => 'work',  'order' => 20, 'label' => 'tenantops::nav.queues',    'icon' => 'fas fa-inbox'],
    // …
    'gov-system'    => ['zone' => 'admin', 'order' => 90, 'label' => 'tenantops::nav.system',    'icon' => 'fas fa-toolbox'],
],
```

```php
// package side: a leaf only, and a translation KEY
$menus->register([
    'id' => 'gov-requests-approvals', 'group' => 'gov-queues', 'order' => 10,
    'label' => 'requestlabels::requests.nav_approvals', 'icon' => 'fas fa-clipboard-check',
    'route' => 'gov.requests.admin.index', 'match' => ['gov.requests.admin.*'],
    'badge' => fn () => app(ApprovalQueue::class)->pendingCount(),   // lazy, only when visible
]);
```

| Change | Fixes |
|---|---|
| `label` must be a translation key. `register()` rejects strings without `::`. `MenuItem::label()` calls `__()` at render time. | L1, L2, L4 |
| `group` must reference a configured group. `zone` comes from the group (`work`, `admin`, `user`, `topbar`). | A2, A5, S3 |
| No `permission` on routed items: it is **derived** from `gov.can:`, keeping the G1 invariant. `permission` is allowed only on route-less leaves, and `register()` throws if a routed item declares one. | R2 |
| Groups have **no permission**. A group is visible only when at least one child is visible, and a group with one child renders as that child. | R1, A1 |
| `match` defaults to `<route-prefix>.*`. The view emits `aria-current="page"` and `aria-expanded`. | R6 |
| Optional lazy `badge` closure, evaluated only for visible items and memoised per request. | A7 |
| `visible` closure for feature flags. Experimentation and theme-lab flags move into it, and the per-user `View::composer` is removed. | R3 |
| Role and designation qualifiers are memoised per request. | R8 |
| Duplicate ids throw everywhere (remove theming's `catch (Throwable)`). Equal `order` values in the same group are allowed but sorted by id, so the result is deterministic. | R4, R7 |
| Icons are validated: `fa-fw` is added automatically and colour utility classes (`text-*`) are stripped, so the theme owns colour. | R5 |

### 4.4 Snipe-IT attachment: `NavigationHook` (same pattern as theming)

`GovStore\TenantScope\Compiler\NavigationHook` is a `Blade::precompiler` that runs when `layouts/default` compiles. It is cached and costs nothing per request. It injects zone renderers at stable anchors:

| Zone | Anchor in core layout | Inserted |
|---|---|---|
| `work` | after the Dashboard `<li …firstnav>` block, before `@can('index', \App\Models\Asset::class)` | `@include('govscope::nav.zone', ['zone' => 'work'])` |
| `admin` | before the closing `</ul>` of `ul.sidebar-menu` | `…['zone' => 'admin']` |
| `user` | before the `<li class="divider"></li>` that precedes Logout | `…['zone' => 'user']` |
| `topbar` | before the user-menu `<li class="dropdown user user-menu">` | `…['zone' => 'topbar']` |
| scripts | before `</body>` | `rollout`, `unified-menu`, `basket-widget` (all `includeIf`) |

- It uses a version marker (`gs-nav-hook:v1`) like `LayoutHook::VERSION`. It is idempotent and compatible with theming's hook, because both use separate markers.
- **A missing anchor skips only that zone, and the page still renders.** A new `tests/Feature/GovStore/Navigation/SnipeItNavContractTest.php` fails loudly after an upstream merge that moves an anchor.
- After this ships, **revert all 9 gov edits** in `resources/views/layouts/default.blade.php`. The core layout goes back to upstream (S1, S2, L3).
- Markup stays AdminLTE-native (`treeview`, `li.header`), so theming's `data-gs-nav-style` variants keep working unchanged.

---

## 5. Implementation phases

All decisions in §6 were accepted as recommended on 2026-10-13. Phases 0–3 are delivered, plus two of the three Phase 4 items. The work is verified by 322 passing gov-store tests and by real pages rendered for an employee, storekeeper, approver and super admin in en-US and bn-BD. Differences from the plan are marked **Changed**.

### Phase 0 — Quick fixes ✅
- [x] Boot-time `__()` removed from every menu registration. Labels are keys, translated at render time by `MenuItem::label()` (L1).
- [x] `nav_*` keys for classification (`texts.php`) and metadata (new `govmeta` lang files), in en-US and bn-BD (L2).
- [x] The hard-coded English user-menu links are gone along with every other core-layout edit (Phase 3) (L3, A3).
- [x] The basket widget is a `body-end` zone item rendered with `@includeIf` (S2).
- [x] `gov-onboard-mine` now lives in My workspace (A6).
- [x] `hooks/menu-injection.blade.php`, `hooks/menu-items.blade.php`, `hooks/sidebar.blade.php`, `hooks/my-access.blade.php` and the 13 lang keys only they used are deleted (R9).
- [x] **Changed:** the translation guard is `tests/Feature/GovStore/Navigation/NavigationRegistryTest.php` (every label is a key, exists in both locales, differs between them, and bn-BD contains Bengali), plus `TenantScopeTest::test_real_navigation_renders_entirely_in_the_users_language` (L7).

### Phase 1 — Registry v2 ✅
- [x] `tenant-scope/src/config/navigation.php` (`govstore-navigation`) defines the groups and zones. `MenuItem` supports `group`, `zone`, `label`, `match`, `badge`, `visible` and `view`.
- [x] Empty groups are pruned and single-child groups collapse. Visibility comes from the route's `gov.can:` ability. All zones of a page share one permission snapshot (`MenuRegistry::zone()`, per request).
- [x] **Changed:** there is no `trigger_deprecation` shim. Legacy `parent`, `title` and `active_patterns` are still accepted silently, because `G1AuthorizationTest` registers that way, and every package has already migrated. Unknown groups throw.
- [x] **Changed:** a routed item that declares a `permission` does not throw at runtime, because routes may not be loaded yet when providers boot. `NavigationRegistryTest` fails instead.
- [x] Tests: pruning, collapse, deterministic order, icon normalisation, locale, badges, the shared snapshot, and a failing badge counter that leaves the page intact.

### Phase 2 — Information architecture & glossary ✅ (bn-BD review pending, D3)
- [x] All 12 packages register leaves into the §4.2 groups. My access, Appearance and the office switcher are in the `user` zone.
- [x] `tenant-scope/src/resources/lang/{en-US,bn-BD}/nav.php` holds zone and group labels. Packages own their `nav_*` labels.
- [x] Dead `permission` declarations on routed items are removed. Theming, tracking, metadata and experimentation routes have no `gov.can:`, so they keep theirs.
- [x] The approval and fulfilment queues share one `RequestQueues` service between page and badge.
- [x] **Changed labels:** "Staff management" and "Access and audit log" keep their existing wording because the breadcrumbs use the same keys. The tenant-scope pages are "Data boundaries / Boundary policy / Boundary explorer".
- [ ] **bn-BD wording review with 2–3 real users (D3).** Drafts are marked in `nav.php`.

### Phase 3 — Precompiler attachment ✅
- [x] `GovStore\TenantScope\Compiler\NavigationHook` and `govscope::nav.zone` / `govscope::nav.items`.
- [x] `tests/Feature/GovStore/Navigation/SnipeItNavContractTest.php`: each anchor matches upstream exactly once, zones land in the intended places, the hook coexists with theming's `LayoutHook`, it is idempotent and degrades gracefully, and the core layout carries no GovStore edits.
- [x] `resources/views/layouts/default.blade.php` is restored **byte-for-byte** to the upstream merge-base (`git diff $(git merge-base HEAD upstream/master) -- resources/views/layouts/` is empty).
- [x] Live check: pages rendered through the HTTP kernel as each role in both locales. Active state, `aria-current`, badges, basket, rollout banner and inline-denial script are all present. Dark mode is handled by theming's existing `li.header` and sidebar rules; no colours were added.

### Phase 4 — Enhancements
- [x] Count badges on "Waiting for me" (Approvals, To fulfil). They are lazy and computed once per render. **Changed:** no 60 s cache, because the counts must reflect revocation immediately and each is a single indexed `count()`.
- [x] **Changed:** instead of select2, the switcher has a type-to-filter box and a scrolling list once a user has more than 8 offices (A9). It needs no endpoint and works without JavaScript (the list simply isn't filtered).
- [ ] Current-office indicator at the top of the sidebar. Not done; the user menu already marks the working office with `aria-current`.

### Adding a menu entry (developers)

```php
// In your package's ServiceProvider::boot()
app(\GovStore\TenantScope\Navigation\MenuRegistry::class)->register([
    'id' => 'mypkg-thing', 'group' => 'gov-store-ops', 'order' => 50,   // a group from config/navigation.php
    'label' => 'mypkg::file.nav_thing',                                  // a KEY, never __('…')
    'icon' => 'fas fa-box', 'route' => 'mypkg.thing.index',              // visibility = the route's gov.can: ability
    'match' => ['mypkg.thing.*'],                                        // keeps the entry active on sub-pages
]);
```

Add `nav_thing` to both `en-US` and `bn-BD`. Personal entries use `'zone' => 'user'`, and page slots use `'view' => 'pkg::hooks.x'` with zone `topbar`, `content`, `scripts` or `body-end`. `php artisan test tests/Feature/GovStore/Navigation` checks all of this.

**Deploy:** run `php artisan view:clear` (part of `optimize:clear`) so the layout recompiles with the hook.

---

## 6. Decisions (accepted 2026-10-13)

| # | Question | Decision |
|---|---|---|
| D1 | Where does the gov **work** zone go: before Snipe-IT's Assets, or after Reports as now? | **Before Assets**, after the Dashboard, with "Government Store" and "Asset management" headers. |
| D2 | Snipe-IT's own *Requestable items* and *Requested assets* duplicate the gov request flow. | **Hidden for gov users.** `assets.view.requestable` was removed from the `employee` capability profile (`tenant-scope/src/config/permissions.php`). Because `SnipePermissionAdapter` replaces native group grants, that profile is the only place to restore it. Super users still see it. |
| D3 | Who signs off on the bn-BD glossary? | A short review with store and office staff. **Pending.** |
| D4 | Should Snipe-IT's own **Settings**, **People** and **Import** move under the gov "Administration" header? | **No.** The admin zone sits directly after them. |

## 7. Risks

| Risk | Mitigation |
|---|---|
| An upstream Snipe-IT change moves an anchor | Only that zone is skipped and the page still renders. The contract test fails in CI. |
| Renamed labels confuse existing users | Phase 2 ships with a one-release "menu moved" notice through the existing rollout hook. Route URLs do not change. |
| Badge counts add query cost | Badges are lazy (visible items only), memoised, and cached per user and office. |
| Packages migrate at different speeds | The Phase 1 compatibility shim accepts both old and new definitions. |
