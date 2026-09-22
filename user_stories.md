# User Stories and UI Verification Guide — Asset Store BD

> **Purpose.** This document is a functional-testing guide for the current
> codebase. Every story includes browser interactions and observable results.
> URLs below are the implemented route paths; the Gov-Store menu may expose
> the same pages after login.
>
> **Status vocabulary**
>
> - **Implemented** — the route and the main UI action exist in this checkout.
> - **Partially implemented** — some supporting actions exist, but the complete
>   business flow described by the original story is not present.
> - **Core Snipe-IT** — delivered by the upstream Snipe-IT UI rather than a
>   Gov-Store package.
>
> Companion references: [workflow.md](workflow.md), [business_logic.md](business_logic.md),
> and [ERD.md](ERD.md).

## Test setup

Use a browser session with at least one account for each role under test:

| Test account | Required setup |
|---|---|
| Superadmin | Snipe-IT superuser/admin access |
| Company or ministry admin | Company-admin assignment in Gov-Store |
| ICT officer | ICT jurisdiction assignment |
| Office admin | Assigned to an office profile |
| Storekeeper | Storekeeper responsibility at an office |
| Approver | Approver responsibility/policy at an office |
| Employee | Authenticated user with an active office membership |

Create two offices (Office A and Office B), at least one product/category,
one employee, and one storekeeper where a story needs isolation or stock
movement. Use the browser network panel only when a story explicitly calls
for an AJAX result; the normal pass/fail evidence should be the page,
redirect, flash message, table row, or changed Snipe-IT record.

## Table of contents

