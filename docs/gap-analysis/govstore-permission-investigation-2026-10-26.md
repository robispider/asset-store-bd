# GovStore permission gaps and mitigation plan

Investigation: 26 October 2026, Asia/Dhaka. Baseline revision: `afbe5da8d6`.
Scope: **all 15 packages**, their effective HTTP routes, shared authorization,
role/membership resolution, relevant services/jobs, and native Snipe-IT integration.

**Re-evaluation (same day, same revision):** every finding below was re-traced
against source, the route inventory counts were recomputed, and read-only data
checks were added for reference ownership. F01–F13 stand, several with corrected
or added detail. Two new findings were added: **F14** (shared national categories
are editable by the office that adopted them first, present in current data) and
**F15** (native create screens bypass reference and office governance). P03 is
now the design decision behind F14, and P05 (governance vocabulary) is new.

## Accepted development decisions — 26 October 2026

The owner accepted the following decisions after the investigation. Corrective
implementation for F01–F15 is now recorded in the
[remediation execution record](../verification/govstore-permission-remediation-2026-10-26.md).
Implementation and automated verification do not establish full live acceptance.

1. **Company organization authority (P01):** keep office creation and jurisdiction
   assignment with authorized ICT/national administrators initially. Remove
   misleading company capabilities; any future company workflow must have explicit
   company scope and agreed tasks.
2. **Employee catalog access (P02):** ordinary employees must not access My Catalog
   at `/gov-store/operations/catalog`, through its menu or a direct URL. This
   decision concerns the management catalog, not the employee request catalog.
3. **Reference ownership (P03/F14):** use explicit national/company/office ownership.
   Adoption grants use and visibility, never ownership. Preserve national governance.
   After reviewing the legacy data, the owner authorized making the 644 ambiguous
   development categories national instead of requiring individual ownership review.
   This was applied to the exact frozen bundle; adoption and inventory were preserved.
4. **Governance and Finance defaults (P05):** use national/company/office vocabulary
   and corresponding scoped managers. Review existing `local`/`location` rows before
   migration. The application is under development and schema/data design may be
   changed as needed; this does not authorize an indiscriminate database reset.
5. **Requested access (F08):** use narrow catalog-maintenance/document-reading
   access; assign operational responsibilities explicitly with the full bundle
   disclosed. Request-based access requires expiry, with a renewable 30-day default.
   Permanent responsibilities belong in role management. Preserve existing grants
   until supported review/revocation/expiry.
6. **Creation workflows (F15):** route reference creation through scoped GovStore
   provisioning and remove office-level native creation permissions. Office creation
   belongs to organization provisioning; national publication requires review.
7. **Development enforcement (F01):** enforcement is enabled by default so problems
   surface during development. Shadow is an explicit override, not the default.
   Repair valid workflows under enforcement rather than broadening roles to make
   tests pass. This supersedes Phase C's instruction to preserve shadow during local
   development; operational observation and acceptance are not claimed complete.

No auditor or independent document-verifier policy (P04) was selected in this
exchange; it remains separate work.

**Current execution status:** all 15 findings have corrective implementation and
regression coverage. Enforcement resolves to `enforce` in the local application
and is visible in My access. The complete GovStore suite passed **765 tests,
77,980 assertions**. Two targeted migrations were applied to the authorized
development database. Final ownership is 725 national, 58 company, zero unresolved;
all 4,938 adoption mappings remain. See the execution record for changes, verification
limits, private audit evidence and cleanup.

Everything below is **historical baseline evidence at `afbe5da8d6`**, including
phrases such as “current,” “pending,” and “documentation only.” It does not describe
the remediated working tree. The companion route inventory has been refreshed to
**332 routes / 160 mutation routes / 69 registered abilities**, including eight
protected native reference update/delete routes. Definition-level CSV flags do
not represent dynamic national review for an individual target.

## Decision summary

There are both excess permissions and legitimate-work blockers. The principal
problems are inconsistent authorization sources, incomplete object checks in a
few paths, and an unfinished enforcement rollout. Adding more menu restrictions
or simply assigning everyone a larger role would leave these problems in place.

**Catalog answer:** an authenticated employee with a usable working office can
currently pass `catalog.office.adopt` in the locally resolved **shadow** mode.
That is deliberate middleware behavior during rollout, not the declared role
policy. In enforcement mode, the declared roles are office administrator,
storekeeper and company administrator, plus the central superuser override.
Anonymous access is not allowed. Company scope still comes from the resolved
company-administrator context; shadow does not itself grant that scope.

The same shadow behavior affects store documents, request approval/fulfillment
and committee administration. Separately, several defects below remain present
**even with enforcement enabled**.

Highest priorities:

1. Close native-location membership self-approval, reference ownership/creation
   (F14 affects 70 shared national categories today) and company/reference boundary
   defects, which enforcement mode alone cannot fix.
2. Make combined roles and temporary cover work consistently through native
   inventory, before tightening access for offices.
3. Expose and constrain the complete permission bundle granted by access approval.
4. Complete a controlled enforcement rollout after role-gap resolution and real
   observation; do not switch modes blindly.

This investigation changes documentation only. No application protection, user
assignment, access mode, business record or existing database schema was changed.

## Evidence and limits

| Evidence | Result | What it establishes |
| --- | --- | --- |
| Current effective Laravel route collection | 320 GovStore controller routes; 150 accept mutation methods; 67 registered abilities | Installed route inventory, including generated resource routes |
| Locally bootstrapped application configuration | `govstore-access.mode = shadow` | Current CLI-resolved local configuration; an independently deployed host or web-worker configuration was not verified |
| Existing `tests/Feature/GovStore` suite | **744 tests, 77,781 assertions passed**, PHP 8.4.15, 5m28s, 344 MB | Current assertions pass; they do not cover every defect in this report |
| Included `G1AuthorizationTest` | 29 tests, 1,707 assertions passed | Existing route/gate and G1 invariants, as part of the combined run |
| Additional investigation probes | **13 tests, 63 assertions passed**, isolated SQLite memory | The specific undesirable behaviors described below were reproduced; these are characterization checks, not mitigation tests |
| Existing local database | Aggregate SELECTs inside an explicit read-only transaction, rolled back | Current assignment counts without exporting identities or credentials |
| Re-evaluation source trace | F01–F13 re-read at the cited lines; route counts recomputed from the inventory (320 / 150 / 47; 58 distinct route abilities of the 67 registered) | The report matches the code at `afbe5da8d6`. The working tree differs from HEAD only by these two report files, so the suite results above were not rerun |
| Re-evaluation data/policy checks | Read-only aggregates plus in-memory evaluation of `ReferenceOwnershipService` and `CategoryBoundaryPolicy` on current rows, inside a rolled-back transaction | Current-data scope of F05, F12, F14, F15 and P05. No HTTP edit was performed |
| F14/F15 probes | **4 tests, 21 assertions passed**, PHP 8.5.0, isolated SQLite memory, real `InitializeTenantContext` and model observers | F14 and F15 (categories, locations) reproduced at model-save level. Native controller HTTP was not exercised; models, suppliers and manufacturers were not probed |
| Live browser journeys | Not run | No claim of completed UI stories, page acceptance, or per-person acceptance |
| Separate experimentation package suite / isolated MySQL concurrency | Not run | No claim of experimentation mutation acceptance or MySQL race verification |

