# Full GovStore functional live testing implementation plan

25 October 2026, Asia/Dhaka. **Status: partially implemented and executing.**
The owner subsequently required interface-only live testing with no database
connection. That requirement supersedes earlier database-observation prerequisites
throughout this plan. Completed work and actual results belong in the execution
record; milestones below still include substantial unfinished work. It builds on the
[acceptance contract](../testing/govstore-functional-live-acceptance.md) and
[coverage review](../verification/live-test-coverage-review-2026-10-24.md).
Actual implementation, local/staging outcomes and open failures are in the
[interface execution record](../verification/govstore-interface-live-execution-2026-10-25.md).
It supersedes the original runner plan's limited delivery acceptance and the
story plan's read-only scope for full functional acceptance. Historical results
remain evidence only for their recorded assertions, deployment and fixtures.

## 1. Intended result and scope

An operator selects the authorized staging server and runs complete, repeatable
business journeys for **all installed GovStore packages, user-facing functions
and user stories**. Fictional users act through normal screens and pass the same
records to the next role. Native Snipe-IT is checked where GovStore depends on it:
inventory creation, receipt materialization, asset custody, checkout/check-in,
maintenance, imports and history. Unrelated upstream features do not become a
separate full regression project.

The first connected journey must demonstrate:

1. Storekeeper creates, saves, reloads and posts a marked stock/asset receipt.
2. Employee finds those same items, edits a basket and submits a request.
3. Primary approver decides; a distinct final approver acts when policy requires.
4. Storekeeper finds that request and fulfils it using the stock or physical
   asset received earlier.
5. Requester acknowledges receipt and sees the completed outcome.
6. Each participant revisits the relevant list/detail/history; rendered screens,
   registers and the next actor's view confirm quantities, custody and documents.
   Record facts the interface does not expose as evidence gaps.

Equivalent connected journeys cover tracking delivery, office/staff setup,
membership/access, committees and the other package functions. Opening a page or
examining an already-issued seeded request cannot complete a business story.

## 2. Starting point and implementation decisions

The reviewed baseline has 69 JSON journeys and 10 suites: 31 story entry checks
and 11 declared write journeys among them. Historical staging evidence includes
31 entry passes and 11 deeper cases blocked by unavailable database observations.
Recheck definitions, deployment and private target settings before implementation;
these are dated facts, not fixed acceptance thresholds.

- Extend the existing JSON runner, browser actions, isolated actor sessions,
  CLI/dashboard and private reports. No replacement framework, new testing
  dependency or language-model calls during test execution are required.
- Keep each connected journey self-contained, using reusable flows and captured
  identities. Do not depend on an earlier suite member leaving a particular
  request/session behind. Shared setup must be explicit, verified and idempotent.
- Use fictional seeded prerequisites where appropriate; perform the transitions
  under test through the UI. Fixture/service workflow execution is setup evidence.
- Cover normal operation first, then supported alternatives and failures. This
  order does not remove any function from the final scope.
- Record application defects separately from locator, expectation and fixture
  defects. Preserve failing evidence; never weaken permissions or assertions.
- Preserve existing working-tree changes, especially the ongoing experimentation
  work. No broad reset, account rewrite or population is incidental test setup.

## 3. Functional coverage and evidence model

Add `tests/live/functional-coverage.json` and a schema under
`tests/live/schemas/`. Keep the existing feature catalogue for grouping; reconcile
its associations rather than replacing or discarding historical IDs.

Each required function/branch entry contains:

| Information | Required content |
| --- | --- |
| Identity and source | Stable ID, package, US IDs, route methods/names, handler, UI entry point and owning business rule |
| Actors and scope | Roles/abilities, distinct-user constraints, ownership, office/company/geography relationships |
| Preconditions | State, policy, inventory/custody, ledger opening, master data, worker and file prerequisites |
| Actions and outcomes | Ordered interactions/handoffs, visible result and independently expected persisted effects |
| Coverage mapping | Journey ID, named matrix branch, evidence level and required assertions |
| Applicability | Supported item types, modes, languages and installation gates; explicit reason for any inapplicable branch |
| Effects | Created/consumed identities, cleanup, restoration and retained business/audit evidence |

Allow many-to-many function/journey mappings. Derive live verification from run
evidence; do not maintain a manual package-level verified flag. Validate all
feature, story, journey and branch references, including the 31 `user-story.us-*`
features currently absent from the feature catalogue.

