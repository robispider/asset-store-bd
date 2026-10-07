# custom-requests Gap Analysis and Mitigation Plan

Oct 4, 2026 · @zahid

> Execution update, 4 October 2026: the package has been refactored and verified locally. See the [implementation, verification and remaining operational work](../verification/custom-requests-refactor.md). The findings and plan below describe the reviewed baseline; they are preserved as historical evidence. Historical repair and the G1 access rollout remain separate work.

## Summary

The custom-requests package has 27 gaps: 1 critical, 8 high, 16 medium and 2 low. The [package-wide review](gov-store-package-gap-analysis.md) found 6 gaps here. This deeper pass found 21 more and corrects two of the earlier ones. The biggest risks are in four places:

- **Routing.** The office a request goes to depends on an optional field that is blank by default. Most requests therefore never reach an approver or storekeeper.
- **Segregation of duties.** Approvers can approve their own requests. One person can also complete both levels of a two-level approval.
- **Stock accuracy.** Issuing consumables lowers Snipe-IT's remaining stock twice. Issuing accessories fails outright.
- **Closure.** A request issued over more than one session never reaches the `issued` state.

| Area | Critical | High | Medium | Low | Top gap |
| --- | --- | --- | --- | --- | --- |
| Routing and approval | 1 | 4 | 1 | 0 | Requests are routed by an optional delivery location that is blank by default |
| Fulfillment and stock | 0 | 4 | 4 | 0 | Bulk issue lowers Snipe-IT stock twice |
| Requester experience | 0 | 0 | 4 | 0 | Requesters cannot withdraw a request or confirm receipt |
| Data, platform and UI | 0 | 0 | 7 | 2 | Audit trail is deleted when a user is purged |
| **Total** | **1** | **8** | **16** | **2** | |

Source: static review of `packages/gov-store/custom-requests` at commit `aab8e8e17a`. The application was not run. Cross-checks were made against Snipe-IT core models (`app/Models`) and against store-operations, office-membership and tenant-scope. Where a gap depends on runtime behaviour, the evidence column says so.

## What the package does

Employees browse a catalog of asset models, accessories, consumables, components and licences, and add items to a draft basket. On submission, the basket is split into one service request per approval policy (`AUTO_APPROVE`, `PRIMARY_ONLY` or `PRIMARY_AND_FINAL`). Approvers decide each line, and storekeepers then issue the stock:

- bulk lines go through the store-operations ledger (a Goods Issue document);
- serialized assets are checked out directly in Snipe-IT.

A fulfillment register lists completed requests together with their Goods Issue documents. The package is about 4,250 lines: 2,350 of PHP and 1,900 of Blade. It also has two language files with 163 keys each.

```
Catalog ─► Basket ─► submitBasket() ─┬─ AUTO_APPROVE ───────────────► approved
                                     ├─ PRIMARY_ONLY ─► pending_primary ─► approved / partially_approved / rejected
                                     └─ PRIMARY_AND_FINAL ─► pending_primary ─► pending_final ─► approved …
approved ─► Fulfillment queue ─► issueItems() ─┬─ bulk ─► SystemGoodsIssueService (GI + ledger OUT) + Snipe-IT checkout row
                                               └─ asset_model ─► direct Asset update + logCheckout (no GI)
                                 ─► issued / partially_issued / closed (force close)
```

## Changes since the package-wide review

| Earlier gap | Status at `aab8e8e17a` |
| --- | --- |
| 1. Legacy `ItemRequest` path runs beside the basket (High) | **Revised to Medium (gap 19).** The path does not run. Its table, `custom_item_requests`, is dropped by migration `2024_01_02` in `up()`, so every `POST /gov-requests/submit` fails. The `/admin` routes `approve` and `reject` are gone, but `/admin` is still declared twice. |
| 2. `store` builds a class name from user input (High) | **Folded into gap 19.** The string is only saved to a column that no longer exists; it is never instantiated. Deleting the legacy path removes it. |
| 3. Approval fixed at two levels, no thresholds, delegation or notifications (High) | **Open (gap 5).** |
| 4. No component adapter (Medium) | **Corrected and raised to High (gap 10).** Components *can* be requested: the catalog lists them, and licences too. They fail only at fulfillment, after approval. |
| 5. Requesters cannot withdraw (Medium) | **Open (gap 15).** |
| 6. Basket and request controllers lack permission checks (Low) | **Closed.** All 20 routes declare `gov.can:` middleware, and approval and fulfillment actions return 404 across offices. `tests/Feature/GovStore/G1AuthorizationTest.php` covers both. |

