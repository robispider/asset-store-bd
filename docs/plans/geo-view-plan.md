# Gov-Store Geo View — Pluggable Geographic Data View (Implementation Plan)

> **Scope:** one reusable, configurable map view of Bangladesh's administrative areas that any page in the national
> inventory and asset register can embed. It covers drill-down (division → district → upazila → union), zoom and pan,
> area selection, legend, tooltip, table fallback and deep links. Data comes from a JavaScript API backed by
> server-side **data sources** that domain packages register. It reads data only and writes no business data.
> **Status:** approved 2026-10-22 (D1–D8 as recommended, §10); implemented on branch `geo-view`, phases A–H (log and open items: `docs/plans/geo-view-progress.md`) · **Updated:** 2026-10-22
> **Pilot page:** tracking programme monitor `gov-store/admin/tracking/initiatives/30`
> **Builds on:** `docs/plans/geo-boundary-data-plan.md` (boundary set `bd-boundaries-2026-10-21.1`, data contract §8),
> `docs/plans/geo-monitoring-plan.md` (this plan generalises its metric contract and replaces its §3.3, §4.2–4.4 and §5;
> see §3), `docs/plans/tracking-monitor-redesign-plan.md` §5–6 (metric definitions, programme/office/inventory keys),
> `docs/plans/dynamic-theming-plan.md` (tokens, `<x-gs::*>` kit, compliance ratchet), `AGENTS.md`,
> `docs/security/govstore-agent-security.md`.

---

## 1. Summary

The boundary build gives GovStore render-ready shapes for every division and district, 502 of 506 upazilas and 98% of
unions, all keyed by `GeoAreaId`. What is missing is the view that draws them, and a clean way for each module to put
its own numbers on it.

This plan splits the problem into four layers that meet only through versioned contracts:

```
 Host page (Blade)                 <x-geoview::map preset="tracking.programme" :params="[...]"/>
      │  config (gs-geo-config/1)
      ▼
 Geo View engine (browser JS)      render · drill · zoom/pan · select · legend · tooltip · table · URL state · events
      │  fetch boundaries            │  fetch data (gs-geo-data/1)
      ▼                              ▼
 Boundary service (geo-areas)      Data API + registry (geo-view)  ──►  Data sources (tracking, organization, …)
 static, versioned, not scoped     authorizes, scopes, aggregates        office facts or area values
```

- **The engine is neutral.** It knows boundary files, a config object and a data payload, and nothing about tracking,
  BBS or permissions. It is demoed in the theming Lab with fixture data.
- **Data sources are pluggable.** A package registers a `GeoDataSource`, such as `tracking.programme`, and a preset
  (its default configuration). It does not write any map code.
- **The server is the only authority.** Every data request is re-authorized and re-scoped on the server. The config a
  page sends to the browser is a convenience, not a permission.
- **Configuration is layered:** engine defaults → intent → preset → host attributes → URL state → runtime JS calls.
  Keys can be locked at any layer.

The stack stays as it is: Blade, vanilla JS, SVG and `--gs` tokens. There is no React, D3, map tiles, GIS server or
spatial database.

---

## 2. What exists today (audit, 2026-10-21)

| Need | Existing piece | Notes for this plan |
|---|---|---|
| Boundary shapes | `geo-areas/src/database/data/boundaries/bd-boundaries-2026-10-21.1/`: 563 `children/<GeoAreaId>.json` (integer SVG paths in one national 10 m frame), `level-1..4.json` (WGS84 TopoJSON), `manifest.json` (SHA-256 per file) | Per-parent files suit drill-down. **Missing:** per-area bbox and label point, and projected *flat* level files ("all 64 districts at once"). Added in phase B (§9) |
| Area tree | `gov_geo_areas` (`hid`, `GeoLevel`, `parent_geo_code`, `bn_name` / `en_name`), `GeoAreaService` (`getById`, `search`, `isWithinBoundary`, `canonicalType`) | `children()` and `rollupArea()` are planned in geo-monitoring phase 1 and still needed |
| Area API | `GET gov-store/api/geo/search` (`gov.geo.search`, web + auth), with `restrict_hid` | Reused for the map's "find area" box |
| Office → area | `gov_location_profiles.geo_area_id` (57 of 58 local offices placed: 1 country, 8 division, 19 district, 29 upazila) | Offices sit at **any** level, not only leaves; §6.3 handles offices placed on the parent itself |
| Scope | `TenantContext` (`allowedLocationIds`, `allowedInventoryLocationIds`), `GovAccess`, `TrackingOfficeAccess`, `InitiativeScope` | Tracking's programme key grants *every* office in the programme, wider than the viewer's office scope (tracking plan §6). So scope must be owned by the data source; see §3 |
| Provider pattern | `MetadataRegistry`, registered only `if ($this->app->bound(...))` | Copied for `GeoSourceRegistry` |
| Map UI today | `x-gs::tile-map` and `TrackingTileMap` (hand-placed grid, Naogaon only, `tile-maps/naogaon.json`), used on the programme monitor and the code dashboard | Replaced by the geo view; the grid is retired after both pages move |
| Assets | `GsTheme::assets()->css/js()` concatenates **all** package JS into one bundle loaded on every page; plain IIFE scripts, no transpile step | The engine should not add tens of KB to every page, so it is loaded only on pages that use it (D2) |
| CSP | `SecurityHeaders`: `connect-src 'self'`, `img-src 'self' data:` | Same-origin fetches and PNG export via `data:` URLs both work; no external tiles are possible anyway |
| Compliance | `GovStoreThemeComplianceTest`, `compliance-baseline.json` (`{}`): no colour literals, no `<style>`, no inline colour `style=` | Map fills must be classes, not inline colours (§7.4) |
| Test tooling | PHPUnit feature tests, Playwright (`npm run test:themes`), pa11y (`tests/pa11y`) | JS unit tests use `node --test` (no new dependency); browser tests use the existing Playwright |

