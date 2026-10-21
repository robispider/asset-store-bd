# Tracking monitor implementation plan

**Date:** 2026-10-18 (Asia/Dhaka)

**Status:** approved and implemented on `codex/tracking-monitor-redesign`; updated 2026-10-19.

**Implementation:** phases 0–5 delivered. Targeted migrations applied to the authorized local database; the directory
is enabled after its automated/live scope checks. Phase 6 local checks are recorded in the
[verification report](../../verification/tracking-monitor-redesign-2026-10-19.md); axe, bn-BD owner copy review and
live superuser acceptance remain open. No CI, production-readiness or shadow-observation completion is claimed.

Design requirements: [redesign plan](../tracking-monitor-redesign-plan.md), especially §§4–7 and the updated §11.
The saved [version 3 prototype](artifact/ictd-lab-monitor.html) is the visual reference. Owner decisions override its
older scope examples. [Build instructions](BUILD-PROMPT.md) require plan approval before implementation.

## 1. Confirmed decisions

| Decision | Implementation consequence |
|---|---|
| Monitor first; Manage tab | Keep `initiatives.show` as the monitor URL; add `initiatives/{initiative}/manage` for the current workspace and maintain existing editing routes |
| Programme access required | Office access never reveals an otherwise inaccessible programme, its receipts or its counts; filter before computing any aggregate |
| MONITOR sees drafts | Include labelled draft rows and read-only details; exclude drafts from plan, receipt value, reach, charts and pace |
| Neutral price spread | Show minimum, maximum, weighted average and spread; no price-based attention warning |
| Directory enabled after verification | Use `tracking.office_directory_enabled`; false during incomplete development, enabled for completed delivery only after verification, retained as a kill switch |
| Named tracking abilities included | Declare `gov.can:` on tracking routes, decide through `GovAccess`, retain narrower existing authorization and state rules |

These decisions are resolved and do not require another preference round.

## 2. Findings from the current code

| Finding | Build response |
|---|---|
| `InitiativeController::show()` serves the workspace; the nested code resource excludes `show`; there are no office-monitor routes | Add the three read-only views and preserve the workspace under Manage |
| `EloquentTrackingProjectionRepository::getLifecycleSummary()` selects every code, sums unlike target quantities and divides aggregate quantities | Use active-code, per-target fulfilment for workspace/report, then reuse the monitor metric service; retain per-item units |
| Workspace/report snapshots query `TrackingFactDelivery`; quantities elsewhere use associations | Associations remain the authoritative quantity source; reconcile receipt facts separately before trusting old derived values |
| The receipt listener has a durable replay key and a replay-once regression test | Reproduce D2 using a read-only diagnostic and the existing test before changing ingestion; do not assume the historical row proves a current replay defect |
| `TrackingDelivery` stores only `delivery_key`, `tracking_code_id`, `created_at`; the movement identity is hashed and discarded | Persist durable receipt provenance for new deliveries; recover historical source links only when proved by matching existing evidence |
| `PostingPipelineManager` already supplies a movement UUID to `InventoryMaterializedAgainstProgramme` | Start by using the existing event contract inside the tracking listener; extend the package event/caller only if an exact receipt-line link cannot otherwise be retained |
| `TrackingCode` has no activation fields; `activate()` updates only status | Add timestamp and provenance for exact versus approximate activation; record new activation inside the existing locked mutation transaction |
| The controller authorizes activation/archive for `HEAD`, company administrators and superusers | Preserve this narrower rule; prototype wording must not grant officers activation |
| Category eager loads use `withTrashed()` but retain the tenant category scope | Read only category identity/name/type for already authorized targets, without applying tenant category adoption scope; do not un-scope catalog browsing |
| `InitiativeScope` already admits any operation-unit designation; `GovAccess::qualifier('project_member')` omits MONITOR | Make tracking navigation and named abilities recognize MONITOR while retaining read-only privileges |
| Documents already have private storage and read authorization independent of draft state | Expose their existing authorized links on active/archived code pages; keep mutation restrictions unchanged |
| `SafeTrackingResponse` and `RequireGovAbility` both manage failed mutations | Verify middleware order, nested transaction levels and failure audits when adding abilities; retain safe responses and rollback on rendered errors |
| New `govstore:` commands must be declared in `govstore-execution.global_commands` | Add the narrowly scoped repair command to the package execution registry; do not rely on an incidental console scope bypass |
| The HTML prototype uses `programme key OR office key` in `officeAccess()` | Implement the owner's programme-key requirement instead, including directory totals, timeline entries, assets and price/supplier summaries |

