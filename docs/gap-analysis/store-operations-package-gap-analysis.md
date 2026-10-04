# store-operations Gap Analysis and Mitigation Plan

Oct 4, 2026 · @zahid

## Summary

The store-operations package has 21 gaps: 1 critical, 7 high, 11 medium and 2 low. The [package-wide review](gov-store-package-gap-analysis.md) found 8 gaps here. Two are now closed, one is now a design decision, and five are still open. This deeper pass found 16 more. Three behaviours are design decisions, not gaps, and are listed under [Design decisions](#design-decisions): posted GRNs are final, posting has no approval workflow, and committee features wait for the committee package.

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
| Documents | 0 | 3 | 3 | 1 | The tracking check never blocks a post |
| Product Rules Studio | 0 | 2 | 1 | 0 | Publishing does not change live rules |
| Platform, code and UI | 0 | 0 | 6 | 1 | The ledger and posting have no tests |
| **Total** | **1** | **7** | **11** | **2** | |

Source: static review of `packages/gov-store/store-operations` at commit `aab8e8e17a`. The application was not run. Cross-checks were made against Snipe-IT core models and against tenant-scope (`config/abilities.php`, `MinistryLocationScope`), tracking (`routes/api.php`), custom-requests and experimentation. Where a gap depends on runtime behaviour, the evidence column says so.

## What the package does

Storekeepers open a receipt or issue document in a single workspace. They add lines through a product search (consumables, accessories, components and asset models), fill in the fields that product rules require (serials, warranty), attach evidence, and post the document.

- **Product rules.** The rules in force for an item are combined in a fixed order: global, then company, office, category and model. The result is frozen into the document as a snapshot.
- **Posting.** Posting runs each line's capabilities: `post_inventory` writes a ledger movement, and `create_assets` creates serialized Snipe-IT assets.
- **Stock updates.** Each ledger movement then updates Snipe-IT's `qty` and writes an action log entry.
- **Programme tracking.** When a “Special Allocation” code is attached, posting also notifies the tracking package.
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
| **Posting has no workflow in this iteration.** | The storekeeper drafts and posts (`storeops.documents.draft` and `.post` are both granted to `storekeeper`). Posting needs no second person and no Ready step. The `READY` state stays reserved for a later iteration. |
| **Committee features arrive with the committee package.** | Committee verification before posting, committee references on receipts, and filing signed committee reports are all deferred to that package. This analysis proposes none of them. |

## Changes since the package-wide review

| Earlier gap | Status at `aab8e8e17a` |
| --- | --- |
| 1. 37 actions have no permission checks (Critical) | **Closed.** All 32 routes declare `gov.can:` abilities, and `DocumentPolicy` checks the document type, office, ability and state on every document action. `G1AuthorizationTest` covers this. |
| 2. Only receipt and issue documents exist; posted documents cannot be cancelled or reversed (High) | **Partly addressed (gap 5).** `AdjustInventoryCapability` and `TransferInventoryCapability` now exist, but neither is registered, and no document type uses them. Reversal is out of scope by design. |
| 3. The Ready state has no effect (High) | **Design decision.** Posting has no workflow in this iteration, and committee sign-off waits for the committee package. |
| 4. No supplier or committee reference; serialized asset receipt unproven (High) | **Supplier part still open (gap 9).** The asset-creation path now exists, but it has its own defects. The committee reference is deferred to the committee package. |
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
| 3 | **Snipe-IT screens still change stock outside the ledger.** `UpdateSnipeQuantity` raises and lowers `qty`, which Snipe-IT treats as the total owned; Snipe-IT then subtracts its checkouts to get what remains. But Snipe-IT's own checkout and check-in actions, and quantity edits on the consumable, accessory and component screens, never create a movement. Custom-requests adds checkout rows on top of the ledger decrement (custom-requests gap 7). The ledger and Snipe-IT drift apart with every native action, and no report shows the difference. | High | Make the ledger the owner of on-hand stock for ledger-managed items. Block or redirect native quantity edits and checkouts for those items: hide them in the UI and add an observer that refuses changes. Add a nightly reconciliation report per office and item. |
| 4 | **The running balance depends on who reads it.** `InventoryMovement` has `MinistryLocationScope`, so the latest-balance read in `LedgerPostingService` is filtered to the poster's working office. Console and global contexts read it unfiltered. Kardex reads are filtered the same way. A transfer, once wired up, would compute the destination office's balance from the source office's rows. | Medium | Keep balances per item and office explicitly, read them with `withoutGlobalScopes()`, lock them, and order by `(created_at, id)`. Make `RepairLedgerBalances` recompute every chain, not only the rows with a `null` balance. |

### Documents

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 5 | **Posting mistakes cannot be corrected, and drafts cannot be voided.** Posted GRNs are final by design, so the only way to fix a wrong quantity or item is an adjustment document. None exists: `initialize` and `DocumentPolicy` allow only `receipt` and `issue`, and the adjustment and transfer capabilities exist but are not in `CapabilityRegistry`. No action voids a draft either (the `CANCELLED` state is unreachable), so abandoned drafts pile up and keep their numbers. | High | Register the two capabilities and add `adjustment` and `transfer` document types with reason codes. An adjustment references the document it corrects. Add a void action for drafts. |
| 6 | **Issue documents record no recipient.** The issue workspace reuses the receipt header (purchase type, supplier challan, PO), so the stock goes out with no user, department or request recorded. It writes only an OUT movement, with no Snipe-IT checkout. A hardware line crashes at draft save: `StockableFactory` has no branch for asset models, so the `match` throws `UnhandledMatchError`. | High | Give issue documents their own header (recipient, department, request reference). Check out to the recipient. Support issuing serials by picking specific assets. |
| 7 | **The server-side tracking check never blocks a post.** `DocumentValidationService` checks the Special Allocation code by calling the app's own `/gov-store/api/tracking/verify-code` over HTTP. The call carries no user session, and the route needs `web` and `auth` (`tracking` provider), so the request is redirected to the login page. Laravel's HTTP client follows redirects by default and gets a 200 HTML page, so `json()` is `null` and the check passes. Exceptions also pass (“fail open”). Even when it works, the call can block for up to 5 seconds inside the posting transaction while the document row is locked. | High | Call the tracking package's evaluation service in-process, through an interface that store-operations owns. Fail closed when a code is present but can't be verified. Run the check before the transaction starts. |
| 8 | **Posting can silently write nothing to the ledger.** Ledger writes happen only when the item's rules enforce `post_inventory`. If an item's category is assigned rules without it, the document still posts as `POSTED`, with no movement and no stock change, and `evaluateDocument` raises no warning. Because posted GRNs are final, a GRN posted this way cannot be fixed afterwards. Capabilities also ignore the document type, so `create_assets` would create new assets on an issue document if a hardware line got that far. | Medium | Always post to the ledger for stockable types (not as an optional rule). Let each capability declare the document types it runs on, and check that in the pipeline. |
| 9 | **Asset receipts produce poor-quality data.** When serials are not required, `CreateAssetsCapability` invents them (`SN-AUTO-…`). It invents tags too (`TAG-AUTO-…uniqid`), ignoring Snipe-IT's tag auto-increment setting, and assumes the status label with id 1 is deployable. It writes a **checkout** log entry for a new, unassigned asset, so the asset's history shows it checked out to the storekeeper. Hardware never reaches the ledger (no asset-model adapter), so hardware receipts are missing from the kardex. Receipts do not capture the supplier, and the tracking event always sends `supplier_id = 0`. | Medium | Require serials for serialized categories; otherwise leave the serial blank. Use Snipe-IT's tag generator and a configured default status. Log `create`, not `checkout`. Record hardware receipts in the ledger as model-level movements. Add a supplier field to the receipt header. |
| 10 | **Audit log entries are misleading.** `WriteNativeAuditLogs` writes `action_type = 'requested'` for receipts and `'checkout'` for issues, with no target. `item_type` holds the short key for workspace documents; Snipe-IT history pages look up by class name, so these entries most likely never appear there (not run). The document number is read from `receipt_no`, `issue_no` or `adjustment_no`, which `gov_documents` doesn't have, so the note's reference is always blank. Failures are only written to the log file. `PostInventoryCapability` passes the allocation note as a ninth argument, which `postMovement()` doesn't accept, so the note is dropped. | Medium | Use proper action types, the class name for `item_type`, and `document_number`. Add a `notes` parameter to `postMovement()`. Make a logging failure fail the post. |
| 11 | **Draft takeover is silent.** A storekeeper can take over another storekeeper's draft without giving a reason, and the previous owner is not told. | Low | Require a reason for takeover and notify the previous owner. |

### Product Rules Studio

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 12 | **Publishing does not change the rules that are applied.** Assignments point to a profile id, and `getActiveAssignment()` never checks the profile's status, so an archived profile keeps enforcing. To change a rule you duplicate it, but the copy is named “– Copy” and has no assignments. Publishing it archives only profiles with the *same* name, so the old version stays live. | High | When a profile is published, move its lineage's active assignments to it, keeping a `lineage_id` across versions. Resolve assignments only to `PUBLISHED` profiles. Show the effective version in the inspector. |
| 13 | **Assigning a rule bypasses publishing.** `assignPolicy()` validates `profile_id` only as an integer, so a draft or archived profile can be made live; the UI lists only published profiles, but the server doesn't check. `target_type` accepts any string. The match has no branch that produces `COMPANY` scope, so the company layer of the rule order can never be assigned. The change reason is optional. | High | Validate `exists` with status `PUBLISHED` and an allow-list of target types. Add company-level assignment. Require a change reason when publishing or assigning. |
| 14 | **Enforcing `require_warranty` crashes.** `RequireWarrantyCapability` does not implement `renderUI()`, which `CapabilityInterface` requires. PHP refuses to load a class like that, so drafting, validation and posting hit a fatal error for any item whose rules enforce warranty. The Rules Studio offers warranty as an option, and the `2024_03_04` seed assigned it to the notebook profile, which a later migration dropped. | Medium | Implement `renderUI()`. Add a test that loads every class in the registry. |

### Platform, code and UI

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 15 | **Dead code and dead tables.** `GoodsReceiptController`, `GoodsIssueController`, `GoodsIssueService`, the `receipts/create` and `issues/create` views and the `GoodsReceipt` and `StockAdjustment` models are unused. So are the tables `gov_goods_receipts`, `gov_stock_adjustments` and their item tables. `RequireProgrammeTrackingCapability` does nothing. Three reports in the package root (1,269 lines) describe earlier states; one names a `Resources/` folder casing problem that has since been fixed. | Medium | Delete the code. Drop the unused tables after gap 2's migration. Move the reports to `docs/` or delete them. |
| 16 | **Migrations are destructive.** `gov_profiles` and `gov_profile_capabilities` are created in `2024_03_02`, then dropped and recreated in both `2024_03_04` and `2024_03_08`. `2024_03_04` also drops `gov_capabilities` and `gov_requirement_definitions` from `2024_03_02`, so the data seeded with them is lost. | Medium | Squash the migrations into one additive migration per table, with a separate seeder. |
| 17 | **The ledger and posting have no tests.** `G1AuthorizationTest` covers routes, state locks, office checks and one Ready-state validation test. Nothing tests balance maths, the posting pipeline, asset creation, rule resolution, publishing or system issues, which is how gaps 1, 2, 12 and 14 got through. | Medium | Add the test suite listed below and run it in CI. |
| 18 | **Search and pages do not scale.** For each type on each search, `ProductResolver` runs `Schema::hasColumn` twice, and counts assets for every asset model it returns. `searchProducts` then runs one more query per result to get `category_id`, at up to 50 results per type. The stock register loads every item with no pagination. The compiled-rules cache is static and never cleared, which goes stale in long-running workers. | Medium | Cache the column lists, aggregate the asset counts, and return `category_id` from the main query. Paginate the register. Use a cache keyed on the policy version. |
| 19 | **The kardex tab never appears.** `InjectStoreOperationsUi` is never registered as middleware, so the stock-card tab registered by `registerKardexTabs()` never shows on item pages. The `menu-injection` hook it would render links to `storeops.receipts.create` and `storeops.issues.create`, which don't exist, so the hook would crash if rendered. | Medium | Render the tab through a view composer on the item pages and delete the hook, or delete all three pieces. |
| 20 | **Hard-coded English and UI issues.** The language files match (67 keys each), but about 70 view lines are literal English: purchase types, reference labels and placeholders. Menu titles (“Store Documents Hub”, “Product Rules Studio”), the capability dictionary and the exception messages are English too. Views depend on tenant-scope's `tenantops::access.*` keys, and 10 views contain inline scripts. The grid is not usable on store-room tablets. | Medium | Move the text into `storeops::storeops`, bundle the scripts, and give the grid a responsive layout. |
| 21 | **Undeclared dependencies and fragile seeds.** `composer.json` has `"require": {}`, but the code depends on tenant-scope and, optionally, tracking. `SnipeCategoryObserver` finds the default rules by name (“System Default … Standard”), so renaming them silently stops new categories from getting rules. `DocumentNumberService` reads the latest number without a lock, so two drafts started at the same moment collide on the unique index. | Low | Declare the dependencies. Find default rules by a fixed key. Generate numbers from a locked sequence row. |

## Mitigation plan

The plan runs in four phases. Phase 0 makes the ledger trustworthy before more offices go live; the rest follows by risk. Each phase finishes with its tests green in CI. Posting workflow, reversal and committee features are outside the plan (see [Design decisions](#design-decisions)).

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
| 1.2 | 12, 13 | Move assignments to the new version on publish, resolve only `PUBLISHED` profiles, validate assignment input, add company-level assignment, and require a change reason. |
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

### Later: when the committee package arrives

- Add committee verification before posting, along with the `READY` state, if the committee design calls for it.
- Add committee references on receipts.
- Let signed committee reports be filed against posted documents.

### Data repair (with Phase 0)

The bugs above have already written bad data. Run these steps once, after a backup and with posting paused. They change only ledger rows and the copy of system issues; no posted GRN is altered or reversed.

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

- Ledger: IN and OUT arithmetic; a negative balance is rejected; concurrent posts (row lock); balances per item and office; the type key is normalised; the repair command recomputes every chain.
- Opening balance: the cut-over command posts on-hand stock without changing Snipe-IT; posting is blocked until an opening document exists.
- Posting pipeline: stockable lines always reach the ledger; capabilities run only for their document types; a hardware receipt creates assets and a ledger entry; Snipe-IT `qty` is updated exactly once.
- Documents: a posted GRN cannot be modified or reversed; an adjustment corrects stock and references the original; a draft can be voided; the tracking check fails closed.
- Rules: the resolution order (global → company → office → category → model); publishing moves assignments; draft and archived profiles cannot be assigned; every registry class loads.
- System issues: a request fulfillment creates a `gov_documents` issue, one balance chain and a unique number.
