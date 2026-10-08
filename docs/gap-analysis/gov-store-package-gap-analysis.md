# gov-store Package-wise Gap Analysis

Original review: 4 October 2026 · @zahid. Reassessed: **7 October 2026 (Asia/Dhaka)** at `a65c7f864f`.

## Updated scorecard

**41 of the original 64 gaps are mitigated (64.06%); 9 are partially mitigated; 14 remain open.** There are **23 gaps requiring further work**, including the partial rows. The package sections below are arranged in dependency order, with foundations before their consumers.

Tenant-scope implementation update: **7 October 2026 (Asia/Dhaka), working tree**. The original reassessment revision above remains the baseline for the other packages. See [tenant-scope implementation and verification](../verification/tenant-scope-implementation-2026-10-07.md).

Organization and starter-consumer update: **8 October 2026 (Asia/Dhaka), working tree**.
ORG-2/CL-2 have verified local implementation; ORG-1 is partial with suspension,
resume and geographic relocation delivered. Closure/merge still require complete
clearance, and live starter delivery requires the missing reviewed collections.
See [organization implementation and verification](../verification/organization-implementation-2026-10-08.md).

Office-membership update: **8 October 2026 (Asia/Dhaka), working tree**. OM-1/2/4/5 join OM-3 as locally mitigated; see [office-membership verification](../verification/office-membership-implementation-2026-10-08.md).

User-onboarding update: **8 October 2026 (Asia/Dhaka), working tree**. UO-1 through UO-4 are locally mitigated; see [user-onboarding verification](../verification/user-onboarding-implementation-2026-10-08.md).

These are implementation statuses, not production closure. Office-role enforcement still has outstanding G1 rollout requirements. A package with mitigated rows can also have deployment, historical-data or policy work remaining.

| Order | Package | Original gaps | Mitigated | Partial | Open | Next dependency-relevant work |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | tenant-scope | 7 | 7 | 0 | 0 | Deploy resolver/job contracts; retain outstanding G1 rollout gates |
| 2 | geo-areas | 4 | 3 | 1 | 0 | Verify dataset publisher/license before an upstream refresh |
| 3 | organization | 6 | 5 | 1 | 0 | Complete closure/merge after consumer clearance; populate reviewed starter library |
| 4 | office-membership | 5 | 5 | 0 | 0 | Deploy notice outbox; finish admin/recipient live UI and rollout checks |
| 5 | user-onboarding | 4 | 4 | 0 | 0 | Deploy history/notices; finish manager UI and historical orphan review |
| 6 | classification | 7 | 2 | 0 | 5 | Deploy reviewed starter library; queue bulk adoption |
| 7 | metadata | 4 | 0 | 0 | 4 | Stabilize provider and field-mapping contracts |
| 8 | tracking | 7 | 6 | 1 | 0 | Deploy additive migration; reconcile historical duplicate facts; finish remaining legacy messages |
| 9 | committee | 2 | 1 | 1 | 0 | Registry delivered; complete consumer integration boundary |
| 10 | store-operations | 8 | 2 | 3 | 3 | Ledger cut-over, remaining document types and receipt fields |
| 11 | custom-requests | 6 | 5 | 1 | 0 | Complete item adapters after stock contracts |
| 12 | experimentation | 4 | 1 | 1 | 2 | Isolated fixture CI, packaging and restore |
| **Total** | **12 packages** | **64** | **41** | **9** | **14** | **23 still require work** |

Original severities are preserved for reconciliation with the baseline. They are not a new assessment of residual risk.

| Original severity | Original gaps | Mitigated | Partial | Open |
| --- | --- | --- | --- | --- |
| Critical | 3 | 3 | 0 | 0 |
| High | 19 | 14 | 4 | 1 |
| Medium | 31 | 18 | 5 | 8 |
| Low | 11 | 6 | 0 | 5 |
| **Total** | **64** | **41** | **9** | **14** |

### Counting and evidence

- **Mitigated:** the original defect or missing control has an implemented remedy supported by inspected source and relevant tests or recorded verification. An equivalent supported workflow can replace the originally suggested fix.
- **Partial:** a compound gap has only some parts addressed, or its foundation exists but the consumer workflow is missing. Partial rows are excluded from the mitigated count.
- **Open:** the original defect or capability remains unresolved.

Each ID combines a package prefix with its **original row number**. The denominator remains 64: overlapping findings remain separate original rows, while deeper analyses are linked rather than added. Removing the request legacy path, for example, addresses both CR-1 and CR-2. Additional ledger risks must still be handled even though they are outside this original inventory.

The baseline was a static review at `76934cd8eb`. This reassessment inspected current source, routes, manifests, migrations, language files and tests, and cross-checked:

- [G1 execution and outstanding rollout](g1-unprotected-actions-analysis.md)
- [Custom Requests implementation, verification and operational work](../verification/custom-requests-refactor.md)
- [Committee implementation](../verification/committee-implementation-2026-10-05.md) and [UI verification](../verification/committee-ui-redesign-2026-10-05.md)
- [Store Operations deeper analysis and 6 October implementation update](store-operations-package-gap-analysis.md)
- [Custom Requests deeper baseline](custom-requests-package-gap-analysis.md)

The companion [cross-cutting assessment](gov-store-gap-assessment.md) still contains historical claims, including “committee is plan only” and “only one test file”. Use this reassessment and execution records for current package status; its G1–G20 identifiers form a separate inventory. Historical line counts, catalog totals and screenshots are not new runtime measurements.

## Package dependency order

The numbered sections are the recommended **primary implementation order**. A consumer requires the specific upstream contract or invariant listed here, not completion of every upstream UI improvement. Independent branches can proceed once their own prerequisites are available.

Current imports contain cycles, so a strict one-pass ordering of the existing implementation is impossible. Define resolver and extension contracts in foundational packages first, then bind them from consumers. This order is a mitigation plan for those cycles, not a claim they are already removed.

| Order | Package | Prerequisite to complete first | Reason and handoff |
| --- | --- | --- | --- |
| 1 | tenant-scope | Host authentication/core models | Trustworthy context and abilities support all protected consumers. Organization/membership resolver interfaces and owning-package adapters are delivered under TS-3. |
| 2 | geo-areas | Core geography schema | Independent of tenant fixes; supplies geography to offices and jurisdiction checks. Can proceed alongside package 1. |
| 3 | organization | Tenant resolver contracts; geography boundaries | Establish office/company identity, safe table ownership, office type and a committed provisioning event. Finish core provisioning first; return to closure after membership clearance. |
| 4 | office-membership | Office/admin identity; tenant contracts | Authorized memberships/responsibilities support onboarding, registrars and approvers. Publish clearance extension points instead of importing consumers. |
| 5 | user-onboarding | Authorized membership assignment; office routing | Queue system-created users and assign through membership services. This branch does not block catalog or metadata. |
| 6 | classification | Provisioning event/office type; tenant-aware job context | ORG-2 must precede CL-2 starter catalogs. Adoption supplies categories/models to inventory consumers. |
| 7 | metadata | Tenant context; native asset models/custom fields | Stabilize mappings before serialized receipts consume them. Independent of catalog search/UI fixes; verify provisioned models at the classification handoff. |
| 8 | tracking | Office/geography scope; metadata provider extension | Repair projections and publish scoped programme verifier/event contracts before Store Operations integrations. Return to asset-event verification after receipt posting emits the event. |
| 9 | committee | Office identity; memberships/registrar; tenant abilities | Registry and roster contracts already exist. Complete the consumer boundary before optional inspection; do not rebuild the registry. Tracking consumers use its scope contract where needed. |
| 10 | store-operations | Tenant abilities; catalog identities; metadata; tracking verifier; committee contracts for optional inspection | Stabilize ledger opening/cut-over, state rules and stock issuing before request fulfillment. Receipt inspection remains deferred under the recorded decision. |
| 11 | custom-requests | Membership responsibilities/clearance; Store Operations issuing/opening stock | Complete adapters after stock/reservation semantics. Reuse delivered cover/notices and register request clearance from this package. |
| 12 | experimentation | Stable schemas/contracts of fixture consumers | Update integration fixtures after business contracts stabilize. Begin isolated test setup/CI alongside package 1, without waiting for final fixture integration. |

```mermaid
flowchart TD
    TS["1. tenant-scope contracts/boundaries"] --> ORG["3. organization core"]
    GEO["2. geo-areas"] --> ORG
    ORG --> OM["4. office-membership"]
    OM --> UO["5. user-onboarding"]
    TS --> CL["6. classification"]
    ORG --> CL
    TS --> MD["7. metadata"]
    ORG --> TR["8. tracking"]
    GEO --> TR
    MD --> TR
    ORG --> CM["9. committee registry/contracts"]
    OM --> CM
    TS --> CM
    CL --> SO["10. store-operations"]
    MD --> SO
    TR --> SO
    CM -. optional receipt inspection .-> SO
    OM --> CR["11. custom-requests"]
    SO --> CR
    UO --> EX["12. experimentation fixture integration"]
    CR --> EX
    CM --> EX
```

Arrows show selected handoffs after contract extraction, not every current import. Tenant context remains a prerequisite for every protected consumer.

### Required returns and completion gates

