# Gov-Store BBS Geocode Adoption — every area gets a geocode (Implementation Plan)

> **Scope:** give every row of `gov_geo_areas` an official (or clearly provisional) BBS geocode, add the
> missing areas down to mauza/village, and ship it as seed data that a fresh install loads in one step.
> Work lives in `packages/gov-store/geo-areas` only. Snipe-IT core is untouched.
> **Status:** approved 2026-10-21 (D1–D4 as recommended: D2 replace junk English, D3 `P` serials,
> D4 localities in `gov_geo_areas`); implementation in progress · **Updated:** 2026-10-21
> **Inputs:** `src/database/data/bbs-geocode-2017/` (extracted BBS volumes + crosswalk), `geo_areas.csv`,
> `DATASET.md`, `GEOCODE-SUPPLEMENT-DESIGN.md`, `docs/plans/geo-monitoring-plan.md`.
> **Owner rules (2026-10-21):** keep areas newer than the BBS data; matched areas get the correct geocode;
> Bangla name = GovStore's when present, otherwise the extracted BBS name; future installs seed the full data.

---

## 1. Where we start

| Fact | Number | Consequence |
|---|---:|---|
| GovStore areas | 9,111 | All keep their `GeoAreaId` (FK target of 4 tables) |
| … linked to a BBS area by the crosswalk | 8,583 (94.2%) | Get the official code directly |
| … not linked | 528 | Need review: newer area, data defect, or parent not yet linked |
| … with a Bangla name already | **9,111 (100%)** | Bangla rule only matters for **added** rows |
| … with junk English (`Www`, `7noSofapur`, `… Distric`) | 173 | See decision D2 |
| … without `hid` (all urban types + country) | 3,990 | Unaffected by this plan; see D6 |
| BBS admin areas not linked | 438 | Either superseded (e.g. Ulania before its split) or missing from GovStore |
| BBS mauza / village rows (no GovStore level yet) | 168,863 | 158,243 sit under a linked union/ward; 10,620 under an unlinked one |

The BBS volumes are the **June 2017** edition. Anything created or renamed later is GovStore-only
(e.g. Dasar, Madhyanagar, Shayestaganj and Karnafuli upazilas, Mymensingh City Corporation, the
2018+ Gazipur metropolitan thanas, unions split after 2017). Those rows are **kept**.

## 2. Decisions

### D1 — Add official code columns. Do not rewrite `geo_code` (recommended, low risk)

`geo_code`, `parent_geo_code` and `hid` are the **internal tree keys**, not geocodes:

- `hid` prefix containment drives jurisdiction and scope checks: `TenantOrganizationResolver`,
  `ProvisioningController`, `OnboardingAccess`, `ScopeValidatorService`, `TrackingCodeController`,
  `GeoAreaService::isWithinBoundary/search`.
- `GeoAreaService::resolveParentNames()` and `OnboardLocationController` look up `geo_code` assuming it is
  unique within a type. BBS unit codes are **not** unique (union code `142` exists in hundreds of upazilas).
- `tracking/src/config/tile-maps/naogaon.json` is keyed by today's `geo_code`.

Replacing those values would silently break access control. Instead, add new columns that hold the
official code, and treat `geo_code` as the legacy portal ID.

| New column | Type | Content |
|---|---|---|
| `bbs_code` | `varchar(40)`, unique when not null | Canonical BBS path, e.g. `10-06-_-02-_-142` (division-zila-city-upazila-paurashava-union/ward[-mauza-village]; `_` = level not used). This is the dataset's `bbs_path`. |
| `bbs_code_legacy` | `varchar(20)`, index | Concatenated old-style code using the 2-digit union/ward and mauza codes (e.g. `10060213`). This is the form older BBS lists and P-code boundary files use; verify against the boundary source in geo-monitoring phase 0. |
| `code_status` | `varchar(20)`, index | `bbs_2017` · `provisional` · `national` (country row only) |
| `code_source` | `varchar(60)` | `bbs-geocode-2017`, or `provisional:<dataset version>` |
| `bbs_attributes` | `json`, nullable | `jl_no`, `rmo`, `land_acres`, `river_acres`, `forest_acres`, `total_parts`, `part_no`, `area_type`, BBS English/Bangla names as printed |

**Invariant ("no missing geo code"):** after seeding, `code_status IS NOT NULL` and `bbs_code IS NOT NULL` for every
row. This is enforced by the build tool (§4) and by a feature test (§6).

### D2 — Names

- **Bangla:** keep `bn_name` as stored. Only rows **added** by this plan take the repaired BBS Bangla
  (`bn_name` from the dataset). Rows whose BBS Bangla still contains an unmapped glyph (1.4% of extracted rows)
  are flagged in the build report for hand correction before release.
