# Programme portfolio: Option A with Table / Cards switching

Status: implemented and locally verified. See [verification record](../../verification/tracking-portfolio-2026-10-19.md).

Prepared 19 October 2026, Asia/Dhaka.

## Scope and reference

The user's request is to build the programme portfolio from Option A and let users switch its results between Option A's table and Option B's card grid. This concerns `/gov-store/admin/tracking/initiatives`, above the individual programme monitor and its existing expandable tracking-code table.

Design reference: `C:/Users/doict/Downloads/Programme Portfolio Options.html`. Related prototype sources already exist in this directory under `src/` and `artifact/`; preserve them. Treat the prototype's example data, dated “Live” snapshot and implementation notes as design reference, not current database evidence or authorization instructions.

## Agreed direction and defaults

- Use Option A's shared page: header, permitted navigation/actions, portfolio callout, four KPI tiles, status tabs, search and filters.
- Add a clearly labelled **Table / Cards** switch above the results. Table is the initial default. Remember an explicit choice for the signed-in user within their session.
- Table mode uses Option A's expandable programme rows. Cards mode uses Option B's programme card design inside the same page and controls.
- Both modes use the same authorized programmes, filters, sort order, pagination and summary values. Switching changes presentation and retains the current query state.
- On phones, cards use a single column and table mode remains available with horizontal scrolling. Resizing does not override the user's choice.
- Keep the individual programme monitor and its tracking-code table working as already implemented.

The reference's prototype controls (design option, example/live dataset, standalone theme/language controls) are not product controls. Use the application's existing theme and language settings.

## Page behaviour

### Shared header and summary

Show the programme title/subtitle, navigation to permitted tracking areas and a New programme action when `TrackingAuthorizationService::canCreate()` allows it.

The callout identifies operational programmes needing attention and the highest-priority next action, including the operation head when assigned. Use a neutral empty/no-operational-programmes message when there is no operational evidence; do not describe an empty portfolio as “all on pace.”

Four KPI tiles show:

1. Operational programmes, with a text-labelled health breakdown.
2. Value received, receipt count, distinct suppliers and completeness information.
3. Distinct offices reached, with allocated offices still waiting as supporting information.
4. Active and draft tracking-code counts, clearly labelled.

Initial value scope: active programmes and their active tracking codes, across recorded fiscal years. Label it accordingly; the prototype's “this year” claim needs a real fiscal-year filter before it can be used. Closed/archived programme values can appear on their own rows/cards without entering current operational KPI totals.

KPI tiles and tab counts reflect the search, funding and “my programmes” filters before applying the status tab. The selected status tab and pagination affect the results. Explain this scope beside the summary so totals remain understandable when a tab is selected.

### Filters, sorting and switching

Validate GET parameters on the existing index route:

| Parameter | Supported values / meaning |
| --- | --- |
| `q` | Bounded search across programme title, purpose, owner and assigned team names; match stored Bengali and English text. |
| `status` | `all`, `Active`, `Planning`, `Closed`, `Archived`. |
| `funding` | `all`, `ADP`, `REVENUE`, `OTHER`. |
| `mine` | Boolean; an actual operation-unit assignment for the current user. Company oversight alone does not mark a programme as “my team.” |
| `sort` | Whitelisted keys: title, health, delivery gap, value, offices, codes, team, last receipt. |
| `direction` | `asc` or `desc`; stable programme-ID tie-break. |
| `view` | `table` or `cards`; explicit URL choice takes precedence over the remembered session preference. |
| `page` | Valid positive page; paginate after filtering and sorting the complete authorized summary result. |

Default ordering: operational problems first by severity, then other operational programmes, setup, closed and archived; within equal priority, most recently updated first, then ID. Keep insufficient-evidence/no-target states distinct from healthy progress.

Table column headers and a Sort control available in both layouts use the same sort keys. Numeric values and dates sort by their actual values. Missing values have an explicit, consistent placement rather than comparing translated “Unavailable” text.

