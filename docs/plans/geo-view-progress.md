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
- [ ] `GeoAreaService::children()`, `rollupArea()`
- [ ] `GET gov-store/api/geo/areas/{area}/children` (`gov.geo.children`)
- [ ] `geo-areas:publish-boundaries` (copy to `public/vendor/geo-areas/boundaries/<version>`, verify SHA-256)
- [ ] Boundary build: `bbox` + `label` per area, projected flat level-2/3 files → new boundary version, gates pass
- [ ] `GeoAreasPackageTest` additions; commit + push phase B

### Phase C — Engine (theming kit)
- [ ] Lazy bundle support in `AssetRegistry` / `ThemeCompiler` / manifest / dev route + loader in `gs-theme.js` (< 1 KB)
- [ ] `resources/js/gs-geo/*.js` modules (store, config, boundaries, source, scale, camera, render, ui/*, url, api)
- [ ] `components/18-geo.css`, `x-gs::geo-map`, kit translations (en-US, bn-BD)
- [ ] Lab demo with fixture shapes and fixture source
- [ ] `tests/js/geo/*.test.mjs` (node --test): scale, camera, url, store
- [ ] Playwright Lab spec; compliance baseline unchanged; commit + push phase C

### Phase D — geo-view package
- [ ] Package skeleton, composer wiring, provider, config
- [ ] Contracts/Data/Registry classes
- [ ] `GeoViewConfigResolver` + intents
- [ ] `GeoDataService`, `GeoAggregator`, `GeoScopeMask`
- [ ] `GeoDataController` data/details routes, throttle, ETag
- [ ] `<x-geoview::map>` with inline first payload + server table
- [ ] `GeoDataSourceContract` trait, `geo-view:doctor`
- [ ] `GeoViewTest`; commit + push phase D

### Phase E — Tracking pilot
- [ ] `TrackingProgrammeGeoSource` + presets, registered when geo-view is bound
- [ ] Replace "where" section on `initiatives/{id}`; then the code dashboard
- [ ] Retire `TrackingTileMap` and `naogaon.json`
- [ ] §11.2 acceptance on initiative 30; viewer matrix; commit + push phase E

### Phase F — National host
- [ ] Geo monitor page + breadcrumb + menu; organization source
- [ ] Tests; commit + push phase F

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
