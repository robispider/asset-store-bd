# GovStore sample data seeding: gaps and mitigation plan

Date: 23 October 2026 (Asia/Dhaka)  
Status: investigation complete; implementation proposed, not started.

Execution update, 24 October 2026: v2 implementation and fresh verification are
recorded in [the execution record](../verification/govstore-seeding-v2-2026-10-24.md).
The findings and database aggregates below remain the dated pre-change baseline.

## Finding

The sample data system is partially out of date. Extend the dedicated
`packages/gov-store/experimentation` system; do not use the root `DatabaseSeeder`
to update GovStore sample data. The root seeder disables foreign-key checks,
invokes native seeders and truncates tables. It is not the GovStore workflow
orchestrator.

The existing experiment code already submits baskets, processes approvals,
partially or fully issues serialized equipment, posts stock documents and opens
office ledgers. The missing work is complete coverage of current package
features, safe ownership of their records, stronger outcome verification and a
version-aware treatment of old datasets. Increasing row counts alone will not
address those gaps.

## Scope and evidence

Reviewed repository instructions, current experimentation services, package
migrations, relevant workflow services, package documentation and experiment
test source. Inventoried all 15 GovStore package directories, including
asset-tagging, committee, geo-view and theming.

Read-only PDO queries inspected the authorized local `snipeit` database in
read-only transactions. Output was limited to schema, run metadata and aggregate
counts; no credentials, account exports or metadata payloads are retained here.
No seeder, migration, repair, wipe, queue worker or business mutation was run.
Automated tests and live UI flows were not executed for this investigation.

The active ready dataset is run `01a105ed-cc65-7290-974f-9ba842abb6d0`, government
profile, created on 4 October 2026, scenario `bd-government-v1`. Its saved
verification report passed the checks available then. That historical result
does not establish compliance with the current verifier or current workflows.
The dataset has also accumulated subsequent activity: current rows and manifest
entries must not all be attributed to the original seed execution.

Fresh observations on 23 October:

| Observation | Result | Implication |
| --- | --- | --- |
| Dataset offices / offices with storekeeper responsibility | 48 / 44 | Four deliberately incomplete offices remain a useful scenario. |
| Offices with ledger-opening records | 2 of 44 operational offices | 42 operational offices lack the current opening marker. Do not assume an older Ready label means issues can post today. |
| Office profile dimensions | All 48 `office_type=default`, all 48 `office_level=NULL` | Specialized office types and explicit administrative levels are absent. |
| Manifest-owned requests still present | 264 | Request population exists; its coverage is incomplete. |
| Request lines | 176 asset-model, 44 consumable, 44 accessory | No component request adapter exists in the current supported workflow. |
| Issued quantities on these request lines | Asset-model 110; consumable 0; accessory 0 | Bulk request fulfillment is absent from these seeded request lines. |
| Request statuses | 44 pending-primary; 11 pending-final; 121 approved/unstarted; 22 approved/partially-issued; 44 approval=`closed`/fulfillment=`issued`; 22 rejected/closed | Existing outcomes include legacy state semantics. Current `RequestWorkflow::APPROVED` accepts approved/partially-approved, not closed. Preserve and review legacy history rather than relabeling it blindly. |
| Event types on those requests | approved, closed, item_issued, line_rejected, pending_final, rejected | Current submission/automatic-approval, receipt acknowledgement, withdrawal and return events are not represented in this selection. |
| Owned tracking codes | 24 ACTIVE, all activation timestamps approximate | Migration-backfilled dates exist; actual activation provenance is not demonstrated. |
| Tracking delivery/document tables, whole database | Both empty | New receipt provenance and supporting-document scenarios are missing locally. Existing associations/facts are not proof of those newer records. |
| Ledger type aliases within dataset offices | Canonical types plus one OUT each using `App\Models\Accessory` and `App\Models\Consumable` | Historical alias normalization is a separate review prerequisite where encountered during cut-over. Do not overwrite movements to make opening succeed. |

Committee, access, typed metadata and managed tag records also exist elsewhere in
the installation. Their presence does not prove the experimentation seed creates
or owns them.

## Gaps, in implementation order

Priority P1 means resolve before claiming a complete current seed. P2 completes
feature variety and usability after the stock and ownership foundation.