Report these dimensions separately:

- **Definition:** missing, implemented or application-unimplemented.
- **Execution:** not run, passed, failed, blocked, interrupted/inconclusive;
  retain flaky and cleanup outcomes separately.
- **Evidence level:** page-entry, seeded observation, focused function or complete
  connected journey. A denial pass cannot satisfy the positive business action.
- **Provenance:** target/origin, deployed application revision, runner revision
  and dirty-state fingerprint, definitions hash, time, dataset, policy, access
  mode, language and actual role/scope facts. Sensitive details remain private.

The runner checkout's revision is not proof of the staging deployment's revision.
Use existing deployment metadata or an operator-supplied deployment record and
record its source. Unknown remote revision remains a provenance gap. Do not add
a public diagnostic endpoint to obtain it.

Coverage must filter by target and compatible deployment/definitions, use actual
timestamps, exclude synthetic harness runs, preserve every matrix branch, and
distinguish latest attempt from last successful run. Older passes cannot hide
later failures. Count ever-executed cases from history. Local evidence cannot
complete staging acceptance, and selecting all existing journeys cannot prove
coverage of missing definitions.

## 4. Implementation milestones

Milestones are ordered deliverables with exit checks. Calendar estimates should
follow the M0 action inventory; 31 story names do not bound the number of branches.

### M0 — Inventory and staging readiness

**Dependencies:** none. **Deliverable:** the revision-specific acceptance backlog.

1. Reconcile effective routes, controllers, screens, forms/dialogs, search/filter,
   uploads/downloads/exports and background consumers across all 15 package
   directories. Include required native integration and actions beyond the US index.
2. Populate the function registry with positive, validation, alternative lifecycle,
   authorization and replay branches. Record missing application behavior explicitly.
3. Verify the selected target's deployment, schema, nonproduction identity, mode,
   mail sink, workers, fixture version, master data, ordinary actors and readiness.
4. Discover actors and records using the selected target's protected account
   download, My access and normal catalogue/queues. Execute and verify all live
   stories through the interface, as explicitly required by the owner on
   25 October. No database connection, tunnel or server observation endpoint is
   required. Reopen screens and compare cross-role views/registers/history.
5. Designate fictional offices/users/company scopes and shared-setting restoration.
   Coordinate a run window without conflicting population, wipe or account changes.

**Exit:** all installed packages/functions and US-01–31 are mapped; every prerequisite
is verified or specifically blocked. Interface-unobservable facts are reported
as evidence limitations. Earlier database-observation prerequisites in historical
runner records are superseded by the owner's interface-only requirement.

### M1 — Harness correctness, provenance and completeness reporting

**Dependencies:** M0 inventory. **Deliverable:** a runner that exposes omissions.

1. Align schemas, semantic validation and execution. Existing drift includes
   cleanup policy values used by journeys but missing from the schema, and
   `preconditions`/`postconditions` declared in schema but not expanded by the
   planner. Migrate compatible definitions, enforce schemas, and execute required
   checks or reject unsupported fields. Do not accept silently ignored assertions.
2. Validate observation registration against implemented adapters, argument types
   and output shape. Some named contracts lack adapter branches; catch this before
   advertising support or starting mutations.
3. Extend actor/record resolution for distinct approvers, multiple offices/companies,
   programme teams and newly created objects. Recheck authority after handover,
   revocation and context change without relogging to hide a next-request defect.
4. Implement section 3 in CLI, HTML and dashboard. Show required-but-unselected,
   missing, stale and skipped branches. Keep acceptance completeness distinct from
   the existing selected-run exit codes.
5. Record identities immediately after creation. If saving succeeds before capture
   fails, reconcile marked objects read-only so retained effects are still reported.
6. Serialize conflicting writes and recheck pinned fixtures/state. Before unattended
   shared staging runs, implement mutual exclusion recognized by runner and
   population/wipe operations, with expiry/crash recovery and isolated concurrency
   tests. A local dashboard lock is insufficient; use a supervised quiet window
   until the shared coordination exists.

**Files:** schemas, validator/planner/interpreter/loader, target bridges, CLI,
reporters/dashboard and harness tests. Lifecycle integration belongs at the
existing experimentation lock boundary; design any targeted migration separately.

