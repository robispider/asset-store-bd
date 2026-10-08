# store-operations Gap Analysis and Mitigation Plan

Oct 4, 2026 · @zahid · Reassessed Oct 6, 2026 (Asia/Dhaka)

## Summary

**Implementation update (8 Oct 2026, working tree):** SO-1/2/5/6/8 are locally mitigated; SO-3/4/7 remain partial. Added atomic paired bulk transfers with actual source/destination authority, active supplier capture and serialized receipt coverage, opened-ledger native stock guards and read-only reconciliation, reasoned takeover notices, additive profile schema upgrades, external workspace/rules assets and server-rendered native stock-card tabs. Selected-office rules previews preserve the shared tenant context. Removed proven dead legacy screens/controllers without dropping compatibility tables. The focused Store Operations/G1/tracking/request run passed 89 tests and 1,704 assertions; the [implementation and verification record](../verification/store-operations-implementation-2026-10-08.md) contains expanded verification, retained cancelled test documents and remaining work. The paragraphs and gap descriptions below are dated baseline evidence, superseded where this update records implementation.

Local `snipeit` retained 70 profiles and 115 assignments after the targeted additive migration. Opening markers remain zero and 15 historical movement aliases remain; no historical repair or cut-over was performed. Isolated MySQL competing transfers passed, and a Bengali tablet receipt save/reload plus native stock-card rendering were verified. Phase 4 inspection remains deferred. Serialized issuing/custody, full localization/keyboard coverage, clean dependency installation and rollout observation remain open or partial; these are not closed by the local checks.

| Deeper gap | Current implementation / remaining work |
| --- | --- |
| 3. Native stock bypass | Opened-ledger model/bulk guards and native web/API movement blocks; nightly read-only reconciliation. Privileged raw SQL and historical stock require reviewed maintenance. |
| 5. Document types | Adjustments, draft void and atomic bulk transfer delivered; receipt reversal remains excluded by design. |
| 6 / 9. Recipient and serialized assets | Supplier-linked serialized receipts, tracking and per-unit warranty verified; serialized issue/handover/custody remains partial. |
| 10 / 11. Audit and takeover | Failed native audit writes roll back posting; takeover preserves creator/drafter and records reason plus prior-manager notice. |
| 14. Warranty | Per-unit UI and integer range validation, including zero months, delivered. |
| 15. Dead code | Unused controllers/screens/hooks/commands removed; compatibility models/tables deliberately retained. |
| 16. Destructive schema | Additive fresh/installed upgrade coverage preserves profiles, links and assignments without automatic national publication. |
| 17 / 20. Scripts and UI | External built assets, safe text rendering, queued saves, bilingual tablet workspace and native tabs delivered; remaining localization/keyboard/admin live coverage stays partial. |
| 18 / 19. Coverage and cards | Posting/rollback/authorization/migrations/concurrency covered; cards select latest 100 movements deterministically, with complete history pagination still pending. |
| 21. Dependencies | Required/optional dependencies declared; manifest/lock validation passes, clean solver/install remains unverified. |
| 22. Inspection | Explicitly deferred; no committee activation or posting-policy change. |

**Implementation update (6 Oct 2026):** The source now includes mitigations for gaps 1, 2, 4, 5 (adjustment and draft void only), 6 (recipient capture only), 7, 8, 9 (asset creation and model-level ledger only), 10, 12, 13, 14, 18, 19 and the numbering portion of 21. The three additive migrations were applied to the authorized local `snipeit` database; all 70 existing profiles received a lineage ID. The new `StoreOperationsWorkflowTest` runs as part of PHPUnit's normal Feature suite and verifies sequence continuation, opening-stock gating, canonical ledger keys, negative-stock rejection, and receipt → issue → adjustment posting. The focused Store Operations, G1 and custom-request fulfillment suites pass, and Blade templates compile. The opening-marker table is currently empty; all 47 offices with historical ledger movements are therefore gated from new ledger posting pending data repair and an authorized office cut-over. Existing ledger data includes 15 class-name aliases (`App\\Models\\Accessory` and `App\\Models\\Consumable`) that need normalization before cut-over. Gaps 3, 11, 15–17, 20 and the remaining portions of 5, 6, 9 and 21 remain open or partial. Historical evidence below describes the reviewed baseline and should be read with this update.

**Phase 4:** receipt inspection using the committee package is explicitly deferred at the user's direction. No committee consumer, inspection workflow or activation policy was changed. Keep gap 22 and its plan as future work.

The store-operations package has 22 gaps: 1 critical, 7 high, 12 medium and 2 low. The [package-wide review](gov-store-package-gap-analysis.md) found 8 gaps here. Two are closed, one is a design decision, and five are still open. The original deeper pass added 16 gaps; this reassessment adds the missing receipt-inspection integration (gap 22). Posted GRNs remain final, and current posting still has no approval workflow. The committee package is now available, so waiting for its delivery is no longer a reason to defer integration.