- **English (needs owner OK):** keep GovStore English, **except** the 173 junk names, which take the BBS English.
  All BBS spellings are kept in `bbs_attributes` either way.

### D3 — Provisional codes for areas BBS 2017 does not have

A kept newer area gets its parent's official path plus a provisional unit segment: `P` + 2-digit serial,
allocated in `GeoAreaId` order within the parent. Examples: `60-90-_-P01` for an upazila, `10-06-_-62-_-P01`
for a union. `code_status = provisional`. The `P` keeps it from ever colliding with a numeric BBS code, and it
keeps prefix queries on `bbs_code` working. When BBS publishes a newer geocode (2022 census edition), the
pipeline replaces provisional codes in place. `GeoAreaId` never changes.

### D4 — Mauza and village go in the same table (recommended)

- Add 168,863 rows with `geo_type` `mauza` (GeoLevel 6) and `village` (GeoLevel 7). Use new `GeoAreaId`s from a
  reserved range starting at **1,000,000**, and `geo_code` = `GeoAreaId`, so the legacy key stays unique.
  `parent_geo_code` = the parent union/ward's `geo_code`. `hid` = parent `hid` + own `geo_code` where the parent
  has a `hid` (rural), otherwise null (as for its urban parent today).
- Same table means offices can later be pinned to a village, and existing `hid` containment covers them.
- **Behaviour guard:** `GeoAreaService::search()` excludes `mauza`/`village` unless the caller asks for those
  types explicitly, so existing typeaheads do not start returning villages.
- The alternative (a separate `gov_geo_localities` table) avoids the guard but loses FK compatibility.
  Choose it only if the owner wants localities kept out of `gov_geo_areas`.

### D5 — BBS-only admin areas

Each of the 438 is reviewed into one of two outcomes:
- **superseded:** a later split or rename that GovStore already models (e.g. `ULANIA` → North/South Ulania).
  It is not imported, and the crosswalk records `superseded_by`.
- **add:** a new GovStore row with a new `GeoAreaId` from 9,359 upward, official code, and BBS Bangla/English
  per D2. This includes the 5 unnamed Dhaka thanas: they need a name from a current source before release.

### D6 — Out of scope: `hid` for urban rows

Backfilling `hid` for the 3,990 urban rows would widen every jurisdiction check: a district officer would start
seeing its pourashavas and wards. That is a behaviour change with its own review. `bbs_code` prefixes give
urban hierarchy queries meanwhile.

## 3. Review: classify every unlinked row

One reviewed file drives the build: `src/database/data/bbs-geocode-2017/review/decisions.csv`.

| Column | Meaning |
|---|---|
| `side` | `current` (keyed by `GeoAreaId`) or `bbs` (keyed by `bbs_path`) |
| `key` | `GeoAreaId` or `bbs_path` |
| `decision` | `link` · `newer` · `superseded` · `add` · `fix_parent` · `duplicate` |
| `target` | `bbs_path` for `link`, `GeoAreaId` for `superseded`/`duplicate`/`fix_parent` |
| `note`, `reviewed_by`, `reviewed_on` | Audit trail |

A helper `tools/prefill_geo_decisions.py` drafts each row from the crosswalk. Every draft stays
`decision = ''` until a person confirms it.

| Bucket | Rows | Draft decision |
|---|---:|---|
| Current, `no_counterpart_in_matched_parent` (unions, wards, thanas, 4 upazilas) | 305 | Top candidate ≥ 0.85 similarity → `link`; else `newer` |
| Current, `parent_not_matched` | 205 | Resolve the parent first, then rerun the crosswalk; leftovers inherit the parent's decision |
| Current, `missing_current_parent` (wards 7829–7837 → 58845) | 9 | `fix_parent` |
| Current, Bakerganj Pourasabha 58605 under Agailjhara (no wards) | 1 | `duplicate` of 58606 |
| Current, country | 1 | `bbs_code = BD`, `code_status = national` |
| BBS, `no_current_counterpart_in_matched_parent` | 275 | Sibling split/renamed in GovStore → `superseded`; else `add` |
| BBS, `parent_not_linked` | 163 | Follow the parent's decision |

Exit: every unlinked row has a confirmed decision, and the crosswalk rerun shows no row without one.

## 4. Build: one deterministic seed bundle

New tool `tools/build_geo_seed.py` (standard library only). It reads `geo_areas.csv`, the BBS dataset, the
crosswalk and `decisions.csv`, and writes `src/database/data/seed/`:

| File | Rows | Format |
|---|---:|---|
| `geo_areas.csv` | 9,111 + added admin rows | **Unchanged 9-column headerless format**, so the 2024 migration, `ReferenceSetup` and `reconcile_bbs_geocodes.py` keep working. Existing rows are byte-identical except for D2 English fixes. |
| `geo_area_codes.csv` | one per admin row | `GeoAreaId, bbs_code, bbs_code_legacy, code_status, code_source, bbs_attributes` (header, UTF-8) |
| `geo_area_localities.csv.gz` | 168,863 (fewer if parents are superseded) | 9 core columns + the code columns (header) |
| `manifest.json` | — | Dataset version (e.g. `bd-admin-areas-2026-11.1`), counts by type and status, SHA-256 of each file |