| ID | Priority | Gap and evidence | Mitigation |
| --- | --- | --- | --- |
| S01 | P1 | **No version-aware refresh of existing Ready data.** `ExperimentManager` still stamps `bd-government-v1`. Resume accepts failed populations, not ready datasets. `unit()` skips saved checkpoints; changing the code does not upgrade completed units. | Introduce a new scenario version and per-module versions/checks. Add read-only audit/revalidation for an existing run. Present old verification as dated/stale. Prefer creating a new v2 dataset; any in-place upgrade needs its own reviewed ownership and history plan. |
| S02 | P1 | **New tables have no experiment lifecycle classification.** See the 30 current tables below absent from both `RecordRegistry::TABLES` and `ExperimentWiper::PRESERVED`. Typed metadata uses polymorphic subjects the wiper does not currently model. New tables can block reset or escape dataset cleanup. | Classify every table as owned, derived, shared master, retained audit/counter, infrastructure or legacy. Add explicit subject/scope relationships, primary keys, deletion ordering and private-file adapters before adding generators. Unknown populated tables must continue to block reset. |
| S03 | P1 | **Actor execution uses an older context pattern.** `ActorContext::run()` creates and rebinds a new `TenantContext` and accepts a supplied role string. Current `TenantExecution` resets/restores the shared object and re-resolves live identity, membership and ability. | Move scoped seed actions to current trusted execution boundaries. Resolve actual assigned roles; preserve shared-object identity and restore context/authentication after success or failure. Keep global maintenance admission separate from office actions. |
| S04 | P1 | **Opening exists, but old data and nonzero opening coverage are incomplete.** `ExperimentLedgerOpening` already locks rows, authorizes the keeper, posts native-minus-ledger differences and avoids changing native stock. Fresh fixtures initially have component qty 1, other bulk qty 0 and no received hardware, so most opening cases are zero. | Preserve this implementation and add meaningful nonzero opening recipes for each supported stock type, zero and legacy-resume cases, and complete coverage for every operational office. Verify running balances and native remaining stock. Audit older offices first; do not automatically rewrite their posted history. |
| S05 | P1 | **Request actions stop short of current workflows.** `requests()` fulfills only `asset_model`. No bulk issue, return, requester acknowledgement, withdrawal, force-close, substitution, threshold escalation or mixed-policy submitted basket recipe exists. All requests ask for qty 2 and request type other. | Use named action recipes through `BasketService`, `ApprovalService`, `FulfillmentService`, `RequesterService` and `RequestReturnService`. Cover each supported request type and important state outcome explicitly; see the action matrix below. |
| S06 | P1 | **Stock examples omit newer document behavior.** Seeded document creation handles receipt/issue and draft/ready/cancelled/posted states, but not real transfer/adjustment/takeover recipes. Header uses `Government Purchase`, while the current purchase-specific supplier requirement checks `Purchase`; no supplier is supplied by this helper. | Use valid purchase modes and supplier attribution, normal validation/checklists and posting. Add supported transfers, positive/negative adjustments with valid source/reason, issue recipient, return receipt and draft takeover examples. Verify effects across both offices and keep original drafter versus actual poster. |
| S07 | P1 | **Finance, typed metadata and managed asset tags are not scenarios.** Seed receipts provide experiment strings as asset tags; no typed company code lists, office facts, enablement or product Finance defaults are generated. New registration evidence and tag sequences are not asserted. | Use `MetadataValueStore`, classification assignment contracts and the tag issuer. Add explicit nonproduction rehearsal with complete office prerequisites, valid Finance references, Govt. Tag and standard-tag fallback receipts. Preserve installation gates and never silently enable issuance for unrelated offices or change national masters. |
| S08 | P1 | **Tracking setup bypasses current action provenance.** Seed creates ACTIVE codes directly, omitting `activated_at`; initiatives also change state directly. All codes have one specificity and targets are category-based. Receipt listeners can now derive modern deliveries, but the verifier does not require their lineage. | Create/activate through the current authorized action path; extract a shared action only if an essential action exists solely in a controller. Seed varied supported scopes/targets, tracked bulk and serialized receipts, authentic activation time, private supporting documents, movement/item provenance and report reconciliation. |
| S09 | P2 | **Committee remains excluded although runtime now exists.** The scenario imports no committee services. The dedicated committee seeder supplies illustrative inactive templates, not operational committees. | Add fictional ministry-owned sample types/bindings and authorized draft/active/lifecycle scenarios through committee services, with private clearly fictional order evidence, seats/tenures, scope, health and hash-ledger checks. Keep national templates inactive and do not claim receipt inspection: that consumer is still unimplemented. |
| S10 | P2 | **Organization, membership, access and onboarding coverage is narrow.** Offices lack parent-office scenarios, explicit level/type and intake/readiness variety. Handshakes are only proposed. New membership/onboarding notices and events are not required by seed verification. No access request/grant lifecycle is generated. | Add school/hospital/ICT/default office scenarios, administrative levels and valid parent relationships, completed/rejected/cancelled handovers, membership destination/expiry/override examples, onboarding decisions and temporary access request/review/expiry/revocation. Use real reviewers and current scope checks; preserve audit records. |
| S11 | P2 | **Geography/map/theme features have no explicit coverage.** `ReferenceSetup` reads the older core geography CSV only. It does not check the BBS dataset fingerprint/codes/localities. Geo-view has no business tables of its own; the seed does not verify its source outputs. Theme preferences/assignments are not represented. | Require the package's versioned geographic dataset and consume its real IDs. Add geographic distributions that exercise current map presets and administrative-level distinctions; verify maps against source totals and denied scopes. Seed fixture-owned theme preference/assignment examples through current authorized paths. Preserve global preferences and shared map/master setup. |
| S12 | P1 | **Ready verification is too shallow.** Current verifier checks counts, ownership, role/geography relationships, basic request ownership, bulk net quantities and some boundaries. It does not assert recipe outcomes, request event order, reservation release, returned stock, serial checkouts, transfer conservation, tag evidence, committee integrity or report totals. Bulk stock checks use raw qty rather than systematically comparing native remaining stock; opening checks mainly validate the marker/document relationship. | Add per-recipe expected outcomes and per-module invariants. Derive expected document/event totals from action recipes, including system issue and return documents. Compare current facts, ownership and side effects, not only requested row counts. A required unsupported/failed recipe must prevent Ready. |
| S13 | P2 | **Limitations and tests lag feature delivery.** Experiment README/plan/verifier still describe bulk fulfillment and native audit as unavailable, and committee as absent. Current bulk issuer uses canonical ledger types and audit listener uses `created_by`; focused workflow tests cover these integrations. Laptop custom-field materialization remains limited in current creation code and must be investigated separately. | Replace blanket exclusions with verified capability status. Extend experiment lifecycle tests with the new invariants, retaining focused package suites. Report unsupported cases honestly rather than bypassing their required metadata or manufacturing final states. |
| S14 | P2 | **Human-facing names begin with long technical markers.** `ExperimentManager` generates an `EXP-` prefix plus 16 random characters. Scenario helpers put that prefix before many category, product, model and supplier names; other labels also expose lengthy experiment identifiers. The user requested smaller ID markers after meaningful words. | Put the meaningful name first and append a short collision-safe dataset/entity marker only where needed, such as `A4 Paper · D7-03`. Keep full primary keys, ownership identities, document numbering and audit links internally. Make this naming contract part of v2 rather than renaming existing user-explored records automatically. |

