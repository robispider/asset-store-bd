# Gov-Store Boundary Data — Source Choice and Vector Build (Phase 0 Plan)

> **Scope:** choose, verify and prepare the administrative boundary polygons (division → district → upazila → union)
> that the geo heatmap will draw. The output is a versioned, render-ready set of vector shapes keyed by `GeoAreaId`,
> built offline into `packages/gov-store/geo-areas`. The heatmap component itself is out of scope; §8 fixes only the
> data contract it will read.
> **Status:** phase 0 done. D1–D5 decided (§5), crosswalk reviewed, boundary set `bd-boundaries-2026-10-21.1` built
> with all six gates passing · **Updated:** 2026-10-21
> **Replaces:** phase 0 of `docs/plans/geo-monitoring-plan.md` (§7) and its §2.1 note that codes are not BBS codes.
> **Reference:** `packages/gov-store/geo-areas/DATASET.md` (dataset and boundary change procedure),
> `docs/plans/bbs-geocode-adoption-plan.md` (`bbs_code`, `bbs_code_legacy`), `bbs-geocode-2017/README.md`.

---

## 1. Summary

GovStore now carries an official BBS geocode on every area (`bbs_code`, plus the concatenated `bbs_code_legacy`).
Both forms line up with OCHA P-codes, so polygons are joined to `GeoAreaId` **by code**, never by name.

The only open-licence union polygons with a BBS lineage are the 2015 BBS/OCHA set, which survives as the geoBoundaries
ADM4 file. Its P-codes were stripped, but they come back exactly from a point-in-polygon join against the current
OCHA points table (5,160 of 5,160, one point per polygon).

**One geometry vintage for every level.** The union polygons come from geoBoundaries ADM4 (BBS/OCHA 2015, CC BY 3.0
IGO). Upazila, district and division shapes are dissolved from those unions along the **GovStore tree**, so nesting is
exact by construction and children share their parents' borders. OCHA COD-AB v03 (March 2025) supplies the P-code
bridge and the evidence for upazila recodes, but is not drawn: its linework differs too much from the 2015 unions to
mix with them (§4.4).

| Level | GovStore areas | With a shape |
|---|---:|---:|
| Division | 8 | 8 (100%) |
| District | 64 | 64 (100%) |
| Upazila + city corporation | 506 | 502 (99.2%) |
| Union + paurashava | 4,916 | 4,817 (98.0%) |

Output: `packages/gov-store/geo-areas/src/database/data/boundaries/bd-boundaries-2026-10-21.1/`, 563 per-parent SVG
path files and 4 TopoJSON level files (10.8 MB), with `manifest.json` and `BUILD_REPORT.md`.

---

## 2. What changed since the geo-monitoring plan

| Then (geo-monitoring §2.1) | Now (dataset `bd-admin-areas-2026-10-21.1`) |
|---|---|
| "Codes are not BBS codes"; a name-free crosswalk had to be invented | Every row has `bbs_code` (`20-19-_-40-_-714`) and most have `bbs_code_legacy` (`20194095`) |
| P-code join impossible without review | P-code = `BD` + code, by rule (§4.1); only exceptions need review |
| 9,111 rows | 9,197 rows (86 BBS areas added), 1,722 `provisional` codes for areas newer than June 2017 |

`geo_code` and `hid` stay the internal tree keys. Boundary files key on `GeoAreaId` only.

---

## 3. Sources investigated

