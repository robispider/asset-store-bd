# GovStore Super Admin Experiment Data Plan

Date: 4 October 2026 (Asia/Dhaka)
Status: implemented in the dedicated `experimentation` package; runtime verification is recorded below.

Recorded counts and limitations below are dated task evidence. For future live UI/database testing of any package or feature, read [Local live testing and database guide](../testing/local-live-testing.md) and verify the current implementation/data.

## 1. Scope and outcome

Provide a super administrator with a usable Bangladesh government experiment dataset, a dataset wipe, and a separately enabled installation reset. Follow the installed packages' services, observers, scopes and schema. All application changes belong to the new package, its configuration, namespace registration and provider registration.

The user explicitly restricted this work to data population and wipe. Existing package defects, permission behavior, schema redesign, and unavailable workflows are outside this implementation. The limitations below describe how fixtures fit the current application.

A normal cycle is Populate → explore using fictional accounts → Preview dataset wipe → type the dataset name → Wipe → Populate again. Installation reset has a separate confirmation using the configured installation label. It removes business data outside the selected dataset as well, preserves the initiating super administrator and protected setup, and requires a verified encrypted backup.

## 2. Source review and package integration

The source has ten installed GovStore runtime packages and a committee design directory. Root `DatabaseSeeder`, `CompanySeeder`, `UserSeeder`, and `PaveIt` can truncate broad sets of data. This feature uses its own services and migration instead of invoking those entry points.

| Package | Existing interface used by fixtures | Seeded coverage and checks |
| --- | --- | --- |
| geo-areas | Bundled `geo_areas.csv`, `GeoArea`, `GeoAreaService` | Reuse actual master IDs and hierarchical paths; add missing master rows without overwriting existing rows; validate office geography and eight divisions |
| organization | `OfficeProvisioningService`, `OfficeConfigurationService`, directory/company administrator/jurisdiction models | Six fictional ministry/division and department company nodes, regional/district/upazila offices, administrators, responsibility configuration, provisioning activity |
| office-membership | `OfficeMembershipService`, `RoleHandshakeService`, current responsibility models | Home memberships, one secondary membership, separate storekeeper/primary/final accounts, pending handovers; verify role holders belong to their office |
| tenant-scope | `TenantContext`, `CapabilityProfileResolver`, current native scopes and category mappings | Bind the actual fixture actor and office for each scoped workflow; check that office asset and bulk queries cannot return another seeded office's stock |
| classification | Bundled compiled nodes, `CategoryAdoptionService`, governance/collection/mapping models | Selected office commodities and ancestors; global and company-owned categories; matching office adoption; six collections; existing mappings remain intact |
| metadata | Existing model/category observers and `ConvergenceEngine` | Model setup triggers current provider convergence; capture metadata state only for owned models; resume interrupted model setup through the existing engine |
| user-onboarding | User observer and `UserOnboardingService` | Complete direct office onboarding for operational employees; leave configured waiting cases; keep dedicated oversight accounts separate from home-office company assignments |
| custom-requests | `BasketService`, `ApprovalService`, `FulfillmentService`, existing approval policy models | Desktop, furniture, consumable and accessory requests; primary-only, primary-and-final and automatic policies; varied approvals/rejections; partial/completed serialized asset fulfillment; mixed draft baskets |
| store-operations | `GoodsReceiptService`, `PostingPipelineManager`, document/profile/movement models | Serialized receipts and registration, bulk receipts and consumable issues, draft/ready/cancelled/posted documents, supporting attachments; reconcile native quantities with ledger balances |
| tracking | Initiative/team/code/target/allocation/scope models and `ScopeValidatorService`; existing posting listener | ADP/GoB initiatives, complete teams, active fiscal-year tasks, geographic and explicit office scopes, receipt-linked associations/facts/timelines; verify permitted allocations and deny an unlisted office |
| committee | No executable provider found | Excluded from data creation; reported as unavailable |

Native Snipe-IT models provide companies, people, suppliers, manufacturers, models, assets, consumables, accessories, components, licenses/seats and maintenance examples. This verifies fixture integration with the current packages; it does not certify every endpoint or repair their behavior.

## 3. Bangladesh government context

### 3.1 Organizations, geography and people

Use three fictional sector scenarios: ICT Division/Department of ICT, Ministry of Education/Secondary Education Department, and Ministry of Home Affairs/Bangladesh Police. Every company and office contains an experiment marker. These names establish a familiar government context and do not assert the current legal organization directory.

Resolve divisions, districts and upazilas from bundled master data rather than hard-coded database IDs. The government profile includes eight division offices, sixteen district offices and twenty-four upazila offices. Four offices deliberately lack a storekeeper and remain incomplete; the other forty-four have the complete operational role set.