**Exit:** synthetic tests catch schema drift, missing observations, ignored checks,
wrong assertions, skipped/missing branches, cross-target/stale results, matrix
collapse, leaked secrets and interruption/cleanup failures. Existing correct
journeys retain their protection and meaning after migration.

### M2 — Receipt to requester acknowledgement

**Dependencies:** M1 and suitable fictional prerequisites. **Deliverables:** proposed
`requests.stock-to-acknowledgement-bulk` and `requests.stock-to-acknowledgement-asset`.

- Implement section 1's full sequence as self-contained journeys, starting from
  menus/list/search and following normal forms and confirmation dialogs.
- Pin office/product/employee/storekeeper/approvers and actual policy. Employee
  must request the item just received; storekeeper must issue that same captured
  stock/asset. Capture request batch, request and all line identities.
- At each handoff, next actor finds the object in their queue, opens it, checks
  identity/quantities, acts and verifies the result. Revisit requester and previous
  actors' views; inspect the issue register, linked document and asset/history.
- Extend `request.state` beyond its current first-line summary: all lines and
  quantities, decisions, fulfillment, reservation, acknowledgement, notices and
  linked documents. Add native custody/history and bulk stock/ledger observations.
- Compare declared input deltas against current item contracts; check on-hand,
  reserved and available separately, including reservation release after issue.

**Files:** request/storeops flows, UI contracts, subtests, capabilities, record
resolution and observations; proposed `core-workflows` suite.

**Exit:** supported bulk and serialized paths pass locally and then on staging
when its observation prerequisite is ready, across genuine distinct roles.
Report consumer gaps such as incomplete serialized-ledger integration rather
than inventing movements. Retain and list receipts/issues/requests/assets/audits.

### M3 — Stock/request alternatives and failure paths

**Dependencies:** M2. **Deliverable:** explicit cases for all supported branches.

- Basket add/update/remove, invalid/empty/expired/dirty basket, duplicate submission
  and mixed-policy batches with correct ownership and item linkage.
- Primary-only, two-stage and automatic decisions; rejection/reduction/reason,
  threshold escalation and self/same-person two-stage decision refusal.
- Partial then final issue, remainder closure, supported substitution, insufficient
  stock/over-issue and already-assigned/unavailable serial refusal.
- Allowed withdrawal, acknowledgement/repeat acknowledgement, Request again,
  bulk return intent → storekeeper draft → physical receipt posting, and native
  serialized check-in with custody/history.
- Receipt/issue required references/metadata/serial validation, draft resume/cancel/
  takeover, posting/reposting/edit-state rules; supported openings, adjustments
  and paired transfers with source/destination reconciliation.
- Register/kardex/fulfillment register, filters/pagination, print, attachment
  upload/download/removal and authorized evidence access for the resulting objects.

**Exit:** every M0 stock/request branch is mapped and tested; failures leave no
unintended business writes/reservations/custody changes. Do not assume component/
license requests exist when the installed type contract excludes them; test their
supported inventory operations separately. Use isolated MySQL tests to establish
concurrency properties; sequential browser actions or SQLite cannot prove them.

### M4 — Tracking programme to delivery and report/map

**Dependencies:** M1 and M2 receipt flows. **Deliverables:** proposed
`tracking.programme-to-delivery` plus focused tracking branch cases.

1. Programme administrator creates/edits a marked initiative and eligible funding
   configuration; verify reuse and referenced funding deletion refusal.
2. Authorized manager assigns head/officer/support staff. Those users open the
   programme/team; test supported assignment removal and scope boundaries.
3. Head/officer creates a task/code with fiscal profile, geography, participating
   offices and delivery matrix; save/reload, check uniqueness, reject incomplete
   activation, upload synthetic evidence and activate valid configuration.
4. Participating office storekeeper finds that active code in receipt selection,
   posts a linked receipt and opens the delivery detail.
5. Office/programme viewers revisit task sheet, progress report, totals and map.
   Independently verify delivery quantities/values, provenance and allocations.
6. Exercise retrospective association/duplicate refusal, authorized private files,
   in-geography but unallocated office, unrelated geography/company and archive.
   Follow current eligibility rules; do not invent an absent approval stage.

**Files:** tracking flows/contracts/subtests/suite and lifecycle/delivery/participant
observations and resolvers; actual consumer geo-view contracts.

