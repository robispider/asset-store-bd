# Finance Division asset identification data

This folder contains machine-readable extractions from the 170-page source manual and a provisional asset-code catalog to support design of an automated asset tagging system. The manual is titled for State-owned Enterprises (SOEs) and Autonomous Bodies (ABs); confirm that it applies to the Finance Division before using it as binding policy.

## Files

- `manual_full_text.md` — extracted text for every PDF page, in source order.
- `manual_tables.json` — tables detected by PDF layout extraction, with PDF page numbers and raw cell values.
- `group_codes.csv`, `location_codes.csv`, `division_codes.csv` — reference code lists transcribed from PDF pp. 89 and 98–99.
- `asset_code_catalog.csv` — normalized rows from Annexures 1–18 (PDF pp. 103–116); each row retains group/subgroup/item codes, labels, abbreviation and source page.
- `asset_register_fields.csv` — fields shown in the Fixed Asset Register form (Annexure 22, PDF p. 120).
- `tagging_specification.json` — source-derived tag segments and operational rules, plus explicit conflicts found in the manual.
- `extraction_manifest.json` — source hash and extraction counts.

## Proposed tag structure from the manual

The worked example on PDF page 99 has 16 digits: `GG SS II MMYY LL DD NNNN` (spaces shown only for readability). The manual says the parts represent group, subgroup, item, purchase month/year, location, division, and a unique asset ID. It gives Finance and Accounts as division code `09`. The unique ID is assigned at registration and should not change after assignment.

**The source is internally inconsistent:** its code list assigns `03` to Building and `05` to Furniture and Fixture, while the worked example uses `03` for Furniture and Fixture. The example's subgroup `01` for Table also conflicts with the detailed catalog, which lists Table as subgroup `08`. Therefore the extracted catalog is marked provisional and the example should not be used to issue production tags until the responsible authority confirms the controlling codebook. See `tagging_specification.json`.

## Data use and coverage

The manual describes a fixed-asset register with asset group/subgroup/name, description/specifications, manufacturer, asset ID, department, division, location/sub-location, purchase date, cost, depreciation rate and other information. Annexure 22 adds invoice number, supplier, useful life, accumulated depreciation, written-down value and remarks. The manual also requires a permanent, visible tag where practicable; an untagged-asset file and detailed register specifications for approved exceptions; and ongoing updates to asset details.

The code catalog is from the annexures rather than a newly invented taxonomy. The full text and raw table export retain broader policy and form content, including acquisition, custody, verification, transfer and disposal. PDF table detection may split or misalign cells; review the raw pages and source PDF for any high-impact decision.

## Provenance

Source file: `Policy and Procedure Manual for Property, Plant, Equipment and Other Assets finance division.pdf`

SHA-256: `83d798aff269704393f275f7af8fcd162f8dd69d61145f0469261c0fceb36b89`

All source page citations in these files use PDF page numbers (1–170).