## Package coverage contract

All packages need an explicit disposition, even when their correct result is
reuse of shared setup or verification of derived data rather than inserted rows.
Native inventory tables remain backing storage used through GovStore services;
they are not a reason to invoke broad native demo seeders.

| Package | Current seed coverage | Required addition or disposition |
| --- | --- | --- |
| organization | Companies, directory, offices, office admin, company admins, ICT jurisdiction, activity | Office hierarchy, types/levels, intake/readiness states; verify all related profiles/activity. Legacy `gov_location_roles` is not a new role source. |
| office-membership | Active memberships/responsibilities, one secondary membership, proposed handshakes | Accepted/rejected/cancelled handshakes, role assignments/history, notices, destination/expiry and controlled override. Verification tokens must be generated only by applicable real flows. |
| user-onboarding | User observer, assigned people, waiting examples | Decision history/notices, ownership/geographic reassignment, rejection/defer cases supported by current services. |
| tenant-scope | Category adoption mappings, scoped fixture execution | Correct shared context execution; access request/review/grant examples and retained audit; preserve shared `gov_tenant_scopes` configuration. |
| classification | Shared selected catalog nodes/definitions, owned categories/governance/mappings/collections/adoptions | Office starter/bulk adoption, Finance-master prerequisite and product mapping/defaults, controlled enrichment/synonym examples where supported. Keep import history authentic and retained. |
| metadata | Model convergence and model metadata states, shared field definitions | Typed `gov_metadata_values` subjects/namespaces/revisions/scopes and safe cleanup; verify model metadata health and supported per-unit fields. |
| asset-tagging | Existing asset-registration bridge populated by receipts | Issuance rehearsal, code lists, office facts, Finance default, enabled/disabled and fallback cases; verify tag format/snapshot and preserve all sequence counters. |
| store-operations | Opening, receipt/issue, document lines/meta/references/attachments/timelines, registrations and movements; simplified profiles | Current validation/purchase/supplier flow, transfers, adjustments, return receipts, takeover/notices, meaningful opening stock, stock-card/register/unit-register reconciliation. Legacy receipt/issue projections should arise only from applicable current actions. |
| custom-requests | Draft baskets, one-line submissions, policies, approvals/rejections, serial issues and events | Action matrix below; include mixed-policy submission batches, threshold explanations, independent routing and current reservation behavior. Legacy request tables should not receive fake current workflow rows. |
| tracking | Teams, initiatives/codes, scopes/targets/allocations; listener-derived associations/facts/timeline | Current activation and receipt provenance, varied scenarios, private documents, monitoring/maps/programme reports; caches are derived/rebuilt. |
| committee | No experiment-owned runtime scenarios | Registry lifecycle and evidence through current services; shared templates/catalogue separated from fictional fixtures; no invented inspection consumer. |
| geo-areas | Core CSV reference insert and hierarchy checks | Verify/reuse BBS codes, locality dataset version/checksum and stable IDs through `GeoAreaDatasetSync` prerequisites. Shared masters are preserved. |
| geo-view | Incidental use of the offices/tracking data | No dedicated business-table seeder. Validate registered sources/presets against fixture facts, geographic grain, empty/partial data and restricted viewers. |
| theming | No explicit scenario | Fixture user preferences and company/office assignments, light/dark/system examples and scope precedence. Preserve organization-wide existing assignments. |
| experimentation | Run/job/action/ownership/checkpoint infrastructure, resume, wipe and backup | Versioned module/recipe manifest, read-only audit/revalidate, current schema preflight, stale status, expanded ownership/file adapters and repeatability checks. Jobs/failures/review-consumption rows must arise from real operations, not fabricated sample history. |

