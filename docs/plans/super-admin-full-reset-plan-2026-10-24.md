# Super administrator full installation reset — investigation and implementation plan

Date: 24 October 2026 (Asia/Dhaka). Status: proposed; no reset implementation or deletion performed.

## 1. Intended result and recommendation

Add a dedicated **Full installation reset** control under System administration, independent of any experiment dataset. Its target is one retained super administrator, the installed schema/migration ledger, a reviewed migration-default baseline, and the minimum configuration needed to sign in and start setup again.

Remove all other users, organization setup, stock, transactions, workflows, custom configuration, imported datasets and business uploads. Include soft-deleted records and abandoned experiment artifacts.

This is broader than today's Experimentation installation reset. Do not relabel that action or expand its preservation list and call the result a factory reset.

Two explicit exceptions are necessary under existing repository rules:

1. Security/audit evidence must survive indefinitely, privately. Keep it in protected tables initially, or move it to a verified, queryable archive with unchanged access and retention before clearing active copies. A recovery backup alone is not an audit archive.
2. Issued managed asset tags and numbering authorities must not disappear or restart. The first implementation must block full reset when issued tags exist. Supporting such installations needs a separate lifecycle design that preserves issuance evidence and prevents reuse.

The local database currently has issued tags, so it cannot pass that first implementation's execution checks. This plan does not propose removing the existing tag-wipe protection. A truly empty historical footprint conflicts with current evidence rules; the UI must disclose retained evidence and counters.

## 2. Findings from current code and read-only inspection

The inspected database is `snipeit`, in the local environment. There are 163 tables, all InnoDB, and no views. Counts below include soft-deleted rows and are a dated, non-atomic discovery snapshot, not an executable approval preview.

| Area | Current rows |
| --- | ---: |
| Users | 983 |
| Active native super administrators | 1 (`admin`, ID 1) |
| Assets | 4,853 |
| Consumables / accessories / components | 1,061 / 704 / 352 |
| Licenses / seats | 96 / 480 |
| Companies / locations | 527 / 106 |
| Generic documents / inventory movements | 483 / 4,026 |
| Service requests | 608 |
| Committees / initiatives | 12 / 16 |
| Catalogue nodes / definitions | 158,463 / 143,573 |
| Finance items / geography areas | 490 / 177,685 |
| Experiment runs / ownership records | 6 / 76,973 |
| Managed tag registrations (`fd18-v1` or `native-v1`) | 20 |
| Access events / committee ledger entries | 640 / 126 |

The retained admin currently has direct superuser permission; no group is needed to keep that account privileged. Resolve this again during preview/execution instead of hard-coding ID 1 or username admin. Interpret “only keep super admin” as exactly the initiating active superuser, deleting every other account, including any additional superusers, with that scope clearly shown before confirmation.

Current sessions and cache use files; clearing database tables would not invalidate them. The default queue is synchronous, while experiments have separate queue infrastructure. A long reset cannot rely on the default queue or the browser request.

Existing actions:

- [ExperimentWiper](../../packages/gov-store/experimentation/src/Services/ExperimentWiper.php) supports dataset and database scopes. It preserves settings, permission groups, custom fields, geography, catalogue/Finance masters, shared policies, audit and counters; it requires a dataset run. Unknown populated tables block it. It does not satisfy the requested baseline.
- [ExperimentAccess](../../packages/gov-store/experimentation/src/Services/ExperimentAccess.php) restricts that feature to active native superusers, explicit configuration and nonproduction. Its installation-reset flag is currently enabled locally; this is not authorization to execute it in this investigation.
- [ExperimentBackup](../../packages/gov-store/experimentation/src/Services/ExperimentBackup.php) provides encrypted database backup and readback checks. It does not include all uploaded files or provide a tested complete restore flow.
- [PaveIt](../../app/Console/Commands/PaveIt.php) is a developer truncation command. Its implementation does not explicitly restrict users to one survivor, retains authentication material, drops custom columns and is not suitable as a backend for this button.
- [Purge](../../app/Console/Commands/Purge.php) handles already-deleted data, not factory reset.
- [DatabaseSeeder](../../database/seeders/DatabaseSeeder.php) creates demonstration organizations, users and inventory. [SettingsSeeder](../../database/seeders/SettingsSeeder.php) installs demo branding/settings. Neither is an acceptable clean-baseline seeder.