**Exit:** complete delivery works across programme and office roles, facts are not
duplicated, and every other supported tracking action has a separate result.

### M5 — Office, staff, memberships, context and access

**Dependencies:** M1; M2/M3 consumers for clearance obligations.

- Directory/geography → office provision → administrator/duty assignment → staff
  invite/join/onboarding → starter job terminal result → readiness → employee work.
- Join approve/reject/cancel; onboarding assign/reject/history and affected user's
  next request; duty propose/accept/decline/cancel with authority and notice checks.
- Switch active offices and log in as another user in the same browser; verify
  no stale context. Refuse expired/inactive membership and out-of-scope actions.
- Leaving blocked by custody/duties/requests → resolve obligations through actual
  workflows → request/sign-off; supported withdrawal and destination behavior.
- Access request → administrator approve/decline → next-request permission;
  cover expiry/revocation, no self-review, matrix/export, audit and shadow reports.
- Suspend/resume/relocate a designated office; verify staff intake and jurisdiction.
  Exercise administrator delegation and protected emergency changes using actual
  review/reason/confirmation on designated fictional users/scopes.

**Exit:** successful actions and denials are both covered, next-request consumers
reflect authority changes, home assignment remains correct, and temporary grants/
settings are expired/restored. Unsupported closure/merge or transfer-wizard behavior
remains an application gap, not a passed alternate path.

### M6 — Catalogue, product/request/scope rules and metadata

**Dependencies:** M1 and M2 downstream consumers.

- Discover/adopt/copy/archive/restore in authorized office/company scope; deny
  ordinary office users' company-wide mutation and foreign object IDs.
- Supported mapping/collection create/edit/publish → office adoption → starter
  consumer result, with protected review where required.
- Exact supported master bundle analyze/validate → review/reason/typed confirmation
  → execute/history → consumer result. Use a designated disposable installation
  when national replacement cannot be safely scoped. Preview alone is partial coverage.
- Product/request/scope rule draft/edit/simulate/review/publish/assign/unassign and
  mappings; verify another actor encounters the intended validation/selector
  change. Restore shared baselines through supported protected operations.
- Metadata health and authorized convergence for a designated inconsistency;
  await completion and test downstream form/materialization. Do not corrupt
  unrelated data just to create the precondition.
- Changed/expired/replayed review, unsupported bundle and wrong actor/scope return
  safe errors, preserve state and retain appropriate failure audit.

**Exit:** reviewed execution and downstream behavior are proven separately;
restoration is verified. National replacement is not routine shared-server setup.

### M7 — Remaining package functions and native integration

**Dependencies:** M1 and relevant M2/M4/M5/M6 flows.

| Work item | Required actions and branches |
| --- | --- |
| Committee | Authorized ministry policy prerequisites; synthetic signed order intake/save/resume/discard; constitute/appoint/activate; member declaration/private files; supported replace/release/amend/reconstitute/extend/suspend/resume/dissolve/correct; transfer, historical roster/print and expiry/clearance effects. Do not enable national illustrative templates as a shortcut or claim receipt inspection exists |
| Asset tagging | Audit/reuse existing nine journeys and diagnose the latest saved GRN issuance failure. Cover Finance/defaults, company codes, office setup/enablement, Govt./standard receipt tags, warnings/invalid inputs, lookup/export, pause/resume and duplicate protection. Preserve permanent-tag retention/wipe restrictions and existing gates |
| Geo-view and geo-areas | Real office/receipt/programme sources: search/area selection, drill/back, layer/filter/detail, flat mode, scoped totals, empty/error states and exports. Verify changes caused by connected journeys, not only synthetic map fixtures |
| Theming and navigation | Personal choice/mode persistence, scoped assignments/default/enforcement/restoration; context/menu/inbox/basket during work. Relevant keyboard, Bangla/English and narrow-screen interaction; distinguish usability checks from WCAG certification |
| Experimentation | Marked owned dataset populate/progress/ready/accounts; supported resume/recover/cleanup on dedicated fixtures, wipe preview/confirmation/result and retained-data guards; worker terminal outcomes. Preserve existing user datasets/audits. Installation-wide reset remains separately scoped destructive work |
| Required Snipe-IT | Receipt materialization/checkout/check-in/history, supported maintenance create/edit/cleanup and supported inventory import validation/success. Verify GovStore scope and consumer effects; avoid unrelated upstream expansion |