| Source | Levels | Vintage | Licence | Codes | Verdict |
|---|---|---|---|---|---|
| **OCHA COD-AB v03** (HDX `cod-ab-bgd`, BBS via OCHA ROAP/FIS) | 0–3: 8 / 64 / 507 (495 upazilas + 12 city corporations) | BBS updates to March 2025; reviewed June 2025 | CC BY 3.0 IGO | P-codes `BD`, `BD10`, `BD1004`, `BD20030004` | **Codes and QA only.** Newest official set; it codes Karnaphuli, Eidgaon, Dasar, Shayestaganj, Madhyanagar, Shantiganj and Mymensingh CC. Not drawn (§4.4). Zip SHA-256 `ec54c97c…c9e5` |
| COD-AB v03 `adminpoints` (in `bgd_admin_boundaries.geojson.zip`) | Points for ADM4: 5,160 unions/pourashavas/wards | 2015 lineage (old 2-digit union codes) | CC BY 3.0 IGO | `adm4_pcode` e.g. `BD40448010` | **The P-code bridge** for the union polygons |
| HDX "administrative boundaries of Bangladesh as of 2015" | 0–4 with P-codes | 2015 (2011 census) | CC BY 3.0 IGO | P-codes | **Withdrawn** (HDX returns 404). Only survives in derived copies |
| **geoBoundaries BGD ADM4** (`geoBoundaries-BGD-ADM4-all.zip`, release commit `9469f09`) | 5,160 unions / pourashavas / city wards | Source: the 2015 HDX set above; built 12 Dec 2023 | CC BY 3.0 IGO (from BBS/OCHA), geoBoundaries CC BY 4.0 for its processing | **None**: `shapeName`, `shapeID` only; 423 names repeat | **Every polygon**, after the P-code recovery join (§4.3) |
| geoBoundaries BGD ADM0 / ADM1 | 0–1 | 2015 | CC0, sourced from **Wikimedia Commons** | None | Reject: not BBS lineage |
| geoBoundaries BGD ADM2 / ADM3 | 64 / 544 | 2015 HDX set | CC BY 3.0 IGO | None | Not needed: dissolved from ADM4 along the GovStore tree instead |
| technicalgis.com Bangladesh page | — | — | — | — | Link directory only. Points to COD-AB, geoBoundaries, GADM, OSM, Survey of Bangladesh. No data of its own |
| GADM | 0–4 | varies | **Non-commercial only** | GID, not BBS | Reject: licence |
| OpenStreetMap (Geofabrik) | mixed `admin_level` | live | ODbL (share-alike) | none | Reject: share-alike, incomplete union boundaries, no codes |
| Survey of Bangladesh / LGED | topo maps, union maps | — | sold / not openly licensed | — | Reject for now; worth a formal request if an official union layer is ever needed |
| BBS (bbs.gov.bd) | geocode books, no GIS download | June 2017 | — | the codes we already use | Codes only |

COD-AB v03 notes that ADM4 "is not officially confirmed or maintained consistently at the national level" and is no
longer published as a COD. So **there is no current, official, open union polygon layer**. The 2015 layer is the
best open option, and the map must say "boundaries: BBS/OCHA 2015".

---

## 4. Measured fit with GovStore codes

§4.1–4.3 are reproduced on every run of `tools/build_boundary_crosswalk.py`. §4.4 was a one-off geometry comparison
(shapely, scratch scripts, not committed).

### 4.1 Code rule

| Level | GovStore | P-code |
|---|---|---|
| Division | `bbs_code` `20` | `BD20` |
| District | `20-19` | `BD2019` |
| Upazila | `20-03-_-04` | `BD20030004` (`BD` + div + dist + `00` + upazila) |
| City corporation | `20-19-50` | `BD20195000` (city code, then `00`) |
| Union (2015 points) | `bbs_code_legacy` `40448010` | `BD40448010` |
| City ward (2015 points) | `thana_ward` legacy `5081662210` | `BD50816210` (city segment dropped) |

### 4.2 First-pass results

| Level | Source | Source rows | GovStore rows | Matched by code | Gap |
|---|---|---:|---:|---:|---|
| ADM1 | v03 | 8 | 8 | **8** | — |
| ADM2 | v03 | 64 | 64 | **64** | — |
| ADM3 | v03 | 507 | 506 | **500** | 6 GovStore `provisional` upazilas/CC that v03 now codes officially (Karnafuli, Dasar, Shayestaganj, Madhyanagar, Mymensingh CC); Southsunamganj (`…27`) vs v03 Shantiganj (`…87`); Eidgaon is missing from GovStore |
| ADM4 | 2015 points | 5,160 | 4,526 coded unions (+64 provisional, 15 duplicate) | **4,442** | 311 pourashava blocks, about 285 city-ward polygons, about 120 unions whose upazila code changed after 2011 (Amtali `09` → Taltali `90`, Galachipa `57` → Rangabali `97`, Cumilla Sadar Dakshin `33` → Lalmai `73`). §6.2 shows how every polygon was resolved |

