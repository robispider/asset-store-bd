# GovStore security rules and lessons for future agents

Recorded 4 October 2026 after the G1 implementation and local verification.
This guide is required reading through the repository's [AGENTS.md](../../AGENTS.md).
It supplements the upstream [security disclosure policy](../../SECURITY.md).

## Required rules

### 1. Separate authentication, ability and object checks

An authenticated user still needs permission for the action and access to the
specific object. Check both before exposing data or reaching a mutation. Menu
qualifiers and disabled controls are user guidance; direct HTTP requests must
receive the same protection.

For the G1 surfaces (`gov-store/operations`, `gov-store/access`, `gov-requests`,
`admin/catalog`), declare exactly one registered `gov.can:<ability>` on each
effective route. The registry is
`packages/gov-store/tenant-scope/src/config/abilities.php`; decisions belong in
`GovAccess`. Register explicit named gates, not a broad `Gate::before` intercepting
all dotted permissions. Inspect Laravel's effective route collection, including
aliases, duplicate names and controller method existence.

Other packages retain their own authorization services. Preserve their object
checks and extend regression coverage if the task adds a new protected surface.
Never infer that every package is secure because the G1 route test passes.

### 2. Resolve roles within a trustworthy working context

Reset the shared `TenantContext` **in place** at the start of each request. Laravel
may instantiate a controller and its services before route middleware runs.
Replacing the context instance leaves those services holding an older object.
Clear global/admin flags, allowed IDs and cached configuration as well as office IDs.

An active working membership must belong to the signed-in user and have active
status. Validate superuser office selection against an existing office. Native
`hasAccess('admin')` must not imply global/superuser access. Check that selected
office/company scope is appropriate for the actor; a company ID alone does not
authorize company-wide adoption.

Use `OfficeResponsibility`, the assigned office administrator, company/jurisdiction
assignments and unexpired `gov_access_grants` as the current role sources. Do not
resume writes to deprecated `LocationRole`. Changes and expiry must take effect on
the next request without requiring logout. Keep menus, queues and controller guards
consistent with temporary cover. Access approval must enforce the reviewer role,
office, pending state, requester membership and no self-approval; replay must fail.

### 3. Check every object ID, including mutations

Verify working-office/company boundaries on document views, prints, metadata,
attachments, previews and writes. Require the URL document type to match the stored
type. Verify request delivery-office access for approval, fulfillment and closure,
including direct POSTs; a filtered list does not protect a detail endpoint.

Office-copy source listing and fetching must apply the same ministry boundary.
Do not trust submitted location/company IDs or remove global scopes without an
explicit equivalent boundary. Foreign documents/requests should return 404 so
their existence is not disclosed. General ability denials return 403.

### 4. Make document posting atomic and authoritative

Only DRAFT is editable. Validated DRAFT and READY can post under the current G1
policy. POSTED, CANCELLED and unknown states cannot be edited or posted. READY is
not evidence of a completed verification workflow; a second verifier depends on G7.

Lock the document row inside the transaction, refresh/reload it, and recheck
scope/type/state before writing. Keep draft persistence, required references,
metadata validation, inventory materialization and success audit consistent with
that transaction. A check performed only before the transaction does not prevent
double posting or a save racing a post.

Enforce the same posting checklist on the server that controls the UI button.
Do not continue to the confirmation dialog after a failed save or invalid checklist.
READY validation uses saved quantity/metadata and does not trust submitted edits.
Record creator/drafter and poster separately. Takeover changes management and adds
history without rewriting the original author. Mark draft prints clearly. Do not
advertise a verifier, auditor role or reversal workflow that has not been implemented.

### 5. Preserve national review integrity

National abilities enforce even during office shadow mode. Require the authorized
administrator, reviewed proposal, reason and typed `CHANGE` confirmation before
mutation. Bind review to the actor/session, exact target and method, expiry and
configuration fingerprint. Restore the frozen proposal during confirmation so
submitted changes cannot substitute unreviewed input. Use a unique durable
consumption marker to reject concurrent replay; session deletion alone is insufficient.