The prototype source was inspected, including `analyse()`, `forecast()`, `sliceOf()` and `officeAccess()`. Opening its
`file:` URL in the browser was blocked by browser URL policy. No browser workaround was attempted; interactive visual
inspection remains pending. This does not prevent source inspection or preparation of this plan.

## 3. File map and build sequence

All paths below are relative to the repository root. Runtime changes stay in `packages/gov-store/*`; tests and design/
verification records use their existing repository directories. Do not change Snipe-IT core, root `phpunit.xml`, or the
unrelated working changes in `resources/assets/js/store-operations.js` and `public/js/dist/store-operations.js`.

### Phase 0 — correctness and receipt provenance

**Design coverage:** §2 D1–D5, D7; §5 foundations; §8 B3–B5.

Change existing tracking files:

- `src/Repositories/EloquentTrackingProjectionRepository.php`, `CachedTrackingProjectionRepository.php` and
  `src/Services/ProjectionRefresh.php`: correct active-only, per-line fulfilment and invalidate relevant lifecycle caches.
- `src/Http/Controllers/InitiativeController.php`, `TrackingCodeController.php`,
  `src/resources/views/initiatives/{index,workspace,report}.blade.php`: correct figures and category names; match
  visible management actions to current server checks; remove unlike-unit headline ratios.
- `src/Models/TrackingCode.php`: activation casts and provenance.
- `src/Listeners/AssociateInventoryToProgramme.php`, `src/Models/TrackingDelivery.php`: preserve receipt provenance
  without weakening the existing unique replay guard or atomic posting behavior.
- `src/resources/lang/{en-US,bn-BD}/general.php`: corrected archive wording, unavailable metadata and approximate dates.
- `src/Providers/TrackingServiceProvider.php`: register the targeted maintenance command.

Add under `packages/gov-store/tracking/`:

- `src/database/migrations/2026_10_18_000001_add_tracking_activation_provenance.php`: nullable `activated_at` plus
  an approximation marker. Backfill existing active codes from their available `updated_at` and label the result;
  preserve null when no usable date exists. Do not imply that an archived code's last update was its activation.
- `src/database/migrations/2026_10_18_000002_add_tracking_delivery_provenance.php`: nullable durable movement and/or
  receipt-line references, with appropriate indexes and compatible key types. Preserve existing delivery identities.
- `src/Services/TrackingReceiptResolver.php`: resolve verified receipt metadata for `InventoryMovement`,
  `TrackingDelivery` and serialized `Asset` associations, including `gov_asset_registrations` where applicable.
- `src/Console/Commands/TrackingRebuildDeliveryFactsCommand.php`: diagnostic by default; require explicit initiative/
  code scope and an apply option for repair. Build supported replacement facts transactionally and idempotently.
  Do not rewrite association quantities, native inventory or historical audit events. If provenance is ambiguous,
  report unresolved records and refuse a destructive partial rebuild for that scope.

Also update `packages/gov-store/tenant-scope/src/config/execution.php` for the command boundary.

The receipt resolver must count each receipt line's value once even when it created many serialized assets. Linked
tracking quantities determine the attributed share when only part of a receipt line is associated. Missing source
information is shown as unavailable, with incomplete financial totals explicitly labelled; it is never invented or
silently treated as a complete zero-value receipt. Preserve duplicate historical timeline evidence; diagnose it
separately rather than deleting audit entries to match a count.