Changing filters resets to page 1. Switching views retains filters, sorting and page. Clear filters retains the view choice. View links work without JavaScript; enhancement may make the switch smoother. Keep preferences actor-specific and clear temporary interaction state when the working user/context changes.

### Option A table

Columns: expand control; Programme; Health; Delivered vs plan; Value received; Offices reached; Tracking codes; Team; Last receipt.

- Programme identity includes title, status, funding, owner, fiscal-year labels and a “Your team” marker where applicable.
- Summary rows remain compact. Long names/purposes cannot expand them into product-length lists.
- Delivery shows actual progress against expected pace. Planning rows show setup facts rather than delivery progress.
- Value distinguishes complete totals, unavailable totals and a verified subtotal.
- Office/code counts have explicit captions; team avatars retain accessible names/designations.
- Each expandable detail row shows up to three priority issues (or setup checklist), the operation head/next action, a code-health mix and permitted Monitor/Manage/Report/Properties links.
- All rows begin collapsed when JavaScript is active. Use stable programme IDs, `aria-controls`, `aria-expanded`, translated labels and native keyboard controls.
- Retain expansion state for still-visible programme IDs when switching layouts or sorting, without saving programme contents in browser storage.
- Reuse the existing monitor disclosure/pairing pattern where appropriate; verify the tracking-code table remains unaffected. Server sorting must operate across the complete filtered result, before pagination.
- Keep a readable server-rendered fallback when JavaScript is unavailable.

### Option B cards

Cards show health/status, funding/year, title/owner, pace, value, office/code counts, the leading issue and operation head/team. Use the same action permissions and links as table details.

Planning cards show the real setup checklist and permitted next action. Closed/archived cards use a quieter presentation and appropriate report/monitor links. Preserve the shared sort order rather than regrouping cards into a different result order.

Use responsive columns with the existing theme tokens. The active layout is the only one exposed in the accessibility tree; hidden duplicate controls must not receive keyboard focus. Announce the selected view and result count without moving focus unexpectedly.

## Backend work and data correctness

The current `InitiativeController::index()` loads scoped initiatives, owners and relation counts. It does not supply the monitor metrics needed by this design. `TrackingMonitorService::programme()` also builds charts/items/receipts/activity-related data for an individual programme; invoking it once per portfolio row would add avoidable repeated work. The prototype reports a previous slow run, but its timing is dated reference, not a measurement from this planning task.

Add a lightweight `TrackingPortfolioService`:

1. Start with `TrackingOfficeAccess::programmes()` and the existing initiative scope. Resolve authorized programme IDs before loading related aggregates.
2. Batch-load the required codes, targets, allocations, associations, minimal receipt evidence and team/owner metadata for those IDs. Avoid per-row relation queries or full monitor construction.
3. Reuse `TrackingReceiptResolver` and `TrackingMonitorMetrics`, extracting shared reductions where necessary so portfolio and monitor definitions cannot drift. Preserve receipt evidence and legacy-fallback checks; benchmark the resolver's legacy lookups as part of this work.
4. Produce a compact per-programme summary: identity/status/funding/years, permissions, membership, team/head, setup facts, code counts/health, actual/expected delivery, financial completeness/subtotal, office sets/counts, last receipt, issue count/top issues and next action.
5. Compute portfolio totals from those authorized inputs. Apply filters and sorting, then paginate result rows/cards. No charts, full receipt tables, product price strips, document payloads or timelines are needed for this page.

Required definitions:

- Preserve matrix allocation caps and the existing mean-of-target delivery calculation. A simple ratio of all received quantities to all planned quantities changes its meaning across product categories and must not replace it.
- Draft codes do not contribute to operational delivery/value totals. Show their setup counts separately.
- Unknown receipt values remain unknown; never convert them into zero or advertise a known subtotal as a complete total.
- Deduplicate office and supplier IDs across programmes for portfolio-wide distinct counts. Count receipt identities consistently with the monitor, including office context where document numbers can repeat.
- Distinguish reached offices outside recorded allocations from reached allocated offices. Use the allocated subset for any coverage ratio and report additional reached offices separately.
- Derive attention from the existing active-code/evidence rules. A draft-code warning or missing target alone must not turn into a false delivery delay or a green “on pace” claim.
- Team readiness requires the actual head/officer requirements; a nonempty team is insufficient. “Code active” and “receipt received” are separate facts. This checklist explains setup and does not introduce new activation rules.
- The application uses `MONITOR`; the mockup's `OBSERVER` label is not a new role.
- Use application time/timezone and recorded dates. `updated_at` is not proof of closure/archive time. Display “Updated” unless an actual transition date exists.

