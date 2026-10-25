# GovStore interface-only live execution

25 October 2026, Asia/Dhaka. **Full functional acceptance remains incomplete.**
This records implementation and actual exercises, not completion of every story.

The owner explicitly required all live testing through the application interface,
without a database connection. The connected runs below discover accounts from
the protected experiment account download, verify roles/working office using My
access, discover stock in Request items, and perform actions in separate browser
sessions. They reopen queues, details, stock cards, issue records, history and
personal asset lists to check the outcome. No database connection is required or
used by configured-target live runs. Earlier database-backed development runs are
not counted as interface-only acceptance.

## Implemented

- Explicit `inspect` and `waitInspect` checks for rendered receipts, stock cards,
  baskets, request detail/history, native asset forms and tracking screens. Legacy
  database observations remain separate and blocked until deliberately converted.
- UI account/role selection, empty-basket checks and catalogue discovery for
  consumables, accessories and asset models. No role grants or bootstrap-admin
  substitution for ordinary business actors.
- Connected receipt/request/approval/issue/acknowledgement journeys, request
  rejection/reduction/withdrawal/two-part issue, native asset return, direct goods
  issue, programme/team setup, duty-handover decline and personal appearance
  persistence/restoration. Tracking delivery is authored but blocked before task
  creation in live execution.
- Strict schema and expanded-flow checks, explicit evidence levels, all matrix
  cases, current-failure precedence, target/definition/deployment provenance and
  missing-branch reporting. New runs retain private definition snapshots and
  prevent overlapping writing runs against the same origin.
- Navigation checks reject unintended login redirects and server failures.
  Failure screenshots and ownership/restoration records remain private.
- Initial inventory of 318 GovStore routes and six native integration entries
  across 15 packages. It is a backlog, not an exhaustive inventory of every UI
  control: **117 of 650 branches have journey mappings; 533 still lack cases**.
  Mappings include authored failing and blocked cases; they are not pass totals.

## Verified outcomes

