# Asset tagging implementation plan — revision 7

The [architecture](ARCHITECTURE.md) controls the work; this is the build order. The tables are in the
[table dictionary](architecture/table-requirements.json) and the screens in [ui/UIUX.md](ui/UIUX.md).

1. **Preflight (read-only).** `php artisan asset-tags:preflight` reports:
   - schema types, collations and constraint names;
   - duplicate bridge tags and assets;
   - product types with more than one UNSPSC code;
   - legacy tag clashes, including soft-deleted assets;
   - native auto-increment settings;
   - experiment offices.

   Nothing is merged or relabelled automatically.
2. **metadata.** Add the `gov_metadata_values` migration, the `MetadataValue` model and the
   `MetadataValueStore` service. Typed namespaces are registered by packages, with a revision
   check and an audit record on every write.
3. **classification.**
   - Tables: the `gov_finance_items` migration and model, with the 490 rows imported as held;
     and `UNIQUE(category_id)` on `gov_catalog_snipe_mappings` once the preflight issues are
     reconciled.
   - `ProductTypeCommodity::assign()` becomes the only way a UNSPSC code is attached to a
     product type.
   - The Finance Code select list: `Api/FinanceItemsController@selectlist` with
     `FinanceItemsTransformer`.
   - The product type form gains a *Finance Code (optional)* field.
   - The Finance catalog screen, including capacity warnings from 90 items and a block at 99.
   - `CatalogReview` gains a payload fingerprint.
4. **store-operations extension points.**
   - New contracts `AssetTagIssuer` (with the default `NullAssetTagIssuer`) and
     `ContributesChecklist`, plus `CapabilityRegistry::extend()`.
   - When the asset-tagging package is installed, `AssetTagIssuerAdapter` is bound, as the
     tracking integration already is.
   - Posting calls `prepare()` after the document lock. `CreateAssetsCapability` gets each
     tag from the issuer.
   - The checklist merges rows contributed through `ContributesChecklist`. The posting
     preview and modal gain one tag summary line, and a posted document shows the issued-tags
     panel.
   - The `gov_asset_registrations` migration.
   - With `NullAssetTagIssuer` bound, behaviour must stay exactly as today; the existing
     store-operations tests prove this.
5. **asset-tagging package.**
   - Formats and factory: `fd18-v1` for the Govt. Tag and `native-v1` for the standard tag.
   - The planner, both allocators and `LineTagResolver`. The acquisition date comes from the
     Supplier Challan date, falling back to the Purchase Order date.
   - `GovtTagCapability` (`issue_govt_tags`): line tag panel, checklist rows and validation.
   - `ManagedTagGuard` and `EnablementGate` (gates G1–G6).
   - Screens: company code lists, office settings (dropdowns only), tag lookup and the
     enablement console.
   - Abilities config, translations (en-US and bn-BD), menu entries and CSS.
6. **theming.** Add `<x-gs::code-segments>` with its CSS and a Lab demo.
7. **Tests.**
   - Workflow, UX and authorization tests, plus a MySQL concurrency script, laid out as the
     existing GovStore suites are.
   - Run the full suite with `php -d memory_limit=1G vendor/bin/phpunit`.
   - Check the compiled Blade for raw `<x-gs::` tags.
   - Walk every screen in the browser preview, in light and dark.
8. **Rollout.** Enable one validated office at a time. Non-GRN issuance stays deferred.

Rule for the GRN: **add, don't change.** Every existing workspace element keeps its label,
position and behaviour. All tagging UI arrives through capability `renderUI`, checklist rows,
one modal line and one posted-state panel.

Status (16 October 2026): steps 1–7 are built and verified, including the MySQL concurrency script
and a light/dark screen walk. The migrations and the held Finance list are applied to the local
development database only. Step 8 (rollout) remains. See
[ARCHITECTURE.md §15](ARCHITECTURE.md#15-implementation-notes-built-16-october-2026).
