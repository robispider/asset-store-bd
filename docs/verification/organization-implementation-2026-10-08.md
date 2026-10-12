# Organization implementation and verification

Date: 8 October 2026 (Asia/Dhaka). Scope: section 3 of the package gap analysis,
including the Classification starter-catalog consumer required by ORG-2.
Branch: `gap-mitigation2`, working tree.

## Implemented

- Office hubs now provide suspend, resume and geographic relocation actions with
  server authorization, reason, typed `CHANGE`, expected status/geography, locked
  rechecks and transactional organization history. Relocation keeps the office ID,
  ministry and hierarchy and clears geography verification. An ICT officer needs
  authority over both territories; office administrators cannot relocate offices.
- Suspension pauses new request intake and office setup. Existing requests,
  approvals, fulfillment and returns remain available to their authorized actors.
  Intake checks the working and delivery office inside the submission transaction;
  readiness cannot restore a suspended or unknown lifecycle state.
- Administration no longer treats native admin permission as superuser permission.
  Office configuration validates live recipients, company, active membership and
  optional membership expiry before writing. It preserves unrelated responsibilities
  such as committee registrar. Routine profile edits cannot retag geography, company
  or parent to bypass lifecycle/consumer checks.
- Provisioning publishes the typed event after commit. New offices wait for active
  membership onboarding and administrator assignment. Fresh-office forms explain
  this order; administrator changes publish the handoff again.
- Starter delivery persists one run per office and a frozen, sorted commodity
  bundle, expanding folders and retaining explicit category types. It verifies all
  required collections, reference fingerprints, actor, office, company and profile
  state. Queue retries use the same bundle; completed deliveries are no-ops.
  Failed preparation/delivery has a private log reference and a hub retry control.
  Failed adoption rolls back category/mapping writes. Existing category governance
  attribution is preserved, and reference visibility checks remain in place.
- Synchronous queue delivery restores its caller's context/identity, including
  console callers. Background-worker cleanup retains its previous behavior.
- All Organization routes now declare named abilities. Directory routes also have
  web authentication. National jurisdiction/company-admin mutations and directory
  synchronization use the existing review/reason/confirmation/expiry/replay controls.
  Review fingerprints include the exact assignment or bundled directory and current
  directory/company data. Unreviewed custom directory uploads are rejected.
- New controls and permission labels have English/Bangla translations. Registry
  filters and status badges show suspension, and hub setup forms explain/disable
  unavailable editing while suspended.

## Verification

- `OrganizationWorkflowTest.php`: **12 tests, 87 assertions**, isolated SQLite.
  Covers actual office creation → active membership → admin assignment → committed
  starter adoption; after-commit timing; folder expansion; duplicate deliveries;
  missing collection repair; atomic rollback/retry; revoked actor; changed reference;
  forged bundle; cross-office/native-admin denial; suspension/readiness/intake;
  both geography boundaries; membership expiry; preserved responsibilities;
  national stale/expired/tampered/replayed review; bilingual rendered controls.
- `G1AuthorizationTest.php`: **29 tests, 1,135 assertions**, isolated SQLite.
  Route coverage includes Organization without absorbing the separate membership
  routes that also use the `/gov-store/office` prefix.
- The eight top-level GovStore test files (Organization, Geo Areas, Tenant Scope,
  G1, Committee, Store Operations and Custom Requests) passed together:
  **146 tests, 3,376 assertions**, PHP 8.4.15, isolated SQLite, **512 MB** process
  memory limit. The first combined run exceeded the default 128 MB limit in an
  attachment check; the larger process-local limit completed successfully without
  weakening coverage or changing machine-wide configuration. Theming's separate
  subdirectory and experimentation's separate-database tests were not run.
- Final focused verification after the last guard/UI edits reran G1, Organization
  package/workflow and Geo Areas: **52 tests, 1,259 assertions passed**. Compiled
  Organization templates and changed guard PHP were checked again.
- All **11 Organization Blade templates** compiled and their generated PHP parsed.
  Changed PHP files passed syntax checks, and the final diff passed whitespace checks.
- An isolated InnoDB contention check held an office profile lock while another
  process called the actual intake guard. The second process waited **913 ms** and
  returned **409** after suspension committed. The exact owned database
  `govstore_organization_test_20261007190747_f0caa2` was dropped afterward. This
  establishes the profile-lock check, not every workflow's concurrency behavior.
- Fresh browser verification at `snipeit.local` used the existing ordinary session:
  Organization registry access rendered a bilingual denial, translated ability,
  reference ID and help links, with no debug toolbar. The existing login was
  preserved. No positive office-admin lifecycle walkthrough is claimed.
- The effective local connection was verified as local MySQL database `snipeit`
  before applying only `2026_10_08_000001_create_office_starter_runs.php`. The new
  table has **0 runs**. All **57 office profiles** retain their baseline states:
  49 operational, 6 configured, 2 provisioned. Access mode remains **shadow**.
  See [read-only live evidence](organization-live-2026-10-08.json).

## Remaining and operational limits

- **ORG-1 is partial.** Closure and merge remain unavailable, with explicit server
  rejection even for superusers. OM-2 and complete membership, holdings, request
  and committee clearance are prerequisites. No empty registry or partial consumer
  check is treated as clearance. This package is not fully closed.
- **ORG-2 and CL-2 are mitigated in implementation.** The provision-to-consumer
  workflow and safe retries are verified with isolated fixtures. Live deployment
  still needs a reviewed starter library: **all configured collection names have
  zero active matches in the current local database**. The task did not invent or
  publish national collection contents, bulk-adopt into existing offices, or run
  an existing dataset through a new starter job.
- A frozen run whose reference version/title/type changes deliberately fails. Restore
  the reviewed reference or design a separately reviewed replacement workflow;
  retries must not silently select a different bundle.
- Apply the additive migration before serving hub pages on other deployments; clear
  stale route/config caches and restart workers to load the job changes. Old queued
  string-code payloads do not have the new durable bundle contract and need operator
  review rather than automatic rewriting.
- No office/user/stock/catalog business fixture was created in the existing local
  database. The additive schema/migration record and access-denial audit remain.
  Task helpers and the isolated database were removed; no access mode or grant was
  changed. Existing organizational role/readiness data was not bulk repaired.
- Positive live admin UI flows, remote CI, full native application regression,
  accessibility/usability certification, production deployment and the real G1
  shadow-observation period remain unverified.