| Environment | Exercise | Result and evidence |
| --- | --- | --- |
| Staging | Receive consumables, request, primary approval, issue, requester acknowledgement | Passed; reopened stock-card deltas, issue record, requested/approved/issued quantities and requester history. `run_1792874498289_R-3CUC` |
| Local | Receive consumables and serialized assets, request, applicable distinct approvers, issue, acknowledge | Both passed. Asset case also checked the exact new asset tag/serial in the requester's personal asset list. `run_1792874634880_R-3CUC` |
| Local | Reject, approve fewer then issue/acknowledge, and withdraw | Passed, including the next actor's view and quantities. `run_1792875521544_R-3CUC` |
| Local | Two separate partial issues followed by acknowledgement | Passed after selecting the actual Partly issued queue for the second handover. `run_1792876096779_R-3CUC` |
| Local | Accessory receipt through requester acknowledgement | Passed. `run_1792876096779_R-3CUC` |
| Local | Direct goods issue | Passed; posted issue reopened and stock-card balance decreased by the declared quantity. `run_1792876096779_R-3CUC` |
| Staging | Duty handover without an optional note; recipient declines | Passed; both users reopened the outcome/reason and unchanged duties. `run_1792875029807_R-3CUC` |
| Staging | Save a different personal theme and dark mode, reopen, restore original settings | Passed, including restoration. `run_1792875029807_R-3CUC` |
| Local | Invalid office creation without an area | Passed through the ICT officer interface; error summary, field error, retained name and subsequent no-match search checked. Original language restored. `run_1792875521544_R-3CUC` |
| Local | Create an active programme, assign its head/officer, and have the appointed head open its workspace | Passed. Team usernames were reread from the administrator's normal operation-unit screen. `run_1792875521544_R-3CUC` |
| Staging | Return the exact asset issued during the native integration attempt | Supported native check-in completed after explicitly selecting its original office location. The asset showed available status and no check-in control. Private `native-checkin-response.json` under `run_1792875222072_R-3CUC`; this supplemental recovery does not turn the earlier failed automated case into a pass. |
| Staging | Reject, approve fewer, withdraw, partial issue, full existing-asset checkout/acknowledgement/check-in, office validation, programme/team setup | All seven passed, with private immutable definitions and successful cleanup where needed. `run_1792876262068_R-3CUC` |
| Staging | Accessory receipt through acknowledgement; direct goods issue | Both passed. `run_1792929124779_R-3CUC` |
| Staging | ICT officer creates an in-jurisdiction office and reopens geography/ministry/readiness | Passed. `run_1792929708755_R-3CUC` |
| Staging | New office through first administrator, four staff memberships, duty assignments and operational requester access | Passed through 151 actions on the same new office. The bootstrap superuser performed only first-member administration; the new ordinary office administrator performed later staff admissions and duty assignment. Four personal codes were consumed and redacted. Fresh-login UI checks confirmed all four original home offices and duties remained intact. `run_1792930228862_R-3CUC` |
| Staging | Accepted primary-duty handover and return handover | Passed; both users' My access changed after acceptance and reverted after the return. Cleanup reread the original administrator's role assignment. `run_1792931156195_R-3CUC` |
| Staging | Define a brand-new consumable from receipt intake, verify zero stock, post six units and have an employee find that exact item | Passed through 63 actions. The native new-item popup explicitly selected company, office and zero initial quantity. `run_1792931631684_R-3CUC` |
| Staging | Issue an office invitation, have another colleague request membership, approve, switch and end additional access | Passed through 202 actions on the same newly created office. The administrator saw the ended membership in Past staff; a fresh colleague login confirmed the original working/home office and absence of the ended office. All five captured codes were redacted. `run_1792933630257_R-3CUC` |
| Staging | Committee order intake: save fields/private PDF, resume, revise, reopen and discard with confirmation | Passed, including supported cleanup and zero remaining intake revision. `run_1792934136807_R-3CUC` |
| Staging | Issue one of two approved units, close the unneeded remainder, show the requester the reason and issued quantity | Passed. `run_1792934136807_R-3CUC` |
| Staging | Complete goods issue/acknowledgement, requester return intent, storekeeper return queue and linked receipt draft | Passed; same quantities and original issued history preserved, stock unchanged while return remains a draft. Return posting is not established. `run_1792934136807_R-3CUC` |
| Staging | Withdraw a request, ask again, submit a distinct new request and withdraw it | Passed; copied item/quantity and distinct new identity checked, original remains withdrawn. `run_1792934136807_R-3CUC` |
| Staging | Request committee access, office administrator declines with a note, requester rereads the decision and unchanged abilities | Passed; no grant was created. `run_1792934711856_R-3CUC` |
| Staging | Draft attachment upload, another role's authorized download, draft print, attachment removal and void | Passed; stock unchanged and the run attachment removed. `run_1792947997850_R-3CUC` |
| Staging | Automatic approval through goods issue and requester acknowledgement | Passed; selected a visible AUTO_APPROVE commodity in another suitable fixture office, checked immediate approval and exact approved/issued quantities, completed issue/acknowledgement and stock-card delta. No policy was changed. `run_1792949203709_R-3CUC` |
| Staging | Native maintenance creation, completion and asset history | Passed through 42 actions on the same marked maintenance and exact scoped fictional asset. `run_1792949641937_R-3CUC` |
| Staging | New unassigned account, first-office onboarding, employee's next-request context and catalogue | Passed through 57 actions. The new employee independently saw Waiting then Completed, the assigned office and ordinary request ability, with no national-management ability. Supported cleanup passed and deactivated only that new account. `run_1792950007269_R-3CUC` |

Further execution is continuing. The native table's visible fixed action column
must be clicked; its covered original link cannot receive pointer events. Asset
history assertions also need the specific table ID because the fixed columns
duplicate rows. A fresh complete maintenance case passed after these runner
corrections. The focused intake and private-attachment rechecks also passed.
Original failed runs and immutable definitions remain unchanged.

Early runs preceded immutable definition snapshots; their complete step events
and recorded definition hash are retained, but they are not certificates for
subsequently edited definitions. New runs identify `definitionsSource` as
`private-run-snapshot`. The remote deployment revision is still unknown and is
reported as a provenance gap, without blocking UI execution or requesting a
database connection.

## Application failures and evidence gaps

1. **Staging serialized receipt posting:** posting returned a safe error with a
   reference ID and left the receipt workspace as a draft. The receipt-to-new-
   asset story could not continue. Private evidence:
   `run_1792874100516_R-3CUC`, asset failure screenshot at step 31. No protection
   was bypassed and no database repair was attempted. Fresh staging execution
   `run_1792931361703_R-3CUC` again remained a draft after posting. The new-item
   definition failure in that same run was a separate runner assumption about
   blank native quantity; the corrected consumable case subsequently passed.
