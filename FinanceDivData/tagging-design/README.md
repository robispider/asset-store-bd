# Prepared tagging data — revision 7

Offline design/reference pack, 15 October 2026. No operational approvals, native
organization IDs or live mappings have been invented. The
[current architecture](../../packages/gov-store/asset-tagging/ARCHITECTURE.md)
uses three new GovStore tables and alters only the GovStore registration bridge;
Snipe-IT tables and model source stay unchanged. Architecture revised 16 October 2026. The
[deployment reference](../../packages/gov-store/asset-tagging/config/tagging-policy.proposed.json)
keeps issuance disabled and contains no executable formatter rules.

| File | Use |
| --- | --- |
| `finance_items.import-draft.csv` | 490 rows shaped for the single `gov_finance_items` table: gov_code, labels, NULL/blank UNSPSC, held status and source evidence. No active rows or fabricated approval. |
| `finance_items.csv` | Original normalized 490-key source view with group/subgroup/item components and issue flags; `entry_key` is the six-digit gov_code. |
| `finance_groups.csv`, `finance_subgroups.csv` | 18 groups and 171 subgroup keys; review/projection data, not additional table requirements. |
| `source-issues.json` | Source conflicts and affected scope; subgroup 11/23 stays unresolved. |
| `finance_unspsc_review.csv`, `unspsc_candidates.csv` | 490 blank decision rows and 470 search leads covering 224 items. Zero approved mappings; 266 items have no lead. |
| `office_location_mapping.csv`, `administrative_division_mapping.csv` | 14 and 17 source examples; real office/company assignments and approved codes remain blank. |
| `organization_onboarding.csv` | Header-only adoption, allocator and legacy-preflight evidence worksheet; no issuer registry/table. |
| `product_type_finance_mapping.csv` | Header-only worksheet: one optional Finance default per product type, owned by its governance scope (national or company); one UNSPSC code per type; no native model field. |
| `legacy_reconciliation.csv` | Header-only exact label/native asset/source-unit verification worksheet. |
| `receipt_unit_intake.csv`, `receipt-unit.example.json` | Saved GRN input worksheet and fictional example. Finance Code is optional (`finance_source` = product_type / line_choice / none); the service issues a Finance tag or a standard tag. |
| `format-examples.json` | FD18 demonstration and negative examples; no second GSA identity. |
| `field-requirements.csv` | 22 issuance/register/context fields and explicit exclusion of accounting/CGA inputs. |
| `policy-decisions.csv` | Eight onboarding/rollout decisions; a checklist, not a new eight-step workflow or set of tables. |
| `validation-report.json`, `design-data-manifest.json` | Structural checks, known limits, source/input/output hashes. |
| `prepare_design_data.py` | Offline reproducible generator/checker; no application or DB access. |

All codes are strings: import CSV columns as text to preserve zeros and avoid
18-digit rounding. Treat blank unspsc_code as SQL NULL in an eventual importer.
The draft CSV is not yet supported by the current UNSPSC-only importer: extend
the existing exact catalog-review executor explicitly for Finance mutations.

Office admins supply actual fixed LL/DD assignments in small office metadata.
Existing authorized product-type creators/editors select optional Finance Code beside UNSPSC. GovStore metadata references the category ID; models and GRN inherit it. Storekeepers supply actual
purchase dates/evidence and physical-unit facts. The existing national catalog
publisher activates approved Finance scope and adds new types through the
same catalog controls. No new type-request engine, release tables, issuer
namespaces or reverse UNSPSC gov_code column is required.

The source manual's worked example conflicts with its code lists. Applicability,
controlling codes and the proposed installation-wide exact-prefix sequence rule
still need authoritative decisions. Repeated codebook/format versions never
reset the sequence. One authoritative allocator is required; four-digit suffixes
allow 9,999 assets per encoded prefix. Current geography is retained and CGA
accounts/document numbering is outside tagging scope.

Candidate scores are lexical search rankings, not semantic confidence. The
local catalog has 158,463 nodes / 149,848 commodities; its UI release label
UNv260801 has not been independently verified against upstream evidence. No
automatic semantic matching or approved live crosswalk is claimed.

```text
python FinanceDivData/tagging-design/prepare_design_data.py --check
python FinanceDivData/tagging-design/prepare_design_data.py --write
```

The write command overwrites generated review worksheets. Copy them elsewhere
before entering actual decisions. A passing check establishes reproducibility,
not policy adoption, runtime behavior, CI or production readiness.
