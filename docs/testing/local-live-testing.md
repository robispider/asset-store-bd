# Local live testing and database guide for agents

Applies to any native Snipe-IT or GovStore package/feature in this repository.
Recorded 4 October 2026, Asia/Dhaka. Recheck the environment, code and data before use.

## 1. Read before working

1. Read [AGENTS.md](../../AGENTS.md), deeper directory instructions, [CLAUDE.md](../../CLAUDE.md), and the affected package's current code/docs.
2. Read [GovStore security rules](../security/govstore-agent-security.md) when working on authorization, context, roles, routes, documents, uploads/downloads, catalog administration, errors or related tests.
3. Inspect the working tree and preserve other agents' and the user's changes. Scope implementation to the requested feature/package. During a seeding task, do not repair unrelated package defects unless separately authorized.
4. Trace effective routes, middleware, controllers, services, jobs and schema. Historical gap reports and verification counts describe their recorded revision; they do not define today's behavior.

## 2. Authorized local environment

| Surface | Location |
| --- | --- |
| Workspace | `D:\git repo\asset-store-bd` |
| Application | `http://snipeit.local/` |
| Database UI | `http://localhost/phpmyadmin5.2.3/index.php?route=/` |
| Last inspected database | MySQL/MariaDB database `snipeit`, host `127.0.0.1`, port `3306` |
| Known working PHP | `C:\wamp64\bin\php\php8.4.15\php.exe` |
| Composer PHAR | `C:\ProgramData\ComposerSetup\bin\composer.phar` |

The user identified this installation as local nonproduction and authorized live UI/database work in this task. Future agents must use their current task's authorization and scope. Access to this host does not authorize another installation.

Read connection credentials privately from the local `.env`/effective database configuration. Verify the resolved database name before every migration or mutation; the table above is a dated reference. Do not dump `.env`, `APP_KEY`, passwords, cookies, tokens, connection strings with credentials, or user tables into logs/evidence.

Use an existing authorized browser session for UI work. The native super administrator login identifier used in this task was `admin`; obtain its credential from the authorized task context or the user if unavailable. Do not record that credential in source or documentation. Do not change real users' passwords, assign permissions, or disable protection merely to gain test access.

For fictional seeded accounts, obtain current usernames/roles from the protected **Download experiment accounts** control or the manifest queries below. Their password is defined by `BangladeshPeople::PASSWORD` in `packages/gov-store/experimentation/src/Services/BangladeshPeople.php`. It is a local fixture default shared by all profiles. Read the constant when needed; do not copy account CSVs into tracked evidence. Logins are lowercase English full names followed by a unique number; display names remain Bengali. A wipe/repopulate changes IDs and logins, so old downloads may be obsolete.

Use PHP 8.4 with Xdebug disabled for these commands. The machine's default PHP previously caused tooling problems; verify its version before substituting it. This guide does not require installing dependencies.

```powershell
$liveTestPhp = 'C:/wamp64/bin/php/php8.4.15/php.exe'
& $liveTestPhp -d xdebug.mode=off -v
& $liveTestPhp -d xdebug.mode=off artisan route:list --path=gov-requests
```

Use the affected feature's route prefix rather than collecting every application route. Effective configuration may be cached; inspect the application's resolved values for the settings relevant to the task instead of assuming `.env` edits are already active.

## 3. Discover current data and actors

Use read-only queries in phpMyAdmin or a local PDO/Laravel helper that reads credentials privately. PDO/raw SQL sees the whole database; an ordinary UI actor sees a scoped subset. Compare these deliberately. Removing model global scopes is not a UI authorization fix.

Read-only dataset discovery:

```sql
SELECT id, label, profile, status, phase, created_at
FROM gov_experiment_runs
ORDER BY created_at DESC;

-- Accounts owned by ready datasets; excludes deleted users and omits credential fields.
SELECT run.id AS dataset_id, run.label, u.id, u.username,
       u.first_name, u.last_name, u.display_name, u.jobtitle,
       u.company_id, u.location_id
FROM gov_experiment_runs AS run
JOIN gov_experiment_records AS own
  ON own.run_id = run.id AND own.table_name = 'users'
JOIN users AS u
  ON u.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(own.record_key, '$.id')) AS UNSIGNED)
WHERE run.status = 'ready' AND u.deleted_at IS NULL
ORDER BY run.created_at DESC, u.id;

-- Inspect actual memberships and assigned responsibilities for a selected user/office.
-- Replace USER_ID and OFFICE_ID with integers from the preceding result.
SELECT user_id, location_id, is_home_office, status
FROM gov_office_memberships WHERE user_id = USER_ID;
SELECT user_id, location_id, role_slug
FROM gov_office_responsibilities WHERE location_id = OFFICE_ID;
SELECT p.location_id, l.company_id, p.geo_area_id, p.office_admin_id
FROM gov_location_profiles p JOIN locations l ON l.id = p.location_id
WHERE p.location_id = OFFICE_ID;
```

