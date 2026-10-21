# Gov-Store Geo Monitoring — Administrative Choropleth Map (Implementation Plan)

> **Scope:** an interactive, drill-down SVG map of Bangladesh administrative areas (division → district → upazila →
> union) coloured by monitoring metrics that other gov-store packages supply. Reference-data parts go in
> `packages/gov-store/geo-areas`; the map, metric contract and aggregation go in a new
> `packages/gov-store/geo-monitoring`. Nothing here writes business data.
> **Status:** proposal; nothing implemented · **Updated:** 2026-10-21
> **Phase 0 (boundary data) done:** `docs/plans/geo-boundary-data-plan.md`; boundary set `bd-boundaries-2026-10-21.1`
> in `geo-areas/src/database/data/boundaries/`.
> **Reference model:** `docs/plans/tracking-monitor-redesign-plan.md` (scope keys, read-only monitor views),
> `docs/plans/dynamic-theming-plan.md` (tokens, `<x-gs::*>` kit, compliance ratchet),
> `packages/gov-store/geo-areas/DATASET.md` (dataset change procedure),
> `packages/gov-store/geo-areas/GEOCODE-SUPPLEMENT-DESIGN.md` (no new geography tables), `AGENTS.md`,
> `docs/security/govstore-agent-security.md`, `docs/testing/local-live-testing.md`.

---

## 1. Summary

GovStore can say *which office* holds stock, receives programme deliveries or has a storekeeper, but not *where in the
country* things are lagging. The only map today is `TrackingTileMap`: a hand-placed grid hard-coded to Naogaon
(`tracking/src/config/tile-maps/naogaon.json`).

The plan adds one read-only screen, a scope-aware choropleth with a side panel and table, and a provider contract so
that any package can contribute a metric. It uses the Snipe-IT/Laravel stack as it is: Blade, vanilla JS, `--gs`
tokens. There is no React, no GIS server and no spatial database.

| View | Question it answers | Proposed route |
|---|---|---|
| **Geo monitor** | Which areas in my scope are behind on metric X, and which offices are responsible? | `GET gov-store/geo-monitor?area=&metric=` |

---

## 2. What already exists (audit, 2026-10-21)

| Need | Existing piece | Notes |
|---|---|---|
| Area hierarchy | `gov_geo_areas` (`geo-areas`), 9,111 rows: `GeoAreaId`, `hid` path (`/36/28/29/32/`), `geo_type`, `geo_code`, `parent_geo_code`, `bn_name`, `en_name`, `GeoLevel` | `GeoAreaId` is referenced by FKs from `gov_location_profiles`, `gov_ict_jurisdictions` and tracking `GEOGRAPHY` scopes; it must stay stable |
| Office → area | `gov_location_profiles.geo_area_id`, `geo_area_verified_at` | One area per office |
| Scope | `TenantContext->allowedLocationIds` / `allowedInventoryLocationIds`, `GovAccess` abilities, `TenantOrganizationResolver::jurisdictionLocations()` (`hid` prefix match) | Reuse; do not re-derive |
| Area lookups | `GeoAreaService::getById/search/isWithinBoundary`, `canonicalType()` (`divison` → `division`) | Extend |
| Provider pattern | `MetadataRegistry`, registered by tracking only `if ($this->app->bound(...))` | Copy for metrics |
| Map UI | `x-gs::tile-map`, `.gs-tile-map` in `theming/components/17-monitoring.css` | Schematic grid; becomes the fallback |
| BBS matching | `geo-areas/tools/reconcile_bbs_geocodes.py` (conservative, never assigns fuzzy matches) | Basis for the boundary crosswalk |

### 2.1 Dataset facts that shape the design

- **`geo_code` is not a BBS code, but every row now has one.** For example, Dhaka division's `geo_code` is `68`.
  Since dataset `bd-admin-areas-2026-10-21.1`, every row also carries `bbs_code` / `bbs_code_legacy`, and these map
  to OCHA P-codes by rule. Names must still not be used to infer IDs (DATASET.md). See
  `geo-boundary-data-plan.md` §4.