### Tables absent from both lifecycle lists

These 30 tables are present in the local schema and are absent from both current
experiment ownership and preservation lists. Absence is a lifecycle gap, not a
directive to delete or populate every table.

| Table(s) | Proposed treatment |
| --- | --- |
| `gov_metadata_values` | Own only exact fixture subjects/namespaces. Add polymorphic subject/scope dependency handling; preserve all unrelated settings. |
| `gov_finance_items` | Shared authoritative master prerequisite; preserve. Any fictional extension must use an explicitly supported isolated scope/workflow. |
| `gov_asset_tag_sequences` | Retain counters permanently through dataset cleanup/reset; never recycle issued prefixes/numbers. |
| `gov_document_notices` | Capture notices derived from owned document actions; specify retention and safe subject cleanup. |
| `gov_catalog_import_history` | Preserve genuine import provenance. Use current exact-bundle review only when exercising import; do not synthesize national publication. |
| `gov_tenant_scopes` | Preserve current shared configuration; validate required setup. |
| `gov_access_requests`, `gov_access_grants`, `gov_access_notices` | Explicit fixture lifecycle adapters; expire/revoke operational grants appropriately and retain required decision evidence. |
| `gov_access_events`, `gov_access_review_uses` | Retained security/replay evidence. Define references/tombstones so cleanup cannot erase audit or reopen consumed reviews. |
| `gs_theme_preferences`, `gs_theme_assignments` | Own fixture user/company/office settings with explicit scope relationships; preserve all shared/unrelated assignments. |
| `gov_committees`, `gov_committee_orders`, `gov_committee_scopes`, `gov_committee_external_members`, `gov_committee_seats`, `gov_committee_tenures`, `gov_committee_active_slots`, `gov_committee_health`, `gov_committee_order_intakes`, `gov_committee_reminder_dismissals` | Model fixture business and derived ownership through exact committee/office/user references; handle private order/intake files and immutable evidence deliberately. |
| `gov_committee_seat_roles`, `gov_committee_types`, `gov_committee_purpose_bindings` | Preserve shared national catalogues; explicitly own only fictional ministry-scoped additions and links. Never activate the illustrative national defaults as a side effect. |
| `gov_committee_ledger`, `gov_committee_reviews`, `gov_committee_sequences`, `gov_committee_catalog_mutexes` | Define authoritative audit/review retention before cleanup, preserve monotonic sequences and shared mutexes. Do not blindly add these to deletable fixtures. |