Confirm the installed columns before using queries against an older schema. Job title or Bengali display name alone is not proof of permission. Resolve office administrator, responsibilities, active memberships, company/ICT assignments and any temporary grants through the current package code. Confirm the actor's working context and the object's office/company/geography before testing a mutation.

### Dated snapshot: 4 October 2026

The most recent read-only inspection found one ready government dataset: **test 3**, ID `01a105ed-cc65-7290-974f-9ba842abb6d0`. The previously reported government dataset and **test 1** were already wiped by the time of this handover. Do not reuse the historical `shamimanasrin617` login without checking whether it exists.

Example accounts currently belonging to the same office, ID **160**, company **543**:

| Intended role | Login |
| --- | --- |
| Office administrator | `mdmahmudulalam1528` |
| Storekeeper | `mdashrafulislam1576` |
| Primary approver | `ripontripura1624` |
| Final approver | `mdmahmudulalam1672` |
| Employee | `mdashrafulislam1720` |

These account/role rows were checked in the database for this note; they are not a claim that every feature was live-tested under these new accounts. Rediscover accounts after every population/wipe cycle.

Database-wide counts at that inspection: 503 users, 2,433 assets, 533 consumables, 352 accessories, 176 components, 48 licenses; the dedicated experiment job table was empty. These are baseline evidence, not fixed acceptance thresholds for other features.

## 4. Reusable live UI procedure

1. Use the available browser tools and their applicable skill/tool instructions. Inspect the visible page before acting; verify the resulting UI after each meaningful action. Do not use an authenticated request shortcut to claim a UI interaction occurred.
2. Record a small baseline: actor/role, working office and company, relevant geography, object IDs/state and relevant row/balance counts. Keep screenshots and outputs free of secrets and real sensitive personal data.
3. Sign in normally with an appropriate fixture account. Choose/check the working context. For office inventory, a real seeded storekeeper is a useful actor; Global Overview is not sufficient evidence of office stock.
4. Exercise the requested flow from list/search to detail, form validation, save/submit, any approval/posting/fulfillment step, and the visible outcome. Use existing package services and ordinary UI actions. Capture the exact object/document/request IDs for database checks.
5. Check only relevant negative cases: no-role actor, another office/company, out-of-jurisdiction geography, wrong URL type, inappropriate state, or an expired/revoked grant. Verify the current expected status/reason and that no unintended database write occurred. A hidden menu or disabled button is not proof of server authorization.
6. Verify expected data changes in the database, then reload the UI and confirm its displayed state matches. For a failed mutation, check rollback and the appropriate failure audit separately. Avoid repeated checks once the needed evidence is clear.
7. For UI changes, check both Bengali and English strings, relevant browser errors, real rendering, keyboard/control behavior and disabled-control explanations. Apply accessibility checks when in scope; do not claim WCAG certification from a visual check.
8. Save a contextual screenshot of the result under `docs/verification/` when it contains no secrets/private evidence. Protected uploaded evidence belongs in the package's private storage and authorized download route. Embed UI proof in the final task response when required by the browser tool instructions.
9. Restore the original authorized session/context and any temporary configuration/access mode. Retain audit history; document retained fixtures and limitations.

Useful existing UI surfaces (check current routes and role access):

| Purpose | Route |
| --- | --- |
| Native dashboard | `/` |
| Assets / consumables / accessories / components | `/hardware`, `/consumables`, `/accessories`, `/components` |
| Licenses and people | `/licenses`, `/users` |
| Employee catalog / basket / request history | `/gov-requests/catalog`, `/gov-requests/basket`, `/gov-requests/my-requests` |
| Experiment management / account download | `/gov-store/admin/experiments`, then the dataset page |

For any other package, discover its routes from the current source/effective route collection. Do not guess a mutation URL or assume an old route still has the same handler.

## 5. Database checks tied to the feature

Use selected columns and object IDs rather than broad row dumps. Typical checks:

- **Organization/membership/onboarding:** native user/company/location relationships, active home/working membership, assigned responsibility and geographic profile.
- **Classification/metadata:** native category type, model/category relationship, catalog adoption, rule/profile mappings and current metadata convergence state.
- **Stock/documents:** draft/posted state, creator/poster attribution, line quantities, required metadata, attachments and inventory materialization; match the ledger to native balances.
- **Requests/approval/fulfillment:** basket/request item types, requester/delivery office, policy/approver decisions, requested/approved/issued quantities, notices and history using the installed workflow.
- **Tracking/initiatives:** both geography and explicit participating-office allocation; include a denied unrelated office where relevant.
- **Authorization/audit:** current role/grant/mode, meaningful HTTP outcomes, successful committed changes and failed/denied audits.