People have fictional Bangladesh-style Bengali names, including Md Arif Hossain, Nusrat Jahan, Shamima Nasrin and Mitali Chakma. Names are deterministic for the supplied variation number. Usernames concatenate the lowercase English first/last names and a numeric suffix, e.g. `shamimanasrin1658`. The suffix starts at the native user ID and advances if another account already owns that login; multiple datasets coexist without renaming unrelated users. Emails retain a random dataset prefix and use `example.invalid`; no real NID, telephone, signature or government seal is generated.

All fictional fixture passwords are `1234567890`, as requested for local experimentation. They remain encrypted in the run record and hashed in native user records; JSON hides the password. The protected super administrator CSV supplies readable usernames, office roles and the shared password. The feature's `accounts` command can update existing inactive datasets under the installation lock and a transaction, using only manifest-owned user IDs. Unrelated users, roles, company and geography assignments are preserved. Ordinary employee accounts receive contextual capabilities through current package mechanisms, never platform superuser privileges.

Fictional memos, challans, stock records and attachments identify experiment data. Dates use a fixed run anchor; fiscal-year labels follow July–June. Supporting attachments are plain text in the dataset's dedicated storage directory. Native costs follow the application's existing currency configuration.

### 3.2 Exact profile targets

Targets are saved with each run so later configuration changes do not alter an interrupted run's intended size.

| Entity | Quick | Government (default) | Large |
| --- | ---: | ---: | ---: |
| Organizations | 6 | 6 | 6 |
| Offices | 4 | 48 | 240 |
| Operational offices | 4 | 44 | 236 |
| People | 64 | 480 | 2,400 |
| Categories | 20 | 64 | 96 |
| Asset models | 12 | 64 | 120 |
| Serialized assets | 120 | 2,400 | 11,800 |
| Consumable records | 48 | 528 | 2,832 |
| Accessory records | 32 | 352 | 1,888 |
| Component records | 16 | 176 | 944 |
| Software licenses | 4 | 48 | 240 |
| License seats | 20 | 240 | 1,200 |
| Stock documents and supporting files | 12 | 180 | 900 |
| Submitted requests | 24 | 264 | 944 |
| Draft baskets | 8 | 44 | 236 |
| Initiatives | 2 | 6 | 18 |
| Tracking codes | 4 | 24 | 72 |
| Waiting onboarding examples | 4 | 24 | 120 |
| Pending handovers | 4 | 16 | 80 |
| Maintenance cases | 4 | 36 | 180 |
| Selected commodity nodes, before ancestors | 40 | 200 | 200 |

Each profile also creates twelve suppliers, eight sample manufacturers, one usable asset status, six company administrator assignments and eight ICT jurisdictions. Listener-created records, memberships, metadata and histories are counted separately.

### 3.3 Meaningful office inventory and requests

Each operational office gets all of these inventory types, with a receipt quantity of 100 per type:

- Twelve consumables: A4 paper, toner, ink, pens, pencils, staples, envelopes, file folders, register books, markers, batteries and cleaning supplies.
- Eight accessories: keyboard, mouse, webcam, headset, HDMI cable, USB hub, network cable and surge protector.
- Four components: memory, SSD, hard drive and power supply.

The native component model requires an initial quantity of one. A separate posted opening document and ledger movement represent that opening quantity; the normal receipt adds 100 through the existing posting pipeline. The resulting component quantity is 101. Consumable issue examples reduce selected balances to 98. The verifier compares ledger net movement and current native quantity.

The default profile receives twelve serialized equipment categories in every operational office: desktops, printers, office chairs, desks, steel cabinets, scanners, UPS, routers, projectors, air conditioners, ceiling fans and whiteboards. Around 54–55 serialized assets per office leave several available examples per category. Larger profiles add CCTV, photocopiers, switches, water filters, meeting tables and bookshelves. Laptop models exist for metadata exploration; laptop receipt is excluded for the reason in section 7.

Posted assets receive the existing native `requestable` flag. The employee catalog therefore has hardware and furniture choices alongside stocked supplies, components and organization-visible software licenses. Category adoption and physical stock custody remain distinct.

Requests rotate between desktops, furniture, consumables and accessories. Draft baskets mix hardware, furniture, stationery and accessories. Components remain stocked and catalog-visible; the installed requestable factory has no component adapter, so they are excluded from prefilled baskets. Approval policies and actors are taken from current services. Serialized requests demonstrate partial/complete fulfillment; bulk requests remain available for approval exploration while receipt/issue records demonstrate bulk stock activity.

## 4. User, company and geography boundaries

