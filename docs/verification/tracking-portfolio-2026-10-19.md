# Programme portfolio verification

19 October 2026, Asia/Dhaka. Local development installation only.

## Implemented

- Option A's portfolio table with expandable programme details and Option B's card grid behind a labelled Table / Cards switch.
- Shared warning/neutral callout, four KPIs, status counts, search, funding/team filters, sorting and 20-programme pagination. Server filtering/sorting happens before pagination; out-of-range pages resolve to the last available page.
- Actor-specific session view preference and context-specific disclosure-ID persistence. View switching retains filters, sort and page; readable server-rendered details remain available without JavaScript.
- Batched, freshly scoped `TrackingPortfolioService`. Allocation math and attention/financial reductions are shared with the monitor. Unknown evidence stays unknown; office/supplier/receipt counts are distinct across the portfolio.
- Existing route abilities and object/action permissions are retained. No new routes, migrations, account permissions or inventory mutations were introduced.
- Bengali/English labels and theme-token styling. The native application heading appears once. Wrapped sort headings have a full clickable block area.

## Automated and build checks

PHP 8.4.15, Xdebug disabled; all database tests use isolated SQLite in memory.

```text
php -d xdebug.mode=off -d memory_limit=1G vendor/bin/phpunit
  tests/Feature/GovStore/TrackingPortfolioTest.php
  tests/Feature/GovStore/TrackingMonitorTest.php
  tests/Feature/GovStore/TrackingCorrectnessTest.php
  tests/Feature/GovStore/Theming/MonitoringKitComponentsTest.php
  tests/Feature/GovStore/Theming/GovStoreThemeComplianceTest.php
  tests/Feature/GovStore/G1AuthorizationTest.php
```

Result: **127 tests, 2,762 assertions passed**, 26.5 seconds, 144 MB reported process memory. This includes inherited workflow coverage, not 127 newly written portfolio tests.

Focused coverage includes monitor/portfolio metric parity for all three plan types; drafts excluded from live value; distinct offices/suppliers and repeated receipt numbers in different offices; incomplete values; all four programme states; empty/no-target states; bilingual escaping; identical row/card IDs; actual head/officer readiness; severity ordering; native-admin and ordinary office-role boundaries; company oversight versus team membership; revocation; per-actor preference; invalid filters; numeric sorting with nulls last; sorting before pagination and page clamping.

Compiled PHP syntax passed for the portfolio index and every portfolio partial. JavaScript syntax and Git whitespace checks passed.

An isolated `gs-theme:build` generated all six themes and the shared CSS/JS bundles, including the new portfolio assets. Build output was placed in an owned temporary directory and removed afterward; production bundles/configuration were not replaced. The build reported the existing absence of bundled WOFF2 files, so installed fallback fonts remain in use.

## Isolated summary benchmark

One code, one target, two office allocations and one receipt per programme. Times measure summary computation, not browser/network rendering. Peak memory is the PHP test process peak, not incremental service memory.

| Programmes | Database queries | Summary time | Process peak |
| --- | ---: | ---: | ---: |
| 1 | 16 | 4.7 ms | 78 MB |
| 10 | 16 | 11.1 ms | 78 MB |
| 50 | 16 | 48.6 ms | 78 MB |

The focused benchmark asserts constant query count and a two-second ceiling for this isolated 50-programme fixture. These results do not define a production response-time guarantee for arbitrary receipt history.

## Fresh local checks

Host: `http://snipeit.local/`. Database target was privately resolved and verified as `snipeit` before read-only inspection. The existing browser actor was user ID 3, assigned `OFFICER` on visible initiative 4, working in company 412 / office 4. No login, permission assignment or access-mode override was needed.

- The actor saw one authorized programme. This was consistent with current assignments, rather than the prototype's dated “Live” list of ten programmes.
- Initiative 4 portfolio values matched the full monitor for actual delivery, expected pace, value, verified subtotal, completeness, receipt count, supplier count, active codes and draft codes.
- Latest measured live summary computation: 18.5 ms, excluding application bootstrap and browser/network time.
- Table and Cards displayed the same programme and KPI values. Only the active layout was rendered.
- Search `ICTD`, ADP funding, own-team filter, value sorting and direction survived layout switching. Reset cleared filters and retained the selected view.
- A nonmatching funding selection showed zero results and a neutral callout. The view switch remained available.
- Programme detail expansion survived switching to cards and back, header sorting and language reloads. Enter and Space expanded/collapsed it.
- All eight sort headings were exercised in both directions. The wrapped Tracking codes heading initially had an incomplete hit area; after making header links block elements, both directions navigated and updated the selected sort correctly.
- English and Bengali showed translated controls/details. Light and dark modes rendered the active layout and selected-view highlight correctly.
- At a 390-pixel viewport, cards used one column without body overflow. Table mode kept horizontal scrolling within its frame and respected the selected layout.
- The browser reported no console errors, and no debug toolbar was present on the protected portfolio response.
- Existing incomplete receipt evidence stayed flagged. The task did not repair historical source data or infer missing values/dates.

Business table counts were identical before and after the read-only exercise:

| Table | Before | After |
| --- | ---: | ---: |
| `gov_initiatives` | 10 | 10 |
| `gov_tracking_codes` | 33 | 33 |
| `gov_tracking_associations` | 1,327 | 1,327 |
| `gov_documents` | 282 | 282 |
| `gov_inventory_movements` | 1,311 | 1,311 |

Language, light mode and Table preference were restored. Temporary viewport overrides were reset; the test disclosure was collapsed. Owned helper files and temporary build output were removed. No business fixtures were retained; existing records and audit history were preserved.

## Evidence and limits

- [Table with expanded detail](tracking-portfolio-2026-10-19-table.jpg)
- [Card grid](tracking-portfolio-2026-10-19-cards.jpg)

Live checks used the existing officer's one-programme portfolio. Multi-programme ordering/pagination, other actor boundaries and 50-programme performance were verified in isolated fixtures. The no-JavaScript check verified the readable server markup and native GET navigation design; JavaScript was not disabled in the live browser. All six themes built, while live visual checks used the existing Lavender theme in light/dark modes. These local checks are not CI success, a full application regression run, production load testing or WCAG certification.