### 2.1 The pilot page (initiative 30, local data)

Walked on `127.0.0.1:8010` as the superuser and checked against the database:

- 4 active codes, all **product-based plans** (`CATEGORY`), each with one office, 48 allocation rows and 220
  receipt associations. Programme delivered 2.3% (expected 6.3%), value ৳1.03 crore.
- 4 offices: a **division-level** regional office in Dhaka (`GeoAreaId` 3142, `hid /68/`) and district offices in
  Patuakhali (2390), Bogura (1246) and Jamalpur (4949), so the offices span four divisions.
- Every office shows "no office-level target", because product-based plans do not allocate by office. A
  "share delivered" layer is therefore empty for this programme; "received quantity" and "value" are not.
- The "where" section is the Naogaon tile grid. Its note says the positions are not geographic.

So the pilot already exercises four things the design must handle: offices above leaf level, a programme spread across
the country, a default layer with no data, and a programme key that is wider than the viewer's office scope.

---

## 3. Corrections to the geo-monitoring plan

| Geo-monitoring said | Problem | This plan |
|---|---|---|
| §3.3 / §4.4: the map package intersects the viewer's `TenantContext` locations, then asks providers for values | The tracking programme key shows a programme member every office in the programme, including offices outside their own office scope (owner decision 2026-10-18). A tenant-only intersection would hide data the viewer is entitled to | **The data source declares the scope** (`GeoScope`, §6.2). The engine then masks the payload again as defence in depth |
| §4.3 `MapMetric`: one value per office, no parameters | A page-embedded view needs parameters (`initiative`, `code`, page filters), object-level authorization, several layers, filters and a details panel | `GeoDataSource` (§6.1) is a superset; geo-monitoring's metrics become sources |
| §4.2: a `geo-monitoring` package that owns the page, the JS and the contract | One page, while the request is "pluggable anywhere" | Rendering goes in the theming kit, wiring in a `geo-view` package. The national "Geo monitor" becomes one host page (phase F) |
| §5: one fixed sequential scale | Different questions need different encodings | **Intents** (§5.2) choose the scale, click behaviour and panels |
| §4.4 "unplaced" bucket only | Offices placed on the parent area (the Dhaka division office) belong to no child | An "at this level" bucket alongside "unplaced" (§6.3) |

When this plan is approved, geo-monitoring's header should say it is superseded except for its metric roadmap (§6) and
access table (§4.6).

---

## 4. Features

Tags: **M** = pilot (phase C–E), **1** = first general release (phase F–G), **L** = later.

### 4.1 Navigation

| Feature | Behaviour | Tag |
|---|---|---|
| Drill down | Click or Enter on a drillable area loads its children file and its data, then tweens the `viewBox` from parent to child. The shared national frame makes the tween exact | M |
| Drill up / breadcrumb | An **Up to <parent>** button on the map itself (hidden at the root), the trail `Bangladesh › Dhaka › …`, and Escape | M |
| Exploring help | A **?** toolbar button and the **?** key toggle a short how-to panel on the map. Tips follow the intent and the enabled toolbar (compare, pick, search, table). A first-visit prompt sits at the root; the open/closed choice is remembered per browser | M |
| Browser history | Each drill is a `pushState` with namespaced params (`?gv-map[area]=3142`); Back and Forward work, and a deep link reopens the same view | M |
| Zoom buttons | `+` / `−` / reset (home) / fit to selection; geometric zoom inside the current level | M |
| Wheel / pinch / drag | Pan by drag; pinch on touch; wheel zoom needs Ctrl or ⌘ by default so long pages don't trap scrolling (D4) | M |
| Keyboard | Arrow keys pan, `+` / `−` zoom, Tab moves through areas in table order (roving tabindex), Enter drills, Escape goes up | M |
| Auto-fit start | `start: "auto"` opens at the smallest area containing all of the source's offices (lowest common `hid` ancestor). Initiative 30 opens on the country; a one-district programme opens on its district | M |
| Find area | Typeahead over `gov.geo.search`, restricted to the view's root `hid`; picking a result drills to its parent and highlights it | 1 |
| Flat mode | Show one level nationally (64 districts, or about 500 upazilas) from projected level files instead of per-parent drilling | 1 |
| Semantic auto-drill | Past a zoom threshold, swap in the children of the area that fills the screen | L |
| Overview inset | Small country outline marking where the current area sits | L |

