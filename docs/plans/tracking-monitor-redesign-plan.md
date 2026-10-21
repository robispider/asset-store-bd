# Gov-Store Tracking — Programme Monitor, Code and Office Views (Design Reference)

> **Scope:** new read-only monitoring screens for `packages/gov-store/tracking`: the programme monitor (replaces the
> initiative workspace as the landing view), a per-code dashboard, and a scope-aware office view at code, programme
> and all-programme level. Also the defects in today's workspace that make its numbers wrong.
> **Status:** implemented on `codex/tracking-monitor-redesign`; local verification recorded below; external acceptance items remain · **Updated:** 2026-10-19
> **Design artifact:** https://claude.ai/artifact/3Qj47v3E28B8gZqroHDeLU (private to the owner until shared) ·
> snapshot in [`tracking-monitor-redesign/`](tracking-monitor-redesign/README.md)
> **Build prompt for an agent:** [`tracking-monitor-redesign/BUILD-PROMPT.md`](tracking-monitor-redesign/BUILD-PROMPT.md)
> **Implementation plan:** [`tracking-monitor-redesign/IMPLEMENTATION-PLAN.md`](tracking-monitor-redesign/IMPLEMENTATION-PLAN.md)
> **Owner decisions (2026-10-18):** §11 overrides conflicting behaviour in the saved prototype. Programme access is
> required to see a programme's deliveries, even when the viewer has access to the receiving office.
> **Reference model:** `docs/plans/dynamic-theming-plan.md` (tokens, `<x-gs::*>` kit, compliance ratchet),
> `docs/plans/organization-ux-redesign-plan.md` (how a package redesign is phased), `AGENTS.md`,
> `docs/security/govstore-agent-security.md`, `docs/testing/local-live-testing.md`.

---

## 1. Summary

Programme tracking can record initiatives, tracking codes, targets, office allocations and the goods receipts linked to
them. Nobody can **monitor** a programme with it. The workspace (`initiatives/{id}`) is an editing console: it lists
codes as cards, shows one headline that adds drafts and unlike units together (`2 / 968 items`), and has no notion of
time, pace, office or price. The `MONITOR` designation exists in the operation unit, but no screen is made for it.

The redesign adds three read-only views, all built on the GovStore theme contract:

| View | Question it answers | Proposed route (under `/gov-store/admin/tracking`) |
|---|---|---|
| **Programme monitor** | Is the programme on track, and what should I chase today? | `GET initiatives/{initiative}` (landing view; existing editing tools remain in a "Manage" tab) |
| **Code dashboard** | What is happening with this code, where, and who do I call? | `GET initiatives/{initiative}/tracking-codes/{trackingCode}` (the resource `show` action, excluded today) |
| **Office view** | What has this office received, under which programmes and codes? | `GET initiatives/{initiative}/offices/{location}` · `GET initiatives/{initiative}/tracking-codes/{trackingCode}/offices/{location}` · **phase 2:** `GET offices/{location}` and `GET offices` |

Every view applies the same two scope keys (§6). Nothing here writes data.

---

## 2. Audit of the current package (2026-10-18)

Mapped from `routes/web.php`, `routes/api.php`, the controllers and views, then walked on the local preview
(`127.0.0.1:8010`) as **mithun** (Operation Officer on ICTD Lab, working office = head office, location 4).

### 2.1 Route map