| Dependency | First pass | Return / completion condition |
| --- | --- | --- |
| tenant-scope ↔ organization/membership | Resolver contracts and owning-package bindings delivered under TS-3 | Shared-context reset, active membership ownership and immediate role updates verified locally. Consumers must preserve these invariants; other workflow cycles remain separate gaps. |
| organization ↔ membership clearance | Finish office identity/provisioning and safe ownership | Complete ORG-1 closure after OM-2 and request/committee clearance extensions cover holdings and obligations. |
| organization → classification | Office type/event and durable frozen starter bundle delivered | ORG-2/CL-2 verified locally on 8 October: actual provisioning-to-adoption, explicit job context, rollback and duplicate-safe retries. Live delivery still needs reviewed collection contents and workers. |
| tracking ↔ store-operations | Repair TR-1; establish verifier/event boundary | Close TR-2 only after successful serialized receipt posting updates programme associations/projections once. A listener alone is insufficient. |
| committee → store-operations | Retain delivered resolver/snapshot/purpose contracts | CM-2, SO-3 and SO-4's committee reference need consumer integration and policy review. Inspection is deferred and does not block other repairs/current direct posting. |
| store-operations → custom-requests | Review opening stock, canonical identities, stock locks and issuing contract | Verify reservations/issue/return against that ledger before exposing new adapters. Recheck live cut-over state; the 6 October record reports empty opening markers and historical aliases. |

Run focused isolated checks at each handoff. Address reachable debug/authorization defects immediately; package order is not a reason to delay a security fix. Deliver bilingual controls, safe failures and accessibility within each package, with whole-workflow usability and observation before enforcement.

## 1. tenant-scope

**7 mitigated, 0 partial, 0 open** in this original inventory. The 7 October implementation corrects stock reads without granting cross-office mutations, extracts context resolver contracts with owning-package adapters, unions capabilities, establishes job/command execution boundaries, caches schema knowledge and renders navigation through the layout. This is local implementation closure; CI, deployment and G1 rollout remain separate.

**Internal order:** TS-1/2 with distinct read/mutation boundaries → TS-3/4/5 → TS-6/7. Contract extraction starts here and finishes in owning-package adapters.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| TS-1 | Critical | Mitigated | Validated `isGlobal` bypasses stock read scope; selected offices remain isolated. Global inventory mutations are refused by model policy and the inventory builder. Live global counts match raw nondeleted totals. | Preserve this read/mutation separation in new consumers. |
| TS-2 | High | Mitigated | Physical stock reads use separate inventory-office/company bounds. ICT jurisdictions authorize office/user support only and never reveal office stock; an independent inventory responsibility stays within its working office. Model ownership includes original values; bulk updates/deletes/counters, OR predicates and direct inserts cannot escape the working office. [ICT boundary regression verification](../verification/ict-jurisdiction-inventory-boundary-2026-10-07.md). | Native licenses have company ownership without an office column; independently authorized inventory users retain company-bound license reads. Pure ICT support users see no inventory or inventory reports. Do not claim physical-office license ownership. |
| TS-3 | High | Mitigated | `OrganizationContextResolver` and `MembershipContextResolver` live in tenant-scope, with bindings/adapters in their owners. Initializer, assignment, access and boundary services consume contracts. Context reset stays in place; foreign, inactive, deleted-office and stale memberships fail closed. | This extracts the context dependency cycle; remaining business-workflow cycles belong to their own package gaps. |
| TS-4 | Medium | Mitigated | `EffectivePermissionSet::merge()` provides an idempotent union while retaining working-role attribution. Company overlay is injected once, preserving local capabilities; native admin never supplies global/company scope. | Maintain role sources and test changes to capability profiles. |
| TS-5 | Medium | Mitigated | `TenantExecution` validates live actor, office/company target, membership and named ability. All four current GovStore jobs declare scoped execution or explicit global maintenance; bus/queue lifecycle clears context and restores synchronous callers, including synchronous console delivery after the 8 October correction. Commands have a reviewed execution registry; ledger opening additionally uses the scoped actor runner. | Restart long-running workers on deployment. ORG-2/CL-2 starter delivery is locally verified; queued bulk UI adoption (CL-3) remains separate. |
| TS-6 | Medium | Mitigated | `SchemaKnowledge` caches columns per connection/database/table. Menu rendering resolves roles once per render. Isolated query checks bound initialization to ≤12 queries, a 30-item menu to ≤5 role queries, and five warm stock counts to exactly five SELECTs. Membership/role/grant checks stay fresh. | No session cache of authorization. Clear schema knowledge/restart workers after schema changes. |
| TS-7 | Medium | Mitigated | Sidebar, My access and rollout banner render as layout partials; response-body rewriting is removed. `MenuRegistry` still derives named route abilities. Eight tenant-administration routes now declare superuser abilities, with national review/fingerprint/replay controls on mutations. | Other packages retain their own narrower policies; G1 coverage is not proof of every application surface. |