Retain `gov.can:tracking.programmes.view`, existing object checks and narrower action permissions. All counts, issue text, people, search results and totals must obey the same programme visibility. No new route or database migration is expected for the initial implementation; session display preference is sufficient. If a new protected endpoint becomes necessary, declare its named ability and add route coverage.

## Implementation sequence

1. **Summary service and correctness:** build the authorized batched summary, shared reductions, filters and metric-parity fixtures. Measure query growth before connecting the complete UI.
2. **Shared portfolio page:** replace the current status-grouped index scaffold with the common header, callout, KPIs, status tabs, filters, sorting and pagination. Use the existing index route.
3. **Table and disclosure details:** implement Option A with accessible row expansion and retained state.
4. **Cards and view preference:** add Option B card partials and the Table / Cards switch with identical results/order/totals.
5. **Verification and handoff:** finish bilingual strings/theme styling, performance measurements, automated checks and live exercises; record results and limitations.

Expected file areas:

- `packages/gov-store/tracking/src/Http/Controllers/InitiativeController.php`
- New `packages/gov-store/tracking/src/Services/TrackingPortfolioService.php`
- Shared metric/receipt helpers only where required for consistent reductions.
- `packages/gov-store/tracking/src/resources/views/initiatives/index.blade.php` and portfolio partials.
- Tracking CSS/JavaScript and their existing theme asset registration.
- `packages/gov-store/tracking/src/resources/lang/{en-US,bn-BD}/general.php`
- A focused `tests/Feature/GovStore/TrackingPortfolioTest.php`, reusing the isolated SQLite fixtures.

## Acceptance and verification

- The same query in Table and Cards produces identical programme IDs, order, total counts, permissions and metric values, including across page boundaries.
- Expansion, keyboard controls, numeric/date sorting and layout switching work with multiple open rows. The existing tracking-code monitor retains its behaviour.
- Test all four programme states; no programmes/no matches; absent team/targets/receipts; incomplete evidence; matrix over-allocation; shared offices/suppliers; repeated receipt numbers; long bilingual/untrusted text; multiple fiscal years; revoked membership; foreign companies and forged query values.
- Compare portfolio summaries with full-monitor results for controlled fixtures. Verify no unauthorized programme leaks through aggregates, search, tabs, contacts, summaries or session state.
- Exercise at least 1, 10 and 50 programmes with representative code/receipt volumes in isolation. Record query count, elapsed time and memory. Keep database-query growth bounded by batches rather than one monitor/query set per programme; profile any slow legacy receipt resolution. Set a measured response-time budget after establishing this baseline, before handoff.
- Run the focused portfolio tests, existing tracking tests, relevant theming tests and `G1AuthorizationTest.php` using installed PHP. Keep automated data isolated; do not reset the development database.
- Check compiled Blade PHP and JavaScript syntax. Verify Bengali/English key parity, both layouts in light/dark themes, narrow/wide layouts, keyboard focus and the no-JavaScript fallback.
- Live-test on the authorized local host with rediscovered fixtures/roles, checking data and displayed metrics. Restore language/context/preferences changed for testing, remove owned helpers and retain audit records. Save safe verification screenshots.
- Report implemented behaviour, exact verified checks and remaining limitations separately. Local checks do not establish CI success, production readiness or WCAG compliance.

## Present task outcome

Implemented the authorized portfolio summary service, shared controls/KPIs, expandable table, card grid, session view preference, server sorting/pagination and bilingual theme styling. The application shell supplies the single page heading. Local regression, metric-parity, performance and browser results are recorded in the linked verification record; its limits are separate from implementation completion.