| Screen / endpoint | Method · path | Route name (`gov.tracking.` prefix) | Reached from | As mithun | Verdict |
|---|---|---|---|---|---|
| Programme list | GET `initiatives` | `initiatives.index` | Sidebar | 200 | Live, linked |
| Start programme | GET `initiatives/create`, POST `initiatives` | `initiatives.create/.store` | List button | 200, 403 on save | Shown, then blocked |
| Workspace | GET `initiatives/{id}` | `initiatives.show` | List card | 200 | Works, wrong data (D1, D2) |
| Edit properties | GET `initiatives/{id}/edit`, PUT, DELETE | `initiatives.edit/.update/.destroy` | Workspace, list card | 403 | Shown, then blocked |
| Progress report | GET `initiatives/{id}/report` | `initiatives.report` | Workspace | 200 | Works, wrong data (D1, D3) |
| Operation unit | GET/POST/DELETE `initiatives/{id}/operation-unit` | `initiatives.operation-unit.*` | Workspace | 200 | Live, linked |
| User search | GET `initiatives/operation-unit/search-users` | `operation-unit.search-users` | Team page AJAX | redirect w/o params | Internal |
| New / edit / delete code | `initiatives/{id}/tracking-codes/...` | `initiatives.tracking-codes.*` | Workspace | 200; edit 409 when active | Live, linked |
| Activate / archive | POST `.../{code}/activate`, `.../{code}/archive` | `.activate/.archive` | Code card | — | Works, wrong label (D5) |
| **Code detail** | GET `initiatives/{id}/tracking-codes/{code}` | (`show` excluded) | — | 405 | **Not built** |
| Order PDF | GET `tracking-codes/{code}/download` | `tracking-codes.download` | Code card | 200 | Live, linked |
| Code sheet | GET `tracking-codes/{code}/view-task` | `tracking-codes.view-task` | Only the GRN `verify-code` JSON | 200 | **Built, no way in**; shows only the viewer's own office allocation |
| Code documents | POST/GET/DELETE `.../{code}/documents/{doc}` | `documents.*` | Edit page of DRAFT codes | — | **Built, no way in** once active |
| Uniqueness check | GET `tracking-codes/check-uniqueness` | `tracking-codes.check-uniqueness` | Code form AJAX | 200 | Internal |
| Retrospective console | GET/POST `initiatives/{id}/retrospective` | `initiatives.retrospective.*` | **Nothing links to it** | 200 | **Built, no way in** |
| Funding types | `funding-types` | `funding-types.*` | Rules & settings (admin) | 403 | Live, admin only |
| API verify code | GET `/gov-store/api/tracking/verify-code` | `api.verify-code` | store-operations GRN form | 403 (office out of scope) | Internal |
| API evaluate | GET `/gov-store/api/tracking/evaluate` | `api.evaluate` | **No caller found** | 403 | Built, unused |
| API search offices | GET `/gov-store/api/tracking/search-offices` | `api.search-offices` | Matrix grid | 200 | Internal |

### 2.2 Defects (fix before or with the new views)

| # | Defect | Evidence | Fix |
|---|---|---|---|
| D1 | Workspace and report totals add drafts and unlike units | Header `2 / 968 items`: 968 includes `ictd-lab-01` and `ictd-203` (DRAFT) and sums computers, reams and tissue rolls. Report "Computer planned 214" includes 5 from a draft. | Count ACTIVE codes only. Use the fulfilment measure in §5. |
| D2 | One receipt counted twice in the fact table | GRN 4324324: `gov_tracking_associations` = 2 reams on code `ictd`; `gov_tracking_fact_deliveries` = 4 reams, `transaction_count` 2; `gov_tracking_timeline` has the event twice. Snapshot card (4, ৳2,000) disagrees with the progress bar (2). | `TrackingWorkflowTest::test_receipt_delivery_replay_updates_facts_associations_and_timeline_once` exists, so this row may predate the replay guard. Confirm, then rebuild facts from associations with a new one-off command (`govstore:rebuild-projections` only rebuilds the lifecycle cache, not `gov_tracking_fact_deliveries`). Read monitor figures from associations until confirmed. |
| D3 | "Unknown category" for real categories | Targets for Facial tissues (12) and Toilet tissue (13) render as `অজানা বিভাগ`. Rows exist, not soft-deleted. Views lift only `withTrashed()`; the tenant category scope hides categories the viewer's office has not enabled. | Resolve target category names with `withoutGlobalScopes()` (name only, read-only) or a tracking-safe lookup. |
| D4 | Buttons a user cannot use | Officers see "Edit general properties" (403) and "Start new initiative" (403 on save). | Render by the same rule the controller enforces (`TrackingAuthorizationService` / company-admin check). |
| D5 | Archive labelled as save in Bangla | `initiatives.tracking-codes.archive` button reads `কাজ সংরক্ষণ করুন`. | Change the bn-BD string (e.g. `কাজ আর্কাইভ করুন`); review with the bn copy owner. |
| D6 | Stage figures never fill in | `EloquentTrackingProjectionRepository::getLifecycleSummary()` always returns `ordered = 0`; `deployed`/`disposed` are computed but not shown. | Show in-use / in-store on the office view (§4.3); drop or implement `ordered`. |
| D7 | Office without an area | Atrai upazila office (location 3) has no `gov_location_profiles.geo_area_id`; it drops out of `distinct_geo_areas`. | Surface it as a "Note" attention item; data fix in Organization. |
| D8 | Theme chart palettes fail colour-blind checks | institutional-green `--gs-chart-1…6` fails lightness, chroma and CVD separation (dataviz validator). | Do not use categorical chart colour. One hue (`--gs-color-primary`) + status tokens with icon and label. |