Verification and operational limits are recorded in [the tenant-scope execution record](../verification/tenant-scope-implementation-2026-10-07.md) and [read-only live counts](../verification/tenant-scope-live-2026-10-07.json).

## 2. geo-areas

**3 mitigated, 1 partial, 0 open.** Reference geography supports office and programme boundaries. Current implementation and live index evidence are recorded in [Geo Areas and Organization verification](../verification/geo-areas-organization-implementation-2026-10-07.md).

**Internal order:** GEO-1 → GEO-2 → GEO-3/4; display/search improvements do not block office schema work.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| GEO-1 | High | Mitigated | Empty non-AJAX searches return the standard bounded JSON response; controller regression covers the request shape. | Preserve safe API behavior. |
| GEO-2 | Medium | Partial | Dataset is versioned and hashed with a reviewed refresh procedure; the original migration no longer drops existing reference data. The bundled CSV still lacks verified publisher, retrieval date and license. | Establish source/terms before adopting a refreshed dataset; retain stable IDs and the `divison` compatibility alias. |
| GEO-3 | Medium | Mitigated | API returns English and Bangla names separately and selects dropdown `text` by locale; both package language files are registered. | Preserve response compatibility for Select2 consumers. |
| GEO-4 | Low | Mitigated | Search is sorted, bounded prefix matching with English/Bangla indexes. Live `EXPLAIN` on 9,111 rows used both indexes (`index_merge`, estimated 8 rows). | Re-measure when data volume or search behavior changes. |

## 3. organization

**5 mitigated, 1 partial, 0 open.** Suspend, resume and geographic relocation are authorized and audited; closure/merge remain blocked until full consumer clearance. Starter delivery has an attributable frozen bundle and verified retry/rollback behavior. The current local library has no matching starter collections. See the [8 October implementation and verification record](../verification/organization-implementation-2026-10-08.md); the [7 October record](../verification/geo-areas-organization-implementation-2026-10-07.md) remains historical evidence.

**Internal order:** ORG-3 safe ownership → ORG-2 office type/event → ORG-1 after complete clearance. Localization/cleanup can proceed within the package.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| ORG-1 | High | Partial | Office hub provides suspend/resume/geographic relocation with office/territory checks, reason, typed confirmation, locked state/geography rechecks and audit. Suspension pauses new intake/setup; readiness cannot reactivate it. Closure/merge return 409 even for superusers until full clearance exists. | Use delivered OM-2 employee rules and finish office-wide holdings/request/committee clearance before terminal transitions; verify positive live admin flows. |
| ORG-2 | High | Mitigated | Typed after-commit event is consumed by Classification. Active member/admin assignment triggers a durable, frozen commodity bundle, tenant-scoped job, reference checks, atomic rollback and duplicate-safe retry. Actual provisioning → membership → assignment → adoption and failure cases pass isolated tests. | Populate the currently missing reviewed starter collections, run workers, and verify live delivery. Existing offices are not bulk-adopted automatically. |
| ORG-3 | Medium | Mitigated | Historical organization migration is non-destructive on rerun. The additive migration backfills valid legacy roles only for active, unexpired memberships; `OfficeResponsibility` is the active owner. Legacy table is retained as an archive; model/relation and live compatibility paths are removed. | Preserve the archive and backfill safeguards. |
| ORG-4 | Medium | Mitigated | G1 access-request/admin review supplies expiring `gov_access_grants`; request `ApprovalRouting` uses responsibilities and unexpired cover. This replaces unused delegate columns and addresses leave cover. | Preserve expiry/next-request checks. Legacy-column cleanup is ORG-3; rollout/mail setup remain operational. |
| ORG-5 | Medium | Mitigated | English/Bangla organization keys have parity. Office registry is paginated at 25 rows; counts and company filters derive from the authorized ICT geography, with a regression test. | Retain scoped pagination as registry filters evolve. |
| ORG-6 | Low | Mitigated | No active consumer existed; unused response-rewriting middleware and role-model integration have been removed. | Keep organization UI on normal layout integrations. |

## 4. office-membership

**5 mitigated, 0 partial, 0 open** in the original inventory. The 8 October local implementation secures both role-transfer stores, completes employee clearance/release/sign-off/claim, adds durable notices and bilingual layout navigation. See [implementation, tests and operational limits](../verification/office-membership-implementation-2026-10-08.md). This does not close office-wide lifecycle or production rollout work.