The [route inventory](../verification/govstore-permission-route-inventory-2026-10-26.csv)
contains one row per effective route, action, middleware, ability, declared roles,
and definition-level national/always-enforced flags. These flags do not summarize
every controller/service guard. A route without `gov.can` is not automatically
unprotected: geography reference APIs, theming, experimentation and metadata use
other checks, reviewed below.

47 mutation routes use role-restricted definitions that can soften in shadow:
classification 8, store operations 7, custom requests 4, committee 28. This is a
**route-level exposure inventory**, not proof that all 47 mutations complete for
every employee. Object, state, ownership and downstream native checks still apply.

### Current local assignment observations

| Aggregate | Observed |
| --- | ---: |
| Active, non-deleted users | 983 |
| Membership records | 908 |
| Active, unexpired membership records for active users | 908 |
| Live user/office pairs with several permanent responsibility slugs | 4 |
| Live office-administrator/storekeeper overlaps | 0 |
| Live primary/final approver overlaps on the same person | 4 |
| Unexpired temporary grants | 1 |
| Responsibility rows without a live membership | 0 |
| Assigned office-administrator profiles without a live membership | 0 |

Reference ownership observations (re-evaluation):

| Aggregate | Observed |
| --- | ---: |
| Live categories | 789 |
| Reference mappings (all `category`, all `location` scope) | 4,938 rows over 772 categories |
| Company-scope mappings / asset-model, supplier, manufacturer mappings | 0 / 0 |
| Categories with several office mappings | 134 |
| Nationally governed (`global`) categories that also carry office mappings | 70 |
| …of which the shared mutation policy allows the first-adopting office to change | **70** (2 distinct offices; median 48 offices share each) |
| Live categories with neither a mapping nor a governance row | 6 (origin not determined) |
| Live asset models, all unmapped (treated as national) | 133 |
| Governance rows by type: `global` / `location` / `local` | 81 / 644 / 58 |
| Memberships created by native location sync, or approved by their own member | 0 / 0 |
| Live users with a native location but no membership | 0 |

The four approver overlaps are configuration risks, not evidence that four
requests are stuck or that misconduct occurred. Whether a request is blocked also
depends on its policy and other eligible approvers. The office-admin/storekeeper
and temporary-storekeeper defects were demonstrated with isolated fixtures; this
snapshot does not assert those exact combinations exist in current user data.

## Package-by-package review

Counts are effective controller routes, not controller-method or user-story counts.
“No additional confirmed defect” means none established within this investigation,
not a security certificate.

| Package | Routes / mutation routes | Authorization and boundary review | Result / follow-up |
| --- | ---: | --- | --- |
| `tenant-scope` | 17 / 6 | GovAccess, mode, context reset, capability injection, native scopes/observer, access requests, national review, worker execution | F01, F05–F08, F11–F15; ownership-model decision P03 |
| `office-membership` | 25 / 18 | Working-member ownership/expiry, switch, join approval, release/clearance, role transfer, native User synchronization | F02; F06/F07/F11 integration effects. Explicit join and handover checks must be preserved |
| `organization` | 23 / 13 | OfficeAdministration, territory/office checks, assignee eligibility, role configuration, readiness, company/ICT assignment | F10, F13, F15 (native location creation); policy question P01. Current protected object checks are substantial |
| `user-onboarding` | 4 / 2 | Always-enforced manage/self abilities, owner-bound queue, current manager eligibility, company/geography/office validation | No additional confirmed bypass in these paths; native synchronization F02 is an alternate path around the intended onboarding/membership process |
| `classification` | 46 / 20 | Read/search versus master administration, adoption/provision/bulk, source-copy boundary, finance defaults | F01, F03, F04, F09, F14; catalog-read question P02; governance vocabulary P05 |
| `store-operations` | 36 / 14 | Document office/company/type/state policy, mutation locks, attachments, posting checklist, rules administration | F01 and native integration F06/F07/F11/F12. Existing state/type/private-file protections are not missing merely because role enforcement is in shadow |
| `custom-requests` | 28 / 14 | Requester ownership, delivery-office boundary, approval stages, distinct decision actors, issue state/version, native serialized handoff | F01, F07, F10, F11. Preserve self-approval and primary/final separation checks |
| `committee` | 60 / 34 | Policy/scope boundary, manage/activate versus self declaration, personal files, commands, company-owned type/purpose administration | F01 affects manage/activate/read. Self declaration and type/purpose mutation definitions always enforce; do not label them all open |
| `tracking` | 40 / 16 | Always-enforced route abilities plus initiative company/team designations, task-parent checks, execution-office verification, private files, monitor scope | No additional confirmed bypass; retain narrower initiative checks. Office/native role inconsistencies can affect integration roles |
| `metadata` | 2 / 1 | Inline superuser/native-admin checks; global convergence job and global model health data | F09: national job trigger bypasses the shared review/audit path; no standalone metadata authorization suite found in the combined directory |
| `asset-tagging` | 9 / 4 | Always-enforced office/company abilities, national enablement review, scoped asset lookup plus native policy, document-policy CSV | No additional confirmed bypass; F06 can change native-policy outcomes. Authenticated lookup ability alone does not reveal every asset |
| `geo-areas` | 2 / 0 | Authenticated reference search/children, no protected inventory facts or HTTP writes | Broad area-name/reference access is intentional; no additional confirmed authorization defect |
| `geo-view` | 3 / 0 | Source ability, source-specific authorize/scope, second masking layer, private cache headers, monitor-page gate | No additional confirmed bypass. Page access does not authorize all map sources |
| `theming` | 15 / 3 | ThemeAccess per-user choice and office/company/national assignment scope, superuser lab, bounded dev assets | Role/scope checks exist; F09 for organization-wide assignment review. Guest dev CSS/JS is a deliberate asset-delivery exception |
| `experimentation` | 10 / 5 | RequireExperimentAccess plus gate, activated/non-deleted superuser, feature flag and production prohibition, management/recovery/wipe boundaries | No additional confirmed role bypass. No populate, recovery or destructive operation was exercised; separate package suite not run |