- **`geo_code` is unique within each type.** No duplicates exist among districts, upazilas or unions.
- **Urban units sit outside the `hid` tree.** Of the 9,111 rows, 3,990 have no `hid`: `city` (12), `city_thana` (95),
  `pourasabha` (325), `pouro_ward` (3,036), `thana_ward` (521) and `country` (1). All 309 distinct pourashava
  `parent_geo_code` values match an upazila `geo_code`. City parents still need checking.
- **The top of the tree.** Divisions have `parent_geo_code = 10000001` (country); the country row has no `hid`.
- **Malformed rows.** Two `union` rows have a shifted column (a comma in the name) and a wrong `GeoLevel`. Flag them in
  the boundary build report; do not rewrite them outside the DATASET.md procedure.

---

## 3. Decisions (deviations from the original proposal)

1. **No `administrative_areas` table.** Reuse `gov_geo_areas`. Boundaries are versioned static files keyed by
   `GeoAreaId`, so no migration is needed.
2. **Two hierarchies, connected only through offices.** Administrative areas (`gov_geo_areas`) and the organisational
   hierarchy (company → office) stay separate and meet through `gov_location_profiles.geo_area_id`.
3. **Providers return values per office, not per area.** The map package resolves which offices are in scope and under
   the area, then rolls the values up. Providers do not implement `hid` maths or office scope.
4. **No D3.** Precomputed SVG paths need only DOM swapping, colouring and a `viewBox` tween. That is vanilla JS in the
   style of `tracking/resources/js/monitor.js`, which uses progressive enhancement.
5. **Boundary processing is offline only.** Mapshaper and the projection run in a reviewed build step. The server serves
   static JSON.

---

## 4. Architecture

### 4.1 `geo-areas` additions (reference data, reusable by other screens)

| Piece | Responsibility |
|---|---|
| `GeoAreaService::children(GeoArea $area)` | Next level by `hid` prefix and `GeoLevel + 1`; the country maps to divisions |
| `GeoAreaService::rollupArea(int $id)` | Walks an urban unit up through `parent_geo_code` and type to the nearest area with an `hid`; returns `null` when unresolved |
| `boundaries/<version>/manifest.json` | Version, source, licence, retrieval date, SHA-256 and coverage per level |
| `boundaries/<version>/children/<GeoAreaId>.json` | `{version, id, viewBox, outline, areas: [{id, kind, path}]}`, integer paths in one national frame (contract: `geo-boundary-data-plan.md` §8) |
| `GET gov-store/api/geo/areas/{area}/children` | Names (by locale), types, `hasChildren` and paths; `web` + `auth` middleware like `gov.geo.search`; cache headers keyed to the version |

### 4.2 New package `gov-store/geo-monitoring`

```
packages/gov-store/geo-monitoring/
  composer.json                      # GovStore\GeoMonitoring\, provider auto-discovery
  src/
    Contracts/MapMetric.php
    Registry/MapMetricRegistry.php   # singleton; packages register when bound
    Services/ScopedOfficeResolver.php
    Services/MetricAggregationService.php
    Metrics/OfficeRegistrationMetric.php   # first provider (organization data)
    Http/Controllers/GeoMonitorController.php
    Providers/GeoMonitoringServiceProvider.php
    config/abilities.php             # merged into govstore-abilities
    routes/web.php
    resources/views/monitor.blade.php
    resources/views/partials/map.blade.php
    resources/css/geo-monitor.css    # GsTheme::assets()->css('geomonitoring', …)
    resources/js/geo-monitor.js      # GsTheme::assets()->js('geomonitoring', …)
    resources/lang/{en-US,bn-BD}/general.php
```

Root core touch: one `require` line (`"gov-store/geo-monitoring": "dev-master"`) and one PSR-4 autoload entry in
`composer.json`, the same as the other gov-store packages.

### 4.3 Metric contract

```php
interface MapMetric
{
    public function key(): string;          // 'tracking.received_share'
    public function label(): string;        // translation key
    public function ability(): string;      // gov ability required to see it
    public function aggregation(): string;  // 'sum' | 'ratio'
    public function inventory(): bool;      // true → use allowedInventoryLocationIds

    /** @return array<int, array{value?: float, numerator?: float, denominator?: float}> keyed by location_id */
    public function valuesByOffice(array $locationIds, array $filters): array;
}
```