**Internal order:** OM-1 → OM-2; preserve OM-3 consumer registration. Employee clearance is delivered; organization closure/merge still require office-wide consumer clearance.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| OM-1 | High | Mitigated | Registered abilities protect all 18 routes. Both transfer paths validate actor, office, supported role, active/unexpired memberships, current ownership and participant/state; locked acceptance rejects stale ownership and replay. Office-admin handover is supported. Isolated tests and concurrent MySQL acceptance checks pass. | Complete disposable admin/recipient browser mutation checks before rollout; preserve narrower ownership checks in all modes. |
| OM-2 | High | Mitigated | Assets, accessories, carried-asset components, licence seats, roles/cover and consumer request/return obligations are checked before release, sign-off and claim. Transactional reads lock current records. Consumable allocations remain consumption history; unresolved returns require a posted receipt in the releasing office. | Native licences remain company-owned. Office closure/merge still need office-wide inventory and consumer clearance; employee clearance cannot authorize terminal transitions. |
| OM-3 | Medium | Mitigated | Request and committee rules remain registered by their owning providers; membership has no reverse consumer import. Request return clearance now checks actual receipt office/type independently of working context. | Preserve extension registration and conservative handling of historical obligations. |
| OM-4 | Medium | Mitigated | Durable transactional notices cover transfer and membership/release transitions; per-user view and optional outbox mail provide committed delivery and retry. Notice failure rolls back role/status/audit; mail failure retains the notice. | Deploy migration. Optional mail is off by default; configure/schedule delivery when intended and account for at-least-once email behavior. |
| OM-5 | Medium | Mitigated | English/Bangla menu, role, dialog and notice strings; shared-layout context/menu and breadcrumbs replace response rewriting. Blade PHP syntax/key parity pass; live Bengali membership/dialog and staff denial verified without browser errors. | Complete broader admin/recipient usability and accessibility checks; local rendering is not WCAG certification. |

## 5. user-onboarding

**4 mitigated, 0 partial, 0 open** in the original inventory. The 8 October local implementation queues system-created accounts, secures manager/office boundaries, adds retained decision history and durable notices, and localizes the queue and personal page. See [implementation, tests and operational limits](../verification/user-onboarding-implementation-2026-10-08.md).

**Internal order:** UO-1/2 delivered through authorized membership services; UO-3/4 delivered with the transitions. Existing memberships still use membership release and transfer.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| UO-1 | High | Mitigated | Unauthenticated creation produces one SYSTEM WAITING record with nullable creator/owner, without inventing a system user or office. Native first-membership synchronization defers to onboarding. Owned company/ICT/local queues apply explicit live boundaries. | Apply additive migration; review historical missing accounts individually, retaining intentionally excluded oversight accounts. |
| UO-2 | Medium | Mitigated | Authorized cancel/reject/reassign/reopen transitions retain reasons and actor/state history. Locked first-office completion checks ownership, office/company/geography, lifecycle, memberships and replay; activation/permissions remain unchanged. Concurrent MySQL completion commits once. | Complete disposable manager/recipient browser mutation checks. Completed memberships cannot use onboarding to bypass release/transfer. |
| UO-3 | Medium | Mitigated | Durable per-recipient in-app notices commit with creation/decision/completion history. Employee, creator/manager and destination office admin receive completion updates. Private reasons remain in the authorized administrative queue. Notice failure rolls back assignment. | Deploy history/notices; integrate their ownership with the pending experiment packaging/cleanup work. No SMTP delivery is claimed. |
| UO-4 | Medium | Mitigated | English/Bangla queue, status, role, choice, action, history, notice, menu, breadcrumb and denial labels; three Blade PHP checks and key parity pass. Actual queue fragments render and escape reasons; personal page and denial verified live in Bengali. | Complete broader manager/recipient usability/accessibility and rollout checks. |

## 6. classification

**2 mitigated, 0 partial, 5 open.** G1 controls national changes/adoption, and starter provisioning is locally verified under ORG-2/CL-2. Live starter-library population and queued bulk UI processing remain separate work.