### 4.3 P-code recovery for geoBoundaries ADM4

Each `adminpoints` level-4 point is placed into the geoBoundaries polygons (simplified file):

- 5,160 polygons, 5,160 points: **every polygon holds exactly one point**, with no misses and no doubles. The
  crosswalk tool refuses to run if this ever stops being one-to-one.
- The names agree on 5,159 of them. This is only a QA check; the join itself is spatial.
- Geometry: 0 invalid polygons, 0 overlaps between unions in the same upazila, 255 parts nationally (islands, chars),
  about 259k exterior vertices in the simplified file. The full file is 341 MB of GeoJSON, about 1,500 vertices per
  union.

Desktop file SHA-256: `c026dd92cc01136b8d503bf38db0925736daa10829314b6333ececb31faf558a` (zip, identical to the
geoBoundaries release file) · full GeoJSON `e5d5dbfb…d37b3` · simplified GeoJSON `83a45746…d648`.
COD-AB v03 GeoJSON zip SHA-256: `ec54c97c2004bbee084afa3f662abb202d8ec038dbfd398af2ebb9b1bf3ec9e5` (37.8 MB, HDX
resource last modified 2026-01-26).

### 4.4 Border check (P0.3): v03 linework vs 2015 unions

Each 2015 union was assigned to the v03 upazila it overlaps most. Each v03 upazila (and district) was then compared
with the dissolved unions assigned to it. The full-resolution and simplified union files give the same numbers, so
this drift is real and not caused by simplification.

| Measure | Result |
|---|---|
| Unions with ≥ 99% of their area inside one v03 upazila | 2,203 of 5,160 (95–99%: 1,955; 80–95%: 894; below 80%: 108) |
| Upazilas: symmetric difference ÷ area | under 1%: 1 · 1–3%: 35 · 3–10%: 333 · 10% or more: 138 (of 507) |
| Upazilas matched by code instead (482 with an unchanged code) | median 6.8%; 140 at 10% or more, 56 at 25% or more |
| Districts: symmetric difference ÷ area | 1–3%: 32 · 3–10%: 21 · 10% or more: 11 (Bhola 46%; Lakshmipur, Noakhali, Patuakhali, Khulna about 20%) |
| National outline | v03 is about 4% larger and includes major rivers and the estuary as land; the 2015 layer leaves them out as gaps |

The differences are concentrated on the coast, the Meghna estuary, chars and the Chittagong Hill Tracts. Inland
districts differ by only 1–3%. Mixing v03 upper levels with 2015 unions would fail the nesting gate almost everywhere,
so D2 dissolves every level from the 2015 layer.

---

## 5. Owner decisions

Decided 2026-10-21: the owner asked for the plan to be executed, so the recommendations below were applied.