Installed legacy `gov_classification_*` tables also need an explicit legacy/preserve
decision during schema audit. Historical create migrations mention
`gov_location_roles` and `custom_item_requests`; neither is a reason to revive
obsolete role/request writes. Determine supported installed schema separately
from the union of historical migration definitions.

## Required action recipes

Use real actors and services for the action. A request whose status was directly
inserted as approved is not an approval scenario. Direct final-state updates are
acceptable only for ordinary static reference fixtures whose package contract
explicitly allows them, not workflow evidence.

| Scenario | Actions to execute | Expected evidence |
| --- | --- | --- |
| Basket / submission | Add/update/remove items; review and submit a mixed basket | Office-scoped draft, submission batch, split requests when policies differ, resolved policy reasons, submitted/auto-approved events. |
| Primary-only | Employee submits; actual independent primary approves | Correct actor, decision, quantity and reservation; approved request remains available to the storekeeper. |
| Two-stage | Submit; primary approves; final approves | Pending-final checkpoint, both decisions/actors and final approved quantities. |
| Threshold escalation | Quantity/value/unknown-cost cases through policy resolution | Two-stage policy and saved explanation when thresholds require it. |
| Rejection / reduction | Reject lines or reduce approved quantity with supported reasons | Line decisions, partial/full rejection, remaining quantities and notices. |
| Automatic approval | Submit against a valid AUTO_APPROVE policy | Actual auto-approved event and reservation, with no invented approver. |
| Serial fulfillment | Issue selected free serials partially; complete another request | Native asset assignment and checkout audit, request issue events, supported issue projection, correct remaining availability. |
| Bulk fulfillment | Approve and issue consumables and accessories, including partial then final issue | Canonical ledger OUT, exactly one native decrement, issue documents, actual keeper/requester attribution and reservation release. |
| Alternative supply | Approve; issue a supported same-category/cost alternative | Substitution event and correct fulfilled identity; original/alternative stock reconciles. |
| Close remainder | Partially issue; close remaining approved quantity with reason/code | Closed event, preserved issued quantity, cancelled remainder, zero remaining reservation. |
| Requester acknowledgement | Fully issue; requester confirms receipt | `received_at` and received event; no second stock movement. |
| Withdrawal | Submit a pending request; requester withdraws before a decision | Cancelled/closed state, cancelled lines and released reservations. |
| Return | Issue bulk stock; requester requests return; keeper drafts receipt; validate/evidence/post it | Original issue unchanged, linked return document and request events; returned stock increases once. Serialized returns use native check-in, not bulk receipt. |
| Waiting / insufficient stock | Leave an approved request waiting; attempt invalid over-issue only in isolated tests | Useful queue example; tests prove refusal and rollback rather than persisting a fabricated failed issue. |
| Membership / onboarding / access | Execute proposed and completed decisions with actual authorized actors | Membership/responsibility changes, decision notices/history, temporary grants and retained audits. |
| Committee / tracking | Execute supported constitution/activation and tracking activation/receipt actions | Authentic state transitions, private evidence, roster/ledger integrity and receipt lineage. |

Every core request recipe should cover `asset_model`, `consumable` and `accessory`
where the operation is supported. Components and licenses are inventory/master
scenarios, not requestable types in current `RequestWorkflow::TYPES`. Do not
force unsupported component/license fulfillment into tables.

## Opening-balance contract

For every operational office:

1. Create identifiable fictional pre-existing stock through supported fixture
   setup. Include both zero and nonzero stock, components, consumables,
   accessories and eligible unassigned asset-model units. Shared model/category
   definitions do not establish office stock by themselves.
2. Before the first ordinary ledger movement, call the authorized opening flow
   for the office's actual storekeeper. Keep the existing owned-office guard,
   row locks, transaction and canonical type checks.
3. Calculate `opening difference = native on-hand at cut-over - existing ledger
   net movements`, per office/type/item. Post only that difference. Opening must
   not increment/decrement native quantities or create duplicate native assets.