## Gaps

### Routing and approval

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | **Requests are routed by an optional, free-choice delivery location.** The basket form defaults to “no location” (`basket/index.blade.php:124-127`), and `delivery_location_id` is nullable and validated only as `integer`. The approval, fulfillment and register queues show non-superusers only requests whose `delivery_location_id` is their working office, so a request submitted with the default never reaches an approver or storekeeper. `NoPendingRequestsRule` misses it too. Requesters can also pick any listed office. Meanwhile, the check that approvers exist and the self-approval check use the requester's Snipe-IT `location_id` (`BasketService:95,107,118`), not the office the request is routed to. | Critical | Set a non-null `office_id` on the server at submit, taken from `TenantContext` (the working office). Route all queues and checks by that field. Keep delivery location as a separate optional field validated with `exists:locations,id` against the user's jurisdiction. Backfill existing rows (see Data repair). |
| 2 | **Approvers can approve their own requests.** If the requester holds `primary_approver`, `PRIMARY_ONLY` baskets are approved at submit. If they hold `final_approver`, `PRIMARY_AND_FINAL` baskets are approved at submit (`BasketService:129-137`). The same shortcut fires again at primary review (`ApprovalService:67-79`). `processDecision()` never checks that the decider is not the requester, so an approver can also open their own pending request and approve it. | High | Never let a user decide their own request. If the requester is the only approver at that level, send the request to the next level or to the parent office's approver. Record the reason in an event. |
| 3 | **Two-level approval is not enforced by role.** `requests.approve` is granted to both `primary_approver` and `final_approver` (`tenant-scope/config/abilities.php:14`). `ApprovalService` never checks which stage the decider is entitled to, and does not stop the primary approver from also giving the final decision. One person can move a `PRIMARY_AND_FINAL` request to `approved` with two submissions. | High | Split the ability into `requests.approve.primary` and `requests.approve.final`, and check it per stage in the service. Store `primary_decided_by` and refuse a final decision from the same user. |
| 4 | **Category policies never apply to hardware.** The catalog adds hardware as `asset_model`, but `PolicyService::getCategoryId()` handles only `asset`, `accessory` and `consumable`. Every hardware line, components and licences fall back to `PRIMARY_ONLY`, so a laptop category marked `PRIMARY_AND_FINAL` gets one approval. There is no screen for item-level overrides, and the policy screen is linked only from the unused `menu-injection` hook (gap 19). | High | Add `asset_model`, `component` and `license` cases, add a menu entry for policies, and add an item-level override on the policy screen. Add a unit test per type. |
| 5 | **No notifications, delegation, escalation or value thresholds.** Nothing notifies anyone on submit, decision, issue or close. `assigned_approver_id` is always written as `null`. The delegate columns in `gov_location_roles` are unused, and there is no timer or escalation for requests left pending. Policy is chosen by category only, never by quantity or value. | High | Send mail and in-app notifications at each transition. Resolve delegates with start and end dates from organization. Add an escalation job (for example, after N working days). Add threshold rules (value or quantity) to `PolicyService`. |
| 6 | **The primary approver can approve more than was requested.** Only the HTML `max` attribute limits the quantity. `processDecision()` caps the quantity at the final stage (`min($qty, approved_qty)`) but not at the primary stage, and any status other than `approved` counts as a rejection. | Medium | Validate the `items.*.status` and `items.*.qty` payload. Cap the approved quantity at `requested_qty`, and require a decision for every line. |