| # | Decision | Outcome |
|---|---|---|
| D1 | Union polygons | **geoBoundaries ADM4** (BBS/OCHA 2015), P-codes recovered by spatial join. Unions and paurashavas newer than 2015 have no shape and appear in the table only (99 of 4,916) |
| D2 | Division / district / upazila polygons | **Dissolved from the 2015 unions along the GovStore tree.** A polygon counts toward the ancestors of the GovStore area it is linked to. Nesting is exact; newer upazilas get a shape from their own unions (Karnaphuli, Lalmai, Taltali, Rangabali, Shayestaganj, Guimara). Cost: 2015 coastline and rivers. An upazila whose unions GovStore still lists under the old upazila has no shape (Tarakanda, Osmani Nagar, Madhyanagar, Dasar; §10) |
| D3 | Urban units | Paurashavas and city corporations are drawn as `kind: "urban"` shapes, not drillable in v1. A paurashava is its 2015 urban block; a city corporation is the dissolve of its 2015 ward polygons (through the metro-thana codes) plus the paurashavas it absorbed. Mymensingh CC (2018) is the former Mymensingh paurashava block. City wards are deferred. Every 2015 polygon is assigned to an upazila or city, so the dissolve has no holes. Offices on urban units roll up through `rollupArea()` as already planned |
| D4 | What goes in git | `sources.json`, the crosswalk, the recode table, review decisions and reports, and the built render files. Raw sources are not committed: `fetch_boundary_sources.py` downloads them and verifies SHA-256, the same as the BBS PDFs |
| D5 | Attribution | Map footer and `manifest.json`: "Boundaries: Bangladesh Bureau of Statistics and OCHA, administrative boundaries of Bangladesh as of 2015 (CC BY 3.0 IGO), via geoBoundaries (CC BY 4.0); P-codes from OCHA COD-AB v03 (CC BY 3.0 IGO). Higher levels are dissolved from the 2015 unions." |

Review rows were confirmed as "Claude (assisted review, owner-approved plan)", the same convention as
`bbs-geocode-2017/review/decisions.csv`. Each row has a note with its evidence; a person can overturn any row and
rebuild.

---

## 6. Crosswalk

### 6.1 Files

Under `packages/gov-store/geo-areas/src/database/data/boundaries/`:

| File | Content |
|---|---|
| `sources.json` | URL, bytes, SHA-256, licence and retrieval date of the two source archives |
| `crosswalk.csv` | One row per source polygon: `level, source, source_id, source_pcode, GeoAreaId, parent_GeoAreaId, method, status, note, reviewed_by, reviewed_on`. 507 v03 ADM3 rows (parent fallback and evidence) and 5,160 ADM4 rows |
| `upazila_recode.csv` | 14 reviewed rows: old 2011 upazila code → current upazila (12 confirmed, 2 rejected as border noise) |
| `review/boundary_decisions.csv` | 130 confirmed review rows (draft, decision, target, parent, note, reviewer) |
| `review/CROSSWALK_REPORT.md` | Counts per method and status, the name QA list, held polygons and areas without a polygon. Names appear only here |

`method`: `code` (§4.1) · `upazila_recode` (reviewed old → new upazila, same district and union number, point inside
the new upazila's v03 polygon) · `urban_block` (the upazila's only 2015 urban block and only paurashava) ·
`city_ward` (a 2015 polygon in a city thana whose ward number GovStore lacks) · `reviewed`.
`status`: `linked`, `no_govstore_area`, `held`. `parent_GeoAreaId` is the upazila or city corporation the polygon is
drawn under: from the GovStore tree when linked, otherwise the reviewed parent, else the v03 area containing its point.

### 6.2 How the 5,160 polygons were resolved

| Method · status | Polygons |
|---|---:|
| `code` → union | 4,436 |
| `code` → city ward | 190 |
| `urban_block` → paurashava | 274 |
| `upazila_recode` → union | 20 |
| `reviewed` → union | 60 |
| `reviewed` → paurashava | 27 |
| `reviewed` · `no_govstore_area` (Sundarbans forest ranges, unions absorbed into a paurashava or city corporation) | 32 |
| `reviewed` · `held` (Ulania, split after 2017; 3 renumbered DNCC wards) | 4 |
| `city_ward` · `held` (renumbered wards and absorbed unions inside city thanas) | 117 |

Every polygon is drawn, at least as part of its parent.

### 6.3 Rules as applied

- **Never link on names.** When a code link's name clearly belongs to another GovStore union under the same upazila
  (2011/2017 numbering swaps such as Islampur and South Rajanagar in Rangunia), the tool sends it to review instead of
  linking. Reviewers use names as evidence; the tool does not.
- A polygon's parent comes from the GovStore tree (`parent_geo_code`), not from its P-code prefix. A 2015 Patiya
  union that GovStore places under Karnaphuli is drawn in Karnaphuli.
- A union polygon is never attached to a paurashava. A union that later became a paurashava stays `no_govstore_area`
  and is drawn as part of its upazila.
