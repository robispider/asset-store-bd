# Expanded common office collection — implementation and verification

Recorded 26 October 2026, Asia/Dhaka. This extends the [v1
record](common-office-collection-2026-10-26.md); its 114-item results remain dated
evidence for that earlier bundle.

## Implemented

- Expanded the same `bd-common-office` collection from **114 to 621 item types**,
  across **22 groups**, with English and Bangla labels. All original 114 CSV rows
  remain unchanged. [Scope and counts](../../packages/gov-store/classification/src/database/data/office-defaults/v2/README.md).
- Added the requested first-aid supplies, books/newspapers/magazines, curtains and
  blinds, decorative living/artificial plants, office vehicles, vehicle maintenance
  tools/spares, trunks, iron-safe aliases and dining/kitchen equipment. Existing
  paper shredders remain, alongside laminators, binding/cutting machines, document
  handling equipment and replacement supplies.
- Steel, iron and aluminium trunks share the exact bundled UNSPSC `56101516`
  storage-chest type, with searchable material aliases. Actual material remains a
  goods specification. Iron safes use `46171506`, with English/Bangla aliases.
  No duplicate classification code or fabricated commodity was added for materials.
- Added a separately pinned v2 bundle and forward-only migration. Fresh
  `php artisan migrate` installs v1 then v2; an existing v1 installation adds the
  upgrade to the same collection. Existing collection names/active status, edited
  descriptions, local labels/notes, defaults, mappings and adoptions are preserved.
  Only exactly unchanged shipped v1 descriptions are refreshed. PHP compares those
  strings exactly, independent of MySQL's case-insensitive collation.
- Inventory defaults: 174 assets, 188 consumables, 213 accessories, 46 components.
  All 621 codes and English titles match the bundled reference; their complete
  ancestor closure has 899 nodes. No new route, gate or web review bypass.
- Changed bulk-adoption submission to JSON: 621 items require more than 1,000 form
  fields when submitted as code/type pairs. The dialog preserves the full selection
  and converts jQuery's numeric data attributes back to string codes for server
  validation. Existing protected controller/service paths and CSRF remain in use.

## Automated verification

PHP 8.4.15, Xdebug disabled, isolated SQLite databases:

- Focused collection suite: **15 tests, 8,174 assertions passed**. Exercises actual
  classification migrations, fresh and existing-v1 upgrade, preserved local edits
  and adoptions, all 621 items adopted through a parsed JSON request, no physical
  stock, repeat installation, conflict rollback, tampered CSV rejection, exact
  source hierarchy, new English/Bangla/material aliases and translation parity.
  Relevant Blade templates compile to valid PHP; adoption dialog renders in both
  languages in isolated tests.
- Required G1 authorization suite: **29 tests, 1,707 assertions passed**.
- JavaScript dialog test: **1 passed**. Executes the real template script with
  the full collection, including numeric jQuery attributes, and verifies every
  transmitted string code and classification in the JSON payload.
- Full GovStore regression suite: **737 tests, 77,737 assertions passed** with
  `php -d xdebug.mode=off -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`.
- v1/v2 CSV hashes validated; v1 data files have no changes. New PHP sources
  passed syntax checks; `git diff --check` passed.

## Local installation and database verification

Rechecked effective application host `snipeit.local`, MySQL driver and database
`snipeit` before applying only the new collection migration. Collection **38** is
still the same active collection and now contains **621 item types**.

| Aggregate | Before task | Final |
| --- | ---: | ---: |
| Collections | 14 | 14 |
| Collection memberships (all collections) | 236 | 743 |
| Common office memberships | 114 | 621 |
| Reference nodes (whole catalog) | 158,463 | 158,463 |
| Bangla enrichments | 114 | 621 |
| Search aliases | 317 | 1,554 |
| Import history | 2 | 4 |
| Native categories | 179 | 179 |
| Native models | 133 | 133 |
| Assets | 4,860 | 4,860 |
| Consumable records | 1,061 | 1,061 |
| Accessory records | 704 | 704 |
| Component records | 352 | 352 |
| Tenant adoption mappings | 4,325 | 4,325 |

Existing catalog nodes were reused; no master catalog reimport occurred. A local
pre-release 498-item draft was installed before the owner's additional requests;
the same v2 installer then added the final items. Both installation history entries
are retained. The task's unchanged draft descriptions were finalized conditionally.
The released bundle and migration contain the final 621-item version. Repeating
the final installer locally created no duplicate history entry.

No stock, models, automatic office adoptions, role changes or access-mode changes
were made by this task. Temporary build/inspection helpers and regression log were
removed after verification; installation history remains.

## Live interface status and remaining limits

**Live collection-page verification: blocked.** The existing local browser session
was inspected and navigation attempted, but the computer-use tool then stopped
because it could not identify the current browser URL confidently enough to enforce
its policy. No fallback browser automation or authenticated-request shortcut was
used to claim a UI pass. The final 621-item page and adoption dialog have not been
visually verified in this run.

**Complete office-role live adoption: not run.** The isolated 621-item controller/
service adoption and JavaScript request checks are not a real office-user browser
journey. Other GovStore stories and native vehicle/first-aid stock workflows were
not in this data-expansion verification scope. No CI, WCAG, production-readiness,
national translation approval or shadow-observation completion is claimed.