Always enumerate the explicitly selected schema, e.g. `Schema::getTables($connection->getDatabaseName())`, and validate every returned schema. This server exposes other applications' schemas through unrestricted discovery. Never enumerate “all visible tables” for deletion. Existing ExperimentWiper and ExperimentBackup already use an explicit database argument; retain that boundary.

## 3. Define “default migration data” precisely

Implement a versioned `ResetBaseline` manifest with the application revision, migration-file hashes, supported schema fingerprint, source dataset hashes, stable row keys, normalized values and expected relationships. Do not identify defaults by low IDs, names alone, NULL owner, absence of experiment ownership or present-day table contents.

Create and validate the baseline in a uniquely named isolated MySQL database. Run the current migration set there; inspect the resulting rows and compare them with the installed schema. Do not run root seeders. Migration backfills derived from preexisting business rows are not defaults.

There is a verified schema discrepancy: historical migration code creates `gov_capabilities` and `gov_requirement_definitions`, but those tables do not exist in this installation; current `gov_profile_capabilities` uses `capability_code` without legacy `capability_id`. Therefore migration-derived profile defaults below are candidates, not a proven runnable baseline. Reconcile the supported schema and rehearse clean installation first. Fail closed until that work is complete; do not recreate obsolete tables during reset.

### Defaults to restore from verified sources

| Data | Proposed baseline |
| --- | --- |
| `migrations` and schema | Preserve installed migration ledger and supported schema. No destructive migration commands. |
| `gov_geo_areas` | Restore the canonical bundled geography and BBS dataset referenced by migrations. Remove manual additions/edits; validate parent links, codes and dataset hashes. Existing count is not the baseline definition. |
| `status_labels` | Restore the three migration labels: Pending, Ready to Deploy, Archived; map creator attribution to survivor under current schema. Do not invoke StatuslabelSeeder, which creates additional labels. |
| `maintenance_types` | Restore the eight migration types: Maintenance, Repair, Upgrade, PAT Test, Calibration, Software Support, Hardware Support, Configuration Change. Remove custom additions. |
| `gov_funding_types` | Restore the six migration rows keyed by primary type plus name; remove custom additions. |
| `gov_tenant_scopes` | Restore six migration strategies for categories, models, manufacturers, suppliers, fieldsets and locations. Clear all scope mappings. |
| `custom_fields`, `custom_fieldsets`, joining rows | Restore only migration-created MAC Address field/fieldset/link after validating the current generated column convention. Remove later custom definitions/mappings. |
| `gov_profiles`, `gov_profile_capabilities` | Historical migration candidates: Global Base, Asset Major Type, Notebook Category and their four capability links. Include only after reconciliation with the installed code-based architecture. No inherited user publication or assignment. |
| `gov_capabilities`, `gov_requirement_definitions` | Historical migration candidates: five capabilities and two requirements. Absent locally: unresolved compatibility, not permission to add or ignore them. |
| `gov_committee_catalog_mutexes` | Restore the two migration keys: types:national and bindings:national. |

### Data that is not migration default data

Clear national catalogue imports, definitions, synonyms, enrichments, mappings, collections, import history and deprecated classification data. Catalogue migrations create tables; the large catalogue is imported separately.

Clear Finance items. `catalog:finance-import` is a separate command, not migration seeding.

Clear the ministries directory and all companies/offices. The directory migration creates an empty table; the bilingual directory import is a separate operation.