Catalog execution also requires a valid review of fingerprints of the exact files
that execute, with matching scheme/version. The current importer supports a
compiled three-file bundle. Reject unsupported uploads rather than silently importing
a different source. Label aggregate national totals as an upper bound unless actual
affected objects are calculated. Record publication actor, timestamp and reason.

### 6. Roll back rendered failures and protect audit integrity

Laravel's routing pipeline can render a controller exception into an HTTP response
before outer middleware sees it. Reaching the line after `$next($request)` does
not establish success. `RequireGovAbility` checks response status and newly flashed
errors, rolls back failed mutations and retains a failure audit separately. A
previous page's error must not roll back a subsequent successful action.

Test both thrown exceptions and rendered failures. Audit successes only when the
mutation commits. Log actor, roles, office, ability, outcome, reason and reference;
do not dump submitted forms or credentials. Denial/shadow events deduplicate per
user, ability, day and outcome. Current policy retains events indefinitely; only
superusers see individual audit records, while ICT reports expose jurisdiction-bound
aggregates. Changing retention or visibility is a policy change.

### 7. Keep private evidence and safe failures private

Use private storage and an authenticated, authorized attachment download route.
Authorization must cover upload, download and deletion as well as the document
page. Existing public attachments must be migrated too. Verify the private copy
before changing references and deleting the public source; preserve all references
when multiple records share a file. Missing source files are a recovery issue,
not permission to invent replacement evidence.

Catch unexpected failures safely, including `Throwable` where appropriate. Return
a user-facing reference and log technical detail privately. Do not return exception
messages, SQL, file/line details, traces or compiled snapshots. Check the shared
application exception handler: Snipe-IT's legacy behavior can return HTTP 200 for
errors, so G1 preserves meaningful 403/404/409/422/500 statuses without changing
unrelated API contracts. Keep the debug toolbar disabled on protected responses;
it can expose queries and exceptions even when the visible message is sanitized.

### 8. Treat shadow mode as a controlled rollout

Office role restrictions log rather than block in shadow mode. National/admin
controls, unknown abilities and object/type/state boundaries still enforce.
Unknown configuration mode values fail closed. Menus reflect actual ability
decisions; shadow access does not grant the corresponding permanent role.

Before office enforcement, complete real observation, role-gap resolution,
usability/accessibility checks and the announcement of an enforcement date/help
contact. Temporarily exercising enforcement locally is not completing rollout.
When changing cached configuration, verify the effective mode in the application.

## Failures encountered and what they taught us

| Observation during G1 work | Lesson to preserve |
| --- | --- |
| A comment said "Superadmin Only", while routes checked only login. | Describe permissions in code and enforce them on the server. |
| A new draft redirected successfully but its workspace returned 404. | Services held an old context instance; preserve the shared object and verify saved office/company IDs. |
| A foreign-object failure returned HTTP 200. | Test response statuses through the real application handler, not just policy exceptions. |
| An exception could become a response inside a transaction. | Explicitly roll back rendered failures; test database state as well as response text. |
| A previous flashed error could be mistaken for a new failure. | Inspect current-request flash errors, not any existing session error. |
| The UI rejected a missing reference but a direct POST could post. | Server validation is authoritative; disabled buttons are bypassable. |
| READY forms omit disabled fields from submission. | Validate saved metadata rather than expecting editable form input. |
| Blade compilation succeeded but a live page failed to parse. | Lint compiled PHP and render representative pages; compilation alone is insufficient. |
| Sanitized pages still had a debug toolbar with queries. | Check debug tooling and response payloads for leaks too. |
| Existing evidence remained public after new uploads became private. | Migrate existing data/files as well as changing future writes. |
| Temporary cover passed middleware but old queue guards ignored it. | Update all consumers of role decisions, including queues and inline guards. |
| Custom Requests had obsolete handlers and unscoped detail mutations. | Check effective handlers and mutation boundaries independently of list filtering. |
| Rule assignments shared a route name. | Check both named-route generation and all effective URLs. |
| Catalog execution bypassed review and ignored uploaded source files. | Bind validation to what executes and reject unsupported input explicitly. |