## Confirmed findings

Priority: **P1** = address before relying on role isolation for operational use;
**P2** = scheduled corrective work or explicit policy decision with acceptance
criteria. These are project priorities, not externally calculated severity scores.

### F01 — Office role denials are non-blocking in current shadow mode (P1)

**Packages:** tenant-scope, classification, store-operations, custom-requests,
committee. **Evidence:** current local configuration, source trace, isolated probe.

`GovAccess::enforces()` and `permitsRequest()` deliberately allow a registered
non-national/non-always-enforced ability during shadow. `RequireGovAbility`
records the denied role decision as `shadow` and continues. DocumentPolicy,
RequestAccess and CommitteePolicy also use `permitsRequest`, so they do not
restore a strict role check downstream.

The employee probe confirmed role denial but effective request permission for
catalog adoption; document view/draft/post; primary/final request approval and
fulfillment; committee view/manage/activate. It separately confirmed denial for
national catalog management and always-enforced membership, organization,
onboarding, tracking and asset-tag configuration abilities.

Menu gates use the strict `decide()` result, while some action views and controllers
use `permitsRequest()`. Consequently, a missing menu is compatible with a direct
URL/action proceeding in shadow, and My access can omit an action that a user can
actually perform during rollout.

Re-evaluation detail. The softening is not uniform, which makes shadow outcomes
hard to predict:

- Abilities flagged `inventory` (`storeops.documents.*`) still deny in shadow when
  the context cannot use an inventory office (`GovAccess::outsideInventoryBoundary`).
  Object scope is therefore partly enforced already.
- Some paths call strict `decide()` even in shadow: transfer posting
  (`TransferPostingService`), the store workspace draft/post flags
  (`ResolvesStoreContext`), approval queues (`RequestQueues`) and the return-draft
  control. In shadow a role-less user can post an ordinary document through
  `DocumentPolicy` but cannot post a transfer, and the button can be hidden while
  a direct POST succeeds.
- My Catalog chooses its mode with `permitsRequest('catalog.office.adopt')`, so in
  shadow every employee with a working office gets the administrator view and
  adoption controls. This is why P02 only shows up in enforce mode.

**Mitigation:** treat this as an unfinished rollout, clearly show effective access
and the rollout status, review aggregate denial patterns, repair assignments and
the blockers below, then announce and execute controlled enforcement. Preserve
object/type/state restrictions in both modes. Do not claim that changing one
environment value completes the required observation or usability work.