**Tests:** add `tests/Feature/GovStore/TrackingCorrectnessTest.php` and
`TrackingDeliveryFactsTest.php`; extend `TrackingWorkflowTest.php` for activation provenance and receipt replay/rollback.
Cover mixed units, drafts, matrix overshoot, scoped category labels, management buttons, archive translation,
missing geography, incomplete provenance, serialized line-value deduplication and repeatable repair.

### Phase 1 — access, metrics and theme kit

**Design coverage:** §§5–7; §8 B1, B2, B10; D6/D8.

Add under `packages/gov-store/tracking/`:

- `src/Services/TrackingMonitorMetrics.php`: pure calculations with an injected observation date, independent of HTTP.
- `src/Services/TrackingMonitorService.php`: shared read model for programme, code and office views; batch queries
  instead of per-row fetches; use associations plus the receipt resolver.
- `src/Services/TrackingOfficeAccess.php`: programme visibility, office-level access, permitted comparisons, level
  fallback and inventory-panel access. Apply explicit approved ID sets to any query that removes a global scope.
- `src/config/tracking.php`: directory kill switch and supported tile-map settings; merge as `tracking` in the provider.
- `src/config/abilities.php`: named tracking definitions merged into `govstore-abilities` in provider `register()` so
  tenant-scope can define the named gates. Cover programme read/manage/create, codes read/manage/activate/archive,
  team read/manage, documents read/manage, retrospective read/manage, offices read, settings and execution endpoints.

Update `src/Services/TrackingAuthorizationService.php` with reusable non-throwing checks for menus plus the current
throwing authorization API. Keep the same target-specific head/officer/support/monitor rules. Update
`src/routes/{web,api}.php` with exactly one declared ability per effective route and retain all per-ID checks.
Coordinate `SafeTrackingResponse` with `RequireGovAbility` rather than changing the shared middleware behavior broadly.

Update `packages/gov-store/tenant-scope/src/Services/GovAccess.php` narrowly for named tracking decisions and programme
role sources: recognize operation-unit membership including MONITOR and relevant company-admin assignments; do not
turn native `admin` into superuser or grant programme membership through office responsibility. Programme-specific
authorization still rechecks the requested initiative, including in shadow mode. Preserve execution eligibility for
storekeepers who legitimately receive against a code without programme-management access.

Add ability labels to `packages/gov-store/tenant-scope/src/resources/lang/{en-US,bn-BD}/access.php` and align tracking
menu entries in `TrackingServiceProvider.php` with the resulting named decisions.

Add eight theme components under `packages/gov-store/theming/resources/views/components/`:
`pace-bar.blade.php`, `attention-list.blade.php`, `status-callout.blade.php`, `tile-map.blade.php`,
`price-strip.blade.php`, `lifecycle.blade.php`, `scope-why.blade.php`, `level-switch.blade.php`.
Put styles in `components/17-monitoring.css`; add semantic statuses to `status-map.php`, bilingual labels to
`lang/{en-US,bn-BD}/kit.php`, and demonstrations to `src/Lab/LabFixtures.php` and
`resources/views/lab/partials/sections.blade.php` following the existing Lab structure. Reuse existing general/workflow
components and the theme asset builder; introduce no colour literals or categorical chart palette.

**Tests:** `TrackingMonitorTest.php`, `TrackingOfficeAccessTest.php`, `TrackingAuthorizationTest.php`,
`Theming/MonitoringKitComponentsTest.php`, plus effective route/ability coverage and labels in
`G1AuthorizationTest.php`. Cover every §5 metric and the revised §6 matrix, including:

- Active target means, per-cell caps, unplanned/open targets, missing/zero plans, zero quantities and zero average price.
- Exact and approximate activation, missing dates, activation at/after FY end, fiscal-year boundaries, no receipts,
  status precedence, 30-day and five-point boundaries, and forecast division by zero. Undefined ratios display
  unavailable with a reason rather than fabricating progress; open targets never create a fictional denominator.
- Office-only actors see an authorized office header/empty tracking state, with no hidden-programme hint or total.
  Explicit foreign programme/code IDs do not redirect into a context that reveals the requested hidden object.