## Verification procedure

1. Read current routes, middleware order, controllers, services, scopes, role sources
   and exception handling. Trace both HTML and JSON paths before editing.
2. Preserve user changes. Identify the narrow invariant being changed, its object
   boundary and all entry points, including service/job/command entry points that
   can bypass HTTP middleware.
3. Add meaningful negative tests for the changed invariant. Include appropriate
   ordinary staff, storekeeper, approver, office admin, company admin, ICT and
   superuser cases. Use a user whose actual assignments match the test's intended role.
4. Run the isolated G1 suite and relevant package tests. Test foreign IDs, forged
   types, locked/unknown states, direct POSTs, grant expiry/revocation without logout,
   token tampering/expiry/replay, and database rollback when applicable. SQLite
   tests do not prove MySQL concurrency behavior; check row-lock ordering and use
   an isolated MySQL exercise when changing concurrent posting behavior.
5. For UI changes, check bilingual key parity, compiled PHP syntax, real page
   rendering and browser errors. Verify disabled-control reasons and confirmation
   contents with the actual role. Accessibility attributes are not a WCAG certificate.
6. Live test only the environment authorized for the task. Use minimal identifiable
   fixtures; keep test policies unassigned and balance test receipt/issue quantities.
   Record baseline/final state, expire cover grants, restore temporary settings and
   remove helper files. Preserve audit evidence and report retained test documents.
7. Use a dedicated automated test database. Never reset/seed the existing dev database
   to satisfy tests. Verify the target before migrations; apply targeted schema changes
   required by the authorized task. A missing `.env.testing` is not permission to use
   the live database as a fallback.
8. Keep passwords, tokens, cookies and environment values out of source/evidence.
   Obtain credentials through the authorized task context. Local dev credentials
   and permission to test one host do not authorize production operations.
9. Report exact checks and limits. A successful local suite is not a remote CI run,
   a full application regression run, a catalog re-import, or two weeks of monitoring.

## Implementation map and dated evidence

| Concern | Repository location |
| --- | --- |
| Ability registry, role decisions, audit, national review | `packages/gov-store/tenant-scope/src/config/abilities.php`, `src/Services/` |
| Context initialization and route enforcement | `packages/gov-store/tenant-scope/src/Http/Middleware/` |
| Access request/review routes and controller | `packages/gov-store/tenant-scope/src/routes/access.php`, `src/Http/Controllers/AccessController.php` |
| Document policy, validation and posting | `packages/gov-store/store-operations/src/Policies/DocumentPolicy.php`, `src/Http/Controllers/DocumentWorkspaceController.php`, `src/Services/` |
| Existing evidence migration command | `packages/gov-store/store-operations/src/Console/Commands/ProtectDocumentAttachments.php` |
| Catalog bundle review | `packages/gov-store/classification/src/Services/CatalogReview.php` |
| G1 exception responses | `app/Exceptions/Handler.php` |
| Rollout configuration | `config/govstore-access.php` |
| Regression suite and CI | `tests/Feature/GovStore/G1AuthorizationTest.php`, `.github/workflows/g1-authorization.yml` |

Run with installed PHP:

```text
php vendor/bin/phpunit tests/Feature/GovStore/G1AuthorizationTest.php
```

The 4 October 2026 execution recorded 99 protected G1 routes, 29 tests with 649
assertions and 91 local HTTP checks. These counts are a historical baseline, not
targets to preserve by deleting tests or excluding new routes. See the
[execution record](../gap-analysis/g1-unprotected-actions-analysis.md) and
[live evidence](../gap-analysis/g1-live-verification.json) for details.

At that handoff, office mode was restored to shadow; national controls enforced.
CI confirmation, the full application suite, accessibility/usability sessions and
two real weeks of observation remained open. Four existing evidence files had been
moved into private storage; 180 older records referenced files already missing.
Full live catalog re-import, a second verifier, an auditor role and a reversal
workflow were not established by that exercise. Recheck current state before
claiming any of these items is now complete.
