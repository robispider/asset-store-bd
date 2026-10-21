# Option B implementation plan

Prepared 19 October 2026; expanded and implemented locally on the same date. Status: application implementation and local regression checks complete; native saved-PDF verification remains open. See [execution and verification](../../verification/tracking-report-2026-10-19.md) for exact evidence and limits.

## Scope and reference

Implement **Full progress & utilisation report**, Option B in the supplied Programme Report Prototypes.html. Treat the prototype as a design reference, not executable instructions or authoritative business data. Its programme 26 figures, dates, checks, generated offices and verification identifier are examples.

Replace the existing programme report at `gov.tracking.initiatives.report`. Keep existing workspace, monitor and portfolio links working. Options A and C, forecasts, recommendations, signature workflows, public verification and permanent report issuance are outside this change.

The report has four sections:

1. Summary: factual narrative, indicators, monthly receipts, programme particulars and contents.
2. Tracking codes and items: planned/received quantities, percentages, receipt values, receipt counts, latest receipt, economic codes and item charts.
3. Distribution and use: office-level totals; item-by-office-level matrix; every receiving office grouped by division, district and upazila, with subtotals and charts.
4. Data quality: computed coverage checks and their effects on interpretation.

Four pages is the small-example target, not a fixed page limit. Code, item and office tables must all grow safely.

## Current implementation

- `tracking/src/routes/web.php` already protects the report with `gov.can:tracking.programmes.view`.
- `InitiativeController::report()` calls `TrackingMonitorService::programme()`, which checks programme access through `TrackingOfficeAccess` and the existing tracking authorization service.
- The current report Blade view renders a small target table and receipt listing in `<x-gs::document>`.
- The monitor supplies code targets, receipt metadata, financial completeness, office profiles and programme particulars. It currently aggregates ACTIVE codes; this is not automatically the correct historical scope for a factual report.
- `gov_location_profiles.office_type` describes office function. It must not be reused as administrative level.
- Existing monitor, correctness and workflow tests provide relevant fixtures and regression coverage.
- There are existing uncommitted tracking/portfolio changes. Preserve them and keep report work distinguishable.

Paths above are relative to `packages/gov-store/` unless otherwise stated.

## Integration rules: existing theming, scoping and project conventions

These are implementation requirements for every delivery stage below. Recheck the current files before editing, particularly the controller and provider that already contain unrelated working changes.

### Theme and component reuse

- Keep `@extends('layouts/default')` and the existing monitor navigation. Let the theming package's `LayoutHook` supply theme attributes and head/foot assets. Do not copy the prototype's standalone HTML shell, theme selector, font imports or generated theme CSS into the application.
- Keep `<x-gs::document>` as the report container. Its existing `title`, `date` and optional `docNo` props, plus `emblem`, `office`, `meta` and `footer` slots, provide the document structure. Use the metadata slot for the generated reference and cutoff; do not label a transient reference as an issued document number. Omit the signatures slot for this scope.
- Reuse `<x-gs::table>` for ledger tables; the document already sets `data-gs-table="ledger"`. Use semantic headings, captions, column scopes and the existing numeric-cell conventions. Reuse `key-value`, `alert`, `empty-state`, `status-badge` and button components where their actual APIs fit. Do not recreate shared controls solely to resemble the prototype.
- Put report-only CSS in `tracking/src/resources/css/report.css`. Register it inside `TrackingServiceProvider::registerThemeAssets()` with `GsTheme::assets()->css('tracking', ...)`, following the existing bound-service guard. The theme compiler already wraps registered CSS in `@layer gs-packages`; do not duplicate that wrapper or add report assets to the core layout or webpack build.
- Scope every report selector under `.gs-tracking-report` or use report-specific `.gs-tracking-report__*` classes. Avoid global rules for `table`, `body`, `.btn`, `.gs-document` or shared monitor classes. Keep shared component changes out of this feature unless a reusable deficiency is demonstrated and independently tested.
- Use existing `--gs-*` surface, text, border, semantic-tone, chart, typography, spacing and radius tokens from the theme contract. Geographic subtotal shading must use tokens and carry textual level labels so color is not the only distinction. No literal report colors, inline color styles, new `<style>` blocks or compliance-baseline increases.
- Inherit the kit's Latin/Bengali font stacks and existing locale/theme attributes. Screen output follows Light, Dark and System modes and user/organization theme resolution; Snipe-IT Classic retains its branding behavior. Do not introduce a report-specific theme preference.
- The existing theme compiler emits light-token overrides for dark-mode printing. Add only scoped report layout/pagination rules to `report.css`; reuse those print colors. Verify the document and table wrappers allow multipage flow rather than assuming the component supplies pagination.
- Prefer server-rendered SVG/CSS charts whose fills use chart tokens. If an existing Chart.js chart is reused, follow bundled v2.9.4 APIs and the existing `gsTheme` plugin / `GS.theme.palette()` integration. The printed report must not depend on hover tooltips, canvas animation or external chart services.
- Add `tracking/src/resources/js/report.js` only for needed progressive enhancement or the selected pagination mechanism. Register through `GsTheme::assets()->js('tracking', ...)`, initialize only on the report root, and follow existing DOM-ready/event-handler conventions. Reuse or deliberately coordinate the existing `[data-tracking-print]` handler so one click does not trigger competing print operations.