Clear permission groups, metadata field mappings, company/office metadata, themes, approval rules, published profiles and profile assignments unless a row is explicitly part of the verified migration baseline. The capability-group seeder is optional setup, not a migration default.

Clear committee types, purpose bindings and seat roles in the strict baseline. The seven roles and five illustrative inactive types come from CommitteeSeeder, not migrations. If the product needs these immediately after reset, add a separately declared minimal bootstrap extension; never silently count them as migration defaults.

Leave catalogue, ministry, Finance, committee-template and metadata setup as explicit follow-up actions on a reset-complete screen. Do not auto-import or auto-publish them after a reset.

## 4. Survivor and essential installation configuration

Preserve the selected superuser's ID, username, password hash, identity/contact details, activation, direct superuser permission and required authentication factors. Do not create another admin, set a default password or disable MFA.

If privilege is group-derived, normalize an already-authorized survivor to explicit direct superuser permission within the reviewed reset plan before removing groups. Verify successful authentication on an isolated clone.

Clear company, office, department, manager, creator and other references into removed data; remove all memberships, responsibilities, company links, group links, temporary grants, working-office choices, baskets and preferences. Reset the shared TenantContext in place. Clear business avatar/signature references according to the reviewed file manifest.

Keep a single valid `settings` record using a new explicit bootstrap builder based on SetupController, current column defaults and validators. This is an unavoidable runtime exception to literal “migration rows only”: initial usable settings are created by setup, not migrations.

The builder should preserve necessary authentication/security requirements and only reviewed login-connectivity settings; restore remaining application preferences to safe, documented installation defaults. Clear organization-specific branding, uploaded logos, webhook/integration credentials and tag-issuance configuration. Disable outbound notifications and external sync until reconfigured. Show retained/reset settings by category, never secret values.

Keep deployment configuration, APP_KEY, private signing keys, source code, dependencies and bundled reference files. Never expose these in preview or evidence. An external-auth-only survivor requires a proven reauthentication/recovery path before reset; do not reset essential identity-provider settings blindly.

Revoke all old OAuth access/refresh/auth-code material, password-reset tokens, verification tokens and remember-me tokens, including the survivor's. Delete user-created OAuth clients; recreate only explicitly required machine clients through a supported installer. Invalidate all file/Redis/database sessions and app-specific caches, then require fresh login. Preserve no old session solely to keep the progress screen alive.

## 5. Wipe scope and evidence treatment

The accompanying [table inventory](super-admin-full-reset-table-inventory-2026-10-24.md) proposes treatment for every currently installed table, including empty legacy tables. New tables, columns, views, triggers or relationships must receive reviewed coverage before reset.

Delete operational data across:

- Native inventory, models/categories, suppliers/manufacturers, depreciation, departments, kits, allocations/checkouts, maintenance, imports and reports.
- Companies, offices, hierarchy/profiles, administrators/jurisdictions, office roles/memberships, handovers, onboarding and approval/access requests.
- Documents, lines, metadata, references, attachments, ledger openings/movements, registrations without issued permanent tags, and legacy receipt/issue/adjustment engines.
- Requests, baskets, approvals, notices, returns and associated workflow state.
- Initiatives, tracking codes, targets, allocations, scopes, associations, deliveries, supporting documents and derived caches.
- Committees, seats, tenures, orders, external members, declarations and setup outside the baseline.
- All experiment runs, ownership manifests, queued/failed experiment work and artifacts; the reset operation must not depend on a run it deletes.
- Nonbaseline master imports, custom policies, metadata/custom fields and theme settings.

Preserve security audits, replay markers, committee hash ledgers/reviews and permanent counters at first. Audit/event copies attached to deleted workflows must be inventoried and archived before clearing their active tables; do not drop history as an incidental cascade. Archive context must retain old actor/office IDs and labels so that deleting users does not destroy attribution. Namespace archived references by installation/reset epoch, and preserve hash chains byte-for-byte. Do not recycle entity IDs into old audit references.