4. Keep one posted opening marker/document per office with correct company,
   creator, drafter, poster, date, items, movements and timeline. Zero-difference
   openings remain valid without invented quantities.
5. Execute receipts, issues, transfers, adjustments and returns afterward. Check
   `closing = opening + receipts + transfer-in + positive adjustments + returns
   - issues - transfer-out - negative adjustments`, with quantities partitioned
   by office/type/item. For serialized hardware also reconcile unit assignments
   and native availability through the supported register rather than treating
   bulk quantity arithmetic as sufficient.
6. Repeat/resume without duplicate opening, receipt, issue, asset or event. Keep
   existing posted legacy component receipts unchanged. Refuse inconsistent or
   foreign history and roll back the whole failed unit.
7. For the existing v1 dataset, first audit all 44 operational offices and
   historical aliases/balance chains. The observed 42 missing markers need a
   reviewed cut-over/reconciliation plan if this dataset is to be upgraded.
   A new v2 population does not authorize altering the old one.

The opening helper already implements much of steps 2–4 and resume protection.
Extend and test it rather than replacing it with raw ledger inserts.

## Mitigation delivery plan

| Phase | Deliverable | Completion condition |
| --- | --- | --- |
| 1. Coverage and lifecycle foundation | Versioned module/recipe manifest; table/column/index/config preflight; supported/optional/unsupported disposition; read-only run audit/revalidate; ownership and preservation/file adapters | Every current package table and critical added column has a disposition. Stale historical verification is visible. Unknown dependencies still fail safely. Context/actor identity is preserved. |
| 2. Stock and opening | Nonzero opening recipes, valid supplier/purchase receipts, bulk and serialized inventory, transfer/adjustment/return/takeover prerequisites | All operational offices open once. Native remaining stock and ledger/unit registers reconcile; rollback, retry and numbering invariants pass. |
| 3. Request action coverage | Explicit recipes from the action matrix, current approvals/routing/reasons, fulfillment and request events | Required outcomes exist by recipe, type and actor; reservations/stock/audit/linked documents agree. No modulo-based loop is accepted as proof of coverage. |
| 4. New package scenarios | Typed metadata/Finance/tag rehearsal, committee registry, current tracking, organization/membership/access/onboarding, BBS/map/theme cases | Each enabled module demonstrates its real current actions and related tables, or reports an explicit supported limitation. Technical locks/caches/counters arise naturally. |
| 5. Verification and lifecycle tests | Isolated test runs; failure/resume, rerun, coexistence, dataset wipe and supporting-file cleanup; current docs/UI reporting | New run reaches Ready only after recipe/module checks. A second run cannot claim the first run's records; cleanup preserves unrelated data, masters, audit/replay evidence and counters. |
| 6. Local rollout | Validate quick first, government second, large for volume; restart only the dedicated worker after deployment; review fresh v2 results | Report exact generated counts, actions and checks. Keep v1 data intact unless a separate reviewed upgrade/wipe is selected. |

Human-facing naming contract requested during this investigation: use meaningful
words first, then a compact marker. For example, `A4 Paper · D7-03`,
`District Office · D7-02` or `Sample Supplier · D7-01`. Allocate a short dataset
alias safely and add an entity ordinal only when needed for uniqueness. Keep the
full run UUID/random ownership identity in the manifest, not at the beginning of
visible names. Preserve Bengali/English names, normal document-number contracts
and existing account-login conventions. Check truncation and uniqueness with two
datasets and all three size profiles. Do not rename the existing v1 dataset as
part of a report-only investigation.

Recommended profile policy: Quick must cover the essential stock/request action
recipes with low quantities; Government adds the full supported module/state
matrix and realistic office variety; Large increases volume while keeping the
same invariants. Save recipe targets with the run. Derive document counts from
actions, because fulfillment, transfer and return actions create additional
documents. Avoid fixed totals that force useful workflow evidence to be skipped.

Keep implementation primarily in experimentation adapters and configuration.
Use each package's public services/contracts; if a necessary action is currently
controller-only, propose the smallest shared action refactor with focused tests.
Do not fix unrelated package defects or weaken protections to make seed finish.

## Verification required during implementation

- Experiment suite on its explicitly guarded separate MySQL schema. Read the
  schema-clone helper before execution; it copies installed schema/reference data
  and is not a substitute for fresh-install migration coverage. Recheck new
  configuration, deferred/after-commit listeners and ownership discovery there.