**Internal order:** CL-7 migration compatibility → ORG-2/CL-2 → CL-3 with TS-5 context; CL-4/5/6 can follow.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| CL-1 | Critical | Mitigated | Explicit route abilities, national review/reason/typed confirmation, exact supported bundle review and office/company adoption boundaries exist with G1 coverage. | Complete G1 rollout; preserve narrower catalog authorization. |
| CL-2 | High | Mitigated | `ProvisionStarterCatalog` consumes the typed committed event/profile type. `OfficeStarterCatalog` freezes expanded commodity items with explicit types and persists one run per office; the job validates live actor/context/reference and completes atomically. Actual handoff, failures and retries are verified under ORG-2. | Populate reviewed starter collections on deployment and verify live queued delivery; this does not complete CL-3 bulk UI adoption. |
| CL-3 | High | Open | `BulkAdoptionController::execute` still runs the service synchronously. | Queue bounded work with validated actor/scope, progress and safe retry; depends on TS-5. |
| CL-4 | Medium | Open | `compiled_synonyms.csv` contains only its header. | Compile reviewed synonyms, Bangla first, within exact reviewed import bundle. |
| CL-5 | Medium | Open | Package-wide hard-coded text and mixed Livewire/Blade interaction remain; G1 translations cover only its controls. | Localize views/menus and use consistent keyboard-accessible interactions. |
| CL-6 | Low | Open | External source remains a placeholder. | Define/implement supported source or remove unavailable control. |
| CL-7 | Low | Open | `2026__08_04_create_gov_catalog_collections_table.php` is still malformed. | Correct fresh-install order with compatibility for installed histories; avoid rerunning a renamed recorded migration. |

## 7. metadata

**0 mitigated, 0 partial, 4 open.** Field registry/mappings support tracking and serialized receipts; this branch can proceed alongside classification.

**Internal order:** MD-1 provider recipe/configuration → MD-2 read-only mapping browser → editor/packaging/localization.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| MD-1 | Medium | Open | Built-ins remain code-defined. Tracking can register a provider, but no supported configurable workflow or complete documented provider recipe exists. | Document/test recipe first; database configuration needs validation/versioning. |
| MD-2 | Medium | Open | Health/convergence exists; mapping browser/editor is absent. | Add scoped read-only view before authorized editor; preserve convergence invariants. |
| MD-3 | Medium | Open | Health UI lacks package language files. | Add English/Bangla and parity checks. |
| MD-4 | Low | Open | Manifest lacks provider auto-discovery and declares `proprietary`. | Add discovery and resolve licensing with host/package policy. |

## 8. tracking

**6 mitigated, 1 partial, 0 open.** Updated 8 October 2026 (Asia/Dhaka). See [Tracking implementation and verification](../verification/tracking-implementation-2026-10-08.md). Local implementation does not close historical data reconciliation or deployment/rollout work.

**Internal order:** TR-7 portable registration → TR-1 projection resolution → TR-5 scoped verifier → TR-2 with receipt posting. Upload/spreadsheet/UI work can proceed independently.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| TR-1 | High | Mitigated | Shared refresh resolves old/new initiative IDs, invalidates inside the transaction and queues rebuild after commit. Targets/tasks and bulk listener paths refresh too. Live progress derives from active associations; rebuild preserves historical delivery facts. | Reconcile the retained two-unit historical fact discrepancy under a reviewed data plan; run cache rebuild after deployment. |
| TR-2 | High | Mitigated | Serialized receipt posts native assets, registrations, ledger, associations, delivery fact and timeline atomically through the unified event. Durable movement keys reject replay; task locking serializes nullable fact dimensions. Isolated pipeline/rollback tests and MySQL replay/lock exercise pass. | Deploy marker migration and restart workers. Legacy replay keys are compatibility payload hashes; historical facts are retained. |
| TR-3 | Medium | Mitigated | Nested task evidence routes use private storage, task/initiative/document ownership, narrower team roles and locked draft-state checks. File deletion follows commit. | Historical reference-only evidence is not assigned to tasks by guessing; inventory and map any such records before use. |
| TR-4 | Medium | Mitigated | `refactor plan2.md` distinguishes delivered clipboard grid editing from proposed workbook import/export, templates and distribution. Unsupported completion claims are removed. | Workbook file import/export remains a future feature, not an advertised delivery. |
| TR-5 | Medium | Mitigated | Session HTTP and in-process verification share an active working-office/ability/lifecycle/geography/participant check. Supplied foreign office is rejected even in shadow mode. Native admin no longer grants programme-wide access. Nested/body IDs and matrix proposals are validated. | Browser endpoints remain session-authenticated. A token API needs a separate approved authentication design. |
| TR-6 | Medium | Partial | All 21 views have no executable inline scripts/handlers. Matrix, task and workspace controls are bundled; 407 English/Bangla keys have recursive parity. Saved matrices now render on edit, hidden inputs serialize once, menus exist, and negative quantity blocks save. | Finish remaining legacy controller messages, status/event presentation and broader bilingual/tablet/accessibility review. Stored historical audit text and native catalog names are retained. |
| TR-7 | Low | Mitigated | Root namespace/provider case matches `GovStore\Tracking`. Manifest uses the user-approved host licence `AGPL-3.0-or-later`; autoload generation and effective route class loading pass. | Confirm Linux CI/deployment; this Windows check is not a Linux execution result. |