**Committee status:** the registry, purpose resolver and dated roster snapshots are implemented. Store-operations declares five purposes but does not yet use them to inspect receipts, record accepted quantities or authorize panel members. Generic `Committee_Report` uploads already exist for drafts; they do not establish committee verification. See [Committee integration assessment](#committee-integration-assessment) for the delivered contracts, missing consumer work and rollout prerequisites.

The document workspace, its access checks and the attachment handling are in good shape: documents are locked by office and state, and evidence files are private. The main problem is that the stock ledger can't yet serve as the official stock register:

- **No opening balances.** The ledger starts every item at zero while Snipe-IT already holds stock.
- **Two balance chains per item.** Each item's running balance is kept under two different type names, one per posting path.
- **Changes outside the ledger.** Snipe-IT's own screens still change stock without writing a ledger entry.

Two checks also don't work as intended:

- The server-side programme-tracking check never actually blocks a post.
- Publishing a product rule does not change the rules that are applied.

Because a posted GRN is final, an adjustment document is the only way to correct a posting mistake. That document type isn't available yet.

| Area | Critical | High | Medium | Low | Top gap |
| --- | --- | --- | --- | --- | --- |
| Ledger and stock integrity | 1 | 2 | 1 | 0 | The ledger has no opening balances |
| Documents | 0 | 3 | 4 | 1 | The tracking check never blocks a post |
| Product Rules Studio | 0 | 2 | 1 | 0 | Publishing does not change live rules |
| Platform, code and UI | 0 | 0 | 6 | 1 | Core ledger and posting coverage is incomplete |
| **Total** | **1** | **7** | **12** | **2** | |

Source: original static review of `packages/gov-store/store-operations` at `aab8e8e17a`, reassessed against `1f90f3aa2b` on 6 October 2026. The reassessment read the intervening store-operations changes, current custom-request fulfillment and regression tests, committee contracts/implementation and design §15. It did not run the application, inspect the development database or rerun tests. The earlier Snipe-IT, tenant-scope, tracking and experimentation findings remain a static baseline unless updated below; dated line counts and UI observations are not new measurements. The [committee implementation record](../verification/committee-implementation-2026-10-05.md) and [UI verification record](../verification/committee-ui-redesign-2026-10-05.md) are prior local evidence, not verification performed by this reassessment.

## What the package does

Storekeepers open a receipt or issue document in a single workspace. They add lines through a product search (consumables, accessories, components and asset models), fill in the fields that product rules require (serials, warranty), attach evidence, and post the document.

- **Product rules.** The rules in force for an item are combined in a fixed order: global, then company, office, category and model. The result is frozen into the document as a snapshot.
- **Posting.** Posting runs each line's capabilities: `post_inventory` writes a ledger movement, and `create_assets` creates serialized Snipe-IT assets.
- **Stock updates.** Each ledger movement then updates Snipe-IT's `qty` and writes an action log entry.
- **Programme tracking.** When a “Special Allocation” code is attached, posting also notifies the tracking package.
- **Committee integration.** The provider declares purposes for receipt inspection, technical receipt inspection, stock verification, disposal survey and disposal execution. No receipt workflow consumes the resolver or roster snapshots yet.
- **Other screens.** A stock register and a per-item stock card (kardex) read the ledger. The Product Rules Studio lets national superusers draft, simulate, publish and assign rules.

The package is about 8,700 lines of PHP, including migrations and language files, and 3,200 of Blade.

```
Workspace (DRAFT) ──saveDraft──► gov_documents + items + item_meta + compiled_profile_snapshot
        │
        └──post──► DocumentValidationService ──► PostingPipelineManager (one transaction)
                       │                              ├─ post_inventory ─► LedgerPostingService ─► gov_inventory_movements
                       │                              │                                    └─► InventoryMovementCreated
                       │                              │                                          ├─ UpdateSnipeQuantity (qty ±)
                       │                              │                                          └─ WriteNativeAuditLogs
                       │                              ├─ create_assets ─► new Snipe-IT Assets + gov_asset_registrations
                       │                              └─ tracking event (if Special Allocation)
                       └─ HTTP loopback to /gov-store/api/tracking/verify-code

custom-requests fulfillment ─► SystemGoodsIssueService ─► gov_goods_issues (legacy table) + movements (own balance maths)
```

## Design decisions

These behaviours are intentional for the current iteration, so they are not counted as gaps:

| Decision | What it means for this analysis |
| --- | --- |
| **A posted GRN cannot be reversed.** | No reversal document is proposed. Posted documents stay final. Mistakes are corrected with an adjustment document (gap 5). |
| **Current posting has no inspection workflow.** | The storekeeper drafts and posts (`storeops.documents.draft` and `.post` are both granted to `storekeeper`). Existing `READY` posting is not evidence of completed inspection. The proposed receipt inspection workflow remains opt-in consumer work (gap 22); registry availability does not activate it. Issue posting is unaffected by that proposal. |
| **Committee owns the registry; store-operations owns receipt inspection.** | The former package dependency is delivered. Panel selection, receipt references, report/outcome recording and inspection-aware posting belong in store-operations, using published committee contracts. Whether and when inspection becomes required still needs an authorized policy decision and observation; it is not enabled by this document. |

## Changes since the package-wide review

| Earlier gap | Status reassessed at `1f90f3aa2b` |
| --- | --- |
| 1. 37 actions have no permission checks (Critical) | **Closed.** All 32 routes declare `gov.can:` abilities, and `DocumentPolicy` checks the document type, office, ability and state on every document action. `G1AuthorizationTest` covers this. |
| 2. Only receipt and issue documents exist; posted documents cannot be cancelled or reversed (High) | **Partly addressed (gap 5).** `AdjustInventoryCapability` and `TransferInventoryCapability` now exist, but neither is registered, and no document type uses them. Reversal is out of scope by design. |
| 3. The Ready state has no effect (High) | **Current design decision; integration pending (gap 22).** Direct posting remains supported. The committee registry is delivered, but the proposed receipt-only submit/inspect/reopen/fallback workflow is not. |
| 4. No supplier or committee reference; serialized asset receipt unproven (High) | **Still open (gaps 9 and 22).** The asset-creation path exists with defects. Committee purpose declarations are delivered, but the active document workspace stores no resolved panel or dated committee reference. A generic report upload is not a structured inspection record. |
| 5. Legacy receipt and issue controllers are dead code (Medium) | **Still open (gap 15)**, with more dead code found. |
| 6. Profile tables are created by three migrations, each dropping the last (Medium) | **Still open (gap 16).** |
| 7. Hard-coded English; grid not usable on tablets (Medium) | **Still open (gap 20).** A simple scan finds about 70 hard-coded view lines, not 19. |
| 8. The route name `storeops.admin.rules.assign` is used twice (Low) | **Closed.** The second route is now `storeops.admin.rules.assign_gpo`. |

## Gaps

### Ledger and stock integrity

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | **The ledger has no opening balances.** Every item's ledger balance starts at 0, while Snipe-IT already holds the office's existing stock. `LedgerPostingService` rejects an OUT movement that would take the ledger below 0. So a workspace issue of existing stock fails with “Ledger violation: Insufficient stock”, even though `DocumentLineItemManager` checked Snipe-IT's quantity and let the line through. Request fulfillment uses `SystemGoodsIssueService`, which does not check the ledger at all, so the same issue writes a negative `balance_after`. No opening-balance document, command or migration exists. The only opening rows are hand-written by the experimentation fixture (`BangladeshScenario:502-516`). | Critical | Add an `opening` document type and a cut-over command. Per office and item, the command posts one IN movement equal to on-hand stock (Snipe-IT remaining) at cut-over, without changing Snipe-IT. Allow no ledger posting for an office until its opening document is posted. |
| 2 | **Each item has two running balances.** Workspace documents post the short type name (`consumable`). `SystemGoodsIssueService` posts the full class name (`App\Models\Consumable`). `LedgerPostingService` and `RepairLedgerBalances` both match the type exactly, so they keep two separate balance chains for one item; only the kardex merges them, and only for display. `SystemGoodsIssueService` also has its own problems: <br>• it reads the balance with no lock, ordered by `created_at` alone; <br>• it does not check for a negative result; <br>• it writes to the legacy `gov_goods_issues` table, so its documents never appear in the hub; <br>• it numbers documents from a separate `GI-` sequence, so two different documents can get the same `GI-2026-…` number; <br>• its movements' document link resolves through the morph map to `Document`, finds no row, and comes back `null`. | High | Use one key per type (the short morph key) everywhere. Send system issues through `LedgerPostingService`, with a `gov_documents` row of type `issue` linked to the request. Normalise the existing rows and recompute balances (see Data repair). |
| 3 | **Snipe-IT screens still change stock outside the ledger.** `UpdateSnipeQuantity` raises and lowers `qty`, which Snipe-IT treats as the total owned; Snipe-IT then subtracts its checkouts to get what remains. But Snipe-IT's own checkout and check-in actions, and quantity edits on the consumable, accessory and component screens, never create a movement. **Updated:** custom-request consumable/accessory adapters now write history only, without checkout pivots after the ledger decrement. `CustomRequestsWorkflowTest::test_real_goods_issue_projects_stock_once_for_consumables_and_accessories` covers that repair. Native stock changes and any older drift still need reconciliation; the wider gap remains open. | High | Make the ledger the owner of on-hand stock for ledger-managed items. Block or redirect native quantity edits and checkouts for those items: hide them in the UI and add an observer that refuses changes. Add a nightly reconciliation report per office and item. |
| 4 | **The running balance depends on who reads it.** `InventoryMovement` has `MinistryLocationScope`, so the latest-balance read in `LedgerPostingService` is filtered to the poster's working office. Console and global contexts read it unfiltered. Kardex reads are filtered the same way. A transfer, once wired up, would compute the destination office's balance from the source office's rows. | Medium | Keep balances per item and office explicitly, read them with `withoutGlobalScopes()`, lock them, and order by `(created_at, id)`. Make `RepairLedgerBalances` recompute every chain, not only the rows with a `null` balance. |

### Documents

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 5 | **Posting mistakes cannot be corrected, and drafts cannot be voided.** Posted GRNs are final by design, so the only way to fix a wrong quantity or item is an adjustment document. None exists: `initialize` and `DocumentPolicy` allow only `receipt` and `issue`, and the adjustment and transfer capabilities exist but are not in `CapabilityRegistry`. No action voids a draft either (the `CANCELLED` state is unreachable), so abandoned drafts pile up and keep their numbers. | High | Register the two capabilities and add `adjustment` and `transfer` document types with reason codes. An adjustment references the document it corrects. Add a void action for drafts. |
| 6 | **Issue documents record no recipient.** The issue workspace reuses the receipt header (purchase type, supplier challan, PO), so the stock goes out with no user, department or request recorded. It writes only an OUT movement, with no Snipe-IT checkout. A hardware line crashes at draft save: `StockableFactory` has no branch for asset models, so the `match` throws `UnhandledMatchError`. | High | Give issue documents their own header (recipient, department, request reference). Check out to the recipient. Support issuing serials by picking specific assets. |
| 7 | **The server-side tracking check never blocks a post.** `DocumentValidationService` checks the Special Allocation code by calling the app's own `/gov-store/api/tracking/verify-code` over HTTP. The call carries no user session, and the route needs `web` and `auth` (`tracking` provider), so the request is redirected to the login page. Laravel's HTTP client follows redirects by default and gets a 200 HTML page, so `json()` is `null` and the check passes. Exceptions also pass (“fail open”). Even when it works, the call can block for up to 5 seconds inside the posting transaction while the document row is locked. | High | Call the tracking package's evaluation service in-process, through an interface that store-operations owns. Fail closed when a code is present but can't be verified. Run the check before the transaction starts. |
| 8 | **Posting can silently write nothing to the ledger.** Ledger writes happen only when the item's rules enforce `post_inventory`. If an item's category is assigned rules without it, the document still posts as `POSTED`, with no movement and no stock change, and `evaluateDocument` raises no warning. Because posted GRNs are final, a GRN posted this way cannot be fixed afterwards. Capabilities also ignore the document type, so `create_assets` would create new assets on an issue document if a hardware line got that far. | Medium | Always post to the ledger for stockable types (not as an optional rule). Let each capability declare the document types it runs on, and check that in the pipeline. |
| 9 | **Asset receipts produce poor-quality data.** When serials are not required, `CreateAssetsCapability` invents them (`SN-AUTO-…`). It invents tags too (`TAG-AUTO-…uniqid`), ignoring Snipe-IT's tag auto-increment setting, and assumes the status label with id 1 is deployable. It writes a **checkout** log entry for a new, unassigned asset, so the asset's history shows it checked out to the storekeeper. Hardware never reaches the ledger (no asset-model adapter), so hardware receipts are missing from the kardex. Receipts do not capture the supplier, and the tracking event always sends `supplier_id = 0`. | Medium | Require serials for serialized categories; otherwise leave the serial blank. Use Snipe-IT's tag generator and a configured default status. Log `create`, not `checkout`. Record hardware receipts in the ledger as model-level movements. Add a supplier field to the receipt header. |
| 10 | **Audit log entries are misleading.** `WriteNativeAuditLogs` writes `action_type = 'requested'` for receipts and `'checkout'` for issues, with no target. `item_type` holds the short key for workspace documents; Snipe-IT history pages look up by class name, so these entries most likely never appear there (not run). The document number is read from `receipt_no`, `issue_no` or `adjustment_no`, which `gov_documents` doesn't have, so the workspace reference is blank. Failures are only written to the log file. `PostInventoryCapability` passes the allocation note as a ninth argument, which `postMovement()` doesn't accept, so the note is dropped. **Updated:** the listener now sets `created_by` instead of `user_id`; the real system-issue regression checks actor attribution. These remaining workspace audit defects are unchanged. | Medium | Use proper action types, the class name for `item_type`, and `document_number`. Add a `notes` parameter to `postMovement()`. Make a logging failure fail the post. |
| 11 | **Draft takeover is silent.** A storekeeper can take over another storekeeper's draft without giving a reason, and the previous owner is not told. | Low | Require a reason for takeover and notify the previous owner. |
| 22 | **Receipt inspection is not connected to the delivered committee registry.** `StoreOpsCommitteeRegistrations` declares purposes only. `Document`, `DocumentValidationService` and `PostingPipelineManager` store no panel snapshot or inspection outcome and do not resolve a committee before posting. The draft workspace offers `Committee_Report`, but it has no panel membership, concurrence or accepted/rejected quantities; the legacy `GoodsReceipt.committee_ref` does not serve the active `gov_documents` workspace. No inspection inbox, fallback certification or inspection-aware print exists. This is missing opt-in integration, not a violation of the current direct-posting policy. | Medium | Implement the store-operations consumer workflow in committee design §15 using public contracts: panel resolution and snapshots, receipt references, private report/outcome recording, line acceptance, narrow panel-member access, fallback, printing and advisory reporting. Preserve current behavior when disabled; activate required inspection only after policy review and observation. |

### Product Rules Studio

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 12 | **Publishing does not change the rules that are applied.** Assignments point to a profile id, and `getActiveAssignment()` never checks the profile's status, so an archived profile keeps enforcing. To change a rule you duplicate it, but the copy is named “– Copy” and has no assignments. Publishing it archives only profiles with the *same* name, so the old version stays live. | High | When a profile is published, move its lineage's active assignments to it, keeping a `lineage_id` across versions. Resolve assignments only to `PUBLISHED` profiles. Show the effective version in the inspector. |
| 13 | **Assigning a rule bypasses publishing.** `assignPolicy()` validates `profile_id` only as an integer, so a draft or archived profile can be made live; the UI lists only published profiles, but the controller doesn't check status. `target_type` accepts any string and `target_id` is only required. The match has no branch that produces `COMPANY` scope, so the company layer of the rule order can never be assigned. **Correction:** the HTTP routes already require national review, a change reason and typed `CHANGE` through `RequireGovAbility` / `NationalChangeReview`; the remaining gap is assignment validity, not absent review or an optional reason. | High | Validate `exists` with status `PUBLISHED`, allow-list target types and validate each target ID. Add company-level assignment while preserving national review, reason and replay protection. |
| 14 | **Enforcing `require_warranty` crashes.** `RequireWarrantyCapability` does not implement `renderUI()`, which `CapabilityInterface` requires. PHP refuses to load a class like that, so drafting, validation and posting hit a fatal error for any item whose rules enforce warranty. The Rules Studio offers warranty as an option, and the `2024_03_04` seed assigned it to the notebook profile, which a later migration dropped. | Medium | Implement `renderUI()`. Add a test that loads every class in the registry. |

### Platform, code and UI

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 15 | **Dead code and dead tables.** `GoodsReceiptController`, `GoodsIssueController`, `GoodsIssueService`, the `receipts/create` and `issues/create` views and the `GoodsReceipt` and `StockAdjustment` models are unused. So are the tables `gov_goods_receipts`, `gov_stock_adjustments` and their item tables. `RequireProgrammeTrackingCapability` does nothing. Three reports in the package root (1,269 lines) describe earlier states; one names a `Resources/` folder casing problem that has since been fixed. | Medium | Delete the code. Drop the unused tables after gap 2's migration. Move the reports to `docs/` or delete them. |
| 16 | **Migrations are destructive.** `gov_profiles` and `gov_profile_capabilities` are created in `2024_03_02`, then dropped and recreated in both `2024_03_04` and `2024_03_08`. `2024_03_04` also drops `gov_capabilities` and `gov_requirement_definitions` from `2024_03_02`, so the data seeded with them is lost. | Medium | Squash the migrations into one additive migration per table, with a separate seeder. |
| 17 | **Core ledger and posting coverage is incomplete.** `G1AuthorizationTest` covers routes, state locks, office checks and Ready-state validation. **Updated:** `CustomRequestsWorkflowTest` now exercises real system issues for consumables/accessories, one stock decrement, movement creation and audit attribution, plus native serial issuance/rollback. Its system-issue fixture seeds full-class-name opening rows, so it does not prove canonical type unification, missing-opening behavior or per-office running balances. Focused coverage is still missing for ledger arithmetic/concurrency, the workspace posting pipeline, asset creation on receipt, product-rule resolution/publishing and the proposed inspection consumer. `CommitteeTest` tests the registry, not receipt inspection. | Medium | Extend the existing suites with the missing invariants listed below and run them in CI. |
| 18 | **Search and pages do not scale.** For each type on each search, `ProductResolver` runs `Schema::hasColumn` twice, and counts assets for every asset model it returns. `searchProducts` then runs one more query per result to get `category_id`, at up to 50 results per type. The stock register loads every item with no pagination. The compiled-rules cache is static and never cleared, which goes stale in long-running workers. | Medium | Cache the column lists, aggregate the asset counts, and return `category_id` from the main query. Paginate the register. Use a cache keyed on the policy version. |
| 19 | **The kardex tab never appears.** `InjectStoreOperationsUi` is never registered as middleware, so the stock-card tab registered by `registerKardexTabs()` never shows on item pages. The `menu-injection` hook it would render links to `storeops.receipts.create` and `storeops.issues.create`, which don't exist, so the hook would crash if rendered. | Medium | Render the tab through a view composer on the item pages and delete the hook, or delete all three pieces. |
| 20 | **Hard-coded English and UI issues.** The language files match (67 keys each), but about 70 view lines are literal English: purchase types, reference labels and placeholders. Menu titles (“Store Documents Hub”, “Product Rules Studio”), the capability dictionary and the exception messages are English too. Views depend on tenant-scope's `tenantops::access.*` keys, and 10 views contain inline scripts. The grid is not usable on store-room tablets. | Medium | Move the text into `storeops::storeops`, bundle the scripts, and give the grid a responsive layout. |
| 21 | **Undeclared dependencies and fragile seeds.** The package's `composer.json` has `"require": {}`, but the code depends on tenant-scope and, optionally, tracking and now committee public contracts. The current application explicitly registers/autoloads committee; standalone installation and the absent-package path need coverage. `SnipeCategoryObserver` finds the default rules by name (“System Default … Standard”), so renaming them silently stops new categories from getting rules. `DocumentNumberService` reads the latest number without a lock, so two drafts started at the same moment collide on the unique index. | Low | Declare required dependencies and document optional integrations. Find default rules by a fixed key. Generate numbers from a locked sequence row. |

## Committee integration assessment

The committee package supplies registry services; receipt inspection remains store-operations work. The [consumer contract](../../packages/gov-store/committee/README.md#consumer-contract) and [design §15](../../packages/gov-store/committee/DESIGN.md#15-integration-contracts) describe the boundary. The design includes proposed consumer code and policies; those examples are not shipped inspection behavior.

| Surface | Current evidence | Assessment |
| --- | --- | --- |
| Package availability | Application autoload/provider registration and committee contract bindings exist. | Registry dependency delivered. |
| Purpose declaration | `StoreOpsCommitteeRegistrations::register()` is called by the store-operations provider. It declares `storeops.receipt.inspection`, `.inspection.technical`, `storeops.stock.verification`, `storeops.disposal.survey` and `.execution` for office/store scopes. | Delivered; purpose declaration alone does not select a panel or enable a policy. |
| Panel resolution | `CommitteeResolver::resolve(purpose, scope, asOf)` returns `FOUND`, `NOT_FOUND`, `INOPERABLE`, `AMBIGUOUS` or `CONFLICT`. | Available; the consumer must authorize the document and supply its verified scope, handle every status and avoid silently choosing an ambiguous candidate. |
| Dated evidence | `RosterSnapshotProvider::snapshot()` and `verify()` provide a roster/fingerprint and verification status. | Available; store-operations must persist the snapshot/provenance and handle `CHANGED_SINCE`, invalid fingerprints and unknown committees explicitly. |
| Receipt reference/report | A `Committee_Report` attachment category exists for drafts. Uploads use the draft policy; posted uploads are not supported. The active document has no inspection relation. | Partly available as private file handling; panel reference, signed-report provenance, outcome and print integration remain open (gap 22). Any later report filing must be a separately authorized, audited action that preserves posted stock and original inspection evidence. |
| Receipt posting | Existing validation and posting contain no committee consumer call or accepted-quantity handling. | Unchanged direct posting; neither `READY` nor an uploaded report proves inspection. |
| Policy rollout | The seeder creates five inactive national templates (GRIC, TIC, SVC, BOS, DSP). The dated local records also report inactive templates. | Real policy/purpose-binding review, office orders, membership/scope data and operational readiness remain prerequisites. Live state was not rechecked here. |

Keep committee imports inside `Integrations/Committee/` and use its published `Contracts`, `DTOs`, `Events` and `Enums`; do not couple receipt logic to committee models or tables. Store-operations should own inspection tables with committee IDs as values and frozen roster evidence. A committee member's access must be limited to their assigned document and report through a dedicated policy; it must not grant general stock access or bypass `DocumentPolicy` across an office.

## Mitigation plan

The plan retains five phases. Implementation updates have started work in Phases 0–3; incomplete steps remain follow-up work. Phase 4 is deferred and untouched. No CI result or live cut-over is claimed. Reversal remains outside the plan. The week ranges below are planning estimates, not completed delivery or calendar commitments.

### Phase 0: Make the ledger trustworthy (weeks 1–2)

| Step | Gaps | Work | Done when |
| --- | --- | --- | --- |
| 0.1 | 2 | Use one short morph key everywhere. Rewrite `SystemGoodsIssueService` to go through `LedgerPostingService` and `gov_documents`. Run the normalisation and recompute (Data repair 1–2). | Every item has one balance chain, and each request fulfillment appears in the hub |
| 0.2 | 4 | Read balances per item and office with `withoutGlobalScopes()` and a lock. Rewrite `RepairLedgerBalances` to recompute every chain. | Concurrent-posting test gives a correct balance |
| 0.3 | 1 | Add the opening document type and cut-over command. Gate posting on a posted opening document. | Every live office has an opening document, and the reconciliation report shows no difference |
| 0.4 | 3 | Add the reconciliation report, and block native quantity edits and checkouts for ledger-managed items. | The report runs nightly; native edits are refused for those items |
| 0.5 | 8, 14 | Always post stockable lines to the ledger. Implement `RequireWarrantyCapability::renderUI()`. | Test: every registry class loads; a document with no rules still writes movements |

### Phase 1: Tracking check and product rules (weeks 3–4)

| Step | Gaps | Work |
| --- | --- | --- |
| 1.1 | 7 | Run the tracking check in-process, fail closed, and run it before the transaction. |
| 1.2 | 12, 13 | Move assignments to the new version on publish, resolve only `PUBLISHED` profiles, validate assignment input and add company-level assignment. Preserve the existing national review and required change reason. |
| 1.3 | 17 | Build the test suite below. |

### Phase 2: Complete the document set (weeks 5–7)

| Step | Gaps | Work |
| --- | --- | --- |
| 2.1 | 5 | Add adjustment and transfer document types with reason codes, with an adjustment referencing the document it corrects; add voiding for drafts. |
| 2.2 | 6 | Give issue documents a recipient header, checkout and serial picking. |
| 2.3 | 9, 10 | Fix asset receipt data (serials, tags, status, `create` log, ledger entries for hardware, supplier field) and the audit log entries. |
| 2.4 | 11 | Require a takeover reason and notify the previous owner. |

### Phase 3: Clean up and performance (alongside Phase 2)

| Step | Gaps | Work |
| --- | --- | --- |
| 3.1 | 15, 16, 19 | Delete dead code and the hook, render the kardex tab through a view composer, and squash the migrations. |
| 3.2 | 18, 21 | Fix the search queries, paginate the register, add a versioned rules cache, generate numbers from a locked sequence, and declare the dependencies. |
| 3.3 | 20 | Move the text into language files, bundle the scripts, and lay the grid out for tablets. |

### Phase 4: Integrate receipt inspection (gap 22; deferred)

Deferred by the user for later handling. No Phase 4 implementation was performed in this mitigation pass. When resumed, follow design §15; the committee registry is already available, and policy choices should be finalized before activation.

| Step | Work | Done when |
| --- | --- | --- |
| 4.1 | Add a store-operations inspection port, disabled adapter and committee adapter. Resolve a general/technical receipt panel by authorized office/store and inspection date; handle every resolution status. | Disabled or absent-package behavior preserves current receipt and issue posting; unavailable authority has an explicit result. |
| 4.2 | Add inspection and inspection-line records, frozen roster/fingerprint, memo reference, inspection date, private report, outcome/concurrence and actor attribution. Implement submit, record, reopen and explicit fallback paths. | Quantities satisfy accepted + rejected = received; obsolete inspections are retained as superseded evidence; mutations lock and recheck the document. |
| 4.3 | Add a narrow inspection inbox and panel-member policy plus office-admin fallback certification. Register abilities and extend G1 route coverage. | Non-members and unrelated document/body IDs fail; cross-office members see only their assigned inspection; the drafter cannot record the outcome; replay and wrong state fail. |
| 4.4 | Integrate accepted quantities into receipt ledger, asset creation and programme tracking; print committee/memo/roster/outcome or fallback evidence. Define how serialized lines identify accepted serials. | A partial acceptance posts only accepted stock/assets/costs exactly once, atomically; rejected quantities create no stock. Posted evidence remains stable after roster changes. |
| 4.5 | Add inspection usage reporting, panel-change flags and an authorized committee inspections tab. Introduce `off` / `advisory` / `required` policy with exception reasons and shadow reporting. | Advisory behavior and disabled compatibility are tested; authorized policy review and real observation precede required-mode activation. Missing or invalid inspection evidence cannot silently pass in required mode. |

Stock verification and disposal purposes are already declared, but their consumer workflows depend on future document types. They are not delivered by receipt inspection or by registering a purpose.

### Data repair (with Phase 0)

These code paths can leave inconsistent historical data; this reassessment did not inspect actual rows. Before repair, follow the [local live testing and database guide](../testing/local-live-testing.md), inspect the target and establish an authorized backup/cut-over plan. The steps below are proposed repair work, not commands run by this review. They change ledger rows and the copy of system issues; no posted GRN is altered or reversed.

1. **Normalise type names (gap 2).**
   ```sql
   UPDATE gov_inventory_movements SET stockable_type = 'consumable' WHERE stockable_type = 'App\\Models\\Consumable';
   UPDATE gov_inventory_movements SET stockable_type = 'accessory'  WHERE stockable_type = 'App\\Models\\Accessory';
   UPDATE gov_inventory_movements SET stockable_type = 'component'  WHERE stockable_type = 'App\\Models\\Component';
   ```
2. **Move system issues (gap 2).** Copy each `gov_goods_issues` row and its items into `gov_documents` as type `issue`, status `POSTED`. Renumber any that clash with an existing workspace `GI-` number, keeping the old number as a reference. Point the related movements' `document_type` and `document_id` at the new rows.
3. **Recompute balances (gaps 2 and 4).** For each item and office, recompute `balance_after` in `(created_at, id)` order. List every chain that goes negative; those mark missing opening stock.
4. **Post opening balances (gap 1).** For each item and office, take Snipe-IT on-hand stock at cut-over minus the recomputed ledger balance, and post the difference as the opening document, with the cut-over date as its reference.
5. **Review data (gaps 8 and 9).** Fix anything found with adjustment documents once gap 5 is in place.
   - Posted documents with no movements: `SELECT d.* FROM gov_documents d LEFT JOIN gov_inventory_movements m ON m.document_id = d.id WHERE d.status = 'POSTED' AND m.id IS NULL`.
   - Assets with invented serials: `WHERE serial LIKE 'SN-AUTO-%'`. Send these to offices for physical verification.

### Tests to add

**Added and passing:** [StoreOperationsWorkflowTest](../../tests/Feature/GovStore/StoreOperationsWorkflowTest.php) is included under PHPUnit's `Feature` testsuite and runs directly against an isolated in-memory SQLite schema. It exercises legacy GI sequence continuation, office opening-marker gating, class-name alias normalization, rejection of negative stock, and the posted receipt → issue → adjustment lifecycle. Current focused verification: this suite 3 tests / 17 assertions; `CustomRequestsWorkflowTest` 26 / 157; `G1AuthorizationTest` 29 / 952. These tests cover those paths, not every package gap. The default full-suite discovery found 2,740 tests but cannot pass in this checkout: 2,640 existing tests stop at `tests/TestCase.php:61` because `.env.testing` is absent. The full run also initially exceeded PHP's 128 MB memory limit; a retry at 1 GB completed and confirmed the environment-configuration errors.

- Ledger: IN and OUT arithmetic; a negative balance is rejected; concurrent posts (row lock); balances per item and office; the type key is normalised; the repair command recomputes every chain.
- Opening balance: the cut-over command posts on-hand stock without changing Snipe-IT; posting is blocked until an opening document exists.
- Posting pipeline: stockable lines always reach the ledger; capabilities run only for their document types; a hardware receipt creates assets and a ledger entry; Snipe-IT `qty` is updated exactly once.
- Documents: a posted GRN cannot be modified or reversed; an adjustment corrects stock and references the original; a draft can be voided; the tracking check fails closed.
- Rules: the resolution order (global → company → office → category → model); publishing moves assignments; draft and archived profiles cannot be assigned; every registry class loads.
- System issues: a request fulfillment creates a `gov_documents` issue, one balance chain and a unique number.
- Receipt inspection: disabled/absent-package compatibility; all resolver and snapshot-verification statuses; verified office/company scope; frozen panel/memo evidence; authorized recorder distinct from drafter; cross-office panel access limited to the assigned document; foreign body IDs, stale states and replay rejected; private report access and provenance; partial acceptance including serial selection and tracking totals; fallback reason/authority; advisory reporting and required-mode enforcement; concurrent outcome/post rollback.
- Integration boundary: committee imports stay in `Integrations/Committee/` and on the published surface; committee remains independent of store-operations; purpose registration, usage reporting and the inspection tab do not expose unrelated documents.

## Mitigation outcome and verification limits

- **Code changed:** ledger opening/cut-over support and office posting gate; canonical ledger posting for workspace and system issues; per-office balance repair; in-process fail-closed tracking verification; mandatory stock posting; warranty capability loading; product-rule publication lineage and assignment validation; adjustment reason/source/direction and draft voiding; issue recipient capture with active-office membership checks; safer asset creation and model-level hardware ledger movement; audit note and attribution handling; search aggregation/cache, register pagination and the kardex tab middleware path; locked document-number sequences.
- **Still open or partial:** native Snipe-IT stock mutations and reconciliation (gap 3); transfers (gap 5); serialized asset issue and native checkout (gap 6); running tracking verification before the posting transaction (gap 7); supplier capture and receipt supplier attribution (gap 9); takeover reasons/notices (gap 11); dead code and destructive migration history (gaps 15–16); focused automated coverage (gap 17); full localization/tablet layout (gap 20); dependency declarations and fixed-key default rule seeding (gap 21); committee receipt inspection (gap 22, deferred).
- **Verification performed:** `G1AuthorizationTest` passed (29 tests, 952 assertions); `CustomRequestsWorkflowTest` passed (26 tests, 157 assertions). `artisan view:cache` compiled Blade successfully. The three package migrations show `Ran` in `snipeit`; expected columns/tables are present, and lineage is populated for 70/70 profiles. Read-only inspection found 0 opening markers, 47 offices with movement rows, 1,272 historical movement rows, and 15 class-name aliases. PHP syntax checks passed for changed/new PHP source. PHP 8.4.15 was used with Xdebug disabled. No live UI flow or office opening command was run.
- **Rollout still required:** normalize historical movement type aliases and repair legacy system issues before opening each office ledger from a verified cut-over baseline. The code changes and migrations are applied only to this local database; other environments need their own reviewed migration. More focused tests for adjustments, opening cut-over, profile publishing and ledger arithmetic remain necessary. Existing row quality and production readiness are unverified.