---

## 3. Who it is for and design principles

**Primary user: the programme monitor.** Someone at head office or the ministry (operation head, `MONITOR`
designation, company admin) who checks several programmes a week, does not enter data, and needs to know where to look
before phoning an office. **Secondary:** office storekeepers and district ICT officers looking at their own offices.

1. **Verdict first.** Every page opens with a one-sentence status, a named next step and, for codes, a forecast.
2. **Time is visible.** Progress is always shown against an expected pace and the 30 June year end.
3. **Status is never colour alone.** Chips carry an icon and a word; bars carry their number.
4. **One click to detail, keep your place.** Codes and offices have their own pages with shareable URLs; browser back
   works; previous/next walks through codes.
5. **Show only what the viewer may see, and say why.** A "Why you can see this" panel on office pages; locked levels
   show a lock and the reason; programmes the viewer has no key for are not shown or counted.
6. **Design the early and empty states.** A programme with one receipt still tells the monitor something useful.
7. **Bangla office names stay visible** next to the English place name; every string is a translation key.
8. **Phone width works** (400 px): tables scroll inside their frame, layouts stack.

---

## 4. Screen specifications

The artifact is the visual source of truth. Section names below match it. All figures use ACTIVE codes unless stated.

### 4.1 Programme monitor (`initiatives/{id}`)

| Block | Content | Notes |
|---|---|---|
| Header | Title, status chip, funding chip (`ADP · GoB (Taka)`), FY, code count, owner (bn + en), team avatars (head filled) | `<x-gs::page-header>` + `<x-gs::status-badge>` |
| Filter row | Show: All codes / Active / Drafts · Plan: Any / By office / By item / Open · search (code, title, item, office en/bn) | `<x-gs::filter-bar>`; filters change every block |
| KPI strip (4) | **Delivered against plan** % with pace bar + expected tick, "N pts ahead/behind (X% by today)" · **Value received** ৳ lakh/crore + cumulative sparkline, GRN and supplier counts · **Offices reached** x of y, "N allocated offices still waiting" · **Tracking codes** active · draft, health mix bar + legend | `<x-gs::kpi>` (has `spark`, `progress`, `caption`); pace tick is new (§7) |
| Needs attention | Ranked list: critical (over allocation, data integrity) → serious (stalled) → warning (behind, waiting office, draft) → info (missing economic code, office without area). Each item: severity stripe, icon, title, one-line detail, **Open** → code or office page | New `attention-list` |
| Delivery through the FY | Line chart Jul–Jun: delivered share (area), expected pace (dashed, from activation to 30 Jun), "Today" marker, hover crosshair | Single hue |
| Tracking codes table | Code (mono) + title + items · Planned (By office / By item / Open) · Status chip · Delivered vs expected (pace bar + %) · Received / planned (unit) · Value · Offices · Last receipt (date + "N days ago") · trend sparkline. Sortable; default sort = status severity; row → code page | `<x-gs::table>` + pace bar |
| Where it is happening | Schematic tile map of upazila offices (positions from a small JSON per district), fill = share of allocation received (sequential ramp from primary), hatched = not in plan, alert flag = waiting/over; off-map tiles for HQ and district office. Beside it: offices ranked by items received with share | New `tile-map` |
| What is being bought | Per item: name, type (Asset/Consumable), economic code(s) (warn if missing), codes, received/planned bar + %, value, supplier count; "Planned only in drafts" note. Suppliers by value; manufacturers as chips | |
| Recent activity | GRN receipts, activations, documents, newest first | `<x-gs::timeline>` |