### Fulfillment and stock

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 7 | **Bulk issue lowers Snipe-IT stock twice.** For consumables, the ledger's OUT movement lowers `consumables.qty` (`UpdateSnipeQuantity`, then `ConsumableAdapter::decrementQuantity`). Then `custom-requests` `ConsumableAdapter::checkout()` also adds a `consumables_users` row, and Snipe-IT subtracts that row again in `numRemaining()` (`qty − checked out`). The adapter also adds **one** row whatever the quantity. Issuing 5 therefore lowers the ledger by 5 and Snipe-IT's remaining stock by 6. | High | Choose one source of truth. Recommended: the ledger owns `qty`, and the request flow writes only an action-log entry, with no checkout rows. Otherwise, write one checkout row per unit and stop the ledger from touching `qty`. Add a reconciliation report that compares ledger balance with Snipe-IT remaining stock. |
| 8 | **Issuing accessories fails.** `AccessoryAdapter::checkout()` attaches through `Accessory::users()`, which Snipe-IT defines as `belongsToMany(AccessoryCheckout::class, 'accessories_checkout')`. The insert targets an `accessory_checkout_id` column that the table does not have, and `assigned_type` is never set. This review read the code but did not run it; the expected result is an unknown-column error that rolls back the whole issue, Goods Issue included. | High | Create `AccessoryCheckout` rows as `AccessoryCheckoutController` does, one per unit, with `assigned_type` and `created_by` set, or leave stock to the ledger as in gap 7. Add a feature test. |
| 9 | **Requests issued over several sessions never close as `issued`.** `$completedLinesCount` counts only lines finished in the *current* submission (`FulfillmentService:32-33, 116-118, 178-180, 185`). For example, if line A is fully issued today and line B tomorrow, the second run counts 1 of 2. The request stays `partially_issued` in the queue until someone force-closes it, and the register then shows `closed` rather than `issued`. | High | After the loop, recount from the database: every approved line must have `issued_qty >= approved_qty`. Add a test that issues across two calls. |
| 10 | **The catalog offers items that cannot be fulfilled, and misstates stock.** Components and licences are listed and can be added, submitted and approved, but `RequestableFactory` has no adapter for them, so fulfillment throws “unsupported type”. Availability reads `users_count`, `assets_count` and `assigned_seats_count`, which are never loaded (there is no `withCount`). They come back `null`, so the full quantity is shown. | High | Until adapters exist, hide components and licences, or build a `ComponentAdapter` and a `LicenseAdapter` (licence seats). Compute availability with each model's `numRemaining()` (or `withCount`), net of open demand (gap 12). |
| 11 | **Serialized asset issue bypasses controls.** `Asset::find($assetId)` is not checked against the line's `model_id`, a deployable status or the `requestable` flag. A tampered form can therefore issue any asset in the office. No row lock is taken (`lockForUpdate`), so two storekeepers can assign the same asset. Writing `assigned_to` directly skips Snipe-IT's checkout path, so no acceptance or EULA and no checkout notifications. No Goods Issue is created, so hardware issues are missing from the ledger and the register (the gap is acknowledged in `FulfillmentRegisterController:53`). | Medium | Validate each asset against the line's model, office and deployable status. Lock the rows. Check out through Snipe-IT's checkout action so acceptance and events fire. Record a Goods Issue line for each serial. |
| 12 | **Approval does not reserve stock.** `reserved_qty` exists but is never written. Approved requests do not hold stock, so two approved requests can compete for the last units. The catalog and basket accept quantities above the stock on hand. | Medium | Reserve stock at approval and release it on issue, rejection or close. Warn when a requested quantity is above the available quantity. |
| 13 | **Substitutions are unchecked.** A storekeeper can substitute any item of the same type, with no approval and no category or value check. The event records the literal string `'Original Item'` instead of the original item's name. Hardware substitution cannot work, because `/catalog/search` has no `asset_model` branch. | Medium | Limit substitutions to the same category, and require approver confirmation above a set value. Record both item names and ids. Add `asset_model` to the search. |
| 14 | **Force close has no guards.** `forceClose()` will close a request that is still awaiting approval or is already closed. The reason is required only in the HTML. The method overwrites `approval_status` with `closed`, which erases `partially_approved`. | Medium | Allow force close only from approved or partially issued states. Require a reason on the server. Leave `approval_status` alone and set only `fulfillment_status`. |

### Requester experience

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 15 | **Requesters cannot withdraw a request or confirm receipt.** No action cancels a submitted request. `NoPendingRequestsRule` looks for a `cancelled` status, but nothing ever sets it. The requester never acknowledges receipt, and there is no return or check-in path back to the store. | Medium | Add a withdraw action while the request awaits a decision, setting status `cancelled`. Add a receipt acknowledgement after issue, which can reuse Snipe-IT acceptance. Add a return request that creates a receipt document in store-operations. |
| 16 | **Collected fields are dropped.** `required_by_date` is validated but never saved by `submitBasket()`. The `cost_center` column is never captured. `request_type` is a free string, not checked against the 7 options the form offers. | Medium | Save `required_by_date` and capture `cost_center`. Validate `request_type` with `in:` against a config list. |
| 17 | **The basket accepts any type string and any quantity.** `add()` stores `strtolower(class_basename(item_type))` without an allow-list or an existence check. A type outside the morph map (for example `user`) breaks that user's basket page when `morphTo` loads the item. `expires_at` is set but never enforced. | Medium | Allow only the types `RequestableFactory` supports, check that the item exists, and cap the quantity. Add a scheduled job that purges expired baskets. |
| 18 | **Status values are used inconsistently.** The basket creates `pending_primary` and `pending_final`, but the catalog tiles count `submitted` and `under_review`, so the “pending” tile always shows 0. `show()` moves a request from `submitted` to `under_review`, but no request is ever `submitted`, so that never happens. `approval_status` is also used to hold `closed`. A rejection does not set `approved_by`, so the approver's “processed” list leaves rejections out. | Medium | Define status enums for approval and fulfillment, add a `decided_by` column, and update the counters, filters and views. |