2. **Tracking task creation:** programme/team setup succeeds, but opening New
   tracking code does not expose its form. A browser recheck on local returned
   HTTP 500 and no task form; the staging recheck returned an empty response.
   Evidence: local `run_1792874878837_R-3CUC/interface-recheck.json`, staging
   `run_1792874680740_R-3CUC` screenshot/events. Task allocation, order upload,
   activation, linked receipt delivery, reports and archive remain unverified.
   The internal cause was not established through interface evidence.
   A fresh authorized staging browser recheck on 25 October still returned
   `net::ERR_EMPTY_RESPONSE`; private evidence is
   `run_1792876262068_R-3CUC/tracking-create-interface-recheck-2026-10-25.json`.
3. **Handover optional note:** a marked note submitted with the proposal was
   absent from the recipient's incoming request card. The note-visibility case
   remains failed (`run_1792874889021_R-3CUC`). A separate case exercises decline
   without an optional note; it does not erase that failure.
4. **Starter catalogue delivery:** the newly operational office's hub reports
   that starter items could not be added, with a safe reference ID. Private
   `run_1792930228862_R-3CUC/starter-interface-check.json` records the actual
   rendered panel. The office administration and requester-context pass does
   not establish US-28 starter delivery; downstream catalogue delivery remains
   open. Internal cause is not established.
5. **Catalogue bulk-adoption control:** after browsing all three hierarchy
   levels and selecting one unadopted commodity, the bulk action remains hidden.
   This repeats after explicit full-load waits on each link. The operator cannot
   reach preview/execute through that control. Private screenshot and events:
   `run_1792933192711_R-3CUC`, `classification.adopt-archive-restore`, step 14.
   The checked-in stylesheet forces `display: none !important` on that action
   bar, consistent with the symptom; remote internal cause is not independently
   established. No forced click, CSS override or direct submission was used.
6. **Map deployment gap:** the staging permission matrix lacks
   `geo.monitor.view`; a separate authenticated browser navigation to
   `/gov-store/geo-monitor` returned 404 and no map widget. Evidence:
   `run_1792933192711_R-3CUC/geo-monitor-deployment-check.json`. The authored
   interactive map case remains blocked on this deployment.
7. **Native consumable import:** upload, import-type selection and all five
   column mappings reached the native Execute action. The actual screen's
   request returned HTTP 500. Evidence: `run_1792934711856_R-3CUC`,
   `native-integration.import-consumable-to-catalogue`, step 29. Its post-error
   consumer check is recorded separately; the failure is not a successful import.
8. **Committee appointment setup gap:** a company administrator created a
   ministry-only fixture type, and the office administrator created its draft
   and selected the named colleague. The seat-role dropdown contained no options,
   so appointment could not proceed. `run_1792934711856_R-3CUC`,
   `committee.ministry-type-to-appointed-draft`, step 53. No role option was
   injected; activation, declarations and later lifecycle remain unverified.
9. **Request time discrepancy confirmed:** a focused comparison of the same
   submission header and submitted history event failed by six hours:
   19:42 versus 13:42, with Dhaka labels.
   `run_1792935248637_R-3CUC`, `requests.request-time-consistency`.
   Cleanup completed; functional request passes remain separately scoped.
10. **Funding-source removal:** creation and a reopened zero-task count succeeded,
    but national-review confirmation for removing that exact new unused source
    returned HTTP 500. Supported cleanup repeated the failure; the source remains
    listed. `run_1792948466347_R-3CUC`, funding case. A fresh interface recheck
    confirms retention. No method override or database repair was used.
11. **Programme creation redirect:** two new programme submissions redirected to
    their own detail screens, which returned HTTP 500. Fresh browser visits also
    returned 500. Programme close/archive and delegation-to-programme therefore
    failed in `run_1792948466347_R-3CUC`. Earlier programme/team passes describe
    earlier execution, not the currently failing detail screens. The interface
    does not establish whether all writes were rolled back.
12. **Intermittent working-office context:** the preceding lifecycle attempt
    selected its new office but My access showed no working-office ID. A fresh
    normal picker selection preserved the exact office through My access.
    `run_1792947997850_R-3CUC/office-switch-interface-recheck.json`. Cause remains
    unresolved. That office has incomplete setup; it is not an operational pass.