### 4.2 Code dashboard (`initiatives/{id}/tracking-codes/{code}`)

| Block | Content |
|---|---|
| Header | Breadcrumb (programme / Tracking codes / code), code in mono with **Copy**, title, chips (status, plan type, FY, funding, item lines). Right: ← previous · jump list (code · status) · next →, **Code sheet** (existing `view-task`), **Order PDF** (if any), **Activate** (existing head/company-admin/superuser authorization, drafts only; current server policy does not grant officers activation) |
| Status panel | Severity stripe + icon, one-sentence verdict, **Next step** naming a person/office, **Forecast** finish date at current rate or "Misses 30 June" (§5) |
| KPI strip (4) | Delivered vs expected · Received of planned (+ "N still to arrive" or "N over plan", GRN count) · Value (+ average unit price) · Allocated offices started x of y (matrix) or Offices receiving (category) |
| Receipts over time | Cumulative received units (step line), expected pace line to plan, plan ceiling with label, dots per GRN (hover: GRN, date, office, value, running total). X axis = activation → 30 June so the space on the right is time left. Draft: explanatory empty state |
| Lifecycle | Stepper: created, activated, first receipt, latest receipt, today (days since last movement), FY end (days left). Rules: geography, who can receive, over-receiving (warns/blocks from `allow_overshoot`), order document required (`require_documents`) |
| Offices | Matrix codes: office × item cells "received / allocated" (ramp fill, red when over), status per office (Over allocation → Waiting → In progress → Complete), last receipt; category codes: offices that received, "no office target". Mini tile map of this code. Row → office view at code level |
| Items and prices paid | Per item: plan bar, economic code, value; **price strip**: one dot per GRN at unit price, dashed average, min/max, "spread N% of average" |
| Goods receipts | Date, GRN, office, item, qty, unit price, value, supplier · manufacturer. Suppliers by value, manufacturer chips |
| Documents | Order PDF and supporting documents, visible for the life of the code (today only on the DRAFT edit page) |
| Who to contact | Operation head (signs off activation and allocation changes), officers |

### 4.3 Office view (`…/offices/{location}`)

One page, three levels chosen by a **Show** bar: **All programmes** (badge *Phase 2*) · one button per visible
programme · **Code** select within the chosen programme. Opening from the monitor lands at programme level; from a code
page at code level; from the Offices directory at all-programmes level.

| Block | Content |
|---|---|
| Header | Office (en), full bn name, level (Head office / District / Upazila), area chip, area verified / not verified, department |
| Why you can see this | Bullets: office reason; per visible programme "whole programme because …"; asset list shown/hidden and why. Office access alone does not grant programme visibility. A note when the requested level was not allowed and the page fell back |
| KPI strip | Programmes here (all) or Codes here · Allocation received % (done / allocated) · Items received + GRN count + last date · Value received |
| Programmes delivering here (all level) | Card per programme with programme access: owner, status, access chip (Whole programme), allocation bar, items, value, codes, last receipt, **Open at programme level**, **Programme dashboard**. Programmes without programme access are omitted entirely |
| Codes at this office | Code, programme (all level), items, allocated, received, status here (Over / Waiting / In progress / Complete / No office target / Draft), value, last receipt, **Code page** link (only with the programme key). Row narrows to code level |
| Items received here | Per item: received of allocated with % bar, or "N received · no office target" (never one bar for unlike units) |
| Receipts by month | Bars Jul–Jun, value (axis in ৳ k / L), hover with receipt count |
| Compared with other offices | Programme/code level only: dot strip of allocation share (or items when no allocation), this office highlighted, "4th of 6 offices". Only offices the viewer can see; note when filtered. All level: prompt to pick a programme |
| Assets from these codes | Received / in use / in store per asset item (from `Asset.assigned_to` and status, via associations). Locked card without inventory access at that office |
| Goods receipts at this office | Date, GRN, code, programme (all level), item, qty, value, supplier |
| People | Storekeeper, office admin, primary approver (from office responsibilities) |
| Denied state | "You cannot open {office}" + reason + buttons to offices the viewer can see |