### Data, platform and UI

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 19 | **The dead legacy path is still routed.** `POST /gov-requests/submit` writes `ItemRequest` rows to `custom_item_requests`, which migration `2024_01_02` drops, so every call fails. No view posts to it. Related dead code: `ItemApproved` is never dispatched, so `ProcessItemCheckout` never runs. `Models/LocationRole.php` is a 0-byte file. `admin/locations.blade.php` links to a route that does not exist. `hooks/menu-injection.blade.php` is never rendered. `/admin` is declared twice in `routes/web.php`. | Medium | Delete the route, `RequestService`, `ItemRequest`, the event, the listener, the empty model, the two dead views and the duplicate route. Drop `custom_item_requests` in a new migration. |
| 20 | **Migrations are destructive and overlap.** `2024_01_02` drops 6 tables in `up()`, including the one `2024_01_01` creates. `2024_01_03` creates `gov_location_roles`, which organization drops and recreates and which this package no longer uses. `2024_01_04` drops the basket tables in `up()`. | Medium | Squash into one additive migration per table, and leave `gov_location_roles` to organization. |
| 21 | **The audit trail is deleted when a user is purged.** `requested_by` and the event table's `user_id` use `onDelete('cascade')`. Purging a deleted user therefore removes their requests, plus every event they wrote on other people's requests: approvals, issues and closures. `delivery_location_id` has no foreign key. Event messages are stored as English text. | Medium | Change these to `restrict` (or `set null` plus a stored name). Add a foreign key for the office. Store translation keys and parameters in `details`, not text. |
| 22 | **Request numbers can collide.** The `creating` hook reads the year's latest row and adds 1. Two concurrent submissions can generate the same number and hit the unique index. The `SoftDeletes` scope also hides a deleted latest row, so its number is reused and collides. | Medium | Use store-operations' `DocumentNumberService` or a sequence table with `lockForUpdate`, and include deleted rows. |
| 23 | **The workflow logic has no tests.** Only `G1AuthorizationTest` touches this package, and it covers route permissions and 404s across offices. Nothing tests basket splitting, policy resolution, approval stages, issuing or closure. Gaps 7, 8 and 9 got through as a result. | Medium | Add feature tests for each service, run in CI (see the test list below). |
| 24 | **The basket widget runs on every page and shows the wrong count.** `InjectGovStoreUi` rewrites `</body>` in every HTML response and runs a database query per page. That query reads `Request` rows with `approval_status = 'draft'`, but baskets are `DraftBasket` rows, so the count always starts at 0. | Medium | Render the widget through a view composer on layout pages only, and count `DraftBasket` items. |
| 25 | **The catalog does not scale.** `CatalogService` loads every asset model, accessory, consumable, component and licence, then runs one count query per asset model (N+1). The 437-line view filters on the client, with no pagination. | Medium | Use `withCount` and aggregate queries, paginate on the server, and filter by category or search in the query. |
| 26 | **Hard-coded English and minor UI issues.** The two language files match (163 keys each), but about 32 view lines are still literal English: table headers, request-type options and confirm dialogs. So are the 5 menu titles and the `FulfillmentService` exception messages. There are 7 inline `<script>` blocks, and the CSRF token is reused as the script nonce. | Low | Move the text into `requestlabels::requests`, bundle the JavaScript, and generate a real per-request nonce. |
| 27 | **Dependencies are undeclared.** `composer.json` has `"require": {}`, but the code imports office-membership (`OfficeResponsibility`), store-operations (`GoodsIssue`, `StockIssuingServiceInterface`) and tenant-scope. Office-membership imports this package in return. | Low | Declare the dependencies. Let custom-requests register its own clearance rule (see the office-membership gap in the package-wide review). |

## Mitigation plan

The plan runs in four phases, ordered by risk. Phase 0 stops new bad data and closes the segregation-of-duties holes. Each phase finishes with its tests green in CI.

### Phase 0: Stop the damage (week 1)