### 4.2 Reading the data

| Feature | Behaviour | Tag |
|---|---|---|
| Choropleth | Areas coloured by the active layer in 3–7 bins | M |
| States | `ok`, `no_data` (in scope, nothing recorded), `no_access` (outside the viewer's scope: **no value, no count**), `no_boundary` (area exists, no shape; table only), `not_applicable` (layer meaningless here, such as no office targets), `partial` (an area the viewer reaches only through a smaller area inside it: drillable, and its value counts only in-scope offices, or is withheld for area-grain sources). Each has its own swatch and pattern, not only a colour | M |
| Legend | Bins with ranges, state swatches, unit and attribution. Hovering a bin highlights its areas; clicking toggles them | M |
| Tooltip | Name in the viewer's locale, formatted value, numerator and denominator for ratios, office count and state. Follows focus as well as the pointer | M |
| Details panel | Offices in the selected area with values and links (for tracking: `initiatives.offices.show`); includes "at this level" offices | M |
| Table view | The same rows as an accessible `<x-gs::table>`. It is server-rendered first, so it is also the no-JS, print and screen-reader fallback | M |
| Layers | Switch between a source's layers (delivered share, received quantity, value, waiting offices) | M |
| Filters | Source-declared filters (code, plan, fiscal year), shown in the toolbar or bound to the host page's own filter form | M |
| Labels | Area names placed at precomputed label points when they fit on screen; simple collision skip | 1 |
| Office markers | Count badges at label points; offices at the current level are pinned to the parent outline | 1 |
| Multi-select / compare | Shift+click selects several areas; the panel shows them side by side; a `select` event goes to the host | 1 |
| Export | CSV of the table as shown; PNG or SVG snapshot including legend and attribution | 1 |
| Fullscreen | Fullscreen API with a toggle | 1 |
| Time | Source-declared time dimension (fiscal year, month), with a slider and optional playback | L |
| Bubble layer | Proportional symbols for counts over a neutral choropleth | L |

### 4.3 Embedding

| Feature | Behaviour | Tag |
|---|---|---|
| Blade component | `<x-geoview::map>` with a preset, params and overrides (§5) | M |
| Several maps per page | Each instance has an `id`; its URL keys and events are namespaced by that id | M |
| JS API | `GsGeoView.get(id)`: `drillTo`, `setLayer`, `setFilters`, `select`, `refresh`, `on`/`off`, `destroy` (§7.3) | M |
| Host-bound filters | `bind-filters="#tracking-filters"` reads the page's existing GET form so the map always shows the same rows as the page | M |
| Picker intent | The map as a form input: choose an area and write its `GeoAreaId` to a hidden input (for example an office location profile) | 1 |
| Responsive | Side panel beside the map at ≥ 992 px, below it on smaller screens; minimum map height 280 px; 44 px touch targets | M |

---

## 5. Configuration system

### 5.1 Resolution order

Later layers override earlier ones, except for keys that an earlier layer **locks**:

| # | Layer | Where it lives | Who sets it |
|---|---|---|---|
| 1 | Engine defaults | `geo-view/src/config/geo-view.php` | Platform |
| 2 | Intent | `geo-view` intents table (§5.2) | Chosen by the preset or the host |
| 3 | Preset | Registered by the source's package (`tracking/src/config/geo-view-presets.php`) | Module owner |
| 4 | Host attributes | `<x-geoview::map …>` on the page | Page author |
| 5 | URL state | `?gv-<id>[area]=…&gv-<id>[layer]=…` | Viewer (deep links, Back button) |
| 6 | Runtime | `GsGeoView.get(id).setLayer(…)` | Host page script |

`GeoViewConfigResolver` merges layers 1–4 on the server and validates the result against
`contracts/gs-geo-config.schema.json`. An unknown key throws in local and testing environments and is dropped with a
warning in production. The resolved config is embedded in the component's markup. Layers 5–6 can change only keys that
are not locked. **No layer can widen access:** a URL that asks for `area=` outside scope still gets `no_access` from the
server.

### 5.2 Intents: what the view is for

The intent is the page's purpose stated once. It fills sensible defaults so most pages set very little.

| Intent | Question | Default scale | Click | Panels |
|---|---|---|---|---|
| `progress` | How far along is each area against its target? | Sequential 0–100%, 5 bins; optional threshold at the expected pace | Drill; at the lowest level, details | Legend, details, table toggle |
| `coverage` | Where do we have X, and where don't we? | Categorical: none / partial / full | Details | Legend, details |
| `distribution` | How much X is where? | Quantile, 5 bins (sum or count) | Drill | Legend, table |
| `compare` | Which areas are above or below a reference? | Diverging around a reference (national value or expected pace) using status tones | Select (multi) | Legend, compare panel |
| `locate` | Where are these offices? | Neutral fill with office markers | Navigate to the office | Details list |
| `pick` | Choose an area | Neutral fill with selection highlight | Select, then emit the value | Selected-area chip |

### 5.3 Config shape (`gs-geo-config/1`)

```php
// tracking/src/config/geo-view-presets.php — registered by TrackingServiceProvider only when geo-view is bound
return [
    'tracking.programme' => [
        'intent' => 'progress',
        'source' => ['key' => 'tracking.programme', 'params' => ['initiative', 'code', 'status', 'plan', 'q']],
        'layers' => [
            'default' => ['delivered_share', 'received_qty'],   // first layer with data wins (§2.1: CATEGORY plans)
            'allow' => ['delivered_share', 'received_qty', 'value', 'waiting_offices'],
        ],
        'levels' => ['start' => 'auto', 'min' => 1, 'max' => 3, 'urban' => 'colour'],   // urban: colour | hide | drill
        'interaction' => ['click' => 'drill', 'leaf_click' => 'details', 'wheel' => 'modifier',
                          'select' => 'single', 'touch' => 'tap-select-tap-drill'],
        'toolbar' => ['zoom' => true, 'reset' => true, 'layers' => true, 'search' => false, 'export' => false,
                      'fullscreen' => false],
        'panels' => ['breadcrumb' => true, 'legend' => true, 'details' => 'side', 'table' => 'toggle'],
        'scale' => ['type' => 'sequential', 'steps' => 5, 'domain' => [0, 1]],   // intent supplies this when omitted
        'size' => ['height' => 420, 'aspect' => null],
        'url_state' => true,
        'locked' => ['source.key', 'levels.max'],
    ],
];
```

Host usage on the pilot page (replaces the tile-map section of `initiatives/monitor.blade.php`):

```blade
<x-geoview::map id="programme-map" preset="tracking.programme"
    :params="['initiative' => $initiative->id] + $monitor['filters']"
    bind-filters=".gs-tracking-filters" />
```

`:params` is validated by the source's own rules (§6.1). It is never trusted beyond what those rules and
`authorize()` allow.

### 5.4 Admin overrides (optional, D5)

A super admin could override a preset's defaults per deployment (default layer, maximum level), stored in a table with an
audit trail. This is **not in v1**: presets are code and config, reviewed like code. Revisit when a second ministry asks
for different defaults.

---

## 6. Data contract and sources (server)

### 6.1 `GeoDataSource`

```php
interface GeoDataSource
{
    public function key(): string;                          // 'tracking.programme'
    public function label(): string;                        // translation key
    public function ability(): string;                      // gov.can ability, e.g. 'tracking.programmes.view'
    public function rules(): array;                         // Laravel validation rules for host params
    public function authorize(GeoRequest $request): bool;   // object level: programme key for this initiative
    public function scope(GeoRequest $request): GeoScope;   // which offices and areas this viewer may see (§6.2)
    public function grain(): string;                        // 'office' (engine aggregates) | 'area' (source aggregates)
    /** @return LayerDefinition[] key, label, unit, format, aggregation (sum|count|ratio|max|min), applicable */
    public function layers(GeoRequest $request): array;
    /** @return FilterDefinition[] key, label, options; mirrored to the toolbar or host form */
    public function filters(GeoRequest $request): array;
    /** grain=office: iterable<OfficeFact{location_id, values[layer => value|[num,den]]}> already restricted to scope */
    public function facts(GeoRequest $request, array $layers): iterable;
    /** Optional side panel rows for one area (offices, links). Same scope rules. */
    public function details(GeoRequest $request, int $areaId): array;
}
```

`GeoRequest` carries the user, validated params, area, level, layers and filters. Packages register sources the same
way as metadata providers, with no hard dependency:

```php
if ($this->app->bound(GeoSourceRegistry::class)) {
    $this->app->make(GeoSourceRegistry::class)->register(TrackingProgrammeGeoSource::class);   // resolved lazily
    $this->app->make(GeoPresetRegistry::class)->merge(require __DIR__.'/../config/geo-view-presets.php');
}
```

### 6.2 Scope: two locks

1. **The source filters** its facts (`facts()` already applies the domain rules: the programme key, drafts excluded,
   the tracking plan §6 rules).
2. **The engine masks** the result with `GeoScope`. Facts from offices outside `GeoScope::offices` are dropped, and
   child areas outside `GeoScope::areas` (`hid` prefixes) become `no_access` with no value or count.

| Factory | Meaning | Used by |
|---|---|---|
| `GeoScope::offices($ids)` | These offices; areas = every area | Tracking: all offices of the programme, for holders of the programme key |
| `GeoScope::tenant($context, inventory: false)` | `allowedLocationIds` (or `allowedInventoryLocationIds`) plus the viewer's jurisdiction `hid` prefixes | National metrics (organization, store operations) |
| `GeoScope::all()` | Global | Super users, public reference layers |

A child area that contains the viewer's scope but is larger than it (Rajshahi division for a Bogura officer) is `partial`. Office-grain sources show the in-scope offices' total with a "only your offices are counted" note; area-grain sources return no value, because their figure would include areas outside scope.

A request that fails `ability()` or `authorize()` returns **404**, not 403, so it doesn't reveal that a programme
exists (tracking rule 2). A missing base ability returns 403, as elsewhere.

### 6.3 Aggregation (`GeoAggregator`, grain = office)

For the requested area *A* at level *n*, each office's area is resolved through `rollupArea()` (urban units go up to
their upazila) and then:

