# Runbook: upgrading the boundary set

The map draws a versioned boundary set (`bd-boundaries-<date>.<n>`) built by `tools/build_boundaries.py` from the
2015 union polygons and the reviewed crosswalk (`docs/plans/geo-boundary-data-plan.md`). `GeoAreaId` keys are
stable across versions, so deep links (`?gv-<id>[area]=…`) keep working after an upgrade.

## When to build a new version

- The GovStore area tree changed (`geo_areas.csv` / `govstore:geo-areas-sync`), e.g. a new upazila or a moved union.
- The crosswalk or review decisions changed (`boundaries/crosswalk.csv`, `review/boundary_decisions.csv`).
- The file format gained fields (as `2026-10-22.1` added `bbox`, `label`, `flat/` and `index.json`).

## Steps

1. **Fetch and verify sources** (only when `sources.json` changed):
   `python -I -X utf8 tools/fetch_boundary_sources.py` — archives are checked against their SHA-256.
2. **Bump the version** in `tools/build_boundaries.py` (`VERSION = 'bd-boundaries-YYYY-MM-DD.N'`).
3. **Check, then build** from `packages/gov-store/geo-areas`:
   - `python -I -X utf8 tools/build_boundaries.py --once --check` — all gates, nothing written (about 1.5 min).
   - `python -I -X utf8 tools/build_boundaries.py` — builds twice; gate 6 requires byte-identical results
     (about 3 min). The build refuses to write anything when a gate fails: collapsed shapes, children outside their
     parent, per-file size budgets, flat file budgets (district 150 KB, upazila 400 KB).
4. **Read `BUILD_REPORT.md`** of the new set: coverage per level, the largest files, parents whose children cover
   less than 98% of their shape, and areas without a shape (they appear in the map's table as "no map shape").
5. **Switch the configured version**: `geo-areas.boundary_version` (`GEO_BOUNDARY_VERSION`) in
   `src/config/geo-areas.php`.
6. **Keep the previous set for one release.** Pages cached in browsers may still request it; delete it from
   `src/database/data/boundaries/` in the release after next.
7. **Publish and verify** on every server: `php artisan geo-areas:publish-boundaries --set=<new> --set=<previous>`,
   then `php artisan geo-areas:publish-boundaries --verify`. Files are immutable (the version is in the URL), so the
   web server may cache them for a year.
8. **Run the checks**: `php artisan geo-view:doctor`, `vendor/bin/phpunit tests/Feature/GovStore/GeoAreasPackageTest.php`
   (every shape id is a GovStore area, label points sit inside their boxes, flat files are complete) and the full
   GovStore suite. Walk one drill to union level and one flat view in the browser.

## Rolling back

Set `GEO_BOUNDARY_VERSION` back to the previous set (still published, step 6) and clear the config cache. No data
migration is involved: the boundary set is static reference data.