- Directory visibility, programme-member fallback, office not allocated/receiving under that programme, foreign code
  nesting, comparison filtering, inventory/company bounds, membership removal, and native-admin non-bypass.
- MONITOR draft reads and rejected mutations; head/officer/support distinctions; enforcement and shadow object
  boundaries; safe errors, no debug toolbar, rendered failure rollback and a single committed success audit.

### Phase 2 — code dashboard

**Design coverage:** §4.2, §8 B6/B9.

Enable resource `show` in `src/routes/web.php`; add `TrackingCodeController::show()` with an initiative/code match and
programme authorization before fetching any read model. Add `src/resources/views/tracking_codes/show.blade.php` and
`tracking_codes/partials/monitor/` partials for verdict, charts, lifecycle, office matrix, prices, receipts, documents
and contacts. Register `src/resources/js/monitor.js` through the tracking provider for copy, accessible tooltips and
table controls. Extend `src/resources/css/tracking.css` with token-based page layout only.

Previous/next/jump options must come from the same authorized programme and filtered code set. Show the existing
private document/code-sheet/order endpoints. Activation remains an existing POST action with current checks;
the dashboard GET performs no business mutation.

**Tests:** `TrackingCodeDashboardTest.php` for active, draft, archived and empty pages; cross-initiative nesting;
private downloads; role-appropriate actions; receipt values and dates; compiled Blade rendering and bilingual keys.

### Phase 3 — programme monitor and Manage tab

**Design coverage:** §4.1, §8 B6/B7/B9.

Make `InitiativeController::show()` serve `src/resources/views/initiatives/monitor.blade.php`; add a `manage()` action
and static-before-resource route for `initiatives/{initiative}/manage` serving the existing workspace. Shared tabs
link to Monitor/Manage with independent server checks on every management action. Keep report, operation-unit,
retrospective and code forms reachable for their existing authorized roles.

Build partials under `src/resources/views/initiatives/partials/monitor/` for the header/filter/KPIs, attention,
FY chart, code table, offices/map, items/suppliers and timeline. Validated query-string filters apply consistently
to every block and remain shareable; drafts may appear in rows and attention but never inflate live aggregates.
Scope before searching, sorting or counting. Group item quantities by unit/category; no misleading cross-unit ratio.

Add `src/config/tile-maps/naogaon.json` using real geographic identifiers rather than office IDs; connect through a
small `src/Services/TrackingTileMap.php`. Unknown districts, missing geography and unmapped offices use a labelled
fallback list/off-map tiles, without implying coordinates or silently dropping an office. Surface D7 as an attention
note. SVG charts have translated accessible names and tables containing the same data.

**Tests:** `TrackingProgrammeMonitorTest.php` for filters, health sort, empty/draft-only/mixed/busy programmes,
hidden data, working Manage routes, missing-area notes, chart/table parity and per-item units.

### Phase 4 — programme and code office views

**Design coverage:** §4.3 levels 2/3; §8 B2/B6/B7.

Add `src/Http/Controllers/TrackingOfficeController.php` with programme and code actions; register the two nested office
routes from design §1. Check initiative visibility, code nesting and an allocation/receipt connection to the office
before reading its profile or tracking rows. Use the shared access service and read model.

Add `src/resources/views/offices/show.blade.php` plus `offices/partials/` for scope explanation, level navigation,
KPIs, code/item rows, monthly value chart, comparisons, assets, receipts and people. Programme access allows offices
in that programme; individual assets and deployment counts additionally require the office's inventory access.
Restrict person/contact fields to information supported by the authorized office/programme context.

**Tests:** `TrackingOfficeViewTest.php` plus access tests for each revised viewer row, direct nested foreign IDs,
inventory locks, filtered ranks, receipt counts and no mixed-unit fulfilment bars.

### Phase 5 — all-programme office view and directory

**Design coverage:** §4.3 level 1; §4.4.