### 4.4 Offices directory (`GET offices`, phase 2)

Every office in the viewer's office scope: office (en + bn), level, programmes here (visible only), active codes,
allocation received bar, items, value, last receipt, Needs (Over allocation / Waiting / No area). Sorted by needs, then
items. A viewer whose office scope is only their working office (programme team members) also sees "You see more
offices through your programmes: …" with links.

---

## 5. Metric definitions (implement exactly; unit-test each)

- **Line fulfilment** for a target: `min(received, planned) / planned`. For office-planned (MATRIX) targets, cap each
  office cell at its allocation first: `Σ min(cell_received, cell_allocated) / Σ cell_allocated`, so an over-delivery at
  one office never hides a shortfall at another. Received = `SUM(gov_tracking_associations.quantity)` where
  `status = 'ACTIVE'`.
- **Code delivered %** = mean of its targets' line fulfilment. **Programme delivered %** = mean over all ACTIVE targets
  (each item line counts equally).
- **Expected** for a code = `clamp((today − activated_at) / (fy_end − activated_at), 0, 1)`. Programme expected = mean of
  its targets' code expectations. FY = 1 July – 30 June. *Activation date is not stored today* (§8, B3).
- **Status** (first match): DRAFT → **Draft**; any cell or target received > allocated/planned → **Over allocation**;
  ACTIVE and no receipt for > 30 days (or none since activation > 30 days) → **Stalled**; delivered < expected − 5 pts
  → **Behind**; else **On pace**. Show the rule on hover.
- **Forecast** = today + `(1 − delivered) / (delivered / days_since_activation)` days; none when delivered is 0; "Misses
  30 June" when past FY end.
- **Office state** (per code at an office): Draft · Over allocation · No office target (no allocation) · Complete
  (done ≥ allocated) · In progress (some received) · Waiting (allocated, nothing of it received).
- **Waiting office** = allocated > 0 and Σ min(received, allocated) = 0 (an office that got paper under a category code
  but none of its allocated computers is still waiting).
- **Value** = Σ GRN line value of linked receipts. **Average unit price** = value / quantity. **Price spread** =
  (max − min) / average.
- **Money format:** `৳` with Indian grouping; ≥ 1 lakh as "৳4.74 lakh", ≥ 1 crore as "৳1.20 crore".
- Drafts never count towards plan, value, offices reached or pace. Show "Planned only in drafts" separately.

---

## 6. Scope: who sees what

Programme and office keys come from rules the app already enforces; inventory access is an additional restriction.
Implement once in a `TrackingOfficeAccess` service and use it in every new controller, query and test. This section
incorporates the owner's 2026-10-18 decision and supersedes the prototype's broader office-only visibility.

| Key | Source today | Grants |
|---|---|---|
| **Programme key** | `InitiativeScope` / `TrackingAuthorizationService`: super user; company admin of `owner_company_id` (`gov_company_admins`); member of the initiative's operation unit (any designation, incl. `MONITOR`) | The whole programme: totals, every office in it (programme and code level), comparisons across its offices, code pages |
| **Office key** | `TenantContext::$allowedLocationIds`: global for super user; all company offices for a company admin; jurisdiction offices for ICT jurisdiction holders; otherwise the working office only | Office directory/header and **all-programme level**, restricted to programmes for which the viewer also has the programme key. Does not grant programme access |
| **Inventory key** | `TenantContext::$allowedInventoryLocationIds` and the existing inventory company boundary | Individual assets / in-use counts from visible programmes at that office. Jurisdiction holders do not get it for other offices; inventory access does not grant programme access |