Sources: [GovAccess](../../packages/gov-store/tenant-scope/src/Services/GovAccess.php#L76),
[RequireGovAbility](../../packages/gov-store/tenant-scope/src/Http/Middleware/RequireGovAbility.php),
[MenuRegistry](../../packages/gov-store/tenant-scope/src/Navigation/MenuRegistry.php),
[access configuration](../../config/govstore-access.php).

### F02 — Native location synchronization can self-approve office membership (P1)

**Packages:** office-membership, tenant-scope, onboarding integration.
**Evidence:** isolated synchronization-service reproduction and complete source path;
the full profile-save HTTP journey was not exercised.

Every employee capability profile grants `self.edit_location`. Native
`ProfileController::postIndex()` accepts the submitted `location_id` when profile
location editing is enabled (`app.lock_passwords` is false). The registered
UserSyncObserver calls `LegacyUserSynchronizationService::handleUpdatedUser()`.
After any old-office clearance, that service creates an **active home membership**
and records the current actor as approver. It does not require destination-office
administrator approval, an invitation, destination eligibility or matching company.
For a user without an old home membership, the clearance branch is skipped too.

The probe used a company-A employee without membership, changed their native
location to a company-B office, and obtained an active membership approved by that
same employee. This bypasses the guarded join/onboarding process. It grants office
membership, not an automatic permanent storekeeper role; F01 can compound its impact.

Re-evaluation detail:

- The only validation on the submitted value is the native `exists:locations,id`
  rule, so any office in any company is accepted.
- The grant uses `updateOrCreate`, so an existing destination row that was
  rejected, suspended or released is reset to an **active home** membership.
- The same observer runs for native user edits by office, company and ICT
  operators (`users.edit` is in all three profiles). Moving another person natively
  therefore admits them to the destination office without that office's review.
- Without an authenticated actor (queue or CLI), the approver falls back to user `1`.
- `AccessController::belongsToOffice` also accepts a matching native
  `users.location_id` without a membership. A changed native location is enough to
  file access requests at the destination office.

Current data shows no use of this path: no membership carries the sync note, and
none is approved by its own member.

**Mitigation:** remove authority-changing location updates from employee profile
editing. Native user changes must either use the approved membership/transfer
workflow or create a pending request; synchronization must never infer approval
from a changed native field. Recheck actor, destination, company, status and
clearance within a transaction. Preserve native display/profile preferences.

Sources: [employee permissions](../../packages/gov-store/tenant-scope/src/config/permissions.php),
[native profile](../../app/Http/Controllers/ProfileController.php#L82),
[observer](../../packages/gov-store/office-membership/src/Observers/UserSyncObserver.php),
[legacy synchronization](../../packages/gov-store/office-membership/src/Services/LegacyUserSynchronizationService.php#L47).

### F03 — Office provisioning can rewrite the shared master commodity mapping (P1)

**Package:** classification. **Evidence:** controller/service reproduction in enforce mode.

`adoption/provision` requires `catalog.office.adopt`, but accepts `custom_name` and
reuses an existing visible category with that name. `CatalogCategoryCreator`
unconditionally calls `ProductTypeCommodity::assign()`. That method deletes the
category's other commodity mappings and upserts the submitted commodity code.
Shared/global references are visible to office actors. Existing-code upsert also
needs protection against redirecting a code already mapped to another category.

An office administrator lacking `catalog.master.manage` successfully reused a shared
category, replaced its original commodity code with another code, and removed the
original mapping. No national review was required. This is master-data mutation
through an office-level entry point, not ordinary adoption.

**Mitigation:** adoption must link approved definitions without remapping them.
Reject conflicting existing category/code relationships for office actors. Put
commodity changes behind `catalog.master.manage` and exact national review; lock
both sides of the mapping and keep uniqueness constraints. Explicitly distinguish
creating an authorized local product type from changing a shared definition.

Sources: [provision controller](../../packages/gov-store/classification/src/Http/Controllers/CategoryAdoptionController.php),
[creator](../../packages/gov-store/classification/src/Services/CatalogCategoryCreator.php#L22),
[mapping writer](../../packages/gov-store/classification/src/Services/ProductTypeCommodity.php#L16).

### F04 — Submitted category IDs can bypass source visibility during adoption (P1)

**Package:** classification. **Evidence:** controller reproduction in enforce mode.

The single-adopt controller validates `category_id` only as an integer.
`CategoryAdoptionService::useCategory()` writes the target mapping without loading
the category or validating its governance/source eligibility. A forged category ID
from an unrelated office can therefore create local adoption and make that
previously hidden category visible. Nonexistent IDs are also not checked here.

The probe established that a foreign category was invisible, called the controller
as a legitimate office administrator, received success, then found the category
visible in the current office. The target office was correctly resolved; the
missing check concerns the **source object**, not a forged target office.

**Mitigation:** centralize source eligibility for single/bulk/provision/copy paths;
require an existing, non-deleted, approved and adoptable source definition. Do not
make all private categories visible to fix adoption. Return 404 for foreign sources
and leave mappings unchanged. Apply this even in shadow and in job/service entry points.

Sources: [controller](../../packages/gov-store/classification/src/Http/Controllers/CategoryAdoptionController.php),
[adoption service](../../packages/gov-store/classification/src/Services/CategoryAdoptionService.php#L18),
[bulk execution](../../packages/gov-store/classification/src/Services/BulkAdoptionService.php).

### F05 — Ordinary office storekeepers can pass company-reference mutation checks (P1)

**Packages:** tenant-scope and native reference integration. **Evidence:** isolated
native-permission/boundary-policy reproduction; full native edit HTTP not exercised.

Storekeepers receive native category/model/supplier/manufacturer edit permissions.
For a company-owned mapping, CategoryBoundaryPolicy checks only that the mapping's
company equals the working company. It does not require company-administrator
authority. An office storekeeper consequently passes both native edit permission
and the shared mutation policy for a company-owned category, as reproduced, and
references whose mapping keys resolve the same way. Asset models have a separate
key-resolution failure described in F12. Read/adoption scope has become mutation
authority.

Current data has **no company-scope mappings**, so the company-owned case is latent
today. The live form of the same defect is office-scope adoption treated as
ownership; see F14.

**Mitigation:** separate reference visibility/use from reference ownership and
management. Company-wide edits require the corresponding company management role;
office-owned edits require local authority and the exact office; global changes
require national authority/review. Keep narrower native gates as an additional
condition, rather than bypassing them.

Sources: [native capability profile](../../packages/gov-store/tenant-scope/src/config/permissions.php),
[boundary policy](../../packages/gov-store/tenant-scope/src/Policies/CategoryBoundaryPolicy.php#L18),
[observer service](../../packages/gov-store/tenant-scope/src/Services/TenantBoundaryService.php).

### F06 — Multiple roles are combined for GovStore but reduced to one for native permissions (P1)

**Packages:** tenant-scope, membership, native inventory integration.
**Evidence:** two isolated initialized-context reproductions.

GovAccess collects permanent roles and unexpired cover together. AssignmentResolver
instead returns office administrator first, otherwise the first permanent role,
otherwise one temporary role. InitializeTenantContext builds the native capability
profile from this single role. SnipePermissionAdapter then replaces native user and
group grants with that profile.

Reproduced outcomes:

- Office administrator + storekeeper: GovStore posting allowed, native asset and
  consumable checkout denied.
- Permanent primary approver + temporary storekeeper: GovStore fulfillment allowed,
  native asset checkout denied.

The first responsibility is not explicitly ordered, making other multi-role
outcomes dependent on returned row order. Current data has four live pairs with
multiple permanent duties, though the exact failing combinations above were fixtures.

**Mitigation:** resolve a single authoritative, scoped **set** of current roles and
merge the corresponding capability profiles deterministically. Use it for native
permissions, GovAccess, menus and consumers. Preserve transaction-specific
separation of duties: holding two roles does not permit approving one's own request
or taking both approval stages on the same request. Never restore arbitrary group
grants as a shortcut.

Sources: [single-role resolver](../../packages/gov-store/tenant-scope/src/Services/AssignmentResolver.php#L11),
[initializer](../../packages/gov-store/tenant-scope/src/Http/Middleware/InitializeTenantContext.php),
[adapter](../../packages/gov-store/tenant-scope/src/Services/SnipePermissionAdapter.php),
[GovAccess role union](../../packages/gov-store/tenant-scope/src/Services/GovAccess.php).

### F07 — Temporary storekeeper cover fails the serialized-asset mutation guard (P1)

**Packages:** tenant-scope, office-membership, custom-requests/native serialized
inventory integration. **Evidence:** isolated guard reproduction.

Even when cover is the user's only role and native checkout permission is injected
correctly, TenantBoundaryService::verifyAssetMutation reads only permanent
OfficeResponsibility roles. It does not read unexpired grants. The probe confirmed
that a temporary storekeeper passed `requests.fulfill` and native `assets.checkout`
but was rejected by the asset mutation guard when assignment changed.

This can stop a legitimate serialized issue/check-in after earlier authorization
has succeeded. F06 is a separate earlier failure for cover combined with another role.

The same guard first requires `asset.location_id` to equal the working office. After
native checkout copies a recipient's home office onto the asset (F11), the issuing
storekeeper fails this check on check-in as `OUT_OF_BOUNDS`. This was traced in
source, not reproduced.

**Mitigation:** use the same current, scoped effective-role decision at the mutation
boundary, with grant expiry/revocation rechecked. Verify both native checkout/check-in
and the complete GovStore serialized-fulfillment transaction and rollback behavior.

Source: [asset mutation responsibility check](../../packages/gov-store/tenant-scope/src/Services/TenantBoundaryService.php).

### F08 — Narrow access requests grant undisclosed wider responsibilities (P1)

**Packages:** tenant-scope, office-membership. **Evidence:** grant-service reproduction
and review-screen source inspection.

REQUESTABLE maps `catalog.office.adopt` to **storekeeper**, and
`storeops.documents.view` to **primary approver**. Approving an action request
therefore grants the whole role. The probe confirmed that granting requested catalog
access also authorized document posting and request fulfillment in enforce mode.
The request and inbox show the requested ability, reason and expiry, without the
full role/capability expansion the reviewer is approving.

An expiry is optional. When it is left empty, `RoleAssignmentService::grantOfficeAccess`
writes a **permanent** `OfficeResponsibility` row instead of a time-limited grant,
so a request for catalog access can end in permanent stock-control duty.
Permanent rows are removed only through role configuration or release, not by
the access-request lifecycle.

Granting a responsibility through review is an existing design choice, not an
unauthenticated escalation. The gap is least privilege and informed approval:
catalog management does not imply that the reviewer intended to delegate stock control.

**Mitigation:** choose explicitly between action-scoped grants and deliberate
responsibility assignment. For role assignment, display the role, every resulting
ability, operational scope, expiry and separation-of-duties consequences before
approval. Provide a narrower catalog-maintainer/document-reader option if required.
Extend the recovery flow for stage-specific primary/final approval; final-stage
access currently has no dedicated requestable mapping. Do not solve this by
allowing self-approval or automatically granting the final-approver role.

Sources: [request-to-role mapping](../../packages/gov-store/tenant-scope/src/Http/Controllers/AccessController.php#L15),
[grant writer](../../packages/gov-store/office-membership/src/Services/RoleAssignmentService.php),
[review UI](../../packages/gov-store/tenant-scope/src/resources/views/access/inbox.blade.php).

### F09 — Some national mutations bypass the shared review protections (P2; financial/master paths first)

**Packages:** metadata, theming, classification. **Evidence:** effective routes and
source traces; global mutation jobs and national settings were not executed live.

| Entry point | Actual guard | Missing protection |
| --- | --- | --- |
| Metadata convergence trigger | Inline superuser OR native-admin check; dispatches a global-maintenance job over all models | No declared national GovAccess ability, frozen review/reason/confirmation/replay path, or standard action audit at trigger |
| Organization-wide theme assignment | ThemeAccess checks the national superuser ability, then controller writes | The route never runs RequireGovAbility/NationalChangeReview; `national => true` in configuration alone does not execute review |
| National product type's Finance default | Route uses non-national `catalog.finance.default`; canEdit correctly requires catalog master authority for a national type | Role check is present, but national review is not selected for this national target |
| Global category provisioning | Superuser/global context through `catalog.office.adopt` | The same office-level route can create a global category without national review |

Ordinary employees do not thereby obtain theme/finance national authority. Also,
the normal initializer replaces non-superuser native admin permissions; the
metadata controller's native-admin branch is an inconsistent latent trust path,
not proof that every native administrator currently reaches it.

**Mitigation:** use explicit registered national abilities and the existing review
service for every national-effect mutation, including dynamic target scope. Split
local/company and national entry points where one route-level ability cannot
describe the distinction. Retain exact-bundle validation for catalog import. Bind
asynchronous global work to a reviewed operation and audit its final result;
recording only that a job was queued is insufficient.

Sources: [metadata controller](../../packages/gov-store/metadata/src/Http/Controllers/MetadataHealthController.php#L32),
[global job](../../packages/gov-store/metadata/src/Jobs/ConvergeMetadataJob.php),
[theme update](../../packages/gov-store/theming/src/Http/Controllers/AssignmentController.php#L65),
[finance controller](../../packages/gov-store/classification/src/Http/Controllers/ProductTypeFinanceController.php#L13),
[finance ownership decision](../../packages/gov-store/classification/src/Services/ProductTypeFinanceDefault.php#L85).

### F10 — Office readiness does not establish approval-path feasibility (P2)

**Packages:** organization, custom-requests, membership/cover integration.
**Evidence:** isolated readiness/routing reproduction and current assignment aggregates.

Office configuration permits the same person in primary/final approver fields.
Readiness checks existence of a primary approver and storekeeper, but not a distinct
eligible final approver when effective policies require two stages. It also derives
those readiness duties only from permanent assignments, while routing recognizes cover.
ApprovalService correctly prohibits self-approval and the same actor at both stages.

The probe marked an office operational with one person holding both approval roles,
then found no final candidate after excluding the requester and primary actor.
Current data contains four live primary/final role overlaps; actual blocked
requests were not enumerated. A one-person overlap is not itself evidence of a
completed invalid approval, because the decision guard remains in force.

ApprovalRouting::candidates filters deleted users and grant expiry, but does not
itself filter account activation or current membership/valid_until for permanent
duty rows. This can nominate an unavailable actor after expiry or deactivation.

**Mitigation:** compute workflow-specific readiness from the same current eligible
role sources as execution. Check distinct people against the effective approval
policy, requester exclusions, cover and expiry. Show the missing role/person and
supported recovery route before a request is stranded. Preserve small-office
posting and all existing no-self/no-double-approval checks.

Sources: [role configuration](../../packages/gov-store/organization/src/Services/OfficeConfigurationService.php),
[readiness](../../packages/gov-store/organization/src/Services/OfficeReadinessService.php#L15),
[approval routing](../../packages/gov-store/custom-requests/src/Services/ApprovalRouting.php#L11),
[decision checks](../../packages/gov-store/custom-requests/src/Services/ApprovalService.php).

### F11 — Native user visibility ignores additional active office memberships (P1)

**Packages:** tenant-scope, office-membership, custom-requests/native issue integration.
**Evidence:** isolated visibility reproduction and downstream source trace; full
checkout/fulfillment journey not exercised.

UserScope includes users by their native `users.location_id`, plus the actor's own
row. It does not include active additional membership at the working office. The
probe created a same-company employee with another home office and a valid extra
membership in the storekeeper's office: membership eligibility was true, but
`User::find()` returned no user under that storekeeper's scope.

Native CheckInOutTrait uses scoped `User::findOrFail()` for the recipient. GovStore
Request::requester uses a normally scoped User relation and fulfillment passes that
relation to native checkout/adapters. Thus a valid secondary-office member can be
missing from the next actor's view or handoff even though membership was approved.
Native asset checkout also copies the recipient's home `location_id` onto the
asset, requiring deliberate handling of the inventory-office boundary for these users.

**Mitigation:** define membership-aware recipient visibility/eligibility for the
working office, with active account, expiry, company and object scope checks. Do
not remove UserScope globally. Resolve authorized request recipients explicitly
and preserve the issuing office's inventory ownership rather than silently moving
stock to the person's home office.

Sources: [UserScope](../../packages/gov-store/tenant-scope/src/Scopes/UserScope.php),
[native recipient resolver](../../app/Http/Traits/CheckInOutTrait.php#L15),
[requester relation](../../packages/gov-store/custom-requests/src/Models/Request.php#L152),
[fulfillment](../../packages/gov-store/custom-requests/src/Services/FulfillmentService.php).

### F12 — Local asset-model ownership uses an incompatible reference key (P1)

**Packages:** tenant-scope, native product-model administration/store integration.
**Evidence:** isolated scoped-model/native-permission/ownership-policy reproduction.

The scope registry, administration form and schema use `reference_type = model`
for App\Models\AssetModel. ReferenceOwnershipService derives the type from the
PHP class basename and searches for `assetmodel` instead. A correctly mapped local
asset model is therefore visible but its ownership is interpreted as GLOBAL.
CategoryBoundaryPolicy then prohibits the local edit.

The probe confirmed a storekeeper with `models.edit` could load the office-mapped
model, but ownership resolved to GLOBAL and mutation permission was false.

Current data has no asset-model mappings at all: all 133 live models are unmapped
and treated as national, so no existing local model is misclassified yet. The defect
takes effect as soon as model mapping is used. Until then, the storekeeper profile's
`models.edit` is always denied, while `models.create` publishes nationally (F15).

**Mitigation:** use one explicit canonical class-to-reference-key registry in
mapping administration, visibility and ownership. Preserve the denial for actual
global models. Test local/company/global model visibility and mutation separately;
review existing mappings before any data normalization.

Sources: [ownership key derivation](../../packages/gov-store/tenant-scope/src/Services/ReferenceOwnershipService.php#L13),
[registered model scope](../../packages/gov-store/tenant-scope/src/Providers/TenantScopeServiceProvider.php),
[mapping input keys](../../packages/gov-store/tenant-scope/src/Http/Controllers/TenantScopeController.php#L107).

### F13 — The native location observer rejects an authorized office-hub update (P1)

**Packages:** organization, tenant-scope. **Evidence:** actual controller-call
reproduction with initialized office-admin context in isolated SQLite.

An active office administrator passes `organization.office.manage` and
OfficeAdministration::office for their own office. OfficeHubController::update
then updates the native Location. TenantMutationObserver sends Location through
the generic reference policy, where a normal unmapped location is interpreted as
GLOBAL and cannot be changed locally. The administrator's valid office update is
rejected by this second, incompatible ownership decision.

The probe passed both initial authorization checks and called the actual hub
controller with an allowed same-company/same-geography rename. It received
TenantBoundaryException from the native location mutation path. This is a concrete
case where showing the office-management screen is not evidence the user can save.

The block skips the policy only for a global context. ICT officers, and superusers
who have selected an office context, therefore fail the same save. In practice
only a superuser in national view can rename an office through the hub. This was
traced in source; only the office-administrator case was reproduced.

**Mitigation:** give office-location administration a dedicated mutation boundary
that recognizes the authoritative office/territory authorization and allowed
fields. Keep strict checks for company/geography/parent changes and unrelated
offices. Do not disable all model observers or make all local administrators
global reference administrators to let this save succeed.

Sources: [hub update](../../packages/gov-store/organization/src/Http/Controllers/OfficeHubController.php),
[protected native models](../../packages/gov-store/tenant-scope/src/Providers/TenantScopeServiceProvider.php),
[generic mutation-policy resolution](../../packages/gov-store/tenant-scope/src/Services/TenantBoundaryService.php),
[reference mutation policy](../../packages/gov-store/tenant-scope/src/Policies/CategoryBoundaryPolicy.php).

### F14 — Adopting a shared category makes the first adopter its owner (P1)

**Packages:** tenant-scope, classification, native category administration.
**Evidence:** read-only evaluation of the actual ownership service and mutation
policy on current rows, plus an isolated probe through the real request initializer
and model observer; the native category edit controller was not exercised.

Adoption (`CategoryAdoptionService::useCategory`) writes the same
`gov_tenant_scope_mappings` rows that `ReferenceOwnershipService` reads as
ownership. `getMapping()` takes the first row for the reference, with no ordering,
no `is_active` filter and no reference to `gov_category_governance`.
`CategoryBoundaryPolicy` then allows a change whenever that row's office equals the
working office. Storekeepers hold native `categories.edit`.

In current data, 70 categories governed as `global` are each adopted by about 48
offices (median 48). For all 70, the policy allows a change by the first-adopting
office (2 offices in total). A storekeeper there can edit a national definition that
dozens of other offices use, with no national review. The owner can also change
silently: if the first adopter abandons the category, the next row becomes the
owner. An archived (`is_active = 0`) adoption hides the category from its own office
but still counts as ownership, so every other adopter stays refused.

The probe set up a `global`-governed category adopted by office 10 and then office 30
(different companies), each with a permanent storekeeper whose injected native
profile includes `categories.edit`. The office-10 storekeeper renamed the national
category and the change was saved. The office-30 storekeeper, with the same native
permission, was refused; mapping row order was the only difference. After office 10
archived its adoption, it could no longer load the category and office 30 was still
refused. Once office 10's row was removed, office 30's rename succeeded.

This is different from F05: no company-administrator shortcut is needed, and the
exposure exists now rather than in a fixture.

**Mitigation:** store ownership separately from adoption and visibility, and derive
it from governance (P03). Make national definitions immutable except through
`catalog.master.manage` with national review, whoever adopted them. Until that
lands, treat any reference whose governance is `global` as GLOBAL in
`CategoryBoundaryPolicy`. Review the edit history of the 70 categories before
assuming no change has been made.

Sources: [adoption writer](../../packages/gov-store/classification/src/Services/CategoryAdoptionService.php#L18),
[ownership read](../../packages/gov-store/tenant-scope/src/Services/ReferenceOwnershipService.php#L13),
[mutation policy](../../packages/gov-store/tenant-scope/src/Policies/CategoryBoundaryPolicy.php#L18),
[storekeeper native profile](../../packages/gov-store/tenant-scope/src/config/permissions.php).

### F15 — Native create screens bypass reference and office governance (P1)

**Packages:** tenant-scope, organization, native category/model/supplier/manufacturer/location
administration. **Evidence:** isolated probe for categories and locations through the
real request initializer and model observers; models, suppliers and manufacturers
share the code path but were not probed; native create controllers were not exercised.

`TenantBoundaryService::verify()` skips the ownership policy on `create`, and
`CategoryBoundaryPolicy` declares no tenant columns to stamp. Nothing in GovStore
overrides or redirects the native `categories.store`, `models.store`,
`suppliers.store`, `manufacturers.store` or `locations.store` routes. As a result:

- The storekeeper profile includes `categories.create`, `models.create`,
  `suppliers.create` and `manufacturers.create`. A new row has no mapping, so
  `TenantScope` treats it as an unadopted shared national standard: it becomes
  visible to every office without national review. Ownership then resolves to
  GLOBAL, so the creating office cannot correct it either. For categories,
  `SnipeCategoryObserver` also attaches the national default store profile.
- The office, company and ICT operation profiles include `locations.create`. A
  native location is created outside the organization package's provisioning
  (lifecycle profile, geography, ICT territory). Its later edits fail as in F13.

The probe reproduced both cases:

- An office-10 storekeeper with injected `categories.create` saved a new category.
  No mapping or governance row was written. A storekeeper at an office in another
  company could then load it, and the creating storekeeper's attempt to rename it
  was refused as GLOBAL.
- An office-10 administrator with injected `locations.create` saved a new native
  location. No organization profile was created, and a later rename was refused by
  the location observer.

Current data has 6 live categories with neither a mapping nor a governance row; how
they were created was not determined.

**Mitigation:** either remove the native create permissions from office profiles and route creation
through GovStore provisioning, or map new references to the creating scope inside
the boundary service, with national publication left to review. Office creation
belongs to the organization provisioning workflow only.

Sources: [create skip](../../packages/gov-store/tenant-scope/src/Services/TenantBoundaryService.php#L52),
[unadopted visibility](../../packages/gov-store/tenant-scope/src/Scopes/TenantScope.php#L90),
[native profiles](../../packages/gov-store/tenant-scope/src/config/permissions.php),
[category store](../../app/Http/Controllers/CategoriesController.php#L65).

## Policy inconsistencies requiring a decision, not an automatic permission increase

| ID | Observation | Recommended decision |
| --- | --- | --- |
| P01 | Company capability profile advertises `office.provision`, `office.onboard`, `jurisdictions.manage`, while `organization.office.manage` excludes company_admin and OfficeAdministration requires superuser, ICT territory or the actual local administrator | Decide which company-level organization functions are intended. Either remove misleading capabilities and explain referral to ICT, or add a deliberately scoped company workflow. Never give company admins national jurisdiction assignment implicitly |
| P02 | My Catalog detail route declares `catalog.view`, but `show()` requires effective adoption access and rejects employees in enforce mode | Decide whether employees should see a sanitized product-type detail or only the grid/request catalog. Use a distinct management-detail ability if operational statistics are privileged |
| P03 | ReferenceOwnershipService treats the first adoption mapping as ownership; several offices can adopt the same definition, and governance is represented separately. The concrete defect is now F14 | Decide where ownership lives (governance row or a dedicated owner column), how existing multi-office mappings migrate, and who resolves conflicts. Adoption and visibility must never confer ownership |
| P04 | There is no implemented general auditor role or mandatory independent store-document verifier in the current registry/workflow | Specify the required read-only auditor scope and future verifier policy before adding them. Do not claim READY proves independent verification or grant posting to auditors |
| P05 | `gov_category_governance.governance_type` is documented as `global`/`company`, but current rows are `global` 81, `location` 644, `local` 58. `ProductTypeFinanceDefault::scope()` treats everything except `company` as national, so the Finance default of every office-provisioned type is editable only by a national superuser, through the non-national route in F09 | Fix one vocabulary (national / company / office) and its owner, and migrate existing rows. Decide who sets an office type's Finance default. Do not widen national authority to make office rows editable |

## Mitigation work plan

### Phase A — Close boundary defects and prepare legitimate roles

Suggested owners: authorization maintainer plus membership/classification owners.

1. **F02:** block employee/native location changes from granting active membership.
   Add negative profile-save tests and approved transfer/onboarding positive tests.
2. **F03/F04/F05/F14:** add source/governance eligibility and actor-specific reference
   mutation checks; forbid office provisioning from rewriting master mappings. As an
   interim guard, treat `global`-governed categories as GLOBAL in the mutation
   policy, then review the 70 affected categories' history.
3. **F15:** remove or reroute office-level native create permissions; cover models,
   suppliers and manufacturers in the corrected tests as well as categories and locations.
4. **F06/F07/F11/F12/F13:** implement a shared effective-role set, canonical model
   ownership keys, a dedicated office-location mutation boundary and membership-aware
   recipient handling; exercise native bulk and serialized integrations and save
   the office-hub form as its actual local administrator and as an ICT officer.
5. **F08:** make grant expansion explicit, decide least-privilege grant types, require
   or default an expiry for request-based grants, and preserve current grants until
   reviewed through supported revocation/expiry.
6. Identify current affected accounts privately through My access/staff/admin
   screens; use the aggregate observations above to prioritize review. Inspect
   suspicious mapping/membership changes and audit history without assuming every
   historical self-approved or shared mapping row is malicious. Repair through
   reviewed workflows; do not rewrite history or remove other people's grants.

**Exit criteria:** same-office wrong-role and foreign-object requests fail before
mutation, including in shadow for boundary violations; legitimate combined-role,
cover and secondary-office users complete the relevant work without bootstrap
superuser substitution. No blanket native-admin bypass or protection relaxation.

### Phase B — Complete national review and workflow feasibility

Suggested owners: metadata/theming/classification owners and request workflow owner.

1. **F09:** declare national metadata authorization; require existing review for
   national Finance defaults, global provisioning and organization theme changes.
   Verify frozen target/payload, reason, typed confirmation, expiry and durable
   replay rejection. Review job execution authorization/provenance separately.
2. **F10:** validate distinct eligible approval actors when the selected policy
   requires them; exclude disabled/expired/non-member candidates; use cover in
   readiness. Provide an administrator recovery path for missing final approval.
3. Resolve P01–P05 and update the role/ability registry, menu reasons and My access
   wording together. Capability profiles must not advertise unsupported authority.

**Exit criteria:** national effects cannot be committed through a non-national
route; users and reviewers understand the exact scope of a grant; supported
approval policies have an eligible path or an explicit actionable blocker.

### Phase C — Controlled enforcement rollout

Suggested owners: deployment operator, office administrators and business policy owner.

1. Preserve shadow as a documented temporary rollout state while Phase A blockers
   are resolved. Review existing denial aggregates and actual user assignments.
2. Perform the required real observation period and role-gap resolution; the
   historical G1 plan calls for two weeks. This investigation does not establish
   that any part of that observation has been completed.
3. Run usability checks with actual employees, storekeepers, approvers, office
   administrators and cover holders; validate English/Bangla denial explanations,
   keyboard operation, draft continuity and who-can-help guidance.
4. Announce enforcement date and help contact. Pilot on a designated test/staging
   scope, then enable the existing enforcement configuration in the authorized
   deployment. If production needs per-office cohorts, implement that capability
   explicitly; the present setting is global, so do not imply a cohort switch exists.
5. Verify resolved web and queue-worker configuration, roles and boundaries after
   rollout; monitor meaningful denials and incomplete handoffs. Resolve a missing
   duty with scoped reviewed cover rather than making every employee a storekeeper.

**Exit criteria:** all required supported role journeys have acceptance evidence,
wrong-role actions are denied server-side, role gaps have owners, temporary grants
expire as intended, and deployment observation/communication requirements are met.

## Required verification when implementing mitigations

Maintain a package/action inventory, including documented controller-service and
reference-data exceptions. Extend route coverage beyond the current G1 prefix
regex; require each protected action to declare an ability or a justified tested
alternative guard. Route coverage alone does not establish object authorization.

For every affected action test positive and negative combinations of:

- Employee, storekeeper, primary/final approver, office admin, company admin, ICT,
  committee registrar/member, tracking HEAD/OFFICER/SUPPORT/MONITOR, superuser,
  native-admin-only account, combined roles and temporary cover.
- Home versus secondary membership; expired/suspended/released membership;
  inactive/deleted user; grant expiry/revocation on the next request.
- Working office, same-company other office, other company and out-of-jurisdiction
  geography; foreign IDs in both URL and body; source and target independently.
- Allowed and forbidden document/request/committee/task state, concurrent/stale
  actions, duplicate/replay, meaningful status and unchanged business state on denial.
- Strict policy versus effective shadow behavior; parity among menu, My access,
  action controls, direct HTTP, native policy, observer and delayed job execution.

Mandatory connected acceptance after the fixes:

| Journey | Required handoff and assertions |
| --- | --- |
| Join/transfer/onboarding | Employee cannot self-approve; authorized destination reviewer approves; next request obtains only intended context |
| Catalog/reference administration | Office actor adopts an approved source; foreign source is denied; shared commodity/Finance definitions change only through the proper owner/review; the first adopter of a national category cannot edit it natively; a storekeeper-created reference is not visible to other offices without review |
| Stock to employee | Real storekeeper receives/posts; requester submits; distinct required approvers decide; storekeeper fulfills; employee acknowledges |
| Combined role / cover | Same workflow with combined duties and temporary cover, then immediate expiry/revocation denial; native checkout/check-in included |
| Secondary-office employee | Storekeeper can locate the authorized recipient and fulfill without changing stock's owning office to the employee's home office |
| Committee | Registrar creates/activates allowed scope; member accesses only their own allowed files/declaration; employee cannot manage after enforcement |
| Programme delivery/maps | Actual team designation configures task; permitted office receives; unrelated office and programme denied; reports/maps/private documents retain boundaries |
| National administration | Exact reviewed national operation executes once; tampered/expired/replayed operation fails; metadata job final outcome auditable |
| Themes/tags/experiments | Scoped positive/negative assignment and lookup; national enablement review; experiment administration remains activated-superuser/nonproduction only |

Run the focused G1 suite and affected package tests, then the combined GovStore
suite using the documented increased memory limit. Add meaningful regression tests
that assert the **corrected invariant**, not permanent tests asserting these
vulnerabilities are acceptable. Use isolated MySQL for concurrency changes.
Compile and lint affected Blade output and complete real UI handoffs separately.
Do not count blocked/unimplemented journeys as passed.

## Reproduction record and cleanup

The temporary investigation class extended the existing SQLite-memory
OfficeMembershipWorkflowTest setup and ran only its thirteen `test_audit_*` methods.
It reproduced, in order:

1. Strict employee denial versus effective shadow permission across four packages.
2. Office-admin/storekeeper union lost in native capability injection.
3. Cover-only storekeeper rejected by permanent-only asset mutation guard.
4. Foreign category becomes locally visible after single-adopt controller call.
5. Office provision controller replaces a shared category's master commodity mapping.
6. Native location-sync service creates a cross-company active membership approved by self.
7. Catalog-access role grant also authorizes stock posting and request fulfillment.
8. Permanent approver suppresses temporary-storekeeper native checkout permission.
9. Office storekeeper passes company-owned reference mutation policy.
10. Readiness accepts an office with no distinct eligible final approver.
11. Active secondary-office member remains invisible to scoped User lookup.
12. A visible local asset model is interpreted as globally owned and cannot be edited.
13. Authorized local office-hub update fails the generic native location observer.

Only isolated tests wrote fixtures; the existing local database was queried
read-only. No runtime mode, user password, permission assignment or national setting
was changed. Temporary PHP helpers, raw run logs and test XML were removed after
recording the sanitized results. The report and route inventory are the retained
task artifacts; no test business records were added to the development database.

Re-evaluation record: three read-only scripts in a session scratch folder (outside
the repository) ran aggregate queries and evaluated `ReferenceOwnershipService` and
`CategoryBoundaryPolicy` in memory, each inside a transaction that was rolled back.
They printed counts only, with no identities.

A second temporary class extended the same SQLite-memory OfficeMembershipWorkflowTest
setup and ran four `test_audit_*` methods. It reproduced:

14. A first-adopting office storekeeper renames a nationally governed category; the
    second adopter with identical native permission is refused.
15. Archiving keeps ownership while hiding the category from its owner; removing the
    owner's row hands ownership to the next adopter.
16. A storekeeper-created category has no mapping or governance, is visible to another
    company's office, and cannot then be corrected by its creator.
17. An office administrator creates a native location without an organization profile;
    its rename is then refused.

The abandon step deleted the mapping row directly, as `stopUsingCategory()` does after
its holdings check, because the fixture stock tables lack the columns that check
reads. The class was deleted after the run; no development data was written.

Remaining: implement the mitigations, resolve the listed business-policy choices,
and complete real role-by-role UI acceptance and deployment rollout. Local tests
do not establish CI success, WCAG compliance, production readiness, incident absence
or completion of real-world shadow observation.