- `ratio` metrics roll up as Σnumerator / Σdenominator, never as an average of percentages.
- `$locationIds` has already been intersected with the viewer's scope. Providers still apply their own domain rules on
  top. For example, tracking keeps the 2026-10-18 owner decision that programme access is required to see a programme's
  deliveries.
- Packages register only when the registry is bound, so none of them gains a hard dependency on geo-monitoring:
  ```php
  if ($this->app->bound(MapMetricRegistry::class)) {
      $this->app->make(MapMetricRegistry::class)->register(new TrackingReceivedShareMetric);
  }
  ```

### 4.4 Request flow

1. `ScopedOfficeResolver` resolves the viewer's allowed location IDs from `TenantContext`. It uses the inventory set
   when the metric is an inventory metric; a global context means all offices.
2. It intersects them with the offices whose `geo_area_id` (or its `rollupArea`) lies under the requested area's `hid`.
3. It calls the metric provider with those IDs, then `MetricAggregationService` groups the values by child area.
4. The response contains values per child `GeoAreaId`, a `coverage` block (offices counted, unplaced, unverified) and an
   `unplaced` bucket.

### 4.5 Routes

| Method · path | Name | Purpose |
|---|---|---|
| GET `gov-store/geo-monitor` | `gov.geo-monitor.index` | Page (breadcrumb required) |
| GET `gov-store/api/geo-monitor/metrics?area=&metric=&…` | `gov.geo-monitor.metrics` | Values per child area |
| GET `gov-store/api/geo-monitor/areas/{area}/offices?metric=` | `gov.geo-monitor.offices` | Side panel: offices in scope with values and links |
| GET `gov-store/api/geo/areas/{area}/children` | `gov.geo.children` | Boundaries (geo-areas, §4.1) |

All routes need `geo.monitor.view` (`gov.can:` middleware), and each metric also checks its own `ability()`.

### 4.6 Access and scope

| Viewer | Initial area | Outside scope |
|---|---|---|
| Superuser / national | Country | — |
| ICT officer | Their jurisdiction area(s) | Greyed out, no values, no office counts |
| Company admin | Country, values limited to company offices | Same |
| Office admin | The office's district | Same |

- The API never trusts a submitted area or office ID: values are computed only from the server-derived scope.
- Ability `geo.monitor.view` roles: `superuser`, `ict_officer`, `company_admin` and `office_admin`. Add its label to
  tenant-scope's `access.php` lang files (the `G1AuthorizationTest` convention).
- Menu: one item through `MenuRegistry`, in the `gov-tracking` group or `gov-workspace` (groups have at most 7 items).

---

## 5. Frontend

- **Page:** `<x-gs::page-header>`, a metric picker and a breadcrumb trail of areas; then the map, a side panel and
  `<x-gs::table>`. The table shows the same data and works as the print, screen-reader and no-JS fallback.
- **JS** (`geo-monitor.js`), which does four things:
  1. Load the children's paths.
  2. Fetch the metric values and assign colour classes.
  3. On click, push the new state to the URL, swap the paths and tween the `viewBox` with `requestAnimationFrame`.
  4. At the lowest level, open the offices side panel.
- **Colour:** a sequential scale from `color-mix(in oklab, var(--gs-color-primary) N%, var(--gs-color-surface))` in
  5 steps, like `--gs-tile-mix`. "No access" and "no data" use hatched or sunken surfaces. A legend is required. No
  colour literals and no `<style>` blocks, so the `compliance-baseline.json` counts must not rise.
- **Accessibility:** each path is focusable with a `<title>` in the locale name (`bn_name` / `en_name`, as in
  `GeoAreaController`); Enter or Space drills down.
- **Package CSS:** it sits in the `gs-packages` layer, which unlayered Bootstrap beats. Use the kit's scoping
  conventions, and rebuild the gs-theme bundles after CSS/JS edits.

---

## 6. Metrics roadmap

