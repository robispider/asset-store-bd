# Bangladesh geographic reference dataset

## Current bundled revision

- Dataset version: `bd-admin-areas-2026-10-07.1`
- File: `src/database/data/geo_areas.csv`
- Rows: 9,111 (headerless CSV)
- SHA-256: `1b1bf5beba43c321262dc587930153eac7cd0ff6d96f61abb5d013a863edc94d`
- Provenance: the repository does not record the publisher, retrieval date, or
  license for this historical file. The per-area `domain` field contains URLs,
  but those URLs are not sufficient evidence that they were the dataset source.
  Confirm publisher, terms, and an authoritative source before adopting an
  upstream refresh.

## Refresh and compatibility procedure

Local metadata correction, 15 October 2026: recomputed the bundled file hash and
corrected the previous 65-character transcription in this document. The CSV data
and application identifiers were not changed; upstream provenance remains
unverified. The [tagging geography decision](GEOCODE-SUPPLEMENT-DESIGN.md)
retains the extracted March 2013 codes as offline reference only; it adds no
geography tables and leaves this dataset unchanged.

1. Obtain a dated source release with documented publisher and reuse terms.
2. Compare identifiers, hierarchy paths, parent codes, both names, and types
   against the bundled revision. Keep `GeoAreaId` stable for existing profile,
   jurisdiction, and tracking references; do not replace the table in place.
3. Validate row shape, unique IDs, parent references, hierarchy prefixes, and
   a reviewed change report. Record the release, retrieval date, license, and
   new SHA-256 in this file in the same change as the data.
4. Submit reviewed additive data changes as a new migration or controlled import
   with a rollback plan. Never rerun the original table-creation migration to
   refresh data; it now leaves an existing table intact.
5. Keep `divison` accepted as an input alias and normalize it to `division` in
   the service/API. Do not rewrite stored types until all SQL consumers and
   existing identifiers have been reviewed.

## BBS geocode reference (June 2017) and crosswalk

`src/database/data/bbs-geocode-2017/` holds the 64 BBS district geocode volumes
(covers dated June 2017, published on bbs.gov.bd in December 2024), extracted to
CSV down to mauza and village level. `crosswalk/` links this bundled dataset to
BBS codes and keeps both sides' unmatched rows with reasons. It is offline
reference data. It adds no tables, and `GeoAreaId`, `geo_code` and
`geo_areas.csv` are unchanged. See that folder's README for columns, limitations
and the `tools/*bbs_geocode*` scripts that regenerate it. A BBS link is a review
aid toward step 2 above, not a new official identifier.

The search endpoint uses English and Bangla prefix matching. Dedicated name
indexes support typeahead on the 9,111-row bundled dataset. Substring search is
intentionally not used because it cannot use these indexes.