Do not assume all inventory is licenses because a dashboard shows only licenses. Verify native category types and office scope:

```sql
SELECT c.category_type, COUNT(*) AS records
FROM assets a JOIN models m ON m.id = a.model_id
JOIN categories c ON c.id = m.category_id GROUP BY c.category_type;

SELECT c.category_type, COUNT(*) AS records
FROM consumables i JOIN categories c ON c.id = i.category_id GROUP BY c.category_type;
-- Repeat the second query with accessories, components or licenses as appropriate.

SELECT COUNT(*) AS assets FROM assets WHERE location_id = OFFICE_ID AND deleted_at IS NULL;
SELECT id, name, qty FROM consumables WHERE location_id = OFFICE_ID AND deleted_at IS NULL;
SELECT id, name, qty FROM accessories WHERE location_id = OFFICE_ID AND deleted_at IS NULL;
SELECT id, name, qty FROM components WHERE location_id = OFFICE_ID AND deleted_at IS NULL;
SELECT COUNT(*) AS license_records FROM licenses WHERE deleted_at IS NULL;
SELECT COUNT(*) AS license_seats FROM license_seats WHERE deleted_at IS NULL;
```

The native dashboard license total has counted **seats**, whereas the license list contains license records. Components in the seeded scenario have quantity 101 from opening stock one plus a posted receipt of 100. Certain consumables have 98 after an issue. These are fixture explanations, not quantities every feature must force.

Historical Global Overview checks showed zero office stock because an active global context lacked a location and `MinistryLocationScope` required one. Current context initialization now also handles a superuser's selected office. Inspect the current initializer/scope and reproduce the chosen context before diagnosing this; older notes about office switching or package workflow gaps may be superseded by later changes.

## 6. Automated tests and background work

Never use `migrate:fresh`, `db:wipe`, `migrate:refresh`, broad root seeding or installation reset against the existing local database merely to prepare tests. Use the affected test's isolated SQLite setup or a uniquely named separate MySQL database. Check `phpunit.xml`, environment overrides and base test traits before running an unfamiliar suite. Missing test configuration is not permission to fall back to `snipeit`.

For protection changes, the required focused suite is:

```powershell
& $liveTestPhp -d xdebug.mode=off vendor/bin/phpunit tests/Feature/GovStore/G1AuthorizationTest.php
```

Run other focused tests relevant to the changed invariant, using their own documented database setup. Check concurrency/locking changes against isolated MySQL when SQLite cannot establish the property.

The experimentation package has its own schema-only clone helper and test guard:

```powershell
& $liveTestPhp -d xdebug.mode=off packages/gov-store/experimentation/tests/prepare-database.php
& $liveTestPhp -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit packages/gov-store/experimentation/tests/ExperimentTest.php
```

This helper creates `govstore_experiment_test_YYYYMMDDHHMMSS` and a temporary marker. Record the exact created database. After testing, remove only that verified test database and marker; do not drop databases by wildcard or delete another agent's active test clone. Prior experimentation evidence was **8 tests / 71 assertions**, recorded before subsequent package changes; rerun the relevant tests for a new change instead of reporting that result as current.

Discover each package's jobs/queue driver before assuming a queued UI action has completed. The experiment controls use a dedicated worker:

```powershell
& $liveTestPhp -d xdebug.mode=off artisan govstore:experiment-worker
```

Its PID file and output/error logs are under ignored `storage/logs/govstore-experiment-worker.*`. A saved PID is not proof a worker is still running. Verify process identity before stopping/restarting it. Start background helpers hidden on Windows. Do not start duplicate workers or restart all application workers for one package's change. The feature README documents memory, queue, locking and recovery details.

## 7. Cleanup and report

- Prefer read-only inspection first. Use minimal identifiable disposable fixtures for live writes. Existing ready datasets belong to the user's experiment session; do not wipe them as incidental cleanup.
- Remove owned helpers and isolated test databases after their tests finish. Restore temporary access mode/configuration and expire temporary grants. Preserve audits and unrelated business data.
- Use each package's supported lifecycle to clean business fixtures. Do not delete arbitrary rows, disable foreign keys, manually rewrite ledger balances, or invent missing evidence to make verification pass.
- Report implementation, exact tests/roles/flows, baseline/final database effects, retained test data and limitations separately. Distinguish historical screenshots, fresh database checks and fresh UI checks. Local evidence does not establish remote CI success, a full application regression result, production readiness or a completed observation period.

## References

- [General upstream testing instructions](../../TESTING.md)
- [GovStore security practices and dated G1 evidence](../security/govstore-agent-security.md)
- [Experiment package operations](../../packages/gov-store/experimentation/README.md)
- [Experiment design and package coverage](../plans/super-admin-experiment-data-plan.md)
- [Historical experiment UI/database verification](../verification/experiment-verification.md)
