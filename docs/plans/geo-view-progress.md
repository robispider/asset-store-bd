# Geo View build — progress log

Build of `docs/plans/geo-view-plan.md`, all phases A–H, on branch `geo-view` (cut from `codex/tracking-monitor-redesign`
at `1af0fd5074`). Each finished phase is committed and pushed to `origin geo-view`. Commits carry no AI attribution.

Sessions may be interrupted by usage limits. A scheduled task (`geo-view-build-resume`) starts a fresh session every
hour; that session reads this file and continues. **This file is the only memory between sessions**: keep it current.

## Rules for every session

1. Lock: `storage/app/geo-view-build.lock` holds an ISO timestamp (heartbeat). If it is less than 45 minutes old,
   another session is working: stop without changes. Otherwise write the current time into it and keep refreshing it
   (at least every 15 minutes, e.g. `date -Iseconds > storage/app/geo-view-build.lock`). Delete it when you stop.
2. Branch: work only on `geo-view`. If the working tree has uncommitted changes, they are the previous session's
   unfinished step: review them, finish or fix them, don't discard them.
3. Work the first unchecked item below. Tick it here when it's done and tested, and add a line to the session log.
4. At the end of a phase: run its tests, commit (`git commit`, no Co-Authored-By or "Generated with" lines),
   `git push origin geo-view`, tick the phase.
