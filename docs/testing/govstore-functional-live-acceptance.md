# GovStore functional live acceptance

Scope clarified by the product owner on 24 October 2026, Asia/Dhaka.
This is the acceptance contract, not a record of completed tests. Current gaps
and inspected evidence are in the [coverage review](../verification/live-test-coverage-review-2026-10-24.md).

## Scope and unit of acceptance

Test all user-facing functions and user stories in the installed **GovStore
packages**. Include native Snipe-IT when a GovStore journey depends on it, such as
creating inventory, materializing receipt assets, assigning custody, checking in,
maintenance, imports, acceptance or activity history. A full independent regression
of unrelated upstream Snipe-IT features is outside this scope.

The unit of acceptance is an observable business outcome reached through real
actions across pages and role handoffs on the selected authorized nonproduction
server. An employee, approver, final approver and storekeeper must use their own
sessions and actual permissions. Carry the same request, document, asset, office,
committee or programme identity between them. Do not inspect unrelated seeded
states and present them as steps of one completed journey.

`user_stories.md` is the story index, not the complete function inventory. For
each package, reconcile its current effective routes, controllers, forms, menus,
dialogs, search/filter controls, uploads, downloads, exports and background work
with named functional cases. Include functions added after the story index was
written. API calls used by a screen belong to that screen's flow; an authenticated
HTTP probe is additional server evidence, not proof of a browser interaction.

## Required package inventory

These are coverage groups to expand into explicit cases from current code. They
do not imply that every listed lifecycle branch exists in every installation.

| Package | Functions and journeys to cover | Story links |
| --- | --- | --- |
| `geo-areas` | English/Bangla area search, hierarchy selection, valid/invalid area choices and jurisdiction at office/programme setup | US-01, 02, 17, 22 |
| `organization` | Directory import/review; office discovery/provisioning; details, administrator and responsibilities; readiness and starter status/retry; national administrators; suspend/resume/relocate with consequences visible to staff | US-01, 02, 03, 27, 28, 31 |
| `office-membership` | Invite/personal-code join and review; staff approval/rejection; working-office switching; duty propose/accept/decline/cancel; leaving, withdrawal, clearance and sign-off; emergency changes, history and notices | US-03, 05, 12, 15, 16, 17 |
| `user-onboarding` | Manager queue and decisions; eligible office assignment; rejection/history/notices; affected user's next login and context | US-04 |
| `tenant-scope` | Login/context isolation; own/foreign object reads and writes; role changes and expiry; access request and admin review; next-request effectiveness; permission matrix/export, audit and shadow report; reviewed national changes and replay refusal | US-05, 16, 17, 25, 27; access functions beyond the story index |
| `classification` | Search/discover/detail; office/company adoption, copy, archive/restore; mappings/collections; supported master-bundle analyze/review/execute/history; starter collection effects visible in a newly provisioned test office | US-13, 23, 24, 28 |
| `metadata` | Required typed fields in product/office/company/document forms; health; authorized convergence and job completion; downstream form/receipt behavior after repair | US-06, 14, 26 |
| `store-operations` | Opening/readiness; receipt and issue drafts, reload, validation, posting and print; attachments and authorized delivery; attribution/takeover; adjustments, transfers and returns where exposed; register/kardex and native inventory effects; product rules draft/simulate/review/publish/assign/unassign | US-06, 09, 10, 11, 14, 18; supported operations beyond the index |
| `custom-requests` | Catalogue search/availability; basket add/update/remove/validation; submit/batches; primary/final/automatic policy paths; reject/reduce; reservation; partial/final issue, asset selection/substitution, close remainder; requester acknowledgement, withdrawal, request again, return intent and linked receipt; queues/register/history/notices | US-07, 08, 09, 17; requester and policy functions beyond the index |
| `tracking` | Funding dictionary; initiative create/edit; head/officer/support assignments; task/code drafts, uniqueness, scope, participating offices and delivery matrix; activation, evidence/order upload and protected downloads; office monitoring, reports, retrospective association and archive | US-21, 22 |
| `committee` | Ministry/national type and purpose configuration with review; signed order intake/save/resume/discard; constitution and appointments; activation and supported amendments/term/status changes; declarations, member views, private files, roster/history/print, transfer workbench, expiry and clearance effects | US-29 |
| `asset-tagging` | Finance/master setup, company code lists, office settings and enablement; required input and warning paths; Govt./standard issuance through receipt; permanent identity lookup/export; pause/resume and repeat/replay protection | Receipt/settings integrations; not a standalone story in the 31-entry index |
| `geo-view` | Monitor and embedded maps; source/preset choice, drill/back/search, layer/filter/detail changes, flat mode, empty/error states and export where offered; scoped counts linked to real office/receipt/programme changes | US-17, 18, 21, 22 integrations; map functions beyond the index |
| `theming` | Personal preference/mode persistence; authorized scope assignments/default/enforcement and restoration; navigation/inbox/basket/context controls during real work; Bangla/English and relevant narrow-screen behavior | US-30; shared workflow UI |
| `experimentation` | Nonproduction admin controls; dataset creation/progress/ready, account download, supported resume/recovery/cleanup, reviewed dataset-owned wipe and retained-record restrictions | Test administration beyond the index |

For each row, keep an explicit list of the discovered actions and branches with
their journey IDs and evidence. A package-level green badge cannot replace this
list. Record missing application functions as unimplemented, not passed. Keep
destructive installation-wide reset outside routine acceptance; its separately
authorized destructive rehearsal requires its own designated environment.