The feature requires a currently active native superuser. Native `admin` permission alone is insufficient. Explicit middleware enforces this even though the application's global gate can grant superusers broad access. Feature enablement and non-production environment are checked on HTTP, CLI admission and queued execution.

| Context | Fixture boundary |
| --- | --- |
| Employee | Home office/company, own baskets and request delivery office |
| Storekeeper | Actual office responsibility, same-office receipts, issues and asset fulfillment |
| Primary/final approver | Separate actual responsibility holder for the request office and correct approval stage |
| Company administrator | Dedicated assignment to a seeded organization; no unrelated home-office inheritance |
| ICT officer | Dedicated valid master geography jurisdiction |
| Secondary member | Explicit second membership; no automatic copy of another office's stock |
| Initiative team | Current HEAD/OFFICER/SUPPORT/MONITOR designations |
| Tracking allocation | Existing validator must accept both geography and explicit participating office; an unlisted seeded office must be denied |

The verifier checks office/company matching, home memberships, responsibility memberships, valid hierarchical geography, request delivery office, native stock/ledger balances, sufficient requestable ICT/furniture inventory, and scoped queries excluding another seeded office's stock. These checks cover the generated fixtures. They are not an exhaustive security audit of existing package controllers.

## 5. Population lifecycle and recovery

`ExperimentManager` admits one operation per installation. A connection-owned MySQL/MariaDB advisory lock coordinates web, CLI and worker actions. Pending/running operations block another admission. The encrypted dedicated job contains run/actor/installation identities; the worker rechecks enablement, environment, current actor permissions and database identity before any business creation.

Phases are organization, people, classification, products, tracking, stock, requests and examples. Small units commit their fixture data and checkpoint together. Logical keys identify generated records, while the manifest stores each table and actual primary key. Repeating completed population units preserves record counts. Metadata model units run outside a transaction because existing convergence can perform DDL; their inserted identity is captured even if the observer subsequently fails.

Failures show a sanitized diagnostic without raw SQL bindings or passwords. A failed population can resume from its checkpoints. A failed wipe needs a new reviewed preview. A pending or stopped operation can be released only after acquiring the same installation lock; an active worker blocks release. New queued jobs include a run-specific display name so recovery can remove only the matching feature job.

Use the dedicated worker command. It sets a process-local cache and log destination, a 512 MB memory budget, one attempt and a one-hour timeout. It handles only the feature queue and its separate failure table. No global queue or package cache behavior is modified.

## 6. Wipe design

### 6.1 Dataset wipe

1. Load actual manifest records; ignore identities already removed through normal app activity.
2. Discover physical foreign keys and explicitly listed legacy/polymorphic dependencies.
3. Capture legitimate child activity for owned documents, stock, requests, models, categories and users.
4. Block on an unrelated business record referencing the dataset, an unknown dependency, or a fixture profile/tracking relationship targeting an unrelated record.
5. Display friendly counts and the exact dataset-name confirmation.
6. Fingerprint the selected records' content and blockers. Recheck at admission and worker execution.
7. Lock selected rows in the transaction and compare a fresh preview.
8. Delete children before parents using row-level dependency layers with foreign keys enabled. A cycle stops and rolls back the deletion.
9. Preserve master geography, shared catalog standards, settings, infrastructure, unrelated business records and the super administrator.
10. Commit wiped status and audit details, then remove only owned supporting files. Files written before a rolled-back seed transaction are also included from the exact dataset document directory.

The manifest is cleared when business deletion succeeds; audit/run history remains. Supporting file cleanup is idempotent and separately retryable if the filesystem rejects deletion. Files outside the `gov-experiments` ownership tree are preserved.

### 6.2 Installation reset

Reset is separately disabled by default and also unavailable in production. The confirmation is the configured installation label. It enumerates explicit business tables in the current database only. Unknown populated tables block reset until an explicit adapter is supplied. System defaults and unowned shared profile/setup rows are preserved; fixture-owned setup rows are removed.

The initiating active super administrator, its group permissions and authentication setup remain. References to removed organization/location/manager/department rows are cleared from that account. Other business users are reset. All experiment manifests are cleared and all affected runs are marked wiped.

Before deletion, stream the current database schema and rows into encrypted JSONL, then re-read, decrypt, hash-check and require a complete footer. Backup storage failure, invalid serialization or verification failure blocks deletion. The backup includes the current installation's tables only and is created inside the final wipe transaction. Keep the application quiet during a full reset because the feature lock does not prevent unrelated application writes.

Backups are retained after reset, outside the document cleanup directory. They require the original `APP_KEY`. There is no automatic restore screen: restoration is an administrator operation using the saved schema and row records in a separate database. Do not advertise a restore capability that has not been implemented.