- Meaningful focused tests for bulk/serial fulfillment, request transitions,
  opening/reconciliation, transfer/adjustment, typed metadata/tagging, committee,
  tracking provenance, membership/onboarding and scope failures. Keep tests that
  prove no double decrement, no duplicate posting, preserved attribution and
  rollback, rather than mirroring implementation details.
- Required `G1AuthorizationTest.php` when protections or their execution paths
  change. Combined GovStore suites use direct PHPUnit with increased memory as
  instructed in AGENTS.md; do not modify core `phpunit.xml`.
- Isolated MySQL checks for concurrent opening/posting/tag sequence allocation
  when those paths change; SQLite alone cannot prove locking behavior.
- Geo engine tests, source contract tests and `geo-view:doctor` for affected map
  sources/presets. Validate source totals, visible scopes and empty/unknown areas.
- If UI changes: compiled Blade PHP lint plus representative live rendering,
  bilingual keys and role-specific queue/detail results.
- Lifecycle checks with an unrelated sentinel and a second dataset; reviewed
  wipe preview, retained audit/counter evidence, private file cleanup, stopped
  worker recovery and no stale context after failure. Never reset the existing
  development database to prepare these tests.

## Source map

Primary current implementation:

- [Scenario orchestration and actions](../../packages/gov-store/experimentation/src/Services/BangladeshScenario.php)
- [Opening implementation](../../packages/gov-store/experimentation/src/Services/ExperimentLedgerOpening.php)
- [Current verifier](../../packages/gov-store/experimentation/src/Services/ExperimentVerifier.php)
- [Run admission/version/resume](../../packages/gov-store/experimentation/src/Services/ExperimentManager.php)
- [Ownership registry](../../packages/gov-store/experimentation/src/Services/RecordRegistry.php)
- [Wipe/preservation/dependency/file handling](../../packages/gov-store/experimentation/src/Services/ExperimentWiper.php)
- [Older actor-context adapter](../../packages/gov-store/experimentation/src/Services/ActorContext.php)
- [Current tenant execution contract](../../packages/gov-store/tenant-scope/src/Services/TenantExecution.php)
- [Request submission](../../packages/gov-store/custom-requests/src/Services/BasketService.php), [fulfillment](../../packages/gov-store/custom-requests/src/Services/FulfillmentService.php), [requester transitions](../../packages/gov-store/custom-requests/src/Services/RequesterService.php), [returns](../../packages/gov-store/custom-requests/src/Services/RequestReturnService.php)
- [Canonical system issue](../../packages/gov-store/store-operations/src/Services/SystemGoodsIssueService.php), [posting pipeline](../../packages/gov-store/store-operations/src/Services/PostingPipelineManager.php), [native audit](../../packages/gov-store/store-operations/src/Listeners/WriteNativeAuditLogs.php)
- [Typed metadata](../../packages/gov-store/metadata/src/Services/MetadataValueStore.php), [tag gates](../../packages/gov-store/asset-tagging/src/Services/EnablementGate.php), [tag issuance](../../packages/gov-store/asset-tagging/src/Services/TagIssuanceService.php)
- [Tracking activation](../../packages/gov-store/tracking/src/Http/Controllers/TrackingCodeController.php), [receipt provenance listener](../../packages/gov-store/tracking/src/Listeners/AssociateInventoryToProgramme.php)
- [Committee delivered scope](../../packages/gov-store/committee/README.md), [geography dataset synchronizer](../../packages/gov-store/geo-areas/src/Services/GeoAreaDatasetSync.php), [map contract](../../packages/gov-store/geo-view/README.md)
- [Existing experiment tests](../../packages/gov-store/experimentation/tests/ExperimentTest.php), [original dated plan](../plans/super-admin-experiment-data-plan.md), [local testing rules](../testing/local-live-testing.md)

## Implemented / verified / open

- **Implemented in this investigation:** this report only. No runtime code or
  database records changed.
- **Verified:** source/schema inventory and the fresh read-only aggregates above.
  Existing tests were reviewed as source, not reported as passing executions.
- **Open:** phases 1–6, isolated and UI validation, and the decision whether to
  retain v1 beside a fresh v2 dataset or commission a reviewed in-place upgrade.
  No seed execution or destructive cleanup is implied by this investigation.