## 9. committee

**1 mitigated, 1 partial, 0 open.** Registry is implemented, no longer plan-only. Registry delivery and actual receipt inspection are separate milestones.

**Internal order:** retain delivered registry; complete CM-2 with authorized consumer work. National template activation still requires policy review.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| CM-1 | High | Mitigated | Models/migrations, services, private orders, memberships/scopes, dated rosters, workspaces/policies and tests exist; see 5 October records. | Complete their deployment/policy/rollout tasks. Registry delivery does not establish national template activation. |
| CM-2 | Medium | Partial | `CommitteeResolver`, `RosterSnapshotProvider` and purpose contracts exist; Store Operations declares five purposes. Posting does not consume panels/snapshots/outcomes. | Implement receipt inspection boundary in Store Operations. Preserve direct posting unless authorized policy/observation enable inspection. |

## 10. store-operations

**2 mitigated, 3 partial, 3 open.** The 6 October changes add opening-stock gating, canonical ledger paths, adjustments, draft voiding and improved asset creation; compound gaps remain.

**Internal order:** SO-6 safe ownership → deeper analysis's opening/canonical-history cut-over and stock invariants → remaining SO-2/4 work. SO-3 inspection is explicitly deferred.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| SO-1 | Critical | Mitigated | Route abilities, `DocumentPolicy`, atomic locked mutations, server checklist, office/type/state checks, private attachments and safe failures are implemented/tested under G1. | Complete rollout; preserve creator/poster attribution and state rules. |
| SO-2 | High | Partial | Adjustment documents require a posted source/reason/direction; draft void exists. Inter-office transfer has no supported document workflow. Posted GRN reversal is excluded by current design. | Implement source/destination authorization, locked balances and paired transfer movements. Use adjustments for corrections; reversal needs a changed policy. |
| SO-3 | High | Partial | Registry/purposes exist; READY is not inspection evidence. Direct posting remains policy, and the deeper analysis explicitly defers receipt inspection. | Deliver optional submit/inspect/reopen/fallback with panel/outcome snapshots when authorized; required inspection needs policy/observation. |
| SO-4 | High | Partial | Asset creation uses native tags/validated status and model-level ledger support. Supplier and structured committee reference remain missing; focused tests do not establish complete serialized receipt verification. | Capture/validate supplier; verify serialized receipt end to end. Add dated committee/outcome through deferred consumer workflow, not generic report upload. |
| SO-5 | Medium | Open | Legacy receipt/issue controllers and create views remain. | Remove proven dead code after dependency/history checks. |
| SO-6 | Medium | Open | Three profile migrations still overlap; `2024_03_04` and `2024_03_08` drop/recreate profile tables in `up()`. | Establish additive ownership and fresh/installed upgrade compatibility; preserve assignments/lineage and migration identities. |
| SO-7 | Medium | Open | New bilingual controls exist, but remaining English text and tablet line-grid usability are unfinished. | Localize workspace/rules/register and verify tablet/keyboard behavior. |
| SO-8 | Low | Mitigated | Second assignment route is now `storeops.admin.rules.assign_gpo`; route coverage includes both protected URLs. | Preserve unique names on new routes. |

The [deeper Store Operations analysis](store-operations-package-gap-analysis.md) is required for this package's delivery. Its implementation update coexists with older baseline descriptions: use the 6 October update and inspected source for what changed. Outstanding native-stock bypass, cut-over, supplier/transfer/inspection and UI work is not added to the 64-row denominator. Opening-stock review must precede new ledger posting/fulfillment in an unopened office. Recheck live state using the [local testing guide](../testing/local-live-testing.md) before any authorized data exercise.

## 11. custom-requests

**5 mitigated, 1 partial, 0 open.** Refactor supplies independent staged approval, cover, thresholds, notices, reservations and requester transitions. Historic data/operations remain separate work.