13. **Paused-office denial:** fresh office setup reached suspension, disabled the
    ordinary administrator's duty controls and refused the employee catalogue
    with HTTP 409. The page showed the generic safe reference message rather
    than the test's expected pause explanation. The captured protected response
    also contained debug-toolbar metrics. `run_1792949203709_R-3CUC`, lifecycle
    step 168. The full journey remains failed; supported resume cleanup completed.
    A fresh employee login subsequently preserved the original home office,
    selected that same restored test office and opened its correct catalogue
    with HTTP 200. Private `resumed-office-consumer-recheck.json` supplements
    restoration evidence without rewriting the original failed case.
14. **Native user editor:** a newly created fictional account was assigned its
    first office through onboarding. Its native edit form then returned 403 when
    the verified superuser attempted to deactivate it. A fresh login and the same
    normal form repeated 403; the activation checkbox stayed on. The supported
    bulk editor, with exactly that one account selected, successfully deactivated
    it. `run_1792949641937_R-3CUC/onboarding-account-cleanup-recheck.json` and
    `onboarding-bulk-cleanup-recheck.json`. No password reset or permission change
    was used. The first onboarding case stopped on a runner assertion that did
    not recognize the Bangla history wording; its full downstream story is not
    counted as passed by that attempt. A corrected fresh onboarding case later
    passed with supported single-account bulk cleanup, as recorded above. The
    single-user editor failure remains open.
15. The interface does not establish invisible database atomicity, hidden
   reservations or internal audit rows. Report only the values/history actually
   exposed by screens and registers. External email delivery was not tested.

Runner corrections are distinct from application defects: repeated login now
uses fresh isolated sessions; exact receipt assets are located through the asset
card; primary-only approval no longer expects a final approver; the second issue
uses the Partly issued queue; visible labels operate styled appearance radios;
and native check-in uses its actual submit button rather than a DOM ancestry
assumption. Original failed runs remain unchanged.
Login and explicitly declared link navigations now wait for the new document
to finish loading before interacting with its controls. A delayed-page browser
contract checks this. Interrupted office-picker attempts remain recorded. The
catalogue action still failed after the wait correction, as reported above.
The experiment account export lists office responsibilities but omits the office
profile's administrator assignment; discovery now checks that role through My
access instead of excluding such candidates before login. A browser contract
covers the absent-export-role case. These are runner changes, not role grants.

## Cleanup and retained records

Only supported interface actions remove temporary run-owned basket lines or
cancel an unfinished run-owned duty proposal. Posted receipts, requests, issues,
assets, programmes, scoped programme-team assignments and audit history are
retained, including records from failed attempts. Some requests remain pending
or partially issued from those attempts; they are not silently rewritten.

Personal appearance settings and the ICT officer's language are restored through
their own controls. The exact earlier run-issued staging asset was returned to
its original office using native check-in. Original local mail configuration and
its originally uncached state are restored after the local exercises. No access
mode was changed. A new fictional colleague's temporary company-admin assignment
was created through national review and removed through the supported workflow
after the programme failure. A fresh interface check confirmed zero assignments,
no programme-creation ability, preserved request ability and original home office.
Detailed identities, document IDs, screenshots and ownership ledgers stay under
private live-test run folders;
no credentials/account CSVs belong in this document.

Marked maintenance records and audit are retained, including incomplete records
from runner failures. The unused marked funding source remains because supported
removal fails. New programme submissions may have persisted before the failing
redirect; no hidden-state or rollback claim is made. Lifecycle cleanup restored
the suspended test offices; a fresh UI check confirmed the earlier restored office
was operational. Offices with incomplete setup remain separately recorded fixtures.
Both new onboarding accounts were deactivated through exact single-account
bulk edits; their first memberships and onboarding histories are intentionally retained.
Generated credentials stayed in memory and are redacted in run artifacts.

The new operational office and its additional memberships/duties are intentional
retained test data. Existing home offices and their role assignments were verified
through fresh UI sessions. The accepted duty-handover exercise returned the
original duty to its original holder and checked the restored form value.

## Still open

All remaining package functions and lifecycle/validation/authorization/replay
branches need substantive interface cases and live execution. The 31 historical
story-entry checks do not satisfy that work. This includes remaining onboarding,
release/clearance and office lifecycle branches, catalogue adoption/import/review,
metadata repair, policy publication/assignment, access requests/national review,
committee lifecycle, government tagging configuration/issuance, maps and further
native maintenance/import integrations. Reconcile UI controls beyond the initial
route inventory rather than treating that inventory as exhaustive.

Full acceptance requires closing the observed application failures and completing
that backlog on staging. Local, harness and selected-journey passes do not prove
CI success, WCAG compliance, production readiness or a completed shadow period.