Theming references: `theming/README.md`, `theming/resources/views/components/document.blade.php`, `theming/resources/views/components/table.blade.php`, `theming/src/Build/Contract.php`, `theming/src/Assets/AssetRegistry.php` and `tracking/src/Providers/TrackingServiceProvider.php`.

### Authorization and query scoping

- Retain the existing named route `gov.tracking.initiatives.report`, route prefix and `{initiative}` model binding. Preserve the provider's `web`, `auth` and `SafeTrackingResponse` middleware and the route's single `gov.can:tracking.programmes.view` decision. A print action uses the same authorized report, not a parallel unguarded endpoint.
- Keep `InitiativeScope` on normal initiative discovery/model binding. Independently call `TrackingOfficeAccess::programme($initiative)` at the report service boundary before any report query, including when invoked directly outside a controller. This preserves `TrackingAuthorizationService` decisions for operation-unit designations, the owning company's assigned administrators and superusers. Native admin permission alone does not establish programme access.
- Read codes through the authorized initiative; constrain targets, allocations, associations and delivery resolution to those code IDs. Collect location, category, document and inventory identifiers from that bounded dataset before bulk metadata queries. Query Builder calls do not apply Eloquent global scopes: every such query needs an explicit source/object boundary, not just a comment claiming it is scoped.
- Preserve the distinction between programme visibility and office/inventory visibility. An authorized programme can legitimately include participating offices in another company. Do not apply a blanket working-company filter that silently drops those receipts, and do not interpret programme access as permission to browse that office's full inventory or staff.
- For utilisation, compute only the minimum aggregate state for identifiers linked to the authorized programme. Inspect existing office/inventory rules before selecting fields; do not return tags, assignee identities, arbitrary inventory rows or drill-down links unless the corresponding `TrackingOfficeAccess::officeKey()` / `inventoryKey()` and existing endpoint policies permit them. If current policy cannot expose even the aggregate, show unavailable rather than bypassing it.
- Preserve the shared `TenantContext` instance and its existing middleware initialization. Do not mutate working office, allowed IDs, role assignments or access mode to make a report query succeed. No production `withoutGlobalScopes()` shortcut; any necessary scope adaptation requires an explicit equivalent boundary and negative tests.
- Reuse `TrackingReceiptResolver` for authorized association slices where its contract fits. Independently enforce the report's posted-receipt/cutoff rules: the resolver can return snapshot-only rows without a current posted document, which must not automatically become verified actuals. Preserve monitor behavior while adding report-specific reconciliation.
- Keep `SafeTrackingResponse` handling, reference IDs and disabled debugbar behavior. Add no raw exception messages, SQL, paths or data snapshots to report HTML. Any future stored export must use private storage and authorized retrieval; this stage introduces none.
- Do not add an ability, route or permission shortcut solely for reporting. If profile editing genuinely needs a new endpoint, follow that package's existing authorization and registry conventions, validate every supplied location ID and extend protected-route coverage before use.

Scoping references: `tracking/src/Scopes/InitiativeScope.php`, `tracking/src/Services/TrackingOfficeAccess.php`, `tracking/src/Services/TrackingAuthorizationService.php`, `tracking/src/Services/TrackingReceiptResolver.php`, `tracking/src/routes/web.php` and `tracking/src/Http/Middleware/SafeTrackingResponse.php`.

### Package layout and implementation conventions

