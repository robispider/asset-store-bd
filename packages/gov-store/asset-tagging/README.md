# GovStore asset tagging — package design

Revision 7, 16 October 2026. **Built in code and covered by tests. Migrated on the local
development database only; no office is enabled, so no tag has been issued.** What was built, and where it differs from the design, is in
[ARCHITECTURE.md §15](ARCHITECTURE.md#15-implementation-notes-built-16-october-2026).

The design adds 3 new GovStore tables and alters 2 existing GovStore tables:
`gov_asset_registrations`, and an index-only change to `gov_catalog_snipe_mappings`. Snipe-IT
tables, model classes and core files stay unchanged.

Every unit received through a GRN in an enabled office gets one permanent tag:

- **Govt. Tag** (`fd18-v1`, 18 digits, `GG SS II MMYY LL DD NNNN`): issued when the unit has an
  active Finance Code and an acquisition date (taken from the Supplier Challan or Purchase Order
  date) on a purchase receipt.
- **Standard tag** (`native-v1`, for example `GS-004217`): issued for every other unit. The
  Finance Code is optional; when it is missing, the GRN shows a warning with a fix instead of
  blocking.

How the pieces fit:

- A product type has exactly one UNSPSC code.
- Each company keeps its own location and division code lists. Offices choose their codes from
  dropdowns built from those lists.
- The GRN screen keeps everything it has today; tagging only adds to it.
- The backend plugs into the existing capability registry, profile rules, validation
  checklist, the contract/null/adapter binding pattern, the metadata registry and
  `govstore-abilities`.

Documents:

- [Architecture: decisions, house-style backend, tables, GRN additions, 99-item policy, decision log](ARCHITECTURE.md)
- [Implementation plan](DESIGN.md)
- [UI/UX specification](ui/UIUX.md) and [interactive screen designs](https://claude.ai/artifact/1xfRGHHkFngBXp5YMQafur) (sources in [ui/canvas/](ui/canvas/))
- [Table requirements](architecture/table-requirements.json) and [unexecuted reference SQL](architecture/schema-proposal.mysql.sql)
- [Office settings input](architecture/organization-intake.schema.json) · [Company code lists](contracts/company-codelists.schema.json)
- [GRN unit inputs](contracts/receipt-unit.schema.json) · [Product-type Finance default](contracts/product-default.schema.json)
- [Roles and abilities](architecture/role-permissions.json) · [New Finance items](architecture/new-type-workflow.json) · [Population plan](architecture/population-plan.json)
- [Deployment policy (issuance disabled)](config/tagging-policy.proposed.json)
- [Prepared Finance data](../../../FinanceDivData/tagging-design/README.md)