| Phase | Provider (package) | Metric | Aggregation |
|---|---|---|---|
| 2 | organization | Offices registered; share with a verified location (`geo_area_verified_at`) | sum / ratio |
| 3 | tracking | Received vs allocated share per programme or code (replaces `TrackingTileMap`) | ratio |
| 4 | office-membership | Offices with an active storekeeper (`gov_location_roles.storekeeper_id`) | ratio |
| 4 | store-operations | Posted receipts and issues in a period | sum |
| 4 | asset-tagging | Asset verification rate | ratio |

Each metric needs an agreed definition before it is shown nationally. The Superset plan's caveat about reconciling
against the posted ledger applies here too.

---

## 7. Phases

### Phase 0 — Boundary data (blocking)

Done under `docs/plans/geo-boundary-data-plan.md` (sources measured, decisions D1–D5, reviewed crosswalk, boundary
set `bd-boundaries-2026-10-21.1` with all build gates passing). The outline below is kept for history.

1. **Choose the source.** The owner picks a source with a known publisher and reuse licence, and confirms that
   union-level (adm4) coverage is available.
2. **Build the crosswalk.** Extend `reconcile_bbs_geocodes.py` output into a reviewed `GeoAreaId` ↔ BBS crosswalk.
   Unmatched areas go to a review report and are never matched by name.
3. **Run the offline pipeline** (`geo-areas/tools/`, not run on servers):
   1. Simplify with Mapshaper, keeping shared borders, with a separate tolerance per level.
   2. Project into a fixed national viewBox.
   3. Join to `GeoAreaId` through the crosswalk.
   4. Output one JSON file per parent, each with its own viewBox.
   5. Write a coverage report.
4. **Record the release** (version, source, licence, retrieval date, SHA-256) in `DATASET.md` in the same change.

### Phase 1 — geo-areas

- `children()`, `rollupArea()`, the boundary manifest loader and the `gov.geo.children` endpoint.
- Extend `GeoAreasPackageTest`:
  - country → divisions
  - children by `hid`
  - pourashava and city roll-up
  - every boundary `id` exists in `gov_geo_areas`
  - locale names

### Phase 2 — geo-monitoring skeleton

- The package, registry, `ScopedOfficeResolver`, aggregation, metrics API, ability, menu item and page with division
  and district levels.
- The organization provider.

### Phase 3 — Tracking provider

- `TrackingReceivedShareMetric`.
- Embed the map partial in the programme monitor in place of `x-gs::tile-map`. Keep the tile-map as a fallback where
  boundaries are missing, then retire `naogaon.json`.

### Phase 4 — Depth and breadth

- Upazila and union levels, the offices side panel and the remaining providers.
- Cache by `(metric, area, scope hash, filters)` with a short TTL if profiling calls for it.

---

## 8. Verification

- **Feature tests** in `tests/Feature/GovStore/GeoMonitoringTest.php`:
  - Scope leakage: an ICT officer for district A who requests district B gets no values or counts.
  - Ratio roll-up is correct.
  - The unplaced bucket is reported.
  - Missing ability → 403; missing metric ability → 403.
  - Names follow the locale.
  - Areas outside scope return no values.
- **Authorization:** `G1AuthorizationTest` covers the new ability label.
- **Theming:** `GovStoreThemeComplianceTest`; the baseline must not rise.
- **Full suite:** `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`.
- **Local walk-through** on `127.0.0.1:8010` as seeded fixture users (a superuser, an ICT officer, an office admin) to
  confirm the starting area, greyed areas outside scope, drill-down and the table fallback, in light and dark mode.

---

## 9. Risks

| Risk | Mitigation |
|---|---|
| No licensed source with union-level boundaries | Ship division and district first; keep the tile-map fallback below that |
| Crosswalk gaps leave areas with no polygon | Coverage report; "no boundary" state in the UI; the table always lists every area |
| Offices on urban units, or with no or unverified location | `rollupArea()`, a visible unplaced bucket, and unverified counts in `coverage` |
| Values leak through aggregates | Scope is applied before aggregation; areas outside scope return nothing; tests cover tampered parameters |
| Payload size at union level | One file per parent, simplified per level, cached by version |