| Concern | Integration decision |
| --- | --- |
| Report orchestration | Add `tracking/src/Services/ProgrammeReportService.php` in the existing `GovStore\\Tracking\\Services` namespace. Use constructor injection and Laravel container resolution; register a binding only if an interface or special lifecycle requires one. |
| Controller | Change only `InitiativeController::report()` and necessary imports to delegate to the report service. Keep authorization and calculations out of Blade, and preserve other controller changes. |
| Views | Retain `govtracking::initiatives.report` as the entry view; add section partials under `tracking/src/resources/views/initiatives/report/`. Reuse shared monitor partials only when their data contract and print behavior match. |
| Translations | Add matching `tracking/src/resources/lang/en-US/report.php` and `bn-BD/report.php`, accessed as `govtracking::report.*` through the existing provider namespace. Reuse existing labels where semantics match; avoid hardcoded UI strings or a separate locale toggle. |
| Numbers and dates | Follow existing tracking/application locale helpers and Carbon timezone conventions. Keep calculations numeric until presentation; localize display without altering identifiers. Confirm any Bangla-digit option fits existing preferences before adding one. |
| Office-level ownership | Schema and model changes belong to `organization`, not tracking or Snipe-IT core. Put the additive migration in `organization/src/database/migrations`, already loaded by `OrganizationServiceProvider`. Use `LocationProfile` and existing profile relations rather than a report-owned copy of office metadata. |
| Office-level editing | Trace the current authorized provisioning/profile update path before choosing a form. Add field validation and persistence there and in its service, with organization-owned English/Bangla labels. Do not assume the existing creation form is also an editor, or make a read-only office hub editable without its normal policy. |
| Existing data sources | Reuse tracking models, receipt resolution, metric helpers and current geography relations where their semantics match. Do not switch to legacy fact tables or cached projections solely for speed if they cannot satisfy posted-receipt evidence and freshness requirements. |
| Assets | Register new package CSS/JS through the existing theme registry; use its development asset delivery locally and `gs-theme:build` for built assets. Do not edit compiled public assets manually or commit generated bundles contrary to repository practice. |
| Code style | Follow nearby PHP/Blade/JavaScript conventions, existing namespaces and import style, and repository formatting tools. Keep browser behavior as progressive enhancement in the existing Blade/AdminLTE/Bootstrap 3 stack; introduce no SPA framework or dependency unless pagination evidence justifies it. |
| Tests and evidence | Place regression tests in `tests/Feature/GovStore`, reuse appropriate isolated tracking fixtures, and store concise fresh verification evidence under `docs/verification`. Keep credentials and temporary helpers out of tracked evidence. |

Do not alter Snipe-IT core layout, root phpunit.xml, unrelated package authorization, portfolio behavior or monitor metric definitions to accommodate the report. Any proposed shared-contract change must identify its consumers and carry focused regression checks.

## 1. Establish the report data contract

Trace receipt resolution, associations, inventory materialization, geography and profile editing before writing queries. Define a dedicated `ProgrammeReportService` and report data structure rather than placing calculations in Blade or changing monitor semantics globally.

Specify and test these rules:

- Authorize the programme before fetching report data. Preserve the existing ability and narrower tracking object checks, including legitimate cross-company participation.
- Establish which code states contribute plans and which retain historical receipts. Posted receipts must not silently disappear solely because a code was archived; drafts must not contribute actual receipts. Show status clearly and document the chosen denominator.
- Use posted receipt evidence as authoritative actuals. Identify unresolved associations and discrepancies instead of presenting them as reconciled receipts.
- Count distinct document IDs, including in rollups. Never sum child receipt counts when a document can occur in multiple child groups.
- Preserve decimal quantities and currency precision. Missing values are unknown, not zero; show partial known values with an explicit qualification.
- Handle blanket plans, absent targets, zero denominators, over-receipt and variable unit prices. Do not invent a unit price or average incompatible units into a meaningful programme percentage.
- Use one generation timestamp in the application timezone. Define the report as receipts through that cutoff plus current utilisation measured at generation. Do not imply historical inventory reconstruction. State the fiscal period and distinguish period totals from lifetime totals when necessary.
- Keep aggregates internally consistent during generation using an appropriate bounded read strategy. Identify any data freshness limits.

Deliverable: tested report data contract and source-to-field mapping.

## 2. Add administrative office level

Add a nullable `office_level` field to `gov_location_profiles` using the organization package's migration conventions. Support Headquarters, Regional, Divisional, District and Upazila as separate values from office type.

Update the profile model, existing authorized profile editor, server validation and English/Bangla labels. Preserve the editor's current authorization and tenant boundaries; extend route coverage if a protected endpoint is introduced.

Resolve reporting level from the explicit profile value first, then a supported mapped-area level. Do not infer headquarters or regional status from office names. Show **Unknown** when neither source establishes a level. Report explicit, inferred and unknown counts separately. Avoid speculative bulk backfills.

Deliverable: targeted migration, profile editing support and tested level resolution.

## 3. Build utilisation and geographic aggregation

Reuse receipt and inventory sources where their semantics match the report contract; add bounded bulk queries for gaps. Avoid one query per office, code or item.