Rules:
1. Programme without the programme key: not shown, not counted, not hinted at, including its drafts, deliveries,
   suppliers, people, documents, activity and assets. Apply this filter before aggregation.
2. Office key without any programme key: allow the scoped office header/directory entry with an empty tracking state;
   show no programme or receipt details and no count of hidden programmes. Explicit requests for an inaccessible
   programme or code fail without revealing its existence. Existing receipt execution through `ProgrammeVerifier`
   remains unchanged; this restriction applies to monitoring views.
3. Comparisons include only offices the viewer can see; say so when filtered; never show a rank that reveals hidden
   offices' figures.
4. Level fallback: an all-programme request without the office key opens the first allowed programme level with a note,
   provided that programme actually allocates to or delivers to the office. Otherwise deny.
5. No key at all: denied page, HTTP 403, with links to the viewer's own offices.
6. Server-side checks on every endpoint and every ID in the URL (`AGENTS.md`); hidden buttons are not authorization.

### Viewer matrix (updated for the owner's decision; use as acceptance tests)

| Viewer | Badalgachhi (location 9) | Patnitala (2) | Asset list at 9 |
|---|---|---|---|
| **mithun** (officer on ICTD Lab, works at head office 4) | All-programmes **locked**; ICTD Lab level (whole programme) | ICTD Lab level only | Hidden |
| **Department of ICT company admin** (company 412) | All levels for ICTD Lab and Office Network; other ministry's programme hidden unless separately authorized | Same | Shown for visible programmes |
| **Naogaon district ICT officer** (jurisdiction, no programme membership) | Office header; empty tracking view; programmes hidden | Same | Hidden |
| **Badalgachhi storekeeper** (works at 9, no programme membership) | Office header; empty tracking view; programmes hidden | **Denied** | Hidden in tracking without programme access; native inventory access unchanged |
| **Super admin** | Everything | Everything | Shown |

Directory row counts in the example: district officer 12 offices, mithun 1 (head office) + programme note.
Also test the district officer and storekeeper after explicit programme membership: programme details become visible,
while the independent inventory restriction still applies. `MONITOR` members see labelled drafts read-only; drafts
remain excluded from plan, value, offices reached and pace.

---

## 7. Visual system and kit

- **Theme contract only.** `--gs-*` tokens, `<x-gs::*>` components, `status-map.php` tones. No colour literals
  (`GovStoreThemeComplianceTest` ratchets them). Check all 6 themes × light/dark in the Theme Lab.
- **Charts:** inline SVG rendered server-side or a small package script; one hue (`--gs-color-primary`), area fill
  ~13% mix, dashed `--gs-color-text-subtle` for expected pace, `--gs-chart-grid` grid, `--gs-chart-label` axes, today
  marker, 2 px lines, ≥ 8 px dots with surface ring, hover tooltips, `aria-label` on each `<svg>`, a table view of the
  same numbers. Sequential ramp for tiles: `color-mix(in oklab, var(--gs-color-primary) {12…92}%, var(--gs-color-surface))`,
  text switches to `--gs-color-on-primary` at ≥ 50%.
- **Existing kit to use:** `page-header`, `kpi` (`spark`, `progress`, `caption`), `progress`, `status-badge`, `table`,
  `filter-bar`, `timeline`, `empty-state`, `key-value`, `tile`, `tabs`, `trail`, `readiness`.
- **New kit components** (in `packages/gov-store/theming/resources/views/components` + `components/*.css`, with a Theme
  Lab demo and tests, as the Organization redesign did): `pace-bar` (progress + expected tick), `attention-list`,
  `status-callout` (verdict + next step + forecast), `tile-map`, `price-strip`, `lifecycle` (dated stepper),
  `scope-why` (why-you-can-see-this panel), `level-switch` (segmented with locked options + reason).