Keep `custom_request_sequences`, `gov_store_document_sequences`, `gov_committee_sequences` and `gov_asset_tag_sequences` monotonic. Do not reset AUTO_INCREMENT merely to make IDs start at 1. A data reset does not require renumbering internal IDs.

If the archive adapter cannot preserve retention, restricted lookup, chain verification and reference integrity, keep the evidence in place or block the applicable deletion. A full reset with existing issued tags remains blocked until a supported preservation/retirement design exists; a checkbox cannot bypass it.

## 6. Files and non-database state

Build a server-owned, fingerprinted file inventory from every native/package file relationship plus bounded scans of known application-owned upload prefixes. Include private evidence, legacy public attachments, asset/model/user/company images, EULA/signature files, tracking/committee orders, imports/exports, thumbnails, temporary uploads and orphaned experiment files.

Inspect the resolved configured disks and prefixes, including public/cloud aliases. Do not assume local files or blindly trust database paths. Reject path traversal, symlinks/reparse points escaping allowed roots, unknown disks, shared bucket roots and paths outside the installation prefix. Deduplicate shared paths and preserve anything referenced by retained defaults/evidence.

The configured `local` disk points to the entire storage directory. Never delete its root or all of storage/public uploads. Keep APP_KEY-dependent material, signing keys, backups, audit archives, reset journal, framework structure and required bundled assets.

Before deleting business rows, create a verified encrypted database AND file backup with a manifest, checksums, encryption-key recovery instructions and a complete footer. Restrict access to active superusers; no public backup URLs. Existing JSONL database backup is a component to extend, not complete disaster recovery.

After database commit, delete only reviewed files through a durable cleanup job. Missing files are reported; unknown files must be classified before claiming a complete wipe. If cleanup fails, show “Database reset complete; file cleanup pending”, retain the exact failed list privately, and support idempotent retry without repeating database deletion. Backups and retained audit evidence remain private and explicitly outside the wipe scope.

## 7. UI, authorization and execution flow

Proposed location: System administration → Full installation reset.

Proposed routes: GET overview, POST preview, POST execute, GET protected status, POST retry cleanup. GET/HEAD never mutate. Use Bootstrap 3/AdminLTE conventions, breadcrumbs and English/Bengali translations.

Register explicit `system.reset.view` and `system.reset.execute` abilities with GovAccess as national, enforced superuser-only decisions. Protect every effective route with its declared gov.can ability and reauthorize in services/CLI/worker. Native admin, company admin, ICT, office admin and temporary cover grants must never qualify.

Use the existing national-review safeguards but extend them for this exact reset manifest. Generic aggregate national totals or a fingerprint of unrelated rules are insufficient. Account for RequireGovAbility's transaction wrapper: the HTTP action should persist an authorized reset request; destructive work starts only after commit in a dedicated worker. Do not run the worker inside that request transaction.

Flow:

1. Show the environment/installation label, survivor, precise scope, baseline version, current blockers, evidence exceptions, backup requirement and file effects.
2. Produce a read-only preview with exact per-table before/delete/restore/retain counts, file totals, active operations and schema/default compatibility results. Do not create trial business data.
3. Require recent password reauthentication and existing MFA where configured, a reason, and typed `RESET <installation-label>`. Bind a short-lived review to actor/session, installation, connection/database identity, schema, baseline, survivor and exact operation. Never accept target database, table lists, disk paths or SQL from browser input.
4. Consume the review once using a durable unique marker; reject tampering, changed preview, expiry, replay and concurrent submissions. Rate-limit attempts.
5. Acquire a durable installation-wide reset lock and write a reset operation UUID in a journal that survives business deletion. Reject/reset competing experiment work.
6. Enable a write barrier for native/GovStore web, APIs, sync/import jobs, schedulers, queue workers and mutation commands. Drain in-flight writers and stop relevant workers gracefully; an experiment-only lock is insufficient. Drain/prohibit deployments and migrations too. Allow only protected progress/recovery access. Verify this barrier before backup.
7. Under the barrier, recheck actor, installed code/schema, survivor, blocker set and preview fingerprints. Any drift requires a new review. Reject issued tags.
8. Create and verify complete backup; record its hashes durably before deletion. Failure leaves business data untouched.
9. In a single InnoDB transaction, lock survivor and reset state; remove dependencies in verified child-first order, clear only declared nullable cycle edges, restore the baseline, normalize survivor/settings, and verify all database postconditions before commit. Keep foreign keys enabled.
10. Record commit state durably. Purge old jobs/tokens/session/cache namespaces, perform file cleanup, restart fresh application workers and verify login/setup screens. Ensure no pre-reset delayed job can replay against new data; tag work with a reset epoch or retire its queue before resuming.
11. Complete only after database, baseline, authentication and file checks pass. If cleanup remains, retain an explicit incomplete state. Reenable writes after appropriate validation; on ambiguous commit/process failure, keep recovery control and the barrier rather than guessing.
12. Require fresh admin login, show a completion receipt and explicit setup checklist. Keep backup/archive locations restricted.

Use separate durable states such as requested, quiescing, backing_up, deleting, database_committed, cleaning_files, verifying, complete and failed. On failure before database commit, roll back; after commit, resume cleanup/verification only. Persist failure audits independently of a rolled-back mutation. Return safe reference IDs; technical detail stays in restricted logs.

The initial release should be separately disabled by default and denied in production, consistent with current destructive controls. Production support, if wanted, is a separate explicitly designed deployment mode; never relabel a production environment to enable it.

## 8. Proposed implementation structure

Prefer a small dedicated `packages/gov-store/system-reset` package, configuration `govstore-reset.php`, a service provider and isolated tests. This separates the installation lifecycle from experiment ownership and avoids modifying currently uncommitted experimentation work.

Components:

- ResetAccess: active superuser, environment/configuration and reauthentication checks.
- ResetBaseline: versioned schema/default manifests and repeatable restoration.
- ResetInventory/ResetPlanner: every table/file relationship, complete preview, unknown-surface rejection.
- ResetReview: exact plan binding, expiry and durable replay prevention.
- ResetCoordinator: global lock, write barrier, writer drain, journal and reset epoch.
- ResetBackup/ResetArchive: separate recovery and evidence guarantees.
- ResetExecutor: transactional data deletion/restoration and database assertions.
- ResetFileCleaner/ResetVerifier: resumable file work and final usable-installation checks.
- Thin controller, dedicated queue job, recovery command and protected status UI.

Add small integrations to ability registry, national review, middleware/entry-point write barriers and navigation. Reuse inspected backup/dependency ideas after extracting stable interfaces. Do not directly call ExperimentWiper, PaveIt, db:wipe, migrate:fresh, migrate:refresh or DatabaseSeeder from the button.

Dynamic custom columns require a declared policy. Recommended first release: clear all business values and remove nonbaseline field definitions while retaining empty physical extension columns, explicitly disclosed as schema artifacts. If exact schema cleanup is required, implement a separately journaled, backed-up DDL stage; MySQL DDL is not part of the data transaction. Never claim data rollback can restore dropped columns. The current seven generated columns, including the MAC column, need this classification.

## 9. Verification and acceptance

Perform destructive exercises only on uniquely named isolated databases and private test storage. No current live dataset reset is authorized by this planning request.

Required coverage:

- One active superuser survives, credentials/MFA work, no dangling tenant links, exactly zero other users including soft-deleted users; test group-derived superuser survival separately.
- Reject all other roles, inactive/deleted actors, shadow-mode bypasses, direct forged POSTs, CSRF failures, missing recent authentication, expired/changed/tampered/replayed review and parallel execution. Recheck queued actor revocation.
- Every installed table and file source is classified, including empty tables, composite keys and polymorphic/native legacy links. Unknown schema/data surfaces fail closed.
- Every delete target is empty afterward; all baseline normalized values/relationships match source hashes; separately retained evidence/counters match pre-reset values and chain verification.
- Imported catalogue/Finance/directory and all custom settings disappear under strict baseline. No demo users/inventory are created.
- Existing issued managed tags block execution with no deletion; no permanent counter/tag reuse.
- Backup storage/decryption/checksum/truncation failure prevents deletion. Rehearse database AND file restore into a separate target with restricted key handling.
- Inject deletion/default/settings verification failures: rollback business data and retain failure audit. Test rendered failures as well as thrown exceptions.
- Race reset with a native write, GovStore posting, experiment population, queue job, scheduler and CLI mutation; verify barrier/drain/epoch behavior on isolated MySQL, not SQLite alone.
- Crash before backup, during deletion, after commit and during file cleanup; verify deterministic recovery, no second deletion and no false complete status.
- Clear file sessions/cache, old API/remember/password-reset tokens and old delayed jobs; verify login, native dashboard, Global Overview, empty/setup screens and explicit later setup flows.
- File traversal/shared-path/cloud-prefix tests; keep backups, signing keys and audit evidence. Report missing files and cleanup failures truthfully.
- English/Bengali parity, compiled Blade PHP syntax, live browser flow on an isolated deployment, useful blocked/disabled explanations and protected response sanitization.
- Run focused G1 authorization tests and the relevant complete GovStore suites using installed PHP and the documented increased memory limit. Run meaningful isolated reset integration tests; do not edit core phpunit.xml or weaken existing protection tests.

Release acceptance: a reviewed machine-readable baseline, complete table/file manifest, proven writer barrier, verified restore rehearsal, preserved evidence/numbering guarantees, usable survivor account, tested failure recovery and no unexplained residual business data. Local tests do not establish CI or production readiness.

## 10. Delivery sequence and open decisions

1. **Baseline and compatibility:** reproduce migrations on an isolated MySQL installation; resolve the observed profile/capability discrepancy; freeze minimal settings/authentication defaults and table/file policy.
2. **Safety foundation:** build journal, lock/barrier, exact review, complete backup/restore and protected evidence retention.
3. **Reset engine:** implement classified transactional deletion, baseline restoration, survivor cleanup and resumable file handling.
4. **Super admin UI:** implement preview, reauthentication, confirmation, progress, blockers, recovery and setup checklist.
5. **Verification and release:** isolated destructive/concurrency/failure/restore exercises, G1/package suites, bilingual live UI checks, then deployment documentation with the feature disabled by default.

Decisions the implementation must make explicit:

- Strict migration baseline is the proposed default: catalogue, Finance, ministries, committee seed templates and later metadata setup are removed.
- Exactly the initiating active superuser survives. A different survivor is a separate reviewed feature, not a freely supplied user ID.
- Protected audits/counters remain or are preserved through a supported archive; complete historical erasure is outside current repository policy.
- Twenty issued tags currently prevent first-release execution. Supporting their retirement/archive is additional lifecycle work, not a bypass.
- Minimum settings/authentication bootstrap and dynamic-column cleanup need explicit signed-off field policies.
- Initial rollout is nonproduction only. Production enablement requires its own deployment/recovery design.

## 11. What was done in this investigation

Created this plan and the dated table-by-table inventory. Read current security/development instructions, reset/purge/seeding implementations, relevant migration/provider/authorization/storage code, and performed read-only local schema/count/authentication-source inspection.

No feature code was changed, no migration/seeder/reset was run, no live business rows or files were deleted, no worker was stopped or started, and no browser verification or automated reset test was performed. Existing unrelated uncommitted changes were left in place.