- The two malformed `union` rows from the geo-monitoring audit no longer exist in `geo_areas.csv` (all rows have nine
  columns, and every union has `GeoLevel` 4).

---

## 7. Build pipeline (offline, `geo-areas/tools/`)

| Step | Script | Notes |
|---|---|---|
| 1 | `fetch_boundary_sources.py` (stdlib) | Downloads both archives to `%TEMP%/gov-store-boundaries/<id>/` with resume, verifies SHA-256 against `sources.json` and extracts the members the later steps read. `--seed ID=PATH` uses a local copy (for example the desktop zip) |
| 2 | `build_boundary_crosswalk.py` (stdlib) | Point-in-polygon P-code recovery, the §4.1 code rules, conflict routing, recode table, review drafts. Keeps confirmed rows on rerun and rebuilds unconfirmed drafts |
| 3–4 | `build_boundaries.py` (stdlib + Mapshaper 0.6.121, pinned in `tools/package.json`: `npm install` in `tools/`, then `npm run geo:boundaries`) | Mapshaper: import with snapping, join the crosswalk ancestors, `-clean`, dissolve each level so all levels share arcs, Visvalingam simplification with `keep-shapes` (1,200 / 350 / 120 / 35 m for levels 1–4). Python: Transverse Mercator (WGS84, central meridian 90°E, k 0.9996; EPSG:3106 without its datum shift), rounded to 10 m in a fixed national frame, relative SVG paths, one file per parent, manifest and report |

Mapshaper is installed from the package's own `tools/package.json`, not the Snipe-IT root `package.json`.

Outputs, under `src/database/data/boundaries/<version>/`:

```
manifest.json            version, dataset version, sources, licences, attribution, tolerances, frame, coverage, SHA-256 per file
level-1.json … level-4.json   canonical simplified shapes, WGS84 TopoJSON, feature id = GeoAreaId
children/<GeoAreaId>.json     render-ready: {version, id, viewBox, outline, areas:[{id, kind, path}]}
BUILD_REPORT.md          coverage, sizes, partial parents, areas without a shape, polygons drawn only as part of a parent
```

Build gates (the build refuses to write unless all pass):

1. Every shape's `id` is a GovStore area of the right type, and no `GeoAreaId` appears twice per level.
2. Both archives match `sources.json`; the extracted members match the archive CRCs.
3. No shape collapses after simplification and rounding (`keep-shapes`; Mapshaper repairs intersections).
4. Full cover and nesting: every 2015 polygon has an upazila/city parent with a district and division above it;
   children cover at most 100.5% of their parent's area and stay inside its bounds.
5. Size budget: `children/<country>.json` ≤ 60 KB, division and district files ≤ 150 KB, upazila files ≤ 80 KB
   (before gzip). Built: at most 42.6 KB, 36.7 KB, 26.5 KB and 30.4 KB.
6. The whole build runs twice and the two results are byte-identical.

---

## 8. Data contract for the neutral heatmap component

The boundary build and the heatmap component meet only here. The component knows nothing about BBS, GovStore or
metrics.

```jsonc
// children/<GeoAreaId>.json as built
{ "version": "bd-boundaries-2026-10-21.1", "id": 2760, "viewBox": "11532 14806 9890 8573",
  "outline": "M…z",                                      // the parent's own shape, drawn underneath
  "areas": [ { "id": 2761, "kind": "area", "path": "M14920 15004l12-8 3 5…z" },
             { "id": 5371, "kind": "urban", "path": "M…z" } ] }
// GET gov-store/api/geo/areas/{area}/children (geo-monitoring phase 1) adds names in the viewer's locale and
// hasChildren, and lists every GovStore child, so areas without a shape are returned without "path"
// Any data source supplies values keyed by the same ids
{ "values": { "2761": 0.82, "5371": null }, "scale": { "type": "sequential", "steps": 5, "domain": [0, 1] } }
```

