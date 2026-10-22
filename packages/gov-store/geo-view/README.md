# gov-store/geo-view

One reusable, configurable map of Bangladesh's administrative areas that any GovStore page can embed. It
covers drill-down (country → division → district → upazila → union), zoom and pan, selection, legend, tooltip,
table fallback, search, flat national levels, export and deep links. The design is in
[`docs/plans/geo-view-plan.md`](../../../docs/plans/geo-view-plan.md).

The view is split into layers that meet only through versioned contracts:

| Layer | Where | Contract |
|---|---|---|
| Engine (browser) | `theming/resources/js/gs-geo/*.js`, `gs-geo-tools.js`, `components/18-geo.css`, `x-gs::geo-map` | `gs-geo-config/1`, `gs-geo-data/1` (`contracts/`) |
| Boundaries | `geo-areas` (`BoundarySet`, `GeoTree`, `geo-areas:publish-boundaries`) | boundary file contract (`geo-boundary-data-plan.md` §8) |
| Wiring | this package: registries, config resolver, data API, `<x-geoview::map>` | `GeoDataSource` |
| Sources | the module that owns the numbers (`tracking`, `organization`, …) | `GeoDataSource` + a preset |

**The server is the only authority.** Every data request is re-authorized and re-scoped by the data API. The
config a page sends to the browser is a convenience, not a permission. A source filters its own facts (lock 1);
the geo view masks them again with the source's `GeoScope` (lock 2).

## Add a data source in five steps

1. **Write the source.** Implement `GovStore\GeoView\Contracts\GeoDataSource` in your package, for example
   `src/Geo/MyThingGeoSource.php`:
   - `key()` — a stable dotted key, e.g. `store.receipts`.
   - `ability()` — the base ability (a missing ability answers 403).
   - `rules()` — validation rules for the host params your page passes (anything else is dropped; bad values 422).
   - `authorize()` — the object check (e.g. "may this viewer see this programme?"). `false` answers **404** and the
     body names nothing.
   - `scope()` — `GeoScope::all()`, `GeoScope::offices($ids)`, `GeoScope::tenant($context, false, $userId)` or
     `GeoScope::of($offices, $areas)`.
   - `grain()` — `office` (return `OfficeFact`s from `facts()`; the geo view aggregates them) or `area`
     (implement `AreaGrainSource::areaValues()` and aggregate yourself).
   - `layers()` — `LayerDefinition`s. Ratios carry `[numerator, denominator]` per office and roll up as Σnum/Σden;
     a ratio whose denominator is zero everywhere is "not applicable" and the view falls back to the next layer in
     the preset's `layers.default`. Use `display:` for money and `suppressBelow:` for sensitive counts (D6).
   - `filters()` — optional `FilterDefinition`s shown in the map toolbar; unknown values are rejected.
   - Implement `CachesFacts` (`factsTtl()`) when computing facts is expensive; the cache key includes the params,
     filters and scope.
2. **Write a preset** in `src/config/geo-view-presets.php` (see `tracking/src/config/geo-view-presets.php`): intent
   (`progress`, `coverage`, `distribution`, `compare`, `locate`, `pick`), `source.key`, the allowed host
   `source.params`, `layers.default` (fallback chain) and `layers.allow`, `levels`, panels and `locked` keys.
   Add `'national' => true` and a `title` to list it on the Geo monitor.
3. **Register both** from your service provider, without a hard dependency:

   ```php
   if ($this->app->bound(\GovStore\GeoView\Registry\GeoSourceRegistry::class)) {
       $this->app->make(\GovStore\GeoView\Registry\GeoSourceRegistry::class)->register(MyThingGeoSource::class, 'store.receipts');
       $this->app->make(\GovStore\GeoView\Registry\GeoPresetRegistry::class)->merge(require __DIR__.'/../config/geo-view-presets.php');
   }
   ```

4. **Embed the map** on your page:

   ```blade
   <x-geoview::map id="receipts-map" preset="store.receipts" :params="['period' => $period]" bind-filters=".my-filters" />
   ```

   The component resolves the config on the server, embeds the first payload (no request waterfall) and renders
   the same rows as a table for no-JS, print and screen readers. Several maps per page need different `id`s.
5. **Test it with the contract trait.** `use Tests\Feature\GovStore\Concerns\GeoDataSourceContract;` and supply the
   source key, valid params, three viewers (permitted, without ability, without object access) and an area outside
   scope. The trait checks sign-in, 403/404/422, `no_access` areas without values or counts, the payload schema,
   locale names and ratio roll-ups. Then run `php artisan geo-view:doctor`.

Never use a `function` source (`source.mode = function`) for data the page has not already rendered for this
viewer: it runs in the browser and bypasses server scope. The doctor command flags it.

## JavaScript API

```js
GsGeoView.ready('receipts-map', view => {
  view.on('drill', ({ area, level }) => {});   // also: ready, select, layer, filter, mode, help, export, error
  view.drillTo(2390); view.setLayer('value'); view.setFilters({ code: 104 }); view.select([2390]); view.refresh();
});
document.addEventListener('gs-geo:select', e => console.log(e.detail));   // bubbling events, no reference needed
```

## Operations

- `php artisan geo-areas:publish-boundaries` copies the boundary set to `public/vendor/geo-areas/boundaries/<version>`
  and verifies SHA-256 against its manifest (`--verify` checks only). Run it at deploy time, after `gs-theme:build`.
- `php artisan gs-theme:build` writes the lazy bundles `lazy/geo.*.js` (engine) and `lazy/geo-tools.*.js` (search,
  flat mode, export, full screen), minified with the project's terser when `node_modules` is installed.
- `php artisan geo-view:doctor` checks contracts, the published boundary set, every source and every preset; run it
  in CI.
- Data API: `GET gov-store/api/geo-view/sources/{source}/data|details`, `throttle:geo-view` (120 requests per
  minute per user, `GEO_VIEW_THROTTLE`). A drill costs one data and one details request, so 60 drills a minute fit.
  Responses are `Cache-Control: private, no-cache` with an ETag; nothing scoped is ever cached by a shared cache.
- Slow requests (over `geo-view.slow_ms`, 500 ms) are logged with the source key.

Measured on the local dataset (2026-10-22, `php artisan serve`): programme 30 first payload 205 ms cold (area tree
and programme computed), 0.3–1 ms for later drills from the 60 s facts cache; office network 4–7 ms; details
120–190 ms end to end. Engine bundle 48.1 KB minified / 15.4 KB gzip (plan target 45 / 15 KB; guarded at 50 / 16 KB
by `tests/js/geo/bundle.test.mjs`), tools bundle 7.0 KB / 3.0 KB.
