# Geo Areas and Organization implementation record

Date: 7 October 2026 (Asia/Dhaka)  
Branch: `package-gap-mitigation`

Organization follow-up: [8 October implementation and verification](organization-implementation-2026-10-08.md)
supersedes the organization remaining-work status below. This record preserves
the 7 October implementation and verification evidence.

## Implemented

### Geo Areas

- Removed the non-AJAX `dd()` diagnostic; empty searches now return the normal
  JSON typeahead response.
- Validated and bounded search inputs. English and Bangla names are returned
  separately, `text` follows the active locale, and the historical `divison`
  type is exposed as the canonical `division` without rewriting stored values.
- Changed typeahead matching to a sorted prefix search and added additive
  English/Bangla name indexes. The original migration now preserves an existing
  geography table if it is rerun.
- Added a dataset version, file hash, refresh review process and an explicit
  provenance/license limitation in `packages/gov-store/geo-areas/DATASET.md`.

### Organization

- Added `office_type` to office profiles and dispatched a typed
  `OfficeProvisioned` event after the provisioning transaction commits. The
  classification listener reads the explicit event type. Starter work waits for
  a company-scoped office administrator and runs under that actor; assigning an
  administrator emits the handoff event too.
- Made the historical organization migration create missing tables without
  dropping existing tables. An additive migration backfills valid legacy
  approver/storekeeper assignments to `OfficeResponsibility`; the old table is
  retained as an archive and is no longer an active model or relation.
- Paginated the office registry at 25 rows, scoped counts and ministry filters
  to the authorized ICT geography, completed English/Bangla key parity, and
  removed the unused response-rewriting middleware.

## Verification

- `tests/Feature/GovStore/GeoAreasPackageTest.php` and
  `tests/Feature/GovStore/OrganizationPackageTest.php`: **11 tests, 37 assertions**
  passed on isolated in-memory SQLite.
- `tests/Feature/GovStore/G1AuthorizationTest.php`: **29 tests, 1,003 assertions**
  passed on isolated in-memory SQLite.
- Compiled all **10 Organization Blade views** and parsed the compiled PHP.
- Read-only live inspection confirmed database `snipeit` contains 9,111
  geography rows. `EXPLAIN` for English/Bangla prefix search selected both name
  indexes (`index_merge`; estimated 8 rows).
- Applied only the two new targeted migrations to the confirmed local `snipeit`
  database. The two indexes and profile `office_type` column are present. All
  57 existing profiles have the non-destructive `default` value. The live legacy
  `gov_location_roles` table was absent, so no live role rows were changed.
- The local browser refused the authorized app URL with
  `net::ERR_BLOCKED_BY_CLIENT`; no live UI walkthrough is claimed. No office or
  user fixture was created.

## Remaining

- **GEO-2 is partial:** the bundled file is versioned and has a refresh/review
  procedure, but its publisher, retrieval date and license are not established.
  Do not adopt a refreshed source until those are verified.
- **ORG-2 is partial:** the event/type contract and listener handoff are wired,
  but the provisioning-to-starter-catalog workflow still needs consumer-side
  verification for an authorized actor, company context and safe retry behavior
  (the CL-2 completion gate).
- **ORG-1 remains open:** suspend/relocate/merge/close transitions are not
  complete. Closure must wait until office-membership and all request/committee
  consumers provide complete clearance rules for holdings and obligations.
- Live UI rendering, remote CI, full application regression, production rollout,
  and real-world observation are not established by these local checks.