- [US-01 — Configure geography and the ministry directory](#us-01--configure-geography-and-the-ministry-directory)
- [US-02 — Provision a government office](#us-02--provision-a-government-office)
- [US-03 — Configure an office and manage staff](#us-03--configure-an-office-and-manage-staff)
- [US-04 — Onboard an existing user](#us-04--onboard-an-existing-user)
- [US-05 — Work inside the active office context](#us-05--work-inside-the-active-office-context)
- [US-06 — Receive stock with a receipt document](#us-06--receive-stock-with-a-receipt-document)
- [US-07 — Request items from the catalogue](#us-07--request-items-from-the-catalogue)
- [US-08 — Approve or reject a request](#us-08--approve-or-reject-a-request)
- [US-09 — Fulfil an approved request](#us-09--fulfil-an-approved-request)
- [US-10 — Issue stock directly with a goods issue](#us-10--issue-stock-directly-with-a-goods-issue)
- [US-11 — Check an asset in](#us-11--check-an-asset-in)
- [US-12 — Manage membership release and office switching](#us-12--manage-membership-release-and-office-switching)
- [US-13 — Adopt a catalogue category for an office](#us-13--adopt-a-catalogue-category-for-an-office)
- [US-14 — Configure product rules](#us-14--configure-product-rules)
- [US-15 — Assign or hand over an office duty](#us-15--assign-or-hand-over-an-office-duty)
- [US-16 — Perform a superadmin membership override](#us-16--perform-a-superadmin-membership-override)
- [US-17 — Verify office and ministry isolation](#us-17--verify-office-and-ministry-isolation)
- [US-18 — View the stock register and kardex](#us-18--view-the-stock-register-and-kardex)
- [US-19 — Record asset maintenance](#us-19--record-asset-maintenance)
- [US-20 — Import inventory data in bulk](#us-20--import-inventory-data-in-bulk)
- [US-21 — Manage programmes and funding sources](#us-21--manage-programmes-and-funding-sources)
- [US-22 — Create and operate tracking-code tasks](#us-22--create-and-operate-tracking-code-tasks)
- [US-23 — Import and review the master catalogue](#us-23--import-and-review-the-master-catalogue)
- [US-24 — Govern mappings and curated catalogue collections](#us-24--govern-mappings-and-curated-catalogue-collections)
- [US-25 — Configure tenant-scope policies and mappings](#us-25--configure-tenant-scope-policies-and-mappings)
- [US-26 — Monitor and repair metadata health](#us-26--monitor-and-repair-metadata-health)
- [US-27 — Delegate ministry/company administrators](#us-27--delegate-ministrycompany-administrators)
- [Appendix A — Route inventory used by testers](#appendix-a--route-inventory-used-by-testers)
- [Appendix B — Known gaps and out-of-scope claims](#appendix-b--known-gaps-and-out-of-scope-claims)

---

## US-01 — Configure geography and the ministry directory

**As a superadmin**, I want the ministry directory and geographic search to be
available so that offices can be created with official organizational data.

**Status:** Implemented.

**UI verification**

1. Sign in as Superadmin and open `/directory`.
2. Confirm the directory page renders an import form.
3. Upload the supported ministry CSV and submit **Import**.
4. Confirm a success or validation message appears, then refresh and verify
   the imported ministry/company is listed in the directory.
5. Open `/gov-store/admin/organization/create`.
6. Click the geographic-area field and type a known English or Bangla area.
   Confirm the dropdown loads matching results instead of a full unfiltered
   list.
7. Select a result and continue to the office form.

**Expected result:** the import result is visible to the tester, and the
geographic selector returns searchable areas through
`/gov-store/api/geo/search` or the provisioning geo-search endpoint.

**Do not infer:** this story does not prove that a particular CSV is shipped
with the repository; test data must be supplied by the environment.

**Code surfaces:** `organization` directory and provisioning controllers;
`geo-areas` search route.

## US-02 — Provision a government office

**As an ICT officer or authorized administrator**, I want to register an office
with a ministry and geographic area.

**Status:** Implemented.

**UI verification**

1. Sign in as an ICT officer whose jurisdiction includes the test area.
2. Open `/gov-store/admin/organization/create`.
3. Enter the Bangla and English office names.
4. Use the geo-area search and select an in-scope area.
5. Select a ministry/company and submit **Create/Provision Office**.
6. Confirm the browser returns to the organization registry or shows a
   success message.
7. Open `/gov-store/admin/organization` and confirm the office appears.
8. Open the office hub at `/gov-store/admin/organization/{id}/hub` and verify
   the office profile and readiness checklist are displayed.
9. Negative test: repeat with an area outside the officer's jurisdiction.
   Confirm validation prevents creation and displays an error; do not accept
   a successful redirect as a pass.

**Expected result:** one office/location is visible in the registry, with its
geographic and ministry information retained. An out-of-boundary selection is
rejected.

## US-03 — Configure an office and manage staff

**As an office admin**, I want to complete the office checklist and manage
staff membership.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/admin/organization/{id}/hub` as an authorized admin.
2. Confirm the readiness checklist shows the office admin, approver,
   storekeeper, and staff conditions.
3. Use the hub's edit controls and submit the office details.
4. Use **Save Roles** and confirm the selected duty assignments remain after
   a refresh.
5. Open `/gov-store/office/staff`.
6. Click **Generate Invite Code** and confirm a code/result is displayed.
7. Add or approve a staff member using the available action. Confirm the
   member appears in the active staff list.
8. Repeat with **Reject** where a pending membership exists and confirm the
   pending row is removed or marked rejected.

**Expected result:** saved office configuration and staff actions are visible
after reload; the hub checklist reflects the saved state.

## US-04 — Onboard an existing user

**As an ICT officer or authorized administrator**, I want to assign an
unassigned Snipe-IT user to an office.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/admin/onboard`.
2. Confirm users waiting for an office are listed.
3. Select a user and an eligible office/location.
4. Submit **Assign**.
5. Confirm a success message appears and the user leaves the waiting queue.
6. Sign in as that user, or refresh their existing session, and confirm the
   office context is available.

**Expected result:** the user has an office membership and the onboarding
queue no longer treats the assignment as pending.

## US-05 — Work inside the active office context

**As an employee**, I want normal pages to show my active office's data.

**Status:** Implemented, subject to tenant test data.

**UI verification**

1. Sign in as an employee assigned to Office A.
2. Open the Gov-Store catalogue and the Snipe-IT assets page.
3. Record one visible Office A item and confirm Office B items are absent.
4. Open `/gov-store/my-memberships`; confirm the active membership is shown.
5. If the user has more than one active membership, use the **Switch**
   action, submit it, reload the catalogue, and confirm the visible office
   changes.
6. Sign out and sign in as a different user on the same browser. Confirm the
   previous user's working-office selection is not reused.

**Expected result:** page data follows the authenticated user's active
membership and changes after an explicit membership switch.

## US-06 — Receive stock with a receipt document

**As a storekeeper**, I want to create a receipt so that stock enters the
office register.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/operations/hub` as a storekeeper.
2. Start a new receipt using the hub's **New Receipt** action. Confirm a
   draft receipt workspace opens.
3. Enter the header/reference fields and add a product through the product
   search grid.
4. Enter a positive quantity. For a serialized product, enter each required
   serial number and confirm the validation/progress indicator responds.
5. Save the draft, reload the workspace, and confirm the entered lines remain.
6. Submit **Post**. For invalid or incomplete lines, confirm the page stays
   open and shows validation errors.
7. For a valid receipt, confirm the document is marked **POSTED**.
8. Use **Preview** or **Print** and confirm the printable document opens.
9. Open `/gov-store/operations/register` and verify the quantity/movement is
   reflected.

**Expected result:** a posted receipt increases the office's stock register;
serialized receipt lines create or register the corresponding Snipe-IT assets.

## US-07 — Request items from the catalogue

**As an employee**, I want to request available items using a basket.

**Status:** Implemented.

**UI verification**

1. Open `/gov-requests/catalog`.
2. Confirm requestable products are listed with an availability/quantity
   indication where provided.
3. Click **Add to Basket** for one item and open `/gov-requests/basket`.
4. Change the quantity with the basket update control and confirm the row
   updates.
5. Remove an item and confirm it disappears.
6. Add an item again and click **Submit Request**.
7. Open `/gov-requests/my-requests` and confirm a new request with the
   submitted line and an initial pending status is visible.

**Expected result:** basket changes are visible immediately and submission
creates a request associated with the current user and office.

## US-08 — Approve or reject a request

**As an approver**, I want to review request lines and make a decision.

**Status:** Implemented.

**UI verification**

1. Create a pending request using US-07.
2. Sign in as an approver and open `/gov-requests/admin`.
3. Open the request detail page (`/gov-requests/admin/{id}`).
4. Confirm requester, lines, quantities, and available decision controls are
   displayed.
5. Approve one line or the request using the visible **Approve/Process**
   action. Confirm a success message and an approved status.
6. Repeat with a separate request and **Reject**, supplying the reason if the
   form requires one. Confirm the rejected status and reason are displayed.
7. Return to the employee's **My Requests** page and confirm the status is
   updated there too.

**Expected result:** approval decisions are visible to both the approver and
the requester; rejected lines cannot enter the fulfillment queue.

## US-09 — Fulfil an approved request

**As a storekeeper**, I want to issue approved lines to the requester.

**Status:** Implemented.

**UI verification**

1. Approve a request with US-08.
2. Open `/gov-requests/fulfillment` as a storekeeper.
3. Confirm the request appears in the fulfillment queue.
4. Open `/gov-requests/fulfillment/{id}` and review each approved line.
5. For an asset line, select the physical asset/serial number. For a bulk
   line, enter the quantity to issue.
6. Click **Complete Issue** and confirm the confirmation prompt.
7. Confirm the line and parent request show issued/closed or partially-issued
   status as appropriate.
8. Open `/gov-requests/fulfillment-register` and the request detail. Confirm
   the fulfillment and any goods-issue reference are listed.
9. Reopen the stock register/kardex and confirm the issue movement is present.

**Expected result:** an issued asset is assigned to the requester, bulk stock
is reduced, and the request is represented in the fulfillment register.

## US-10 — Issue stock directly with a goods issue

**As a storekeeper**, I want to issue consumables, accessories, or components
without first receiving a service request.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/operations/hub`.
2. Start a new issue document using **New Issue**.
3. Add a product and enter a quantity within available stock.
4. Save the draft, reload it, then click **Post**.
5. Confirm the status changes to **POSTED** and use **Print**.
6. Repeat with a quantity greater than the available balance. Confirm posting
   is rejected, the draft remains editable, and no partial success is shown.
7. Verify the stock register and Snipe-IT quantity/history reflect a valid
   issue.

**Expected result:** valid OUT movements reduce stock atomically; an
insufficient-stock document is not posted.

## US-11 — Check an asset in

**As a storekeeper**, I want to return an assigned asset to stock.

**Status:** Core Snipe-IT.

**UI verification**

1. Sign in as a storekeeper and open the assigned asset from the hardware
   list or scan/open its asset URL.
2. Click **Check In** on the asset page.
3. Select the resulting status/location options and enter an optional note.
4. Submit the form.
5. Confirm the asset page shows no assigned user and shows its return/default
   location.
6. Open the asset **History** tab and confirm a check-in event is present.
7. Negative test: sign in as an employee and confirm the check-in control is
   unavailable or the submission returns an authorization error.

**Expected result:** the asset is unassigned, history is updated, and an
unauthorized employee cannot mutate the asset.

## US-12 — Manage membership release and office switching

**As an employee**, I want to request release from an office and switch among
my memberships when permitted.

**Status:** Partially implemented; no complete transfer wizard is exposed.

**UI verification**

1. Open `/gov-store/my-memberships`.
2. Confirm current memberships and their statuses are visible.
3. Click **Request Release** for an eligible membership and confirm the
   resulting pending/release-requested state appears after reload.
4. If two active memberships exist, use **Switch Context** and verify the
   active office changes on the next scoped page.
5. Use the membership **Join** form with a valid invite/verification code and
   confirm the new membership appears.

**Expected result:** release requests, joining, and switching work through the
   membership UI. Do not mark this story as a full employee transfer unless a
   separate return-assets/handover workflow has been added.

## US-13 — Adopt a catalogue category for an office

**As an office or ministry administrator**, I want to discover official
catalogue categories and adopt them into the operational catalogue.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/operations/catalog/discover/explorer` or
   `/gov-store/operations/catalog/discover/collections`.
2. Search/browse for a category and open its detail view.
3. Use **Adopt**, **Copy**, or the equivalent action shown for the current
   role.
4. Confirm the success message and open
   `/gov-store/operations/catalog/` (the My Catalog page).
5. Confirm the adopted category appears. If the role is allowed to archive or
   restore, exercise that action and verify the status after refresh.

**Expected result:** the adopted category is visible in the office catalogue;
the master catalog and office catalog remain separate views.

## US-14 — Configure product rules

**As a superadmin or authorized policy administrator**, I want product rules
to control required fields and operational behavior.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/operations/settings/product-rules`.
2. Create or edit a rule using the available template/editor.
3. Save the draft and confirm it is visible in the rule list.
4. Open the simulator at
   `/gov-store/operations/settings/product-rules/simulator` and run it for a
   representative category/product.
5. Publish the rule using the visible **Publish** action.
6. Start a new receipt and select the affected product. Confirm the workspace
   displays the newly required metadata fields.
7. Confirm an existing draft does not silently lose its saved lines when the
   rule changes.

**Expected result:** draft, simulation, publish, and assignment controls are
visible; a new operational document uses the published rule.

## US-15 — Assign or hand over an office duty

**As an office administrator or current duty holder**, I want to assign or
hand over a responsibility.

**Status:** Implemented.

**UI verification**

1. Open the office hub at `/gov-store/admin/organization/{id}/hub` and review
   the role controls.
2. Save a duty assignment and confirm the checklist/role display changes.
3. Open `/gov-store/my-memberships` as the current duty holder.
4. Propose a handshake to another active colleague and confirm a pending
   handshake is shown.
5. Sign in as the colleague, accept the handshake, and reload both membership
   views.
6. Confirm the duty is held by the new user and duplicate/self-assignment is
   rejected with a visible error.

**Expected result:** role changes are transactional from the user's
perspective: the old holder is removed and the accepted holder is shown.

## US-16 — Perform a superadmin membership override

**As a superadmin**, I want to force-release a user or strip duties with a
justification.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/admin/memberships/override/console` as Superadmin.
2. Confirm users and available override history are displayed.
3. Select a target user and choose **Force Release** or **Strip Roles**.
4. Enter a justification of at least ten characters and submit.
5. Confirm a success message and updated membership/role state.
6. Repeat with a shorter reason and confirm validation prevents submission.
7. Sign in as a non-superadmin and confirm the console is denied or absent.

**Expected result:** the selected override takes effect, requires an
auditable reason, and is restricted to superadmins.

## US-17 — Verify office and ministry isolation

**As a tenant user**, I must not be able to view or mutate another office's
records.

**Status:** Implemented; test with two populated offices.

**UI verification**

1. Sign in as an Office A employee and open an Office A asset, user, and
   stock page.
2. Copy an Office B asset URL and paste it into the same browser session.
   Confirm a not-found or authorization response, with no Office B details.
3. Open the API-backed page/list normally used by the UI and confirm Office B
   rows are absent.
4. Attempt to submit an edit/check-out/check-in form for the Office B asset
   using the copied URL. Confirm a 403/authorization error or equivalent
   rejection and verify the record is unchanged.
5. Switch to an authorized ministry/superadmin account and confirm the
   record is visible there, proving the test data—not a missing record—is
   the reason for the denial.

**Expected result:** cross-boundary reads and mutations do not leak data or
change the foreign record.

## US-18 — View the stock register and kardex

**As a manager or storekeeper**, I want to inspect stock movements and the
running balance.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/operations/register`.
2. Confirm stock items and balances are listed for the active context.
3. Select an item and open `/gov-store/operations/kardex/{type}/{id}`.
4. Confirm movement rows show direction, quantity, balance/reference, date,
   and actor where available.
5. Create one valid receipt and one valid issue using US-06 and US-10.
6. Refresh the kardex and confirm the two movements appear in chronological
   order with the expected running balance.

**Expected result:** the register and kardex agree with the posted documents
and do not expose another office's movements.

## US-19 — Record asset maintenance

**As an asset manager**, I want to record maintenance against an asset.

**Status:** Core Snipe-IT.

**UI verification**

1. Open an asset and select the **Maintenance** tab/action.
2. Create a maintenance record using the available maintenance type,
   supplier/date/cost/notes fields.
3. Save the record and confirm it appears in the asset maintenance list.
4. Open the maintenance record, edit one field, save, and verify the change.
5. Delete it only in a disposable test environment and confirm the UI
   updates accordingly.

**Expected result:** maintenance is visible from the asset and retains the
   entered details.

## US-20 — Import inventory data in bulk

**As an administrator**, I want to import supported Snipe-IT inventory data
from a CSV rather than entering every row manually.

**Status:** Core Snipe-IT.

**UI verification**

1. Open the Snipe-IT importer page from the administrator/import menu.
2. Download or inspect the template for the selected entity.
3. Upload a small valid CSV in a disposable environment.
4. Review the preview/validation result and continue the import.
5. Confirm the imported rows appear in the relevant index page.
6. Repeat with a malformed required value and confirm the importer reports
   the row/field error without creating a partial unexpected record.

**Expected result:** valid rows are imported and invalid input is surfaced in
the importer UI. Do not use this story to verify Gov-Store receipt/ledger
posting; that is US-06.

## US-21 — Manage programmes and funding sources

**As a programme administrator**, I want to create and maintain initiatives
and their funding dictionary so that operational work is grouped under an
accountable programme.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/admin/tracking/initiatives` and confirm the portfolio shows initiatives grouped by
   status, with counts for operational, setup, closed, and archived work.
2. Click **Launch New Initiative** and complete the required title, purpose,
   owning ministry/company, status, and primary funding fields.
3. Save the initiative and confirm it appears in the portfolio.
4. Open the initiative workspace and then **Edit General Properties**.
   Change one value, save, and verify the updated value after refresh.
5. Open `/gov-store/admin/tracking/funding-types`.
6. Add a funding source by selecting a primary segment, entering a name, and
   optionally entering a description.
7. Confirm the new funding source appears in the dictionary and is available
   when creating/editing an initiative or tracking code.
8. Attempt to delete a funding source that has active tracking codes. Confirm
   the delete control is disabled or the server rejects the deletion.

**Expected result:** programme status and funding configuration are visible
and reusable by downstream tracking workflows; referenced funding sources
cannot be removed accidentally.

## US-22 — Create and operate tracking-code tasks

**As an operation head or officer**, I want to define, activate, monitor, and
report on tasks under an initiative.

**Status:** Implemented.

**UI verification**

1. Open an initiative workspace at
   `/gov-store/admin/tracking/initiatives/{initiative}`.
2. Open **Manage Operation Team** and assign one Operation Head and at least
   one Operation Officer using the staff search. Confirm both appear in the
   team list.
3. Add optional support staff and confirm the person appears in the support
   list; remove a test assignment and confirm it disappears.
4. Return to the workspace and click **New Tracking Code / Task**.
5. Complete the task identity, fiscal profile, execution scope, and delivery
   matrix fields. Submit the form.
6. Confirm the task appears in the initiative workspace with its tracking
   code and draft/active status.
7. Use the task's **Activate** action. Confirm the status changes to
   `ACTIVE`; attempt to activate invalid/incomplete data and confirm
   validation is shown instead.
8. Open the task's **View Task** page and verify the official code, fiscal
   period, funding classification, geographic scope, participants, and
   delivery matrix are displayed.
9. Use **Download/View PDF** where available and confirm a document response
   opens.
10. Open **Full Progress Report** and confirm initiative delivery totals and
    task-level progress are shown.
11. Open **Retrospective** for the initiative, associate a completed
    inventory/operation record, and confirm the association is displayed.
12. Archive a test tracking code and confirm it no longer appears as active.

**Expected result:** initiative work can be assigned to a team, represented by
unique tracking codes, activated/archived, inspected, reported, and tagged
retrospectively.

## US-23 — Import and review the master catalogue

**As a catalogue administrator**, I want to load an official classification
dataset and review its validation results before it becomes available for
adoption.

**Status:** Implemented.

**UI verification**

1. Open `/admin/catalog` and confirm the master catalogue dashboard renders.
2. Open `/admin/catalog/import`.
3. Test the bundled dataset path with **Analyze & Review**. Confirm the
   wizard advances to a validation/review step and shows counts or warnings.
4. Return to the import form and test the bundled **Direct Import** action in
   a disposable environment. Confirm an import result or redirect is shown.
5. Upload a valid custom metadata CSV, enter a scheme and version, optionally
   upload a hierarchy/tree CSV, and choose **Analyze & Review**.
6. Confirm the review page exposes validation findings before execution.
7. Execute the reviewed import and confirm the completion result.
8. Open `/admin/catalog/history` and confirm scheme, version, processed-node
   count, warnings, and duration are listed.
9. Open `/admin/catalog/external` and confirm the external/imported catalogue
   view is available.
10. Repeat with an invalid CSV and confirm the UI reports an error without
    treating the import as successful.

**Expected result:** bundled and uploaded catalogue datasets follow the
select/review/import flow, and completed imports are auditable in history.

## US-24 — Govern mappings and curated catalogue collections

**As a master-catalogue administrator**, I want to map official nodes to
Snipe-IT categories and publish curated collections for offices to discover.

**Status:** Implemented.

**UI verification**

1. Open `/admin/catalog/search` and search for an official catalogue node.
2. Open the node's adoption/mapping controls and select a Snipe-IT category.
3. Save the mapping and confirm the selected category/mapping status is shown.
4. Open `/admin/catalog/governance` and confirm the category appears with its
   governance type, owner, adoption count, and mapped-model count.
5. Open the category detail page and verify its governance/adoption information.
6. Open `/admin/catalog/collections` and create a collection with a name and
   description.
7. Edit the collection, attach one or more catalogue nodes, and confirm they
   appear in the collection builder.
8. Open `/gov-store/operations/catalog/discover/collections` as an office user
   and confirm the published collection is discoverable.
9. Use the collection's adoption or bulk-preview action, review the preview,
   then execute it. Confirm the adopted items appear in **My Catalog**.
10. Test **Abandon** or **Detach** on a disposable mapping/collection member
    and confirm the UI reflects the removal.

**Expected result:** official nodes can be mapped, audited through governance,
curated into collections, previewed, and adopted without editing the master
catalogue directly.

## US-25 — Configure tenant-scope policies and mappings

**As a system administrator**, I want to define how reference data is scoped
by ministry or office so that selectors and records follow the intended
boundary.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/admin/scope/dashboard` and record the mapping, ministry
   scope, office scope, and active-policy counts.
2. Open `/gov-store/admin/scope/config`.
3. For models, manufacturers, suppliers, fieldsets, and locations, choose a
   scope strategy (`global`, `company`, or `location`) and set or clear
   **Show only used**.
4. Click **Save Policies**, confirm the success response, and reload to verify
   the selected values persist.
5. Open `/gov-store/admin/scope/mappings`, filter by reference type and scope
   type, and apply/reset the filters.
6. Click **Assign New Mapping Rule**, select a reference item and company or
   location boundary, then save.
7. Confirm the new mapping appears in the grid and in the dashboard recent
   activity.
8. Delete the test mapping and confirm the confirmation prompt and refreshed
   grid.
9. Open an affected inventory form as an office user and verify the selector
   behavior follows the saved policy.

**Expected result:** scope strategies and explicit reference mappings persist,
are filterable/revocable, and affect the corresponding office/ministry UI
without exposing unrelated records.

## US-26 — Monitor and repair metadata health

**As a platform administrator**, I want to see metadata provider compliance
and queue a safe convergence when models are out of sync.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/admin/metadata/health`.
2. Confirm the page shows a health score, total/compliant/non-compliant model
   counts, loaded provider versions, and field counts.
3. If the score is below 100%, review the listed non-compliant models and
   click **Queue Schema Convergence**.
4. Confirm a success message says the process was dispatched/queued.
5. Reload the page and confirm the report remains readable while the
   background process is pending or after it completes.
6. If the score is 100%, confirm the healthy state is shown and no repair
   action is incorrectly presented as required.

**Expected result:** administrators can distinguish healthy, warning, and
critical metadata states and can explicitly queue convergence only when
needed.

## US-27 — Delegate ministry/company administrators

**As a superadmin**, I want to assign and remove ministry-level company
administrators so that oversight responsibility is delegated without
changing office staff duties.

**Status:** Implemented.

**UI verification**

1. Open `/gov-store/office/company-admins` as a superadmin or authorized
   company administrator.
2. Select a ministry/company and a user in the delegation form.
3. Submit **Assign/Save** and confirm the administrator appears in the
   assigned-admins table.
4. Sign in as the delegated user and confirm ministry-level organization or
   oversight pages are available according to their permissions.
5. Remove the assignment using the table action and confirm it disappears
   after refresh.
6. Sign in as a normal employee and confirm the company-admin management
   page is unavailable or returns an authorization response.

**Expected result:** company-admin assignments are visible, removable, and
separate from office staff memberships and storekeeper/approver duties.

---

## Appendix A — Route inventory used by testers

| Capability | Primary URL |
|---|---|
| Organization registry/provisioning | `/gov-store/admin/organization` |
| Organization create | `/gov-store/admin/organization/create` |
| Office hub | `/gov-store/admin/organization/{id}/hub` |
| Office staff | `/gov-store/office/staff` |
| User onboarding | `/gov-store/admin/onboard` |
| Memberships | `/gov-store/my-memberships` |
| Membership override | `/gov-store/admin/memberships/override/console` |
| Request catalogue | `/gov-requests/catalog` |
| Basket | `/gov-requests/basket` |
| My requests | `/gov-requests/my-requests` |
| Approval queue | `/gov-requests/admin` |
| Fulfillment queue | `/gov-requests/fulfillment` |
| Fulfillment register | `/gov-requests/fulfillment-register` |
| Store operations hub | `/gov-store/operations/hub` |
| Stock register | `/gov-store/operations/register` |
| Product rules | `/gov-store/operations/settings/product-rules` |
| Office catalogue | `/gov-store/operations/catalog/` |
| Master catalogue | `/admin/catalog` |
| Programme portfolio | `/gov-store/admin/tracking/initiatives` |
| Funding sources | `/gov-store/admin/tracking/funding-types` |
| Tenant-scope dashboard | `/gov-store/admin/scope/dashboard` |
| Tenant-scope configuration | `/gov-store/admin/scope/config` |
| Tenant-scope mappings | `/gov-store/admin/scope/mappings` |
| Metadata health | `/gov-store/admin/metadata/health` |
| Company administrators | `/gov-store/office/company-admins` |

Dynamic `{id}`, `{type}`, and `{template}` segments must be replaced with
values displayed by the application.

## Appendix B — Known gaps and out-of-scope claims

The following claims from older versions of this document are not accepted as
UI-tested behavior unless the corresponding route and control are added:

- A single end-to-end employee transfer wizard that automatically checks in
  every asset and hands over every duty.
- A separate Gov-Store “Goods Receipt Note” or “Goods Issue” menu item if the
  operational hub does not render it in the current environment.
- A standalone “Kardex” tab on every core Snipe-IT page; the route-backed
  register/kardex pages are the reliable verification surface.
- Automatic email delivery, scheduled notifications, or exact generated
  document numbers; verify those only when the environment has mail/queue
  workers configured.
- A UI proving database seed files were loaded. Verify imported/visible data
  through the pages above instead.

If a route returns 404 in a clean installation, record the story as
**blocked by deployment/package registration**, not as a functional pass.