| Office area | Bucket |
|---|---|
| Under child *c* of *A* | Counted in *c* |
| Exactly *A* (the Dhaka division office when viewing Dhaka) | `here`: "at this level", listed in the panel and pinned on the outline |
| Above *A* or outside it | Not counted at this view (counted when the viewer goes up) |
| No `geo_area_id`, or an unresolvable urban unit | `unplaced` (shown only at the view's root) |

- `ratio` layers roll up as Σnumerator / Σdenominator, never as an average of percentages. `sum` and `count` add.
  `max` and `min` take the extreme.
- A layer whose denominator is zero everywhere (initiative 30's `delivered_share`) is marked `applicable: false`.
  The engine then takes the next layer in `layers.default` and says so in the legend: "No office targets in this
  programme; showing received quantity".
- Optional small-number suppression per layer (`suppress_below: 3`) for sensitive counts: the value becomes
  `suppressed`. Off by default (D6).

### 6.4 Payload (`gs-geo-data/1`)

```jsonc
{
  "contract": "gs-geo-data/1",
  "source": "tracking.programme",
  "area": { "id": 1, "level": 0, "name": "বাংলাদেশ", "trail": [ { "id": 1, "name": "বাংলাদেশ" } ] },
  "level": 1,
  "boundary": { "version": "bd-boundaries-2026-10-21.1", "children": "/vendor/geo-areas/boundaries/bd-boundaries-2026-10-21.1/children/1.json" },
  "layer": { "key": "received_qty", "label": "Received", "unit": "items", "format": "integer", "aggregation": "sum",
             "fallback_from": "delivered_share", "fallback_reason": "no_office_targets" },
  "layers": [ { "key": "delivered_share", "applicable": false }, { "key": "received_qty", "applicable": true } ],
  "stats": { "min": 55, "max": 55, "national": 220 },        // the engine bins with config.scale; the server sends stats only
  "areas": {
    "3142": { "state": "ok", "value": 55, "offices": 1, "drillable": true },
    "<Barishal id>": { "state": "ok", "value": 55, "offices": 1, "drillable": true },
    "<Sylhet id>": { "state": "no_data", "drillable": true }
  },
  "here": { "offices": 0 }, "unplaced": { "offices": 0 },
  "coverage": { "offices": 4, "placed": 4, "verified": 0, "rolled_up": 0 },
  "filters": { "status": "all", "plan": "all" },
  "attribution": "Boundaries: BBS and OCHA 2015 …",
  "links": { "details": "/gov-store/api/geo-view/sources/tracking.programme/details?…" }
}
```

Names come from `gov_geo_areas` in the viewer's locale and are rendered only with `textContent`. Areas without a
shape still appear in `areas` with `state: "no_boundary"`, so the table lists every child.

### 6.5 Routes

| Method · path | Name | Middleware | Purpose |
|---|---|---|---|
| GET `gov-store/api/geo-view/sources/{source}/data` | `gov.geo-view.data` | web, auth, `throttle:geo-view` (120/min/user) | Payload above |
| GET `gov-store/api/geo-view/sources/{source}/details?area=` | `gov.geo-view.details` | same | Side panel rows |
| GET `gov-store/api/geo/areas/{area}/children` | `gov.geo.children` | web, auth | Names, types, `hasChildren` (geo-areas, phase B) |
| Static `public/vendor/geo-areas/boundaries/<version>/…` | — | none (open-licence data) | Boundary files; `geo-areas:publish-boundaries` copies them and verifies SHA-256 against the manifest (D7) |

No page routes are added for the pilot; it embeds the component in an existing page. The national geo monitor (phase F)
adds one page route with a breadcrumb.

### 6.6 Caching

- **Boundaries:** the version is in the URL, so they are immutable (`Cache-Control: public, max-age=31536000,
  immutable`). The browser keeps an LRU of 40 files in memory.
- **Data:** `Cache-Control: private, no-cache` with an ETag computed from the payload hash, so revalidation answers 304
  and no shared cache ever holds scoped data. A source can opt into a server cache of its facts for (source, params,
  filters, scope hash) with a short TTL (tracking: 60 s), so successive drills don't recompute the programme.
- **First paint:** the Blade component calls `GeoDataService` in-process for the initial view and embeds the payload,
  so there is no request waterfall. The same payload renders the server-side table.

---

## 7. Engine (browser)

### 7.1 Placement and loading

- The neutral engine lives in the **theming kit**: `theming/resources/js/gs-geo/*.js`, `components/18-geo.css`,
  `x-gs::geo-map` (the markup shell) and a Lab demo with fixture shapes. Like `x-gs::tile-map`, it is a kit pattern
  with no knowledge of GovStore data.
- It is **loaded on demand** (D2). `AssetRegistry` gains `lazy('geo', $files)`. `gs-theme:build` writes
  `geo.<hash>.js` next to the main bundle and lists it in the manifest. The main bundle gets a loader of under 1 KB that
  injects the script only when `[data-gs-lazy~="geo"]` is on the page. The same mechanism suits later heavy widgets.
- It is plain ES2019 in IIFE modules concatenated in order, matching the repo's practice (no transpiler). Pure modules
  also export to `globalThis.GsGeo` so `node --test` can load them in a `vm` context.

### 7.2 Modules

| Module | Responsibility |
|---|---|
| `store.js` | One state object (area, level, layer, filters, selection, camera) and a reducer; renders on change |
| `config.js` | Applies defaults and URL state to the server-resolved config; enforces locked keys |
| `boundaries.js` | Fetch, verify `version`, LRU cache, abort superseded requests |
| `source.js` | Adapters: `remote` (the data API), `inline` (embedded payload), `function` (a host-supplied async loader). Deduplicates and aborts |
| `scale.js` | Sequential, quantile, threshold, categorical and diverging bins; maps values to classes `gs-geo__area--b1…b7` |
| `camera.js` | `viewBox` math, clamping, tween with `requestAnimationFrame` (skipped under `prefers-reduced-motion`), wheel, pinch and drag |
| `render.js` | `createElementNS` paths, outline, hatch pattern `<defs>`, roving tabindex, `<title>` per area |
| `labels.js`, `markers.js` | Label points and office count badges (phase G) |
| `ui/*.js` | Toolbar, breadcrumb, legend, tooltip, details panel, table, search |
| `url.js` | State ↔ namespaced query params; `pushState` on drill, `replaceState` on layer and filter changes |
| `api.js` | `GsGeoView` facade and `gs-geo:*` events |

### 7.3 JavaScript API

```js
const view = GsGeoView.get('programme-map');          // or GsGeoView.mount(el, config) for a script-built host
view.on('drill', ({ area, level }) => { … });           // also: ready, select, layer, filter, error
view.drillTo(2390);                                     // the server still decides what is visible
view.setLayer('value');
view.setFilters({ code: 104 });
view.select([2390, 1246]);
view.refresh();                                         // refetch after the host changed its own data
view.destroy();
```

Events are also dispatched as bubbling `CustomEvent`s (`gs-geo:select`) on the root element, so hosts can listen
without holding a reference. A `function` source runs in the browser and bypasses server scope, so it may only be used
with data the page has already rendered for this viewer. The developer guide says so, and `geo-view:doctor` flags any
preset that sets it.

### 7.4 Look and accessibility

- Tokens only. Bins are `color-mix(in oklab, var(--gs-color-primary) N%, var(--gs-color-surface))`, the same recipe as
  `--gs-tile-mix`. Diverging scales use the status-map tones. `no_access` is hatched, `no_data` uses
  `--gs-color-surface-sunken`, and `no_boundary` appears in the table only. Fills are **classes**, never inline
  `style`, so the compliance baseline stays at zero. All six themes, light and dark, are checked in the Lab.
- Strokes use `vector-effect: non-scaling-stroke`, so borders keep their width at any zoom.
- Numbers use `Intl.NumberFormat(locale)`, which gives Bengali digits for `bn-BD` and matches
  `TrackingMonitorMetrics::digits`. Money uses the server's formatted strings.
- WCAG 2.2 AA:
  - Every area is focusable, with a name and a value in its accessible name.
  - Level changes are announced in an `aria-live` region.
  - Colour is never the only channel: values also appear in the tooltip, the patterns and the table.
  - Focus is visible.
  - The table is a complete alternative.
  - pa11y runs on the Lab page.
- Package CSS sits in `@layer gs-packages`, where Bootstrap's unlayered element rules win. Map markup uses
  `div`/`svg`/`button`, never bare `section` or `label` styling, and `!important` only where the known traps require it.

### 7.5 Performance budgets

| Measure | Budget |
|---|---|
| Engine script | ≤ 45 KB minified, ≤ 15 KB gzip; zero bytes on pages without a map |
| Per-parent boundary file | Already gated: country 60 KB, division/district 150 KB, upazila 80 KB (raw) |
| Flat level file (upazilas nationally) | Target ≤ 400 KB raw / 120 KB gzip; gate set after the first build |
| Paths per view | ≤ 600 SVG paths. Unions nationally (about 4,800) are not offered; that would need a canvas renderer (later) |
| Data API, district view | p95 ≤ 300 ms server time with warm facts cache; slow-source log above 500 ms |
| Drill transition | ≤ 300 ms after data arrives; input stays responsive (tween on `requestAnimationFrame`) |

---

## 8. Packages and files

```
packages/gov-store/geo-view/                       # new; GovStore\GeoView\
  composer.json
  contracts/gs-geo-config.schema.json              # used by PHP tests and node tests alike
  contracts/gs-geo-data.schema.json
  src/
    Contracts/GeoDataSource.php
    Data/{GeoRequest,GeoScope,OfficeFact,LayerDefinition,FilterDefinition}.php
    Registry/{GeoSourceRegistry,GeoPresetRegistry}.php
    Config/{GeoViewConfigResolver,Intents}.php
    Services/{GeoDataService,GeoAggregator,GeoScopeMask}.php
    Http/Controllers/GeoDataController.php
    View/Components/Map.php                        # <x-geoview::map>
    Console/GeoViewDoctorCommand.php               # validates presets, sources, boundary version, contract self-checks
    Providers/GeoViewServiceProvider.php
    config/geo-view.php
    routes/web.php
    resources/views/components/map.blade.php       # wraps x-gs::geo-map + server table
    resources/lang/{en-US,bn-BD}/general.php
packages/gov-store/theming/                        # kit additions
  resources/js/gs-geo/*.js · components/18-geo.css · resources/views/components/geo-map.blade.php
  src/Assets/AssetRegistry.php (lazy bundles) · Lab section + fixture shapes
packages/gov-store/geo-areas/                      # boundary service
  GeoAreaService::children(), rollupArea() · GeoAreaController::children · Console publish-boundaries
  tools/build_boundaries.py: bbox + label point per area, flat level files (new boundary version)
packages/gov-store/tracking/
  src/Geo/TrackingProgrammeGeoSource.php · src/config/geo-view-presets.php
tests/Feature/GovStore/GeoViewTest.php · tests/Feature/GovStore/Concerns/GeoDataSourceContract.php
tests/js/geo/*.test.mjs (node --test) · scripts/live-tests/geo-view/*.spec.mjs (Playwright)
```

The only Snipe-IT core change is the usual one per package: a `require` line and a PSR-4 entry in the root
`composer.json`. Mapshaper stays in `geo-areas/tools/package.json`.

---

## 9. Phases

Each phase ends in a working, tested state and can be reviewed on its own.

| Phase | Work | Exit criteria |
|---|---|---|
| **A. Contracts** | Owner decisions D1–D8 (§10); commit both JSON schemas and this plan; mark geo-monitoring superseded in part | Schemas reviewed; fixture payloads validate |
| **B. Boundary service** (geo-areas) | `children()`, `rollupArea()`, `gov.geo.children`, `geo-areas:publish-boundaries`; boundary build adds `bbox` and `label` per area plus projected flat level-2/3 files → new boundary version with all gates passing | `GeoAreasPackageTest`: country → divisions, children by `hid`, pourashava/city roll-up, every shape id exists, locale names; published files match manifest SHA-256 |
| **C. Engine** (theming kit) | Lazy bundle support; modules in §7.2 for the **M** features; `x-gs::geo-map`; Lab demo with fixture shapes and a fixture source | node tests for scale, camera, URL and store; Playwright: drill, Back button, zoom buttons, keyboard, table toggle, six themes × light/dark screenshots; pa11y clean; compliance baseline unchanged; main bundle grows by < 1 KB |
| **D. Geo view wiring** (geo-view package) | Registries, config resolver and intents, `GeoDataService`, aggregator, scope mask, data/details API, `<x-geoview::map>` with inline first payload, `GeoDataSourceContract` test trait, `geo-view:doctor` | Resolver tests (layer order, locks, unknown keys); aggregator tests (ratio, `here`, `unplaced`, urban roll-up, fallback layer); contract trait passes on a fixture source |
| **E. Pilot: tracking** | `TrackingProgrammeGeoSource` (layers reuse the tracking plan §5 definitions exactly: office `share`, received, value, waiting); preset; replace the "where" section on `initiatives/{id}`; then the code dashboard; retire `TrackingTileMap` and `naogaon.json` once both pages have moved | §11.2 acceptance on initiative 30; viewer matrix from tracking plan §6 passes for map data; live walk in both locales and themes |
| **F. National host** | Geo monitor page (geo-monitoring §4.5–4.6) as a host; organization source (offices registered, share verified) | Geo-monitoring §8 feature tests, re-pointed at the data API |
| **G. Depth** | Tag **1** features: search, labels, markers, flat mode, multi-select, export, fullscreen, picker intent, upazila and union levels; remaining metric providers (geo-monitoring §6) | Each feature has a Lab demo and a Playwright step |
| **H. Hardening** | Profiling against a full-size dataset, facts cache tuning, rate-limit review, developer guide "Add a data source in five steps", runbook for boundary version upgrades | Budgets in §7.5 met; doctor command clean in CI |

Phases B and C can run at the same time: C uses fixture shapes and only needs the boundary file contract, which is
already fixed.

---

## 10. Owner decisions

Decided 2026-10-22: the owner accepted every recommendation below.

| # | Decision | Recommendation (accepted) | Alternative |
|---|---|---|---|
| D1 | Where the code lives | Neutral engine in the **theming kit**; GovStore wiring in a new **`geo-view`** package; sources in their own packages | Everything in one `geo-view` package (simpler, but the engine can't be demoed in the Lab without GovStore data) |
| D2 | How the engine reaches the browser | **Lazy bundle** loaded only on pages with a map | Append to the main bundle (no theming change, but adds about 15 KB gzip to every page) |
| D3 | Geo-monitoring plan | **Supersede** its contract and package; keep its access table and metric roadmap | Keep both contracts |
| D4 | Wheel zoom | **Ctrl/⌘ + wheel** by default, with a hint on first wheel; presets may set `always` | Plain wheel zoom (traps scrolling on long monitor pages) |
| D5 | Admin overrides stored in the database | **Not in v1** | Build in phase H with an audit trail |
| D6 | Small-number suppression | **Off by default**, opt-in per layer | On for all counts below 3 |
| D7 | Serving boundary files | **Static publish** to `public/vendor/geo-areas` (open data, cacheable by the web server) | Laravel route behind auth (slower, adds no security for open-licence data) |
| D8 | Export in the first release | **CSV and PNG in phase G**, client-side, of data the viewer already sees | Server-generated exports with an audit log |

---

## 11. Verification

### 11.1 Automated

- **Contract trait** (`GeoDataSourceContract`), which every source's test must use:
  - Guest → redirect to login.
  - Missing ability → 403.
  - Object the viewer can't access (another ministry's programme) → 404 with a body that doesn't name it.
  - Tampered `area` outside scope → `no_access` with no value or count.
  - Tampered `params` → 422.
  - Payload validates against `gs-geo-data.schema.json`.
  - Locale names follow the viewer's locale.
  - Ratio roll-up equals Σnum/Σden.
- **Engine:** node tests for the pure modules; Playwright on the Lab page (interaction, history, keyboard, reduced
  motion, mobile 375 px, theme screenshots); pa11y.
- **Regression:** `GovStoreThemeComplianceTest` (baseline unchanged), `TrackingMonitorTest` (the page still renders
  without JS, with the server-side table), `G1AuthorizationTest`.
- **Full suite:** `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`.

### 11.2 Pilot acceptance (initiative 30, local data)

| Check | Expected |
|---|---|
| Opening view | `start: auto` opens on the country (offices in four divisions) |
| Default layer | `delivered_share` is not applicable (product-based plans, no office targets), so the view shows `received_qty` and the legend says why |
| Country view | Dhaka, Barishal, Rajshahi and Mymensingh divisions coloured, 55 each; the other four `no_data` |
| Drill to Dhaka division | All districts `no_data`; panel shows "At this level: 1 office" (the regional office) |
| Drill to Barishal | Patuakhali coloured; details list one office linking to `initiatives/30/offices/171` |
| Filters | Choosing code `…-ADP-12` in the page form updates the map to Patuakhali only |
| Without JS | The "where" section shows the server-rendered table with the same four offices |
| Viewers | Super admin and programme members (operation unit) see all four offices. An ICT officer without programme membership gets 404 from the data API, without the programme being named (tracking rule 2) |

### 11.3 Live walk

On `127.0.0.1:8010`, as the superuser, a programme member, the owner company's admin (company 547) and a district ICT
officer outside the programme:

- Both locales.
- Two themes, light and dark.
- Desktop and 375 px.
- Back and Forward after three drills, plus a deep link pasted into a new tab.

---

## 12. Prototype findings (2026-10-22)

A clickable prototype (real boundaries, initiative 30 data, simulated server) was built before phase B:
https://claude.ai/artifact/5XusBkkRhHrm4intGdmFBj. It confirmed the design and changed four details, already applied
above:

| Finding | Change |
|---|---|
| A viewer scoped to one district still needs to drill from the country down to it | New `partial` state (§4.2, §6.2) |
| The scale depends on the page's intent and the active layer, not on the data source | Scale lives in config; the payload carries `stats` only (§6.4). A `progress` intent uses the fixed 0–100% domain only for percent layers and falls back to quantiles otherwise |
| Initiative 30 has one value (55) in every area with data | A single-bin legend ("55 · all areas with data") instead of empty quantile bins |
| Pointer capture on press swallows clicks on areas | Capture the pointer only once a drag passes 4 px; set the camera before the first label pass |

---

## 13. Risks

| Risk | Mitigation |
|---|---|
| Scope leak through aggregates or tampered URLs | Two locks (source filter and engine mask), server-only authority, 404 for hidden objects, contract trait on every source |
| Every module builds its own map anyway | Presets and intents make embedding a three-line change; the developer guide and Lab show it; `TrackingTileMap` is retired |
| Config sprawl | Schema-validated config, unknown keys rejected in development, intents carry most defaults, `geo-view:doctor` in CI |
| Default layer is empty for a page's data (initiative 30) | Fallback chain in `layers.default`, `applicable` flag from the source, and the reason shown in the legend |
| Offices placed above leaf level look missing | "At this level" bucket in the panel and on the outline; `coverage` block |
| 2015 boundaries and GovStore tree gaps (Tarakanda, Osmani Nagar, Gazipur/Rangpur CC) | `no_boundary` state, table lists every area, attribution states the vintage; tree fixes follow `geo-boundary-data-plan.md` §10 |
| Page weight | Lazy bundle, per-parent files, size gates, path budget; no national union view without a canvas renderer |
| Bootstrap and layer cascade breaks map chrome | Kit conventions from the gotchas list, Lab screenshots in every theme |
| Boundary version upgrade breaks deep links | `GeoAreaId` keys are stable across versions; config pins `boundary_version`; the previous version stays published for one release |