**Internal order:** stabilize Store Operations issuing/opening stock → CR-4 adapters. Deploy delivered maintenance/mail and reconciliation instead of rebuilding approval features.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| CR-1 | High | Mitigated | Dead legacy submission route/model/service and duplicate `/admin` removed. Historical table remains for reviewed archival, with no active second path. | Archive retained history under reviewed data plan; preserve evidence. |
| CR-2 | High | Mitigated | Legacy unchecked class-string `store` path removed; basket uses fixed factory/type validation. | Extend allow-list only with verified adapters. |
| CR-3 | High | Mitigated | Threshold policies, responsibility/expiring-cover routing, durable notices, optional mail and deduplicated weekday escalation implemented. | Configure scheduler/mail and complete G1 role rollout; old delegate columns are superseded. |
| CR-4 | Medium | Partial | Unsupported components/licences are hidden/rejected, preventing unfulfillable new requests. Component/licence-seat adapters remain absent. | Define fulfillment/reservation/stock-seat/clearance semantics before exposure; verify legacy requests separately. |
| CR-5 | Medium | Mitigated | Owner withdrawal before decision/issue has state/replay guards; acknowledgement and linked bulk-return drafts also exist. | Preserve return evidence; partial/multiple bulk returns remain outside current design. |
| CR-6 | Low | Mitigated | Route abilities, own-basket checks and office-bound mutations with cross-user/cross-office coverage exist. | Preserve stage/object/state checks in shadow; no blanket native-admin bypass. |

See the [refactor record](../verification/custom-requests-refactor.md) for unresolved historical offices, opening-stock reconciliation, retained documents, scheduler/mail, native acceptance listener verification and deployment. Mitigated original rows do not close these operational tasks.

## 12. experimentation

**1 mitigated, 1 partial, 2 open.** Final fictional-fixture integration follows consumers; isolated test infrastructure starts early.

**Internal order:** EX-1/2 during foundation work → update integration scenarios after handoffs → EX-3 separate restore capability.

| ID | Original severity | Status | Current finding and evidence | Remaining mitigation |
| --- | --- | --- | --- | --- |
| EX-1 | Medium | Partial | `tests/prepare-database.php` creates a guarded separate schema and tests refuse other database names. Setup still relies on installed source schema; G1 CI runs `tests/Feature/GovStore`, excluding this package test. | Make CI preparation reproducible and include experiment tests; never reset development DB. |
| EX-2 | Low | Open | Root registration exists, but no root `require`/`require-dev` entry or provider auto-discovery. | Declare dev dependency/discovery and verify optional nonproduction installation. |
| EX-3 | Low | Open | Backups exist; CLI has no restore action or supported restore UI. | Provide verified restore into explicit separate destination with integrity/environment checks. |
| EX-4 | Low | Mitigated | Current `ui.php` has **59 English and 59 Bangla keys**, with no missing/extra top-level keys. Original seven-key deficit is gone. | Maintain parity; parity alone is not usability verification. |

## Verification performed for the 7 October baseline reassessment

Source inspection established statuses and package handoffs. Key comparison confirmed ORG-5's 20 missing Bangla keys and EX-4's complete 59-key parity.

**The focused `tests/Feature/GovStore` suite passed: 99 tests, 2,775 assertions**, using installed PHP 8.5.0 and isolated SQLite memory. The initial run hit the CLI's 128 MB limit; a process-local 512 MB rerun completed (134 MB reported). The CLI also reported a configured Xdebug DLL mismatch at startup; test completion was successful. No protection/test was weakened and no machine-wide runtime setting changed.

```text
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/GovStore
OK (99 tests, 2775 assertions)
```

No live HTTP/database inspection, migration, ledger cut-over, catalog re-import or access-mode change was performed for this document update. Linked live records are dated prior evidence. This suite does not cover every original package gap or run experimentation's separate-schema tests.

The **8 October Organization follow-up** ran the eight top-level GovStore test
files on PHP 8.4.15 with isolated SQLite and a process-local 512 MB limit:
**146 tests, 3,376 assertions passed**. It also verified compiled Organization
templates, a local ordinary-user denial, the additive starter-run migration and
an isolated MySQL profile-lock check. Current live starter collections are missing;
closure/merge clearance and positive live admin flows remain incomplete. See the
[new execution record](../verification/organization-implementation-2026-10-08.md).

## Work remaining outside the mitigation count

1. **G1 enforced rollout:** remote CI/full isolated application regression, two real weeks of shadow observation, role-gap resolution, named usability sessions, pa11y/screen-reader checks, announcement/date/help contact and authorized enforcement switch.
2. **Existing private evidence:** G1 recorded 180 older missing source files. Recheck/recover/resolve them and migrate old public evidence on other deployments; new upload protection alone is insufficient.
3. **Ledger/request history:** review aliases, opening stock, unresolved request offices and native-stock drift from the deeper records. Back up and review exact repairs; code improvements do not reconcile history.
4. **Committee policy/deferred inspection:** registry delivery does not activate national templates or mandatory receipt verification. Consumer inspection remains deferred under the recorded decision.
5. **Target operations:** apply reviewed additive migrations; configure workers/scheduler/mail and verify native acceptance/EULA listeners/live workflows. Local tests do not establish remote CI success, WCAG compliance or production readiness.
