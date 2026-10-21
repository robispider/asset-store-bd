# Bangladesh geographic reference dataset

## Current bundled revision

- Dataset version: `bd-admin-areas-2026-10-21.1` (see `src/database/data/seed/manifest.json`)
- Every row carries a geocode (`bbs_code` + `code_status`); plan:
  [bbs-geocode-adoption-plan.md](../../../docs/plans/bbs-geocode-adoption-plan.md)
- Files (all under `src/database/data/`):

| File | Rows | Role |
| --- | ---: | --- |
| `geo_areas.base.csv` | 9,111 | Hand-maintained base: the pre-BBS dataset. Edit this, not `geo_areas.csv`. SHA-256 `1b1bf5beba43c321262dc587930153eac7cd0ff6d96f61abb5d013a863edc94d` (CRLF checkout) |
| `geo_areas.csv` | 9,197 | **Generated.** Base lines byte-identical except 173 English fixes (plan D2), plus 86 BBS areas missing from GovStore. Same headerless 9-column format, read by the 2024 migration and `ReferenceSetup` |
| `seed/geo_area_codes.csv` | 9,197 | `GeoAreaId → bbs_code, bbs_code_legacy, code_status, code_source, bbs_attributes` |
| `seed/geo_area_localities.csv.gz` | 168,488 | Mauza (GeoLevel 6) and village (GeoLevel 7) rows, GeoAreaId from 1,000,000 |
| `seed/manifest.json`, `seed/BUILD_REPORT.md` | | Version, counts, SHA-256 of each file (geo_areas.csv hashed as CRLF), review items |

- Code status: `bbs_2017` 175,946 · `provisional` 1,722 · `duplicate` 16 · `national` 1.
- Provenance of the base: the division/district/upazila/union rows are a June/July
  2023 scrape of the bangladesh.gov.bd portal (its domain IDs are the `geo_code`
  values); the urban rows' source is unrecorded. Codes come from the BBS
  *Bangladesh Geocode* district volumes dated June 2017 (published on bbs.gov.bd
  in December 2024), see `bbs-geocode-2017/README.md`.

### Geocode columns

`bbs_code` is the BBS path `division-zila-city-upazila-paurashava-union/ward[-mauza-village]`
(`_` = level not used), e.g. `10-06-_-02-_-142`. A child's code always starts with
its parent's (or, for an official child of a provisional parent, its nearest official
ancestor's). `geo_code`, `parent_geo_code` and `hid` stay the internal tree keys used
by jurisdiction checks; they are never rewritten.

| `code_status` | Meaning |
| --- | --- |
| `bbs_2017` | Official code from the June 2017 volumes |
| `provisional` | Area not in the 2017 volumes (newer, moved to a newer upazila, or a code BBS printed twice): parent code + `P` serial, e.g. `30-54-_-P01`. `bbs_attributes.bbs_2017_code` keeps the 2017 code where one exists |
| `duplicate` | A repeated current row; shares the original's code, `bbs_attributes.duplicate_of` names it |
| `national` | The country row (`BD`); also records the installed `dataset_version` |

## Installing and updating

- Fresh install: `php artisan migrate` imports `geo_areas.csv` (2024 migration),
  then `2026_10_21_000001_add_bbs_codes_to_gov_geo_areas` adds the columns and runs
  the sync: codes, missing areas and localities (about 15 s on MySQL).
- Existing install: back up `gov_geo_areas`, run
  `php artisan govstore:geo-areas-sync --dry-run`, review the counts, then
  `php artisan migrate` (or the sync command once the columns exist).
- The sync (`GeoAreaDatasetSync`) verifies every file against the manifest, inserts
  missing rows, writes the code columns, fills empty Bangla names, applies only the
  reviewed English fixes and never deletes or re-keys a row. It exits non-zero if
  any area is left without a code. Unit tests get the columns only.
- Search (`GeoAreaService::search`) returns mauza/village rows only when a caller
  asks for those types.

## Refreshing the data

1. Base edits go in `geo_areas.base.csv`. A newer BBS edition goes through
   `tools/fetch_bbs_geocode_pdfs.py` and `tools/extract_bbs_geocode_pdfs.py`.
2. `tools/crosswalk_bbs_geocode_2017.py` links base areas to BBS areas;
   `tools/prefill_geo_decisions.py` drafts a decision for every unlinked area into
   `bbs-geocode-2017/review/decisions.csv`. A person confirms each one (`decision`,
   `target`, `note`, `reviewed_by`, `reviewed_on`); reruns keep confirmations.
3. `tools/build_geo_seed.py` regenerates `geo_areas.csv` and `seed/`, and refuses
   to write unless every gate passes: every row coded, unique codes, nested codes,
   resolvable parents, base identifiers and Bangla unchanged, every unlinked row
   decided. Two builds are byte-identical. Bump `VERSION` in that script.
4. Keep `GeoAreaId` stable for profile, jurisdiction and tracking references.
   Never rerun the original table-creation migration to refresh data; it leaves
   an existing table intact.
5. Keep `divison` accepted as an input alias and normalize it to `division` in
   the service/API. Do not rewrite stored types until all SQL consumers and
   existing identifiers have been reviewed.

The search endpoint uses English and Bangla prefix matching. Dedicated name
indexes support typeahead. Substring search is intentionally not used because it
cannot use these indexes.