- **Status vocabulary** to add to `status-map.php`: code health `on_pace / behind / stalled / over_allocation / draft`;
  office state `complete / in_progress / waiting / no_target / over_allocation / draft`; attention severity
  `critical / serious / warning / info` — each with tone, Font Awesome icon and en/bn label.
- **Copy:** plain, user-side words ("received nothing", "Next step"), not table names. All strings in
  `govtracking::general` (en-US and bn-BD).

---

## 8. Backend work

| # | Change | Why |
|---|---|---|
| B1 | `TrackingMonitorService` (read model) returning codes, targets, allocations, associations with dates, GRN line value, supplier, manufacturer, office, category; computes §5 metrics. Associations remain the quantity source. Current `TrackingDelivery` rows retain only an identity hash, code and timestamp, so add durable source linkage for new deliveries and recover legacy links only when verifiable. Unresolved receipt metadata is unknown, not zero or invented. Do not read the fact table until D2 is confirmed fixed | One source for all three views; testable |
| B2 | `TrackingOfficeAccess` service implementing §6 on top of `InitiativeScope`, `TrackingAuthorizationService` and `TenantContext` | One scope rule everywhere |
| B3 | Store `activated_at` on `gov_tracking_codes` (set in `TrackingCodeController::activate`, which today only flips `status` and writes no timeline event; backfill approximately from `updated_at` and say so) | Expected pace and forecast need it |
| B4 | Category name lookup that ignores the tenant category scope (read-only) | D3 |
| B5 | Fix/verify D2; rebuild facts | Trustworthy value and counts |
| B6 | Controllers + routes for §1 views; enable `show` on the tracking-code resource; office routes; phase 2 routes behind a config flag | New pages |
| B7 | Tile positions per district as JSON next to geo areas (e.g. `config/tile-maps/naogaon.json` keyed by `geo_code`) | Tile map without hard-coded offices |
| B8 | Optional, later: `planned_unit_cost` on `gov_tracking_targets` | Spend against budget (not designed yet) |
| B9 | Link the retrospective console (heads/officers) and the code sheet; keep documents visible for active codes | Orphaned features |
| B10 | Add named tracking abilities through `gov.can:` and `GovAccess`; preserve narrower existing controller, object, tenant and state checks | Approved by owner; include route coverage, role, shadow-mode and rollback regression tests |

---

## 9. Implementation phases

Implementation was authorized by the owner on 18 October. The delivered code, verification evidence and remaining
acceptance items are recorded in [the verification report](../verification/tracking-monitor-redesign-2026-10-19.md).
Tests are consolidated into `TrackingCorrectnessTest`, `TrackingMonitorTest`, the existing workflow suite and
`Theming/MonitoringKitComponentsTest`, rather than creating a separate test class for each screen.

| Phase | Delivered status | Local verification |
|---|---|---|
| 0 | Complete: corrected read figures, activation/provenance migrations, safe targeted fact-repair command | Replay, partial/serialized value, active-only, unknown metadata and repair tests; ICTD Lab discrepancy diagnosed, unrecoverable facts preserved |
| 1 | Complete: shared metrics/access/read model, eight kit patterns, named abilities | Isolated metrics/viewer matrix, route coverage, rollback/failure audit and theme tests |
| 2 | Complete: code dashboard, charts, lifecycle, prices, private document links | Head/officer/MONITOR and denied non-member checks; sparse/draft and populated Blade rendering |
| 3 | Complete: Monitor landing and retained Manage workspace | ICTD Lab and busy programme 25; filters, category labels and quantities |
| 4 | Complete: programme/code office levels with separate inventory checks | Scoped fallback/denial tests; locked panel and authorized 55-asset panel live |
| 5 | Complete: scoped directory and all-programme office view; enabled, kill switch retained | Hidden-programme invariance tests; storekeeper/jurisdiction empty tracking views live |
| 6 | Local checks complete; external acceptance remains | Full GovStore suite, Blade/JS checks, 36 view/theme/mode renders, all three views at 400 px; axe and bn owner review remain open |

