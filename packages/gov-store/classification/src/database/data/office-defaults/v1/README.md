# Bangladesh Government Common Office collection — v1

This is one optional collection of **114 generic item types**, installed by
`php artisan migrate`. No separate seed or import command, network connection,
catalog upload, or office adoption is required to install it.

An office opens **Catalogue → Collections → Bangladesh Government Common Office →
Explore & adopt**, chooses the types it needs in the dialog, and confirms adoption.
English and Bangla collection/item labels are available. The collection is not
added to automatic office starter templates. Installing it creates no categories,
models, assets, quantities, purchases, Finance codes, or office/company adoptions.
Existing starter-template configuration is unchanged.

## Scope and data

| CSV | Content |
| --- | --- |
| `collections.csv` | Stable collection identity, English/Bangla labels and description |
| `items.csv` | Exact UNSPSC commodity, group, Bangla label, recommended inventory type, base unit and search aliases |
| `nodes.csv` | The 114 selected commodities and their ancestor closure: 193 reference nodes total |

Groups are furniture, stationery, ICT/computing, printing/office equipment,
electrical/power, cleaning/sanitation and meetings/general office. More specialized
medical, education, fleet, building, land and data-centre inventories are excluded.

Inventory types are operational defaults, not capitalization or depreciation
rules. Furniture/computers are assets; paper/ink/soap are consumables; separately
issued small tools/peripherals are accessories; replacement drives are components.
The dialog permits a choice for newly created categories; an existing mapped
category's type is preserved. No brand, capacity, paper size, purchasing pack,
price, opening balance or depreciation rate is invented.

Units describe base counting (`piece`, `sheet`, `roll`) in the reference CSV and
collection membership. A bottle, box or finished register is counted as a piece;
pack sizes and conversions must be specified when configuring real goods. This
change does not add an operational unit/conversion engine.

## Evidence and limitations

- Reference codes, English labels, levels and paths are exact selections from
  `../../compiled_nodes.csv`; its SHA-256 is in `manifest.json`. This pack does not
  assert a separately verified upstream UNSPSC release number or new licensing claim.
- [FinanceDivData](../../../../../../../../FinanceDivData/README.md) and its
  `tagging-design/finance_groups.csv`, `finance_items.csv` and `source-issues.json`
  informed the furniture, office-equipment, electrical and computing shortlist.
  The extracted manual has provisional values and source conflicts; no Finance
  crosswalk is approved or activated by this collection.
- [Bangladesh Stationery Office activities](https://www.dpp.gov.bd/bso/index.php/page/view/191)
  identifies stationery supply to government offices as an office function.
- [Finance Division SPFMS procurement examples](https://spfms.gov.bd/home/Procurement?sid=1)
  include furniture, cleaning/stationery, desktops, laptops, small laser printers
  and photocopiers. These inform a practical starter list, not a mandatory national
  inventory standard. Sources consulted 26 October 2026.
- Bangla labels and local aliases are application defaults, not official translations.
  Offices can select a subset; physical item specifications remain their responsibility.

## Migration, preservation and maintenance

The schema migration adds nullable stable collection keys, bilingual collection
labels and membership defaults. The data migration loads this pinned bundle
offline. Its unusual `2026__10_26` prefix deliberately sorts after the existing
`2026__08_04` collection-table migration on a fresh installation; do not rename
historical migrations or run schema reset commands on an existing installation.

The installer verifies a code-pinned manifest hash and every CSV hash, validates
the complete hierarchy and item membership, then inserts missing reference data
and the collection in one transaction. Existing node labels/versions, enrichments,
notes, mappings, collection edits and existing membership defaults are retained.
Incompatible existing node structures cause an actionable migration failure and
rollback; they are not overwritten. Installation is recorded in catalog import
history as `bd-common-office-v1`, with a null user for the system migration.

The data migration is forward-only: rollback refuses to remove reference data
that may already be adopted or used in documents. Normal repeated `migrate` runs
do nothing; repeating the installer also avoids duplicate rows and history.

Treat v1 as immutable. A future data change needs a new versioned CSV bundle and
data migration with an explicit preservation/update policy. Keep CSV/JSON as UTF-8
with LF endings (enforced by this directory's `.gitattributes`) and keep codes as
text. The installer does not extend or bypass the web catalog review executor;
national UI edits retain their current authorization and review requirements.

Focused isolated regression:

```text
php vendor/bin/phpunit tests/Feature/GovStore/CommonOfficeCollectionTest.php
```
