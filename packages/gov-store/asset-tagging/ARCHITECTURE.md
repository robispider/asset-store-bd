# Asset tagging architecture — revision 7

16 October 2026. **Implemented in code (see §15). Applied to the local development database only: the five migrations and the Finance list (490 rows, held). No office is enabled and no tag has been issued.** Snipe-IT tables, model classes and core files stay unchanged. This revision adds **3 GovStore tables** and alters **2 existing GovStore tables** (`gov_asset_registrations`, `gov_catalog_snipe_mappings`). Issuance stays GRN-only.

## What changed from revision 6

| # | Change | Why |
| --- | --- | --- |
| 1 | The 18-digit tag is called **Govt. Tag** on every screen (Bengali: সরকারি ট্যাগ). The technical format id stays `fd18-v1`. | User decision. |
| 2 | **Office settings are chosen from dropdowns, not typed.** Each company keeps one list of registration-location codes and one list of division codes. An office picks its location (codes already taken by another office are shown as unavailable) and the divisions it uses. The **authority reference field is removed.** | User decision. |
| 3 | **The existing GRN screen is unchanged; we only add to it:** a tag panel under each asset line (preview, warning, solve control), checklist items in the existing validation checklist, one line in the existing posting modal, and an "Issued tags" box after posting. No new header fields: the acquisition date comes from the existing *Supplier Challan* date, else the *Purchase Order* date. | User decision. |
| 4 | **No Finance Code → a warning, not a block.** The line panel warns and offers a fix: choose a code for this line, or knowingly accept standard tags. A missing acquisition date stays **Needs attention** and blocks posting until fixed or the line is switched to standard tags. | User decision. |
| 5 | **The backend now follows the house architecture** (§2). Tagging plugs in through the existing capability registry, profile rules, validation checklist, contract-plus-null-implementation binding, metadata registry, `govstore-abilities` config and `MenuRegistry`. | User decision. |
| 6 | **A policy for item codes beyond 99** (§9). | User question. |
| 7 | **Office enablement is an audited metadata record,** not a config list. | It fits the metadata package and keeps the audit trail. |

## 1. Decisions

- **D1 Two kinds of tag.** A **Govt. Tag** (`fd18-v1`) is 18 digits, `GG SS II MMYY LL DD NNNN`. It is issued when a unit has an active Finance Code, an acquisition date and a purchase-type receipt. A **standard tag** (`native-v1`, for example `GS-004217`) comes from the installation's native auto-increment sequence and is issued for every other unit in an enabled office. Both are permanent.
- **D2 Format as code.** Formats are PHP classes selected by version through a factory. Office users choose facts; they never edit formats.
- **D3 One UNSPSC code per product type.** A Finance item's optional `unspsc_code` equals that code or is NULL.
- **D4 The Finance Code is optional at every level.** It never blocks creating a product type, saving a draft or posting.
- **D5 Ownership follows category governance.** Global types are national (`catalog.master.manage`); company types belong to their company. Offices never override.
- **D6 Codes come from lists.** The company maintains its code lists. The office selects from them. Nobody types an LL or DD code into office settings.
- **D7 Reuse first.** No new workflow engine, request, release, issuer, label or approval tables.
- **D8 Out of scope:** geography changes, CGA codes, non-GRN issuance.

## 2. Backend architecture (house style)

Tagging follows the patterns the gov-store packages already use. Nothing new is invented where an existing mechanism fits.

