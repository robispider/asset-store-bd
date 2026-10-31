# Bangladesh Government Common Office collection — v2

One optional bilingual collection with **621 generic item types in 22 groups**.
It adds 507 types to v1 and retains all 114 original rows and their defaults.
`php artisan migrate` installs the upgrade offline, into the same
`bd-common-office` collection. Offices still choose what to adopt.

| Group | Item types |
| --- | ---: |
| Furniture | 31 |
| Stationery and records | 114 |
| ICT and computing | 50 |
| Printing and office equipment | 46 |
| Electrical and power | 36 |
| Cleaning and sanitation | 50 |
| Meetings and general office | 24 |
| Networking and cabling | 18 |
| Phones and communication | 10 |
| Audio, video and presentations | 17 |
| Office dining, pantry and refreshments | 53 |
| Fire safety and protective equipment | 12 |
| Security and access | 13 |
| Storage and handling | 16 |
| Tools and maintenance | 28 |
| Office fittings and curtains | 9 |
| Emergency first aid | 22 |
| Books, newspapers and magazines | 15 |
| Decorative plants and decor | 15 |
| Office vehicles | 9 |
| Vehicle maintenance tools | 18 |
| Vehicle spares and maintenance supplies | 15 |

Examples include steel/iron/aluminium trunks (the single UNSPSC storage-chest
type, with material aliases), iron safes, paper shredders and bags, laminators,
binding machines, barcode printers, webcams, network equipment, spare RAM,
first-aid dressings and refills, reference/law books, newspapers, magazines,
curtains/blinds, living/artificial decorative plants, cars, microbuses, motorcycles,
bicycles, jacks, tyre gauges, repair tools, tyres, filters and engine oil.
Dining includes tables, existing chair types, sideboards, utensils, dinner sets,
food containers, rice cookers, food warmers and other basic kitchen appliances.

## Source and classification

`items.csv` contains exact codes and English titles from the existing
`../../compiled_nodes.csv`. `nodes.csv` is their complete 899-node ancestor
closure. The manifest pins the source and all three CSV files; the installer pins
the manifest. Codes are text, UTF-8 files use LF endings, and all labels have
application-supplied Bangla translations. These are not official translations or
an assertion of a newly verified upstream UNSPSC release.

Inventory defaults: **174 assets, 188 consumables, 213 accessories and 46
components**. Durable loanable books and small tools default to accessories;
periodicals, dressings and maintenance liquids default to consumables; vehicle
spares default to components. Offices must determine actual specifications,
material, compatibility, expiry and accounting policy. Generic types can cover
several variants (e.g. cable interfaces, trunk materials or paper sizes); those
variants are not duplicate classification codes.

First-aid coverage was checked against the [HSE workplace first-aid kit
guidance](https://www.hse.gov.uk/simple-health-safety/firstaid/what-to-put-in-your-first-aid-kit.htm),
consulted 26 October 2026. It is a coverage reference, not Bangladesh law or a
mandatory shopping list. The collection supplies no treatment instructions,
medicines, stock quantities or first-aider qualification. Specialized emergency
equipment is optional; an office's assessed needs determine its selection.

Base units are `piece`, `sheet`, `roll` and `metre`. Bulk network/power cable uses
metres; a bottle, packet or finished object counts as a piece. Purchase pack sizes,
liquid volumes and unit conversions still require real goods configuration.
No unit conversion engine is introduced.

## Upgrade and preservation

The new forward-only migration reuses the v1 installer with a separately pinned
v2 manifest. It adds missing reference nodes, translations, aliases and membership
rows transactionally. It preserves existing collection identity, custom names,
active status, edited descriptions, labels, notes, membership defaults, category
types, mappings and adoptions. Only descriptions still exactly matching the
shipped v1 description are refreshed, independently for each language. Existing
node-structure conflicts abort the entire upgrade. Repeating the final installer
is a no-op with no duplicate history entry.

Installation creates no native categories, models, physical inventory or
office/company adoptions. Automatic office starter templates remain unchanged.
Adoption uses existing authorization and review boundaries; no route or permission
is added. Large adoption submissions use JSON to avoid PHP's form-field limit.

v1 is retained unchanged. Treat the released v2 bundle as immutable; future
changes require another version and migration.

## Verification

```text
php vendor/bin/phpunit tests/Feature/GovStore/CommonOfficeCollectionTest.php
php vendor/bin/phpunit tests/Feature/GovStore/G1AuthorizationTest.php
node --test "tests/js/classification/*.test.mjs"
```

The isolated collection suite exercises fresh migration, upgrading adopted v1
data with local edits, full 621-item JSON adoption, no stock creation, duplicate
prevention, atomic conflict rollback, pinned bundle tampering, exact reference
hierarchy, representative bilingual searches, translation parity and Blade syntax.
The JavaScript test executes the real dialog script and checks every selected code
and type in the JSON request, including jQuery's numeric data-attribute conversion.