**Exit:** all remaining supported functions have cases/results; irreversible effects
are retained explicitly, and cleanup never wipes another run's or the user's data.

### M8 — Full staging execution and defect closure

**Dependencies:** M0–M7 definitions and required environment prerequisites.

1. Validate contracts/plans, run applicable isolated checks, and freeze deployment
   and definition fingerprints. Preflight and prepare only designated fixtures.
2. Execute core workflows, stock/request branches, tracking, people/access,
   administration and remaining functions. Serialize mutations sharing actors,
   stock, office state, policies, grants or installation gates.
3. Exercise shadow/enforce expectations in appropriately configured nonproduction
   deployments/windows. Do not silently change shared access mode; one mode's
   pass does not establish the other's behavior.
4. Classify failures, retain evidence, fix the owning application/fixture/harness
   defect and rerun affected cases plus downstream regression. No automatic
   write retries; read-only retries remain marked flaky.
5. Repeat core connected journeys with fresh marked records and run IDs to prove
   repeatability without relying on earlier retained state.
6. Deliver package/function/story/branch results, role handoffs, before/after effects,
   retained records and restoration outcomes for this staging deployment.

**Exit:** section 8 holds. Missing definitions, application gaps, latest failures
and blocked observation/mode/fixture prerequisites keep full acceptance incomplete.

## 5. All-story traceability checklist

Every row remains open until its required functions/branches have current action
evidence. Existing story entry checks remain useful smoke tests and cannot close it.

| Story | Actions to demonstrate | Milestone |
| --- | --- | --- |
| US-01 | Directory import and geography search/selection/jurisdiction | M5, M6 |
| US-02 | Provision office, registry/hub persistence, invalid/foreign area refusal | M5 |
| US-03 | Office details/roles, staff invite/review and readiness | M5 |
| US-04 | Onboarding decisions/assignment and affected user's next access | M5 |
| US-05 | Working-office switch, scoped consumers and cross-user session reset | M5 |
| US-06 | Receipt draft/reload/validation/post/print and stock/asset effects | M2, M3 |
| US-07 | Catalogue/basket interactions, submission and requester outcome | M2, M3 |
| US-08 | Primary/final/automatic decisions, reduce/reject and actor constraints | M2, M3 |
| US-09 | Fulfillment, physical unit selection, partial/final issue and acknowledgement | M2, M3 |
| US-10 | Direct issue and insufficient-stock refusal; atomic stock effects | M3 |
| US-11 | Native serialized check-in, custody/history and unauthorized refusal | M3 |
| US-12 | Join/release/withdraw/sign-off/switch; resolve blocked clearance through work | M5 |
| US-13 | Discover/adopt/copy/archive/restore and office/company scope | M6 |
| US-14 | Rule draft/simulate/review/publish/assign and consumer validation | M6 |
| US-15 | Duty handover accept/decline/cancel, effective authority and notices | M5 |
| US-16 | Reviewed emergency changes and scope/replay refusal | M5 |
| US-17 | Office/company/geographic/ownership boundaries on reads and writes | M2–M7 |
| US-18 | Register/kardex/filter/print reconciled to new movements | M2, M3 |
| US-19 | Supported maintenance create/edit/cleanup and scoped asset history | M7 |
| US-20 | Supported import validation, valid marked rows and consumer inventory | M7 |
| US-21 | Initiative/funding create/edit/reuse and referenced-deletion refusal | M4 |
| US-22 | Team/task/activation, scoped delivery, files/report/retrospective/archive | M4 |
| US-23 | Exact master bundle analyze/review/execute/history and bad/replay refusal | M6 |
| US-24 | Protected mappings/collections and downstream adoption/consumption | M6 |
| US-25 | Scope policy/mapping changes and actual selector/boundary effects | M6 |
| US-26 | Health/convergence completion and downstream metadata correctness | M6 |
| US-27 | Administrator grant/remove/restore and resulting authority | M5 |
| US-28 | Starter setup/job completion/retry and actual office catalogue | M5, M6 |
| US-29 | Supported committee/order/member lifecycle, history/files/boundaries | M7 |
| US-30 | Personal/scoped appearance save, effective precedence and restoration | M7 |
| US-31 | Office suspend/resume/relocate and staff/intake/scope consequences | M5 |