The original phase requirements follow for traceability.

| Phase | Deliverable | Done when |
|---|---|---|
| **0 — Correctness** | D1–D5, D7 surfaced; B3, B4, B5 | Workspace/report show ACTIVE-only, per-line figures; tissue categories named; officer sees no 403 buttons; bn archive label fixed; tests for each |
| **1 — Kit + read model** | New kit components (§7) with Lab demos; status-map entries; B1, B2 with unit tests of every §5 metric and every §6 rule | `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore/Theming` and new tracking tests pass |
| **2 — Code dashboard** | §4.2 at `tracking-codes/{code}`; links from workspace cards, attention list, office pages | Walked live as head, officer, monitor; 403 for a non-member |
| **3 — Programme monitor** | §4.1 as the initiative landing view; editing actions remain reachable | Walked live with ICTD Lab (one receipt) and an experiment initiative (25–30, ~220 associations) |
| **4 — Office view (programme + code level)** | §4.3 levels 2 and 3; tile map; comparison; asset panel | Viewer matrix (§6) passes as feature tests and a live walk |
| **5 — Phase 2: all-programme office view + directory** | §4.3 level 1, §4.4, behind a config flag | Viewer matrix incl. directory counts |
| **6 — Verification** | Phone width, 6 themes × light/dark, axe pass, bn copy review, full GovStore suite | Listed in the PR |

---

## 10. Testing and verification

- Feature tests in `tests/Feature/GovStore/` (new `TrackingMonitorTest.php`, `TrackingOfficeAccessTest.php`); keep
  `TrackingWorkflowTest.php` green. Whole suite: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`.
- One test per §5 definition (including over-delivery masking, drafts excluded, no activation date, zero receipts).
- One test per §6 viewer row and per rule (hidden programme not counted, comparison filtered, denied 403, fallback level).
- Live walk on the local preview per `docs/testing/local-live-testing.md` (sign in at `http://127.0.0.1:8010`, not
  `localhost`; discover current fixture users from the database). Check compiled Blade output for raw `<x-gs::` tags.
- Known traps: package CSS sits in `@layer gs-packages`, so unlayered Bootstrap element rules win; inline `@php()` before
  a `@php … @endphp` block breaks Blade; multi-line component attributes are not parsed (build arrays in `@php` first);
  in stub-layout view tests forget the `composing: layouts.default` composer.

---

## 11. Owner decisions — resolved 2026-10-18

1. **Monitor first**, with a **Manage** tab for the existing editing tools.
2. **Require programme access.** Office access alone does not reveal deliveries from a programme the viewer cannot
   access, including programmes owned by another ministry. Apply this to every block, count and link.
3. **MONITOR can see drafts read-only**, clearly labelled. Drafts do not contribute to plan, value, reach or pace.
4. **Neutral prices:** show minimum, maximum, average and spread; no automatic threshold warning.
5. **Enable the all-programme office view and directory when complete and verified**, using
   `tracking.office_directory_enabled`. Keep the flag off during incomplete development; the completed delivery enables
   it after its verification gates pass and retains the flag as a kill switch.
6. **Add named tracking authorization abilities** through `gov.can:` as part of this redesign. Retain existing
   programme authorization and all object, office, inventory and state checks.

The saved version 3 HTML remains historical design evidence. Its office-only programme visibility, viewer examples
and officer activation wording do not override these decisions or the current narrower server policy. The concrete
build sequence is in [`IMPLEMENTATION-PLAN.md`](tracking-monitor-redesign/IMPLEMENTATION-PLAN.md).

## 12. Artifact

Interactive prototype (one HTML file, 4 tabs + code and office pages, Example / Live data switch, 6 themes, light/dark,
viewer preview on office pages). Example figures are invented on top of ICTD Lab's real codes, targets, offices and team;
Live mode reflects the local database on 18 Oct 2026. See [`tracking-monitor-redesign/README.md`](tracking-monitor-redesign/README.md).