- Coordinates are integers in one national frame (10 m units, origin at the country's north-west corner), so files at
  different levels line up and a `viewBox` tween between them is exact. Paths are relative and need
  `fill-rule="evenodd"` (holes are separate subpaths).
- `outline` is drawn first. Parts of it with no child shape (forest ranges, absorbed unions) show as "no area".
- `kind: "urban"` marks city corporations and paurashavas: coloured, but not drillable in v1 (D3).
- `duplicate` GovStore rows have no shape; the API should colour their original (`bbs_attributes.duplicate_of`).

---

## 9. Phase 0 steps

| Step | Work | Status |
|---|---|---|
| P0.1 | Owner confirms D1–D5 | Done (§5) |
| P0.2 | `fetch_boundary_sources.py` + `sources.json` | Done; both archives verify |
| P0.3 | Compare v03 ADM3 with upazilas dissolved from ADM4 | Done (§4.4): drift too large to mix vintages; D2 dissolves along the GovStore tree |
| P0.4 | Crosswalk + upazila recode table + review | Done: every polygon `linked`, `no_govstore_area` or `held` with a note; 0 open rows |
| P0.5 | Mapshaper build + gates + report | Done: `bd-boundaries-2026-10-21.1`, all six gates pass |
| P0.6 | Docs | Done: DATASET.md "Boundaries" section; geo-monitoring plan §2.1 and §7 point here |

Tooling on this machine: Python 3.11 (stdlib is enough), Node (Mapshaper via npm). GDAL, shapely and geopandas are
not required.

---

## 10. Dataset follow-ups (not part of this build)

These are GovStore tree issues the boundary review surfaced. Fixing them is a `bbs-geocode-2017/review/decisions.csv`
change under DATASET.md with a new dataset version; then rerun the crosswalk and the boundary build, and the affected
upazilas get their own shapes.

| GovStore issue | Effect on the map today |
|---|---|
| Tarakanda's 10 unions are listed under Phulpur (provisional codes) | Phulpur is drawn with Tarakanda's area; Tarakanda has no shape |
| Osmani Nagar's 8 unions are listed under Balaganj | Balaganj is drawn with Osmani Nagar's area; Osmani Nagar has no shape |
| Naldanga's 5 unions are listed under Natore Sadar | Natore Sadar is drawn with them; Naldanga is only its paurashava |
| Gazipur and Rangpur city corporations: the unions they absorbed are still unions of Gazipur Sadar / Rangpur Sadar | The city corporation shapes are only the former paurashavas |
| Madhyanagar and Dasar have no unions in GovStore; Guimara has only one | No or partial shape |
| Shayestaganj paurashava is listed under Habiganj Sadar | Drawn in Habiganj Sadar |
| v03 gives official codes for the 6 provisional upazilas/CC and a code for Eidgaon (not in GovStore) | None for shapes; codes stay provisional |
| 53 code links whose 2015 name differs from both the GovStore and BBS 2017 names (CROSSWALK_REPORT QA list) | Probably renames; a person should skim the list |

---

## 11. Risks

| Risk | Mitigation |
|---|---|
| Union layer is 2015 vintage; unions created later have no shape | Coverage report; "no boundary" state; table lists every area; replace with an official layer if BBS/LGED ever publish one |
| 2015 coastline, chars and river courses are out of date (v03 differs by up to 46% in Bhola) | Accepted for a choropleth; stated in the attribution. If an official current union layer appears, swap the base layer; the crosswalk and dissolve stay the same |
| GovStore tree issues move area between upazilas on the map | §10 follow-ups; the map follows the tree so it matches office scoping |
| Licence misread | Both sources are CC BY 3.0 IGO (BBS/OCHA) plus geoBoundaries CC BY 4.0; attribution in footer and manifest (D5); geoBoundaries ADM0/1 (Wikimedia) not used |
| HDX or geoBoundaries withdraws or reissues a file | Pinned SHA-256 and release commit; the built files are committed, so the app never fetches at runtime |
| Wrong polygon attached by a code coincidence | Conflict routing to review, spatial check on every recode, name QA list in the report |
| Payload size | Per-parent files, per-level simplification, size gate |