Tagging, map controls, access governance and experimentation also require their
full function inventory; their scope is not limited by the 31-story index.

## 6. Code areas and deliverables

| Area | Planned changes |
| --- | --- |
| `tests/live/functional-coverage.json`, schemas and feature catalogue | Complete function/branch registry, source/story mappings, applicability and evidence requirements |
| `core/validator.mjs`, `core/planner.mjs`, interpreter/loader | Schema/semantic/execution agreement, explicit branches/preconditions and actual result states |
| `tests/live/flows/`, UI contracts, browser actions | Reusable normal UI flows; missing semantic locators/routes and only demonstrated widget/response/download needs |
| Interface inspection contracts and browser fixture resolver | All visible request/stock/custody/history and tracking screens; explicit gaps for facts not exposed to users |
| Target/browser bridges | Matching target/origin and UI identity, role and record discovery; no database connection or staging-to-local fallback |
| CLI/reporters/dashboard | Target/deployment/definition/evidence filtering, completeness/skip/stale/blocked reporting, ownership and restoration |
| Subtests/suites | Separate entry, observation, functional and connected suites; preserve correct existing IDs. Proposed suite IDs are available only after implementation |
| Harness and owning-package tests | False-pass/schema/reporting/target/no-write/cleanup/interference coverage; isolated security/consumer checks for any actual application fix |
| Documentation | Exact implemented commands, target/fixture prerequisites and sanitized fresh verification with open gaps |

Application/lifecycle coordination changes must be separately reviewable from test
definitions. No public test endpoint, scheduler or broad migration is assumed.

## 7. Verification and operational rules

Use relevant harness checks during implementation:

```text
npm run test:live -- validate
npm run test:live:contract
npm run test:live:targets
npm run test:live:bridge
```

Inspect the bridge no-write test's guards before running it. For protection
changes, run the required focused suite; use the combined isolated GovStore suite
when changes span consumers. Resolve the documented installed PHP 8.4 executable:

```text
php vendor/bin/phpunit tests/Feature/GovStore/G1AuthorizationTest.php
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore
```

For map changes use `node --test "tests/js/geo/*.test.mjs"` and
`php artisan geo-view:doctor`. UI changes also need compiled Blade/PHP syntax and
actual role-based rendering. Follow the [local guide](../testing/local-live-testing.md)
and [security rules](../security/govstore-agent-security.md). Automated database
setup uses isolated schemas, never resets the development/staging business database.

Browser acceptance writes only identifiable authorized fictional records on the
selected nonproduction application, using the existing explicit `--allow-writes`
policy. Credentials are discovered privately; no password/permission rewriting,
membership detachment or protection disabling to make fixtures fit. Use suitable
mail sinks; external-mail overrides are not default flags and in-app assertions
do not prove external delivery.

Keep detailed reports, object identities, downloads and private screenshots under
ignored `storage/app/private/live-tests/<run-id>/`. Clean only through supported
UI workflows; retain audit/permanent evidence. Stop dependent mutations when
restoration fails or shared state becomes uncertain, and report the exact effects.

## 8. Full acceptance completion criteria

- All 15 installed GovStore packages and discovered supported functions have
  validated action/branch coverage, including US-01–31 and additional functions.
- Required connected journeys finish on the selected staging deployment, keeping
  exact identities through genuine role sessions and revisited views.
- Positive, alternative, relevant validation/boundary/replay cases and native
  integration effects have independent persistence evidence; queued work reaches
  terminal results and every declared required branch is accounted for.
- Current staging evidence is distinct from local/synthetic/historical/entry-only
  results. No missing, skipped, blocked or unimplemented case is counted green.
- Core flows pass again on fresh marked records. Latest failures, flaky results,
  application gaps and unverified modes/prerequisites remain visible.
- All created/consumed identities and permanent effects have verified supported
  cleanup or explicit retention, and temporary settings/grants are restored/expired.
- Applicable harness, isolated security/package and rendering checks pass. These
  results do not certify remote CI, WCAG compliance, production readiness or the
  real-world shadow observation period.

Final handover separates **implemented**, **verified on staging** and **still
open**, with private run references and a sanitized tracked summary. Full
acceptance remains incomplete while required gaps remain unresolved.