| Step | Gaps | Work | Done when |
| --- | --- | --- | --- |
| 0.1 | 1 | Add a non-null `office_id` set from `TenantContext` at submit. Switch the approval, fulfillment, register and clearance queries and the approver checks to it. Validate `delivery_location_id` with `exists`. | A request submitted with the default form appears in the working office's approval queue |
| 0.2 | 2, 3 | Block deciding on your own request. Split approval into primary and final abilities and check them per stage. Store `primary_decided_by` and refuse the same user at final. | Tests: a requester-approver cannot approve; a primary approver cannot give the final decision |
| 0.3 | 7, 8 | Make the ledger the only thing that changes stock, and remove the checkout rows from the bulk path. Replace accessory `attach()` with an action-log entry, or with native `AccessoryCheckout` rows if the ledger is not used. | Issuing 5 consumables lowers Snipe-IT remaining stock by exactly 5; an accessory issue succeeds |
| 0.4 | 9 | Recount line completion from the database after each issue. | A request issued in two sessions ends `issued` |
| 0.5 | — | Run the data repair below on the live database. | The reconciliation report shows no difference |

### Phase 1: Make the workflow correct (weeks 2–3)

| Step | Gaps | Work |
| --- | --- | --- |
| 1.1 | 4, 10 | Resolve policies for `asset_model`, `component` and `license`. Hide or build adapters for components and licences. Fix availability with `numRemaining()`. Add a menu link to the policy screen. |
| 1.2 | 6, 17 | Validate the approval payload and cap quantities; add a type allow-list, an existence check and a quantity cap to the basket. |
| 1.3 | 11, 12 | Validate and lock assets, and check them out through Snipe-IT's checkout action. Write Goods Issue lines for serials. Reserve stock at approval. |
| 1.4 | 13, 14 | Add the substitution category rule and record original names. Add state and reason guards to force close. |
| 1.5 | 16, 18 | Save `required_by_date` and `cost_center`; add status enums and `decided_by`; fix the counters. |
| 1.6 | 22 | Generate request numbers from a locked sequence. |
| 1.7 | 23 | Build the test suite below and add it to CI. |

### Phase 2: Complete the process (weeks 4–6)

| Step | Gaps | Work |
| --- | --- | --- |
| 2.1 | 5 | Notifications at each transition, delegate resolution with start and end dates, an escalation job, and value or quantity thresholds in policies. |
| 2.2 | 15 | Requester withdraw, receipt acknowledgement, and a return-to-store flow linked to store-operations receipts. |
| 2.3 | 24, 25 | Render the basket widget through a view composer, paginate the catalog on the server, and remove the N+1 count. |

### Phase 3: Clean up (alongside Phase 2)

| Step | Gaps | Work |
| --- | --- | --- |
| 3.1 | 19 | Delete the legacy path, the dead views, the empty model and the duplicate route; drop `custom_item_requests`. |
| 3.2 | 20, 21 | Squash the migrations into additive ones. Change the cascade deletes to `restrict`. Add the office foreign key. Store events as keys. |
| 3.3 | 26, 27 | Finish moving text into language files, bundle the scripts, generate a real nonce, and declare the composer dependencies. |

### Data repair (with Phase 0)

The bugs above have already written bad data. Run these steps once, after the fixes are deployed and after a backup:

1. **Orphaned requests (gap 1).** Find rows with `delivery_location_id IS NULL`. Set `office_id` to the requester's office at the time, taken from office-membership history if available, or else from `users.location_id`. List them for each office's approvers.
2. **Double-counted consumables (gap 7).** Rows written by the request flow carry the note prefix `Logged in Goods Issue:`. Count them per item:
   ```sql
   SELECT consumable_id, COUNT(*) AS extra_rows
   FROM consumables_users
   WHERE note LIKE 'Logged in Goods Issue:%'
   GROUP BY consumable_id;
   ```
   The ledger has already lowered `qty` for these issues, so archive these rows and then delete them. Re-run the reconciliation report.
3. **Requests stuck as `partially_issued` (gap 9).** Mark `issued` any request where every approved line has `issued_qty >= approved_qty`, and write a `closed` event noting the repair.
4. **Self-approved requests (gap 2).** List requests where the approver or auto-approval matches the requester and the policy was not `AUTO_APPROVE`, and send the list to office admins for review.

### Tests to add

- Basket: add, merge and update quantities; rejected types; split by policy; empty basket; requester without an office.
- Policy: item override, then category, then default, for each requestable type.
- Approval: primary only; primary then final; a requester who is an approver; the same user at both stages; partial rejection; quantity cap.
- Fulfillment: a consumable issue changes stock once; accessory issue; serial validation and locking; issuing across two calls closes the request; substitution rules; force-close guards.
- Scope: queues per office; request numbers are unique under concurrent submission.