## Required connected journeys

1. **Office becomes usable:** directory/geography prerequisites, authorized
   provisioning, administrator and duty assignments, staff join/onboarding,
   starter catalogue completion, readiness and employee context. Test the next
   actor's ability to begin ordinary work after setup.
2. **Stock to employee:** storekeeper creates and posts a marked receipt;
   inventory/assets appear; employee finds that inventory, edits a basket and
   submits; primary and, when policy requires, distinct final approver decide;
   storekeeper issues; employee acknowledges. Revisit each actor's list/detail
   and verify quantities, custody, reservations, linked documents and history.
   Exercise supported bulk and serialized types separately.
3. **Exceptions and recovery:** separate marked cases for invalid input,
   rejection/reduction, automatic approval, partial issue and balance closure,
   insufficient stock/over-issue, withdrawal, substitution, repeat request,
   bulk return and physical return receipt, and native serialized check-in.
   Include repeated actions to confirm no duplicate posting/receipt/issue.
4. **Programme delivery:** programme administrator creates the initiative and
   assigns its team; authorized head/officer creates a task, scope and delivery
   allocations, attaches required evidence and activates; the participating
   office's storekeeper posts a linked receipt; office/programme viewers see
   the resulting delivery and report/map totals. Test retrospective association
   for duplication, private file access, unrelated office denial and archive.
   Follow current eligibility rules rather than inventing an approval stage.
5. **People and access:** employee joins/requests access, administrator reviews,
   employee acts with the resulting permission on their next request; eligible
   colleagues complete duty handover; leaving checks custody/unresolved work,
   resolves those obligations through their supported flows and obtains sign-off.
   Verify notices, old/new authority, expiry and working context.
6. **Committee lifecycle:** authorized catalogue/registrar setup on a marked
   scope, synthetic signed order intake, constitution/appointments/activation,
   member declaration and document access, supported amendment/transfer/status
   changes, historical roster and final state. Registry completion does not
   establish an unimplemented receipt-inspection consumer.
7. **Rules and administration:** authorized actor configures a marked supported
   rule, mapping, catalogue or scope change through review and confirmation;
   another affected actor encounters its actual effect in a normal workflow;
   verify history and rejected tampering/expiry/replay. Include themes,
   metadata repair and tagging at their real downstream integration points.

Every other function in the inventory also needs a focused case. The connected
journeys above ensure the packages work together; they do not replace individual
function and alternative-branch coverage.

## Pass criteria for each functional case

- Record target/environment, application revision, journey definition version,
  dataset, working office/company/geography, access mode and actual actor roles.
  Keep sensitive identities and object IDs in private artifacts only.
- Establish relevant starting stock, custody, state, permissions and queue counts
  from current observations. Use marked fictional fixtures and verify the actor
  can perform the intended role without substituting the bootstrap superadmin.
- Start from the normal menu/list/search, complete the forms/dialogs and follow
  the visible next step. Assert meaningful content and status after each action;
  a visible `body`, HTTP 200, redirect or success flash alone is insufficient.
- At every handoff, locate the captured object in the next actor's queue and
  open it through that UI. Check its identity, quantities and allowed action.
  Reopen/reload the originating actor's view to verify the returned outcome.
- Independently compare saved state, actual decision/poster identities, native
  balances/custody, ledger movements, reservations, notices and audit/history
  appropriate to the case. Expected values come from declared inputs and the
  business rule, not another value copied from the same screen.
- Verify relevant failure branches: missing/invalid values, wrong role/office/
  company/jurisdiction/ownership/state/type, stale actions and duplicate/replay.
  Confirm the intended denial and unchanged business state/rollback. Honor the
  actual shadow/enforce contract; boundaries remain enforced in both modes.
- Follow asynchronous work to a terminal result before checking its consumer.
  An accepted job or queued badge is not evidence of completion. Record in-app
  notice delivery separately from external email delivery.
- Account for every write, supported cleanup and retained evidence even after
  failure. Never rewrite stock, permissions, roles or history to make a test pass.

## Environment, fixtures and result reporting

Use the configured target's accounts and observations. The local database cannot
verify a staging mutation. When independent staging observations are unavailable,
report UI outcomes separately and mark the persistence assertions blocked; do
not substitute local observations or remove them to turn the case green.

Use existing authorized fictional data or create minimal identifiable fixtures
through supported UI/setup workflows within task authorization. Full functional
testing implies writes to those designated test fixtures; do not replace those
writes with page checks. Preserve other datasets, real users and audit history.
Shared/national changes need their real review protections and a designated test
scope. Do not execute destructive reset/wipe as incidental test preparation.
Follow the [local guide](local-live-testing.md) and each package's current rules.

Report these independently:

| Evidence level | What a passing result establishes |
| --- | --- |
| Page-entry / boundary check | The explicitly checked page or denial behaved as asserted |
| Seeded-state observation | Existing UI data matched the stated observation |
| Focused function / branch | The specified user action and its effects completed |
| Complete connected journey | The stated business outcome completed across all required actors and packages |

For each function and journey report passed, failed, blocked, not run or
unimplemented, with the reason and supporting run. List skipped/conditional
branches explicitly. Report local and staging results separately; old passes
cannot establish behavior on the current deployment. A selected suite passing
means only that suite's assertions passed. Full acceptance requires evidence
for every supported inventory item and branch in the agreed installed revision,
with unresolved failures, missing definitions and blocked cases still visible.