## 7. Current package limitations respected

- Super administrator Global Overview currently hides office stock. `InitializeTenantContext` sets active/global without office IDs; `MinistryLocationScope` checks only active and adds `1 = 0` for office-based tables with no office. Licenses have no location column and remain visible; the dashboard counts seats. The existing tenant-scope package is unchanged. Inspect native inventory using a seeded office storekeeper.
- Laptop receipt currently lacks CPU metadata capture in its asset-creation capability. Seed laptop models; receive the other supported ICT and furniture models.
- Bulk receipt and request fulfillment use different stock type formats. Seed bulk receipts/issues and request approvals; use serialized assets for fulfilled request examples.
- The installed bulk native audit listener references a column absent from the native action log schema. Ledger/native balances are verified; those audit entries are not claimed as populated.
- Model-level metadata cannot exercise every field requiring an individual asset context. Use current convergence without changing providers.
- The legacy role registry differs from current office responsibilities. Use current configuration/membership/handshake interfaces.
- Existing middleware, onboarding and permission edge cases are unchanged. Seed within actual owned company/office/geographic bounds and state verification limits accurately.
- Committee has no runtime implementation. It is excluded.

## 8. Installation and operation

See `packages/gov-store/experimentation/README.md` for exact commands. Integrate through the root PSR-4 namespace and the provider entry already used by this repository. No new third-party dependency is required.

Enable the feature only in a local/testing installation. Apply only its migration path; do not invoke the root reset seeders or broad migrations for this feature. Start the dedicated worker before using Populate or Wipe in the super administrator page. The menu entry is under the current administration/context menu, and the direct page is `/gov-store/admin/experiments`.

Download fictional accounts as super administrator. Pick an account with a home office and appropriate role; use ordinary login. Existing accounts in existing offices do not automatically receive another office's experimental inventory. Explore the catalog, mixed basket, request history, approval/fulfillment screens, receipt documents and tracking tasks through those accounts.

## 9. Verification and acceptance

Automated tests run only in a unique schema-only clone named `govstore_experiment_test_YYYYMMDDHHMMSS`. The helper copies shared geography and metadata definitions and refuses to run assertions against the configured application database. It does not run the broad root refresh migrations.

Required checks:

- Native superuser admission; ordinary administrator, disabled mode and production are denied.
- Quick population meets exact counts, hashes passwords, authenticates through the existing guard, exposes furniture/ICT plus 12/8/4 inventory types, and preserves counts when repeated.
- Home/company, roles, geography, requests, ledger/native quantities, ownership and tracking validation pass.
- Office-scoped native queries cannot return another seeded office's stock.
- Dataset wipe preserves unrelated company/user/master records and removes owned attachments including rolled-back unit files.
- External dependents and stale previews block before deletion.
- A queued job rechecks revoked initiating permissions.
- Installation reset in the separate test database verifies its encrypted backup and preserves the protected actor and shared setup.
- Recovery only releases a stopped/pending operation; another process holding the installation lock blocks release.

Live local verification uses `http://snipeit.local/`: create a small disposable scenario through the form, reject an incorrect wipe confirmation, wipe it, create the government scenario, authenticate a fictional employee, inspect/search ICT and furniture, add items to the basket and view the basket. Verify an employee cannot open the experiment controls, then restore the super administrator session. Installation reset is exercised only in the isolated test database; the live application's existing business data is preserved.

The expanded live government dataset contains 48 offices, 480 people, 2,400 assets, 528 consumable records, 352 accessory records, 176 component records, 180 stock documents/supporting files, 264 requests and 44 baskets.

Completed verification: eight tests and 71 assertions pass in the isolated database, including readable logins, the shared password, collision handling, account-update idempotency and preservation of unrelated credentials. Composer validation, feature PHP syntax checks and whitespace checks pass. Live UI population and dataset wipe succeeded, including a government dataset with more than 33,000 owned records and dependent UI activity. All ten data checks pass on the replacement government dataset `01a10391-e96b-70cb-8301-de5a89cc207f`. Both ready datasets' 960 fictional accounts have been updated to the requested login format and shared password; live login succeeds with `shamimanasrin617` and `1234567890`.

On 2026-10-04 the application contains two ready government datasets, including a subsequent separately created dataset which is preserved. Direct database inspection reports 4,833 assets, 1,061 consumables, 704 accessories, 352 components and 96 licenses. Each native item joins the correct category type. Global Overview shows zero office stock and 480 license seats due to the existing scope issue described above. Ordinary login as the replacement dataset's Rajshahi storekeeper shows 55 assets, 12 consumables, eight accessories and four components with positive balances. Screenshots and detailed evidence are in `docs/verification/experiment-verification.md`.