Add all-programme and directory actions to `TrackingOfficeController`, routes `GET offices/{location}` and
`GET offices`, and `src/resources/views/offices/index.blade.php`. Gate both routes and navigation with
`tracking.office_directory_enabled`. A disabled direct route must also fail, not just hide its link.

Directory rows come from the office key; their programme/code/value figures come only from independently accessible
programmes. A scoped office with no accessible programmes has a neutral empty state, even when hidden deliveries
exist. Programme-only actors can fall back from all-programme to an allowed programme level for a connected office.
Preserve a note linking to programme offices outside the actor's directory office scope. Never query or return a
hidden programme name/count as part of fallback, denial, tooltip, search, sort, chart or accessible table content.

**Tests:** extend `TrackingOfficeViewTest.php` and add `TrackingOfficeDirectoryTest.php` for enabled/disabled behavior,
the updated viewer matrix, hidden-programme invariance, zero-visible-programme offices, and deterministic fallback.

### Phase 6 — verification and completed-delivery switch

**Design coverage:** §§9–10 and final visual/behavior acceptance.

After each phase run the relevant focused tests and the full GovStore suite; inspect failures without weakening
authorization or assertions. For authorization work run `G1AuthorizationTest.php`; for components run the Theming suite.
Use installed PHP with Xdebug disabled and `-d memory_limit=1G` when running PHPUnit directly. Confirm the tests use
isolated SQLite or a dedicated isolated database before execution. Root `phpunit.xml` stays unchanged.

For UI phases compile and lint Blade output, render representative pages and check that no raw `<x-gs::` tags remain.
Walk the local preview at `http://127.0.0.1:8010` with rediscovered fixture actors: head, officer, MONITOR, company
administrator, jurisdiction officer, storekeeper and no-access user. Check the current small programme and a suitable
busy experiment programme rather than relying on dated IDs/counts. Exercise ordinary navigation and direct denied
URLs; reconcile displayed quantities/value against selected source records. Check both languages, keyboard behavior,
400 px width and a dark theme after UI phases, then all six themes in light/dark at final verification. Run the
available axe checks and record their scope; retain bn-BD owner copy review as explicit if not completed.

Add `docs/verification/tracking-monitor-redesign-2026-10-18.md` (or use the actual completion date) with tests, live
roles/contexts, screenshots without secrets, scoped data reconciliation, any retained fixtures and limitations.
Update design status and phase table with separate implementation, automated-test and live-verification results.
Enable `tracking.office_directory_enabled` in the completed delivery only after its relevant automated/live checks
pass. If a required check remains blocked, leave the switch off and report the specific remaining gate.

Use a feature branch prefixed `codex/` after plan approval; commit completed phases without unrelated files. No push
or pull request unless requested. Preserve audit history; remove owned temporary helpers and restore temporary
configuration/session context. No database reset, broad seeding, password changes or access weakening for test setup.

## 4. Approval and delivered verification status

The owner approved implementing the full plan after resolving all six decisions. No further preference round was
needed. The runtime implementation covers the programme/code/office views, retained Manage workspace, named tracking
abilities, provenance and activation migrations, safe repair tooling, eight kit patterns and bilingual strings.

Tests are consolidated into `TrackingCorrectnessTest.php`, `TrackingMonitorTest.php`, the existing
`TrackingWorkflowTest.php` and `Theming/MonitoringKitComponentsTest.php`; the proposed per-screen class names above
are a coverage map, not additional missing suites. Effective route checks are in `G1AuthorizationTest.php`.

Live verification used the owner's explicitly authorized `http://snipeit.local` host. Starting the suggested loopback
preview was rejected by automatic approval review; no alternate launch bypass was attempted. The existing WAMP site
was sufficient for the checks. Prototype `file:` navigation remained policy-blocked; its source was inspected and the
saved artifact remains historical evidence. No interactive prototype walkthrough is claimed.

See the [verification report](../../verification/tracking-monitor-redesign-2026-10-19.md) for final test counts, source
reconciliation, role/theme/phone checks, restored settings, retained historical inconsistencies and acceptance items.