5. Follow `CLAUDE.md`, `AGENTS.md` and the plan. Snipe-IT core stays untouched (only root `composer.json` require and
   PSR-4 lines for the new package). UI uses `--gs` tokens and kit classes only; run `php artisan gs-theme:build`
   after package CSS/JS edits. Full GovStore suite: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`.
6. Never run PHPUnit while config is cached.
7. When every box is ticked: write a final summary at the bottom, push, and leave a note that the scheduled task
   `geo-view-build-resume` can be deleted.

## Resume task (create once, delete when the build is done)

Schedule: every hour (`7 * * * *`), working folder `D:\git repo\asset-store-bd`. Prompt:

> Continue the unattended Geo View build in `D:\git repo\asset-store-bd`. Read `docs/plans/geo-view-progress.md` first
> and follow its "Rules for every session" exactly; it is the only memory between sessions. If
> `storage/app/geo-view-build.lock` is less than 45 minutes old, stop and change nothing. If every box is ticked, stop
> and say the task can be deleted. Otherwise take the lock, work the unchecked items in order on branch `geo-view`
> following `docs/plans/geo-view-plan.md`, `CLAUDE.md` and `AGENTS.md`, commit and `git push origin geo-view` at the
> end of each phase with no AI attribution, update the progress file before stopping, and delete the lock. Do not open
> pull requests, merge into master or push other branches.

## Checklist

### Phase A — Contracts
- [x] `packages/gov-store/geo-view/contracts/gs-geo-config.schema.json` and `gs-geo-data.schema.json`
- [x] Fixture payloads under `tests/fixtures/geo-view/` that validate against both schemas
- [x] Mark `docs/plans/geo-monitoring-plan.md` superseded in part (§3); commit the plan + this log
- [x] Commit + push phase A

### Phase B — Boundary service (geo-areas)
- [x] `GeoAreaService::children()`, `rollupArea()`
- [x] `GET gov-store/api/geo/areas/{area}/children` (`gov.geo.children`)
- [x] `geo-areas:publish-boundaries` (copy to `public/vendor/geo-areas/boundaries/<version>`, verify SHA-256)
- [x] Boundary build: `bbox` + `label` per area, projected flat level-2/3 files → new boundary version, gates pass
- [x] `GeoAreasPackageTest` additions; commit + push phase B

### Phase C — Engine (theming kit)
- [x] Lazy bundle support in `AssetRegistry` / `ThemeCompiler` / manifest / dev route + loader in `gs-theme.js` (< 1 KB)
- [x] `resources/js/gs-geo/*.js` modules (store, config, boundaries, source, scale, camera, render, ui/*, url, api)
- [x] `components/18-geo.css`, `x-gs::geo-map`, kit translations (en-US, bn-BD)
- [x] Lab demo with fixture shapes and fixture source
- [x] `tests/js/geo/*.test.mjs` (node --test): scale, camera, url, store
- [x] Playwright Lab spec; compliance baseline unchanged; commit + push phase C

### Phase D — geo-view package
- [x] Package skeleton, composer wiring, provider, config
- [x] Contracts/Data/Registry classes
- [x] `GeoViewConfigResolver` + intents
- [x] `GeoDataService`, `GeoAggregator`, `GeoScopeMask`
- [x] `GeoDataController` data/details routes, throttle, ETag
- [x] `<x-geoview::map>` with inline first payload + server table
- [x] `GeoDataSourceContract` trait, `geo-view:doctor`
- [x] `GeoViewTest`; commit + push phase D

### Phase E — Tracking pilot
- [x] `TrackingProgrammeGeoSource` + presets, registered when geo-view is bound
- [x] Replace "where" section on `initiatives/{id}`; then the code dashboard
- [x] Retire `TrackingTileMap` and `naogaon.json`
- [x] §11.2 acceptance on initiative 30; viewer matrix; commit + push phase E

### Phase F — National host
- [x] Geo monitor page + breadcrumb + menu; organization source
- [x] Tests; commit + push phase F

### Phase G — Depth
- [ ] Search, labels, markers, flat mode, multi-select, export, fullscreen, picker intent, upazila/union levels
- [ ] Lab demo + Playwright step per feature; commit + push phase G

### Phase H — Hardening
- [ ] Profiling, facts cache, rate-limit review
- [ ] Developer guide "Add a data source in five steps", boundary upgrade runbook
- [ ] Doctor clean; full suite green; commit + push phase H

## Session log

- 2026-10-22 01:12 — Branch `geo-view` created; progress log and resume task set up. Prototype engine extracted for
  reference (artifact https://claude.ai/artifact/5XusBkkRhHrm4intGdmFBj, read it with the Artifact tool if needed).
- 2026-10-22 01:40 — Phase A done: schemas, fixtures in `tests/fixtures/geo-view/`, node validator `tests/js/geo/schema.mjs`
  (run `node --test "tests/js/geo/*.test.mjs"`; a bare directory argument fails on Windows).
- 2026-10-22 02:30 — Phase B done: boundary set bd-boundaries-2026-10-22.1 (bbox + label per area, flat/level-2|3.json, index.json;
  old 2026-10-21.1 kept one release), GeoTree + BoundarySet services, gov.geo.children, geo-areas:publish-boundaries,
  9 GeoAreasPackageTest tests green. NEXT: phase C, first item (lazy bundle in theming AssetRegistry/ThemeCompiler/Manifest/DevAssetController).
  Prototype engine extracted from the artifact is the reference for the gs-geo modules.
- 2026-10-22 03:20 — Phase C done: lazy bundles (AssetRegistry::lazy, manifest `lazy`, dev route lazy-<name>.js, loader
  GS.lazy in gs-theme.js), engine modules 00-core…90-api, 18-geo.css, x-gs::geo-map, kit strings en/bn, Lab demos
  (GeoLabFixture: progress with every state, compare, pick), GeoKitTest + 118 theming tests green, 16 node tests.
  Walked in the browser pane (bn locale, light/dark): drill, Up, Back/Forward, Esc, help, table, layer, compare.
  NOT run: scripts/live-tests/theme-visual/geo-view.spec.mjs (needs the user-recorded storage state).
  OPEN for phase H: geo bundle is 85 KB raw / 22.6 KB gzip unminified (budget 45 KB min / 15 KB gzip) → add minify.
  Engine already has labels/markers at label points and multi-select (phase G items still need Lab demo + spec each).
  NEXT: phase D (geo-view package). Data payload contract: areas[id] needs name; details API returns
  {area, offices:[{id,name,area,area_name,value,display?,link,here?}]}.
- 2026-10-22 04:30 — Phase D done: packages/gov-store/geo-view (composer require + PSR-4 in root composer.json/lock),
  GeoDataSource (+AreaGrainSource, CachesFacts), GeoScope (all/offices/tenant/of), resolver with locks + intents,
  GeoAggregator, GeoDataService, data/details API (403 ability, 404 object, 422 params, ETag), <x-geoview::map>,
  geo-view:doctor, JsonSchema (PHP). Tests: GeoViewTest 18 + contract trait tests/Feature/GovStore/Concerns/
  GeoDataSourceContract.php, fixtures tests/Feature/GovStore/GeoView/. Full GovStore suite 673 green.
  Local: boundaries published to public/vendor/geo-areas (ignored via committed .gitignore).
  NEXT: phase E — TrackingProgrammeGeoSource on initiative 30; reuse tracking plan §5 metric definitions.
- 2026-10-22 05:40 — Phase E done: TrackingProgrammeGeoSource (figures from TrackingMonitorService::programme →
  officeSummary; programme-key scope; code filter + param; CachesFacts 60 s), tracking/src/config/geo-view-presets.php,
  monitor "where" and code page use <x-geoview::map>; TrackingTileMap + naogaon.json removed (x-gs::tile-map kit
  component kept for the Lab). Auto start opens one level up when an office sits on the common area. Live walk on
  initiatives/30 (bn): country view, fallback note, 55 in Dhaka/Barishal/Rajshahi/Mymensingh, Barishal→Patuakhali,
  Dhaka "at this level 1", code ADP-12 filter → Barishal only, Back/Forward. TrackingGeoViewTest (contract trait +
  pilot acceptance) green; full GovStore suite 701 green.
  NOT done here: live walk as company 547 admin / district ICT officer (needs their sessions); covered by tests.
  NEXT: phase F — national geo monitor page + organization source.
- 2026-10-22 06:40 — Phase F done: /gov-store/geo-monitor (gov.geo-monitor.index, gov.can:geo.monitor.view, breadcrumb,
  menu gov-workspace), ability geo.monitor.view (+ tenant-scope labels en/bn), OrganizationOfficesGeoSource
  (offices, verified_share; tenant scope) + preset flagged `national`. Auto start moves up a level only when the
  parent is fully in scope. Quantile scale falls back to distinct-value breaks on ties. GeoMonitoringTest 11 green.
  Live: 58 offices, 57 placed, 1 unplaced, Rajshahi 13 distinct tone. Full suite was 712 with 1 nav failure → fixed.
  NEXT: phase G — search, flat mode, export, fullscreen, picker intent (labels/markers/multi-select exist already).