| Existing mechanism | Where it lives today | How tagging uses it |
| --- | --- | --- |
| **Capabilities** (`CapabilityInterface`: `getRequirements`, `validate`, `execute`, `renderUI`) in `CapabilityRegistry` | store-operations `Capabilities/`, `Services/CapabilityRegistry.php` | New capability `issue_govt_tags` (class `GovtTagCapability` in asset-tagging). `renderUI` draws the line's tag panel through the existing `renderMeta` endpoint; `validate` checks line input. `execute` is a no-op, like `RequireSerialCapability`. |
| **Profile rules** (GLOBAL / COMPANY / LOCATION / NATIVE, ENFORCE / DISABLE / INHERIT) | `ProfileCompilerService`, Rules admin | A default GLOBAL rule enforces `issue_govt_tags` for asset models. It does nothing unless the office is enabled (§8), so the Rules admin can never bypass the gates. |
| **Validation checklist** (`DocumentValidationService::evaluateDocument` → right-panel checklist) | store-operations | A new optional interface `Contracts/ContributesChecklist` lets a capability add checklist rows. Tagging adds "Needs attention" rows (block posting) and warning rows (don't block). |
| **Contract + null implementation + adapter bound when the package exists** (`TrackingCodeVerifier` → `NullTrackingCodeVerifier`, `Integrations/Tracking/TrackingCodeVerifierAdapter`) | store-operations `Contracts/`, `Services/`, `Integrations/`, provider `register()` | New `Contracts/AssetTagIssuer` with `NullAssetTagIssuer` (today's behaviour: typed tag, else `Asset::autoincrement_asset()`). When `GovStore\AssetTagging\Services\TagIssuanceService` exists, the provider binds `Integrations/AssetTagging/AssetTagIssuerAdapter`. |
| **Registry `register()` from a package provider** (`MenuRegistry`, `MetadataRegistry`, `TabRegistry`) | tenant-scope, metadata, store-operations | Add `CapabilityRegistry::extend($code, $class, $dictionary)` so asset-tagging registers `issue_govt_tags` from its provider. The registry stays the single list. |
| **Abilities config merged into `govstore-abilities`** + `gov.can:` route middleware + `<x-gov-action>` | theming `config/abilities.php`, tenant-scope | asset-tagging `src/config/abilities.php` (below). |
| **Transformers for JSON lists** | committee `Http/Transformers/` | classification `Http/Transformers/FinanceItemsTransformer` for the Finance Code select2 list. |
| **Theme assets and kit** | `GsTheme::assets()->css(...)`, `<x-gs::*>` | `asset-tagging.css` in `@layer gs-packages`; new kit component `<x-gs::code-segments>` in theming. |
| **Single-use national review** | classification `Services/CatalogReview` + `gov_access_review_uses` | Extend `CatalogReview` to fingerprint a payload as well as files, then reuse it for Finance activation. |
| **Model event guards from providers** | store-operations provider (`saving` closures for GRN-only intake) | `ManagedTagGuard` registered the same way on `Asset` and `Setting`. |
| **Artisan commands** | `storeops:*`, `gs-theme:*` | `asset-tags:preflight` (read-only report) and `asset-tags:counters --check`. |

### Package layout: `packages/gov-store/asset-tagging`

Uses the lowercase layout of classification, metadata and tenant-scope.

```text
composer.json                          # gov-store/asset-tagging; requires tenant-scope, metadata, classification, store-operations, theming
src/Providers/AssetTaggingServiceProvider.php
src/config/abilities.php               # merged into govstore-abilities
src/config/asset-tagging.php           # format allowlist, standard-tag prefix rule, capacity warning threshold
src/Capabilities/GovtTagCapability.php # CapabilityInterface + ContributesChecklist; registered as issue_govt_tags
src/Contracts/TagFormat.php
src/Formats/FinanceDivisionV1Format.php    # fd18-v1 (Govt. Tag)
src/Formats/NativeSequenceV1Format.php     # native-v1 (standard tag)
src/Factories/TagFormatFactory.php
src/Services/TagIssuanceService.php        # prepare(document), tagFor(item, unit), summary(document)
src/Services/TagReservationPlanner.php     # builds and locks the whole posting's reservations
src/Services/GovtTagSequenceAllocator.php  # gov_asset_tag_sequences
src/Services/NativeSequenceAllocator.php   # settings row FOR UPDATE
src/Services/LineTagResolver.php           # Finance source, acquisition date, division, kind per unit
src/Services/OfficeTagFacts.php            # typed read/write of office and company metadata
src/Services/EnablementGate.php            # G1–G6
src/Services/TagDecoder.php
src/Guards/ManagedTagGuard.php
src/Models/TagSequence.php
src/Http/Controllers/OfficeTagSettingsController.php
src/Http/Controllers/CompanyCodeListController.php
src/Http/Controllers/TagLookupController.php
src/Http/Controllers/EnablementController.php
src/Console/Commands/PreflightCommand.php
src/database/migrations/…_create_gov_asset_tag_sequences_table.php
src/resources/views/{grn,office,company,lookup,enablement}/…
src/resources/lang/{en-US,bn-BD}/tagging.php   # asset-tagging::tagging.*
src/resources/css/asset-tagging.css
src/routes/web.php                     # gov.can on every route; breadcrumbs per CLAUDE.md
```

### Changes in other GovStore packages (no Snipe-IT core)

| Package | Change |
| --- | --- |
| store-operations | `Contracts/AssetTagIssuer`, `Contracts/ContributesChecklist`, `Services/NullAssetTagIssuer`, `Integrations/AssetTagging/AssetTagIssuerAdapter`, provider binding. `CapabilityRegistry::extend()`. `PostingPipelineManager::materialize()` calls `$issuer->prepare($document)` after the document lock and before the line loop. `CreateAssetsCapability` takes the tag, `purchase_date` and bridge fields from the issuer. `DocumentValidationService` merges `ContributesChecklist` rows. `preview()` adds `tags` to its JSON. `store-operations.js` prints that line in the posting modal. `workspace.blade.php` outputs `{!! $tagIssuer->postedPanel($document) !!}` (empty string from the null issuer). Migration altering `gov_asset_registrations`. `ExperimentWiper`-style guard: the bridge refuses deletes of issued rows. |
| classification | Migration `gov_finance_items`; migration `UNIQUE(category_id)` on `gov_catalog_snipe_mappings`; `Services/ProductTypeCommodity` (the single code-assignment path, used by `CatalogSearchController::saveMapping`, `CatalogCategoryCreator`, `OfficeStarterCatalog`); `Models/FinanceItem`; `Http/Controllers/FinanceCatalogController`; `Http/Controllers/Api/FinanceItemsController@selectlist` + `Http/Transformers/FinanceItemsTransformer`; Finance Code field on the product-type form; `CatalogReview` payload fingerprint. |
| metadata | Migration `gov_metadata_values`; `Models/MetadataValue`; `Services/MetadataValueStore` with typed namespaces registered by packages (`register('asset-tagging.office', OfficeFactsSchema::class)`), the same shape as `MetadataRegistry::register()`. Revision check and audit on every write. |
| theming | `<x-gs::code-segments>` component, its CSS and a Lab demo. |
| experimentation | `ExperimentWiper` refuses `fd18-v1`/`native-v1` registrations. |

### Abilities (`src/config/abilities.php`)

```php
return [
    'asset-tags.office.configure'  => ['roles' => ['office_admin'], 'enforce' => true],
    'asset-tags.company.codelists' => ['roles' => ['company_admin'], 'enforce' => true],
    'asset-tags.lookup'            => ['roles' => ['authenticated'], 'enforce' => true], // plus object check on the asset
    'asset-tags.scope.enable'      => ['roles' => ['superuser'], 'national' => true, 'enforce' => true],
];
```

Existing abilities: `storeops.documents.*` for GRN work and `catalog.master.manage` for the national Finance list and global product types. Labels are added to tenant-scope's `access.php` lang files, following the theming convention that `G1AuthorizationTest` enforces.

## 3. Identifiers

| Value | Example | Storage |
| --- | --- | --- |
| Finance item code `gov_code` | `090809` | `gov_finance_items` PK. Optional on product types and lines. |
| UNSPSC code | `43211503` | One per product type in `gov_catalog_snipe_mappings` |
| Product-type Finance default | `{ "gov_code": "090809" }` | `gov_metadata_values` (category subject) |
| **Govt. Tag** | `090809102601090001` | `assets.asset_tag` + bridge, `fd18-v1` |
| Standard tag | `GS-004217` | `assets.asset_tag` + bridge, `native-v1` |
| Office facts | LL `01`, divisions `09`,`01` | `gov_metadata_values` (office subject), chosen from the company lists |

## 4. Product type classification

1. **One UNSPSC code.** `ProductTypeCommodity::assign()` replaces rather than adds. `UNIQUE(category_id)` is applied once preflight has reconciled types that have several codes (D-06).
2. **Finance Code (optional)** sits under the UNSPSC field. Items whose UNSPSC equals the type's code are listed first (marked "matches UNSPSC"), then items with no UNSPSC. Blank saves no row.
3. **Who edits:** the national publisher for global types; the owning company's editor for company types. Everyone else sees it read-only, with the owner named.
4. **Change of commodity** marks a conflicting default *needs review*. GRN ignores it until the owner resolves it. Issued tags never change.

## 5. Database changes

The table dictionary is generated by [build_schema.py](architecture/build_schema.py) into [table-requirements.json](architecture/table-requirements.json) and [schema-proposal.mysql.sql](architecture/schema-proposal.mysql.sql). The SQL has never been run.

| Table | Owner package | Change |
| --- | --- | --- |
| `gov_finance_items` | classification | **New.** `gov_code` PK, labels, optional soft-linked `unspsc_code`, held/active/retired, origin, source/authority reference (national evidence). |
| `gov_metadata_values` | metadata | **New.** Unique on `subject_type` + `subject_id` + `namespace`; `scope_type`/`scope_id` record the owner; typed JSON; revision. |
| `gov_asset_tag_sequences` | asset-tagging | **New.** Counters for Govt. Tag prefixes and Finance code extension. |
| `gov_asset_registrations` | store-operations | **Altered.** + `unit_index`, `format_version`, `gov_code` (NULL for standard tags), `tag_snapshot`; unique keys; receipt FK RESTRICT. |
| `gov_catalog_snipe_mappings` | classification | **Altered (index only).** `UNIQUE(category_id)`. |

**Metadata subjects and namespaces**

| Subject | Namespace | Scope | Payload | Written by |
| --- | --- | --- | --- | --- |
| `company`/id | `asset-tagging.codelists` | company | `{locations:[{code,name}], divisions:[{code,name}]}` | company admin |
| `office`/id | `asset-tagging.office` | office | `{location_code, division_codes[], default_division_code}`, each value from the company lists | office admin |
| `office`/id | `asset-tagging.enablement` | national | `{enabled, gates_passed_at, reason}` | operator, after gates |
| `category`/id | `asset-tagging.default` | from category governance | `{gov_code}` | publisher / company editor |

Rules:
- Codes are two-digit text, unique within each company list, at most 99 entries per list.
- A list entry used by an office can be renamed but not deleted, so issued tags stay explainable.
- **Each location code belongs to at most one office in a company.** The dropdown shows taken codes as unavailable.
- Every write needs the expected revision and is audited.

## 6. Registration bridge

The plan keeps the existing bridge and adds: `unit_index` (= GRN `row_index`); `format_version` (`fd18-v1` / `native-v1` / `entered-v1`); `gov_code` (NULL for standard and entered tags); and `tag_snapshot`. The snapshot holds the segments, acquisition date and the reference it came from (challan or PO), the office/company facts and metadata revisions, the Finance source (`product_type` / `line_choice` / `none`), any standard-tag reason, and the actor.

Unique keys are on the tag, the source unit and the asset. The receipt FK changes from CASCADE to RESTRICT. Rows outlive asset deletion.

## 7. GRN: additions to the existing workspace

The existing GRN workspace keeps every element, label and position: administrative details, the received-items grid with its JS rows and capability sub-grids, supporting documents, the right-hand draft panel with its validation checklist, and the posting modal. Tagging adds only:

1. **Tag panel per asset line.** This is `GovtTagCapability::renderUI()`, appended by the existing `renderMeta` endpoint under the serial and warranty sub-grids. It shows:
   - **Finance Code** (product-type default, read-only with the owner named), or the warning *"No Finance Code — these N units will get standard tags"* with a fix: a Finance Code select (select2, `data-endpoint="finance-items"`, this line only) or *Keep standard tags*.
   - **Division**, only if the office uses more than one (defaults to the office default).
   - **Tag preview**: `<x-gs::code-segments>` with the sequence unallocated, or `GS-······`.
   - Values are posted through the existing `items[i][meta][unit][…]` naming (`gov_code`, `division_code`, `tag_choice`), so they save with the draft as usual.
2. **Checklist rows** in the existing validation checklist, from `ContributesChecklist`:
   - **Needs attention** (blocks posting): "*Projector: Govt. Tag needs the Supplier Challan or Purchase Order date*". Fixed by filling the existing date field, or by choosing *Use standard tags* in the line panel.
   - **Warning** (does not block): "*Office chair: no Finance Code — 6 standard tags*".
   - **Paused** (blocks Govt./standard issuance, not drafting): shown when gate G1 or G4 fails.
3. **Posting modal:** one added line, "*Tags: 9 Govt. Tags · 2 standard tags (permanent)*", from `preview()` JSON.
4. **After posting:** an "Issued tags" box in the right column of the read-only workspace (`postedPanel()`), with **Print labels** (existing native label route) and **Download CSV**.

**Acquisition date (D-09).** The Supplier Challan `reference_date` is used, else the Purchase Order `reference_date`. These are existing fields. Receiving source *Donation*, *Transfer* or *Confiscated* issues standard tags until D-04 decides otherwise. A typed `asset_tag` in unit metadata is rejected in enabled offices.

### Posting transaction and lock order

Lock order inside the existing transaction:

1. Document row `FOR UPDATE` (existing).
2. `$issuer->prepare($document)`, which:
   1. builds the plan per unit (kind, Finance Code, prefix);
   2. takes `gov_finance_items` and office/company metadata rows `FOR SHARE` and re-checks them;
   3. takes the Govt. Tag counters (`INSERT IGNORE`, then `FOR UPDATE` in sorted order) and reserves;
   4. if any unit needs a standard tag, takes the `settings` row `FOR UPDATE` and reserves the next N numbers, skipping used ones.
3. Line loop (existing): `CreateAssetsCapability` asks `$issuer->tagFor($item, $unit)`, sets `purchase_date`, saves the asset and writes the bridge fields.

Any error rolls everything back. Locks are held until commit.

## 8. Uniqueness, native compatibility, guards and enablement

- **Govt. Tag counter:** exact first 14 characters, across the installation, 9,999 per prefix, never reset or wrapped.
- **Standard tags:** native sequence read under the `settings` lock. The bridge unique key is the final barrier.
- **Enablement** is the office's `asset-tagging.enablement` metadata row, written only by `EnablementController` after the gates pass, with a reason, and audited. The capability does nothing for offices without it.
- **Gates.** ★ means also re-checked at every posting.
  - G1★ Native auto-increment is on, and the prefix is non-empty and does not start with a digit.
  - G2 The bridge preflight is clean.
  - G3 Legacy tag clashes are reviewed.
  - G4★ Office facts are complete (location plus at least one division).
  - G5 This installation is the single place tags are issued, and clone fencing is on.
  - G6 The office is not an experiment office.
- **`ManagedTagGuard`:** `Asset` saving rejects 18-digit or reserved tags outside issuance and tag changes on issued assets. `Setting` saving rejects breaking G1 while any office is enabled. It covers native UI, API and import; raw SQL and `withoutEvents` stay closed in production.
- **Scope of the guarantee:** one authoritative installation.

## 9. New Finance items and the 99-item limit

**Numbering.** In all 490 source rows, the item number `II` runs 1..N across each whole group, never per subgroup. The next item in group 09 is therefore 12 (`0908`**`12`**), whatever its subgroup. The allocator uses namespace `finance-code:item`, scope `GG`.

**What happens at 99?** `II` has two digits, so a group can hold at most 99 items under group-wide numbering.

| Stage | Behaviour |
| --- | --- |
| 90–98 items used | The Finance catalog shows a capacity warning on the group ("Group 12 Tools: 92 of 99 used"). The publisher is told before it becomes urgent. |
| 99 used | Creating another item in that group is **refused** with a clear message. Codes never wrap, truncate or reuse retired numbers. |
| Meanwhile | **Receiving never stops.** The Finance Code is optional: units of a new item type get standard tags, or the publisher maps them to an existing item that genuinely fits. |
| Fix (D-08, needs Finance Division approval) | **Option A, recommended:** for an exhausted group only, switch to per-subgroup numbering, so `II` restarts at 01 within each subgroup. `GGSSII` stays unique because the subgroup differs. This gives up to 99 per subgroup, needs no format change, and existing codes and tags stay valid. The allocator scope changes from `GG` to `GGSS` for that group by a reviewed config change. **Option B:** a new tag format with a three-digit item (`fd19-v2`). Old tags remain valid, but `gov_code` becomes seven digits, which ripples through every list and report. Use it only if the authority rejects A. **Not recommended:** borrowing another group's numbers or inventing a new group, which changes meaning. |

Current headroom: group 12 (Tools) has used 87 of 99. All other groups have used 62 or fewer.

**Steps for a new item:** the publisher picks group and subgroup, the allocator previews the next number (not reserved), and publishing goes through the extended `CatalogReview` (exact payload, typed confirmation, expiry, single use). New subgroups and groups are created together with their first item.

## 10. Data and population

| Data | Provider |
| --- | --- |
| Finance list (490 held) | National publisher activates after Finance/Administration review |
| UNSPSC on Finance items (470 leads, none approved) | Domain reviewer |
| Product type → one UNSPSC | Catalog reviewer reconciles types with several codes (D-06) |
| Product-type Finance default | Publisher / company editor; optional |
| **Company code lists** (the source has 14 location and 17 division examples from one organization) | Each company admin enters its real lists |
| Office facts | Office admin selects from the lists |
| Native settings prefix | Operator (D-03) |
| Legacy tags | Developer preflight; custodians verify |

## 11. Roles

| Role | Tagging responsibility |
| --- | --- |
| Storekeeper | Works the GRN as today, fixes tag warnings in the line panel, posts and prints. |
| Office administrator | Chooses the office location and divisions from the company lists; views readiness. |
| Company administrator | Maintains the company's location and division code lists. |
| Company product-type editor | Finance default for company product types. |
| National catalog publisher | Finance list, UNSPSC links, global product-type defaults, capacity decisions. |
| Deployment operator | Preflight, gates, office enablement. |

A tag or QR code never grants access. Lookup answers "not found" and "not yours" identically.

## 12. UI surfaces

Specification: [ui/UIUX.md](ui/UIUX.md). Designs: [design canvas](https://claude.ai/artifact/1xfRGHHkFngBXp5YMQafur) (sources in [ui/canvas/](ui/canvas/)).

| Screen | Change type | Owner package |
| --- | --- | --- |
| S1 GRN workspace | **Additions only** to the existing screen | store-operations + asset-tagging |
| S2–S3 Posting modal and posted GRN | **Additions only** | store-operations + asset-tagging |
| S4 Product type form | One added field | classification |
| S5 Office tag settings + company code lists | New screen | asset-tagging |
| S6 Finance catalog | New screen | classification |
| S7 Tag lookup | New screen | asset-tagging |
| S8 Enablement console | New screen | asset-tagging |

## 13. Implementation sequence

1. **Preflight (read-only):** `asset-tags:preflight` reports schema, duplicates, multi-UNSPSC types, legacy clashes, native settings and experiment offices.
2. **Metadata and classification foundations:** `gov_metadata_values` + `MetadataValueStore`; `gov_finance_items` + held import; `ProductTypeCommodity` + `UNIQUE(category_id)`; Finance select list API + transformer; product-type field; `CatalogReview` payload fingerprint.
3. **store-operations extension points:** `AssetTagIssuer` + null issuer (behaviour identical to today, proven by the existing `StoreOperationsWorkflowTest`), `ContributesChecklist`, `CapabilityRegistry::extend`, the `prepare()` call, bridge migration.
4. **asset-tagging package:** formats, factory, planner, allocators, `GovtTagCapability`, guard, gate, office/company screens, lookup, enablement, `asset-tags:preflight`.
5. **UI:** additions to the GRN workspace and modal; new screens on `<x-gs::*>`; `code-segments` Lab demo; compliance at zero colour literals.
6. **Rollout:** one office at a time.

**Tests** follow the existing layout:
- `tests/Feature/GovStore/AssetTaggingWorkflowTest.php`, `AssetTaggingUxTest.php` and `AssetTaggingAuthorizationTest.php` (the abilities matrix, as in `G1AuthorizationTest`).
- A MySQL concurrency script `tests/Feature/GovStore/asset-tagging-mysql-concurrency.php`, modelled on `store-operations-mysql-concurrency.php`.
- Run the whole suite with `php -d memory_limit=1G vendor/bin/phpunit`.

Cases: concurrent same and interleaved prefixes; mixed Govt./standard postings; retries; rollback; the optional Finance Code paths; the needs-attention date rule; the 90/99 capacity behaviour; one UNSPSC per type; gates; the guard; the code-list dropdown rules (taken codes, in-use entries can't be deleted); cross-office access; lookup non-disclosure; compiled Blade; light/dark walks.

## 14. Decision log

| ID | Decision | Status / default |
| --- | --- | --- |
| D-01 | Govt. Tag counter scope = exact 14-character prefix across the installation | Proposed; needs authority confirmation |
| D-02 | Source conflicts (Furniture 03/05, `11/23` labels, p.103 pairings) | Open; affected items held |
| D-03 | Standard-tag prefix and zerofill | Operator chooses; G1 enforces its shape |
| D-04 | Acquisition date for donation/transfer/confiscated receipts | Open; standard tags meanwhile |
| D-05 | Whether a standard-tagged unit may later get a Govt. Tag | Deferred; tags immutable |
| D-06 | Product types with several UNSPSC codes | Reviewer keeps one per type |
| D-07 | Cross-installation uniqueness | Out of scope |
| D-08 | Item numbers beyond 99 in a group | Recommend Option A (per-subgroup numbering for exhausted groups); block at 99 until approved; warn from 90 |
| D-09 | Acquisition date = Supplier Challan date, else Purchase Order date | Proposed; confirm with Finance |

**Verified now:** the GovStore test suite (isolated SQLite), including the new workflow, UX and authorization tests; the MySQL concurrency script (same-prefix overlap, interleaved prefixes, mixed standard tags, rollback, retired-code fallback); a preflight on the local database (clean, unique keys applied); and a walk of every screen in light and dark. The walk rendered each screen through the HTTP kernel as fixture users, inside rolled-back transactions. **Open:** authority decisions, the native auto-increment prefix (G1 fails locally because auto-increment is off), the single-issuer flag (G5), printer testing, a walk signed in as a real user in a real office, and CI.

## 15. Implementation notes (built 16 October 2026)

Everything in §2–§9 is in code. Where the build differs from the text above, this table is authoritative.

| Topic | As built | Why |
| --- | --- | --- |
| Default rule for `issue_govt_tags` | `CapabilityRegistry::extend($code, $class, $dictionary, defaultFor: ['assetmodel'])` adds a **DEFAULT layer beneath GLOBAL** in `ProfileCompilerService`: it applies only where no profile layer decided the code, and is appended last, so the tag panel renders after the serial and warranty sub-grids. No profile rows are written. The Rules admin can still DISABLE it at any layer. | Writing into the published global profile would mutate a versioned, published rule set. Issuance does not depend on the capability: `prepare()` re-checks every rule at posting. |
| Finance activation and enablement review | Both POSTs run under national abilities, so tenant-scope's existing `NationalChangeReview` freezes the exact payload, asks for typed confirmation, expires and is single-use. Its fingerprint now covers `gov.catalog.finance.*` (the Finance rows) and `asset-tags.enablement.*` (the office's tag rows). `CatalogReview` is unchanged. | The national review already does what the payload fingerprint was for. |
| New Finance item numbers | `FinanceCatalog::create()` takes `max(II)+1` for the group under a row lock on that group's items. No `finance-code:*` rows in `gov_asset_tag_sequences`. | Finance codes are never deleted, so the highest code is the counter. Refused at 99, warning from 90. |
| Audit of metadata writes | Every write needs the expected revision, logs before/after and dispatches `MetadataValueChanged`; the route's `gov.can` middleware records the actor, ability and reason in `gov_access_events`. The enablement console lists those events. | No extra table. |
| S4 Finance Code field | A box under the UNSPSC mapping on the product-type (category governance) page, saved by `POST admin/catalog/product-types/{id}/finance-code` under the new ability `catalog.finance.default` (company admins) plus an object check (national types need `catalog.master.manage`). | The governance page is national-only today; a company-facing entry point is a follow-up. |
| Line choices | Saved with the first unit: `items[i][meta][0][gov_code|division_code|tag_choice]`. | They apply to the whole line. |
| `format_version` | `entered-v1` also covers Snipe-IT's auto-increment in offices that are not enabled; only `fd18-v1`/`native-v1` are protected by the guard and `ExperimentWiper`. | Experiment and pilot offices keep today's behaviour and stay wipeable. |
| Unique keys | Added only when existing rows already satisfy them (`registration_*` on the bridge, `catalog_mapping_category`); otherwise preflight lists the conflicts and G2 stays failed. `php artisan catalog:commodity-index` applies the category index after D-06. | Nothing is merged automatically. |
| Menus | Office tag settings under Rules & settings; company code lists under Offices; lookup under Store; enablement under System. | Rules & settings already holds the 7-item maximum. |

**Commands:** `catalog:finance-import` (490 rows as held, never overwrites), `catalog:commodity-index`, `asset-tags:preflight [--json]`, `asset-tags:counters --check`.

**Tests:** `tests/Feature/GovStore/AssetTaggingWorkflowTest.php`, `AssetTaggingUxTest.php`, `AssetTaggingAuthorizationTest.php`, shared fixture `Concerns/AssetTaggingFixtures.php`, and the opt-in `asset-tagging-mysql-concurrency.php` (passed 16 October 2026).

**Native counter note:** Snipe-IT's own `AssetObserver` advances `next_auto_tag_base` whenever any asset is created, including Govt. Tag assets. Standard-tag numbers can therefore skip, but they never repeat: numbers are reserved under the settings lock, used numbers are skipped, and the bridge has a unique key.