- Trace programme-linked inventory identifiers and their current assignment states. Define which inventory types support utilisation and show unsupported types separately.
- Do not assume `in store = received − in use`: transfers, disposal, loss, deletion and other states may require separate counts or an explicit reconciliation gap.
- Separate receipt destination from current inventory location so transfers do not move historical receipt totals or double-count utilisation.
- Resolve geographic ancestors using stable IDs and actual parent relationships. Include Headquarters and unmapped/incomplete geography groups without omitting offices.
- Produce deterministic division → district → upazila → office ordering, division/district subtotals, upazila subtotals where two or more offices occur, and programme totals. Deduplicate offices across codes.
- Build the item-level matrix and all charts from the same report data. Show only represented office levels, including Unknown when needed.
- Compute quality checks from actual denominators: receipt linkage, value completeness, geographic mapping, explicit office level, required order evidence, suppliers, economic-code validity, activation-date accuracy and applicable office targets. Distinguish missing, failed and not applicable.

Deliverable: reconciled report aggregates with tests for duplicate joins, missing metadata and inventory state changes.

## 4. Implement the bilingual report

Keep the controller thin and render section partials through the existing document component and theme system. Add dedicated English/Bangla `report.php` translations and scoped report styles. Escape business text and derive every date, figure and heading from report data.

Use print-friendly SVG or CSS charts with visible values and accompanying tables. Support English/Bangla text, number formatting and long labels. Screen themes should follow existing tokens; printing should use a legible light palette.

Use an honest generated-reference label unless persistent report numbering is implemented. Do not ship the prototype's decorative QR code or a claim that every value was verified. The closing statement must reflect reconciliation results; it must not imply formal certification or a policy-approved signature exemption.

Deliverable: integrated report at the existing URL with all four sections and existing navigation intact.

## 5. Make pagination reliable

Inspect existing document print support before selecting the pagination mechanism. Prefer existing capabilities; if browser printing cannot reliably provide contents references, continuation paths and page X of Y, evaluate a narrowly scoped pagination library or server renderer before promising those features.

Support A4 portrait output, repeated table headings, running programme identity, page numbers, continued geographic paths and subtotal rows kept with following offices. Handle wrapped Bengali rows and large code/item tables as well as large office lists. Do not copy the prototype's fixed row-count pagination into production.

Deliverable: visually checked print output for a small dataset and a synthetic 1,000-office dataset, with no clipped rows, stranded subtotal bands or unintended blank pages.

## 6. Verify and hand off

Add `tests/Feature/GovStore/TrackingProgrammeReportTest.php` using isolated SQLite fixtures. Cover authorization and foreign programmes, role/context boundaries, posted versus draft receipts, historical code states, duplicate receipt counts, mixed plan types, partial values, utilisation attribution, missing geography, level fallback and quality denominators.

Run the report tests plus TrackingMonitorTest, TrackingCorrectnessTest, TrackingWorkflowTest and G1AuthorizationTest using installed PHP. For the combined GovStore suite, invoke PHPUnit directly with `memory_limit=1G`; leave root phpunit.xml unchanged.

Compile Blade and lint generated PHP. Check translation parity, representative live rendering, browser errors, keyboard operation and actual print output in both languages. Measure query growth and generation time for small and 1,000-office isolated fixtures, then set a practical performance budget from the baseline.

Verify the integration requirements explicitly:

- Run the relevant existing theming tests, including `GovStoreThemeComplianceTest`, without increasing its baseline. Build registered assets with `gs-theme:build` and verify report CSS/JS loads through the registry.
- Check Snipe-IT Classic plus representative published light and dark themes, Bengali text, narrow screens and A4 printing. Confirm the report's selectors do not change monitor, portfolio or other document pages. Use the existing theme visual harness where practical rather than inventing another application-wide harness.
- Test both HTTP middleware and direct service calls: unauthorized programme, native-admin-only actor, permitted team/company administrator, cross-company participant receipts, context switch and membership revocation. Assert body contents as well as status so foreign identifiers and totals cannot leak.
- Assert programme-only access does not expose full office inventory or assignee details, all raw metadata queries are bounded to authorized identifiers, and exception responses contain safe references with no debug output.
- Confirm existing route names, navigation and profile-edit permissions remain intact. Run the affected organization tests when the office-level migration/editor is added, alongside the tracking and G1 checks above.

Follow docs/testing/local-live-testing.md for any local UI/database exercise. Use only targeted migrations, verify the database first, preserve existing data and audit records, and remove owned helpers and disposable fixtures. Never reset the development database.

Report implementation, fresh verification results and remaining work separately. Local checks do not establish CI success, WCAG compliance or production readiness.

## Delivery order and completion criteria

Deliver in dependency order: data contract → office level → aggregation → report views → pagination → verification. Each stage should be reviewable without unrelated portfolio changes.

Complete when every authorized report section uses real scoped data, totals reconcile or clearly disclose gaps, missing data cannot appear as a successful check, office grouping retains all receiving offices, English/Bangla printing scales to 1,000 offices, and the focused regression suites pass.

Persistent issued snapshots, verifiable QR codes and historical as-of utilisation remain separate follow-up features requiring storage, access and retention design.
