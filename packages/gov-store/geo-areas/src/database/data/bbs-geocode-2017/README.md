# BBS geocode, June 2017 (district volumes)

Reference data extracted from the 64 district volumes of *Bangladesh Geocode*
(Bangladesh Bureau of Statistics, Strengthening Geocoding System programme).
Every cover is dated **June 2017**. BBS published the PDFs in December 2024 on
<https://bbs.gov.bd/pages/static-pages/6922ded1933eb65569e1d95d>. The PDFs were
retrieved on 21 October 2026. `sources.json` records each URL, SHA-256, page count
and row count.

These files are the extracted source. The seed build (`tools/build_geo_seed.py`)
turns them, the crosswalk and the reviewed `review/decisions.csv` into the geocodes
and localities loaded into `gov_geo_areas`; no existing `GeoAreaId` changes (see
[DATASET.md](../../../../DATASET.md)).

## Files

| File | Contents |
| --- | --- |
| `bbs_geocode_2017_admin.csv` | 8,957 rows: zila, city corporation, upazila/thana, paurashava, union/ward (cards 2–6) |
| `bbs_geocode_2017_full.csv.gz` | 177,820 rows: the above, plus 63,358 mauza/mahalla (card 7) and 105,505 village/mahalla/para (card 8) rows |
| `extraction_issues.csv` | Rows that failed a structural check, or where a wrapped figure was rejoined |
| `sources.json` | Source manifest, per-volume counts, Bangla glyphs still unmapped |
| `crosswalk/` | Links to the current `geo_areas.csv`, both matched and unmatched (see `crosswalk/README.md`) |

CSV files are UTF-8 with a BOM. Read the code columns as **text** to keep leading zeros.

## Columns

The 22 numbered columns of the PDF table, plus provenance:

| Column | PDF | Meaning |
| --- | --- | --- |
| `source_file`, `page` | | Volume file (see `sources.json`) and 1-based page |
| `district_label` | | District as labelled on the BBS web page |
| `division_name` | | From the division code. The web page lists the four Mymensingh (45) districts under Dhaka. |
| `card` / `level` | 1 | 2 zila, 3 city corporation, 4 upazila/thana, 5 paurashava, 6 union/ward, 7 mauza/mahalla, 8 village/mahalla/para |
| `division_code` … `paurashava_code` | 2–6 | Division, zila, city corporation, upazila/thana, paurashava |
| `union_ward_code_old` / `_new` | 7–8 | Union/ward code: old 2-digit and new 3-digit |
| `mauza_mahalla_code_old`, `mauza_code_new` | 9–10 | Mauza/mahalla code, old and new |
| `jl_no` | 11 | JL number as printed (may be `70/138` or `27/a`) |
| `village_code_old`, `village_mahalla_para_code_new` | 12–13 | Village/mahalla/para code, old and new |
| `rmo` | 14 | RMO code |
| `en_name` | 15 | English name as printed |
| `bn_name` | 16 | Bangla name, repaired (see below) |
| `bn_name_raw` | 16 | Bangla text exactly as extracted |
| `total_parts`, `part_no` | 17–18 | মোট খণ্ড / খণ্ড নং |
| `land_acres`, `river_acres`, `forest_acres` | 19–21 | Area in acres |
| `area_type` | 22 | ধরণ code, as printed |
| `bbs_path` | | Hierarchy key: `division-zila-city-upazila-paurashava-union/ward-mauza-village` (new codes; `_` for an empty level) |

Rural unions have no city or paurashava code. City wards carry the city and
thana codes. Paurashava wards carry the upazila and paurashava codes.

## Known limitations

- **Vintage.** Boundaries and names are as of June 2017. Later units are absent,
  for example Dasar, Madhyanagar, Shayestaganj and Karnafuli upazilas, the
  Mymensingh City Corporation (2018), and the 2018-onward Gazipur metropolitan
  thanas. Later renames are also missing, such as Zianagar → Indurkani.
- **Unnamed thanas.** The Dhaka volume does not name its metropolitan thanas: the
  thana code only appears on ward rows.
- **Bangla names.** The PDFs embed Nikosh glyphs without a usable Unicode map.
  `bn_name` reorders the pre-base vowel signs and maps 128 ligature glyphs. The
  glyph table was learned from names matched on English, and from English
  transliterations. 1.4% of rows still contain an unmapped glyph (listed in
  `sources.json`). A few glyphs were dropped by the PDF itself (for example
  রুপসা → পসা). Some words keep a stray space where the PDF split them. Treat
  `bn_name` as a strong hint, not an authority.
- **Source quirks.** The Jashore volume misprints column 12 as "11". Columns are
  located by position, so this is handled. Long figures that wrap inside a cell are
  rejoined and listed in `extraction_issues.csv`. 26 `bbs_path` values repeat in
  the source (split mauzas and villages).

## Reproduce

From the repository root:

```text
python packages/gov-store/geo-areas/tools/fetch_bbs_geocode_pdfs.py          # stdlib; PDFs to %TEMP%/gov-store-bbs-geocode/pdfs
pip install -r packages/gov-store/geo-areas/tools/requirements-bbs-pdf.txt
python packages/gov-store/geo-areas/tools/extract_bbs_geocode_pdfs.py        # writes this folder
python packages/gov-store/geo-areas/tools/crosswalk_bbs_geocode_2017.py      # stdlib; writes crosswalk/
python packages/gov-store/geo-areas/tools/prefill_geo_decisions.py           # stdlib; drafts review/decisions.csv
python packages/gov-store/geo-areas/tools/build_geo_seed.py                  # stdlib; writes geo_areas.csv + seed/
```

`review/decisions.csv` holds one confirmed decision per unlinked area (`link`,
`moved`, `newer`, `duplicate`, `national`; BBS side `linked`, `superseded`, `add`).
The first review (21 October 2026) was an assisted review under the approved plan;
rows marked "verify" in `note` are inferences worth a human check.

The extractor checks every PDF against the SHA-256 in the fetch manifest
before reading it.