`geo_areas.csv` stays in its current location as the canonical core file. The build writes it in place, and
`seed/` holds the rest.

**Build gates** (the tool fails and writes no output if any gate fails):

1. Every row has `bbs_code` and `code_status`. `bbs_code` is unique.
2. No existing `GeoAreaId`, `geo_code`, `parent_geo_code` or `hid` changed. No existing row deleted.
3. Every `parent_geo_code` resolves. Every child `bbs_code` starts with its parent's (`P` segments included).
4. Bangla rule held: existing `bn_name` unchanged. Added rows are Bangla, with unmapped-glyph rows listed in the report.
5. Every unlinked crosswalk row has a confirmed decision.
6. Running the build twice produces identical files.

## 5. Database and install path

**Migration** `2026_11_xx_000001_add_bbs_codes_to_gov_geo_areas.php` (idempotent, like the name-index
migration): adds the D1 columns if missing, then calls the sync below.

**Service + command** `GeoAreaDatasetSync` / `php artisan gov-store:geo-areas:sync {--dry-run} {--without-localities}`:

- Inserts missing rows (`insertOrIgnore` by `GeoAreaId`), in chunks of 1,000.
- Updates the code columns of all rows from `geo_area_codes.csv` and the locality file.
- Updates `bn_name` only when empty. Updates `en_name` only for the D2 list. Never deletes.
- Records the dataset version and SHA-256 in a single settings row, so it knows when a newer bundle exists.
- Prints counts by status and exits non-zero if any row is left without a code.

**Fresh install:**
1. `php artisan migrate` creates the table and imports `geo_areas.csv` (2024 migration, unchanged).
2. The new migration then adds the columns and runs the sync, so codes and localities load in the same step.

**Existing install:** back up `gov_geo_areas`, run `sync --dry-run`, review counts, then `migrate`.

**Rollback:** the new columns are nullable and dropped by `down()`. Added rows are deleted only where
nothing references them (`gov_location_profiles`, `gov_ict_jurisdictions`, tracking facts, onboardings).

## 6. Application changes and tests

- `GeoArea`: fillable/casts for the new columns, plus `isProvisional()`.
- `GeoAreaService`: `getByBbsCode()`, `search()` default type guard (D4), and a `bbs_code` prefix scope.
- `GeoAreaController` / select2 results: return `bbs_code` and `code_status`. The office hub's "area code" line
  shows `bbs_code` instead of `hid`.
- Keep `geo_code` lookups (`resolveParentNames`, `OnboardLocationController`) as they are.
- Tests in `tests/Feature/GovStore/` (run with `php -d memory_limit=1G vendor/bin/phpunit`):
  - Sync is idempotent and never deletes or renames existing rows.
  - Every row has a code after sync (the D1 invariant).
  - The Bangla precedence rule holds.
  - Search excludes localities by default and returns them when asked.
  - `hid` containment results are unchanged for a sample jurisdiction before and after sync.

## 7. Phases

| Phase | Work | Exit criterion |
|---|---|---|
| 0 | Owner confirms D2 (English), D4 (same table), D3 (provisional format) | Decisions recorded here |
| 1 | `prefill_geo_decisions.py`, then human review of about 966 unlinked rows | `decisions.csv` complete; crosswalk rerun clean |
| 2 | `build_geo_seed.py` and seed bundle | All six build gates pass; `DATASET.md` version bumped with SHA-256 |
| 3 | Migration, sync service/command, model/service changes, tests | Full GovStore suite green |
| 4 | UI/API exposure (search results, office hub) | Seen on local preview (127.0.0.1:8010) |
| 5 | Rollout on existing installs: backup, dry run, migrate | Sync report shows zero rows without a code |
| Later | Replace `provisional` codes from a newer BBS geocode edition; consider D6 | — |

## 8. Risks

- **2017 vintage:** official codes for areas changed since 2017 may themselves have changed. Mitigation:
  `code_source` records the edition, so a newer edition can be applied by rerunning the pipeline.
- **Name-based links:** the crosswalk's `skeleton` tier (243 links) is the weakest. Phase 1 reviewers sample it,
  and any wrong link is corrected through `decisions.csv`, not by editing outputs.
- **Bangla repair:** unmapped glyphs and PDF-dropped glyphs affect only added rows. They are listed for
  correction before release.
- **Table size:** about 178k rows. Name indexes already exist, and the D4 search guard keeps typeahead behaviour unchanged.
