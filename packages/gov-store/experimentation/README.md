# GovStore experimentation

Super administrator controls for fictional Bangladesh government data. This package adds populate, resume, dataset wipe, and separately enabled installation reset to the existing application. It does not modify the other GovStore packages.

## Setup

Requires the current installed GovStore schema and MySQL/MariaDB. Root namespace/provider registration is included; no third-party dependency is added.

```powershell
php composer.phar dump-autoload --no-scripts
php artisan migrate --path=packages/gov-store/experimentation/src/database/migrations
```

Use your installed Composer executable if the phar is elsewhere. Apply only the feature migration path for this setup; the existing broad reset seeders are not used.

Local environment options:

```dotenv
GOVSTORE_EXPERIMENTS_ENABLED=true
GOVSTORE_EXPERIMENT_DATABASE_RESET=false
GOVSTORE_EXPERIMENT_INSTALLATION=my-local-govstore
GOVSTORE_EXPERIMENT_QUEUE_CONNECTION=govstore-experiments
GOVSTORE_EXPERIMENT_QUEUE=govstore-experiments
```

Production is always denied. After changing cached configuration, refresh it using the application's normal configuration deployment process.

Start the dedicated worker:

```powershell
php -d xdebug.mode=off artisan govstore:experiment-worker
```

For one queued operation or processing an existing backlog:

```powershell
php -d xdebug.mode=off artisan govstore:experiment-worker --once
php -d xdebug.mode=off artisan govstore:experiment-worker --stop-when-empty
```

The command handles only the feature queue, uses its own failure table/log, a process-local array cache, 512 MB memory and a one-hour job timeout. The configured dedicated database queue retries reservations after 3,900 seconds. Run one such worker as a local background process or use your normal process supervisor. Restart it after changing feature code. The local UI verification worker PID is recorded in ignored `storage/logs/govstore-experiment-worker.pid`; its output/error logs are alongside it. Stop that process after checking that the recorded PID still belongs to this PHP worker.

## UI

Open `/gov-store/admin/experiments` as an active native superuser. The navigation entry is in the current administration/context menu. Ordinary native administrators cannot open these controls.

1. Choose Quick, Government or Large, name the dataset and populate it.
2. Wait for Ready and inspect the data checks.
3. Download the fictional account CSV. Log in normally as the employee, storekeeper, approver or oversight account for the office you want to explore.
4. Browse `/gov-requests/catalog`, view `/gov-requests/basket`, and explore the existing package screens.
5. Preview a dataset wipe and type its exact name. The job removes only owned data and dependent activity; unrelated business records remain.
6. Populate again when the previous wipe is complete.

Government creates 48 offices, 480 people and 2,400 assets. Its 44 operational offices each have twelve consumable types, eight accessory types and four component types with substantial stock, plus ICT equipment and furniture. Four incomplete offices demonstrate readiness configuration. Supporting stock documents, requests, baskets, maintenance and tracking records are included.

Every fictional account uses password **`1234567890`**. Login names are the lowercase English full name plus a number, for example `shamimanasrin1658`. The native user ID supplies the suffix; a collision advances the number without changing another account. Bengali display names remain available for human checking. Download the current CSV after updating older datasets; previous downloads contain obsolete logins.

The existing super administrator **Global Overview** currently shows zero office stock: tenant initialization marks the context active/global without an office, while `MinistryLocationScope` requires an office and does not honor the global flag. Licenses lack an office column, so they remain visible; the dashboard counts license seats. This existing package issue is unchanged. Use a downloaded **storekeeper** account to inspect its office's `/hardware`, `/consumables`, `/accessories` and `/components` inventory.

Components are stocked and visible in the catalog, but excluded from prefilled request baskets because the installed requestable factory has no component adapter. Laptop models are included for metadata exploration; laptop receipt, bulk request fulfillment and committee workflows are excluded. See the detailed plan for the remaining current package limitations.

## Recovery

Failed population can resume from saved checkpoints. If a worker stops and leaves an active status, use **Release pending or stopped operation**. A live worker holds the installation lock and blocks release. Recovery removes the matching new feature job and makes the run failed; resume population or review a new wipe preview as appropriate.

If business deletion succeeds but supporting file cleanup fails, the run remains wiped and shows a cleanup error. **Retry supporting file cleanup** retries only the audited owned paths. Dataset documents from rolled-back population units are included in cleanup.

## Installation reset and backup

Enable `GOVSTORE_EXPERIMENT_DATABASE_RESET` deliberately for a disposable installation. Preview installation reset and type the configured installation label. This removes business data outside the selected dataset as well. It preserves the initiating native superuser and protected system/master setup. Unknown populated tables, unrelated protected dependencies, changed previews, backup failure and deletion cycles block the operation.

Keep ordinary application writes quiet during reset. The experiment lock coordinates feature operations only. Foreign keys stay enabled during deletion.

Backups use the configured local disk under `gov-experiments/backups/*.jsonl.enc`. Each line is independently encrypted with Laravel `Crypt` using the original `APP_KEY`. Decrypted records have `kind` equal to `header`, `schema`, `rows` or `complete`. Schema records contain table DDL; row records contain batches of source rows. Before deletion, the stored file is re-read, decrypted and checked against the ciphertext SHA-256 and complete footer. Backups remain after wipe. No automatic restore UI is provided; an administrator can restore into a separate database from the schema/rows while retaining the original key.

## CLI

All actions require `--actor` identifying a currently active native superuser:

```powershell
php artisan govstore:experiment populate --actor=1 --profile=government --seed=2026
php artisan govstore:experiment status --actor=1 --run=RUN_UUID
php artisan govstore:experiment resume --actor=1 --run=RUN_UUID
php artisan govstore:experiment recover --actor=1 --run=RUN_UUID
php artisan govstore:experiment accounts --actor=1 --run=RUN_UUID
php artisan govstore:experiment preview --actor=1 --run=RUN_UUID --scope=dataset
php artisan govstore:experiment wipe --actor=1 --run=RUN_UUID --scope=dataset --confirm="EXACT_DATASET_NAME" --fingerprint=PREVIEW_HASH
php artisan govstore:experiment cleanup --actor=1 --run=RUN_UUID
```

The database scope uses `--scope=database` with the installation label and its own fresh preview fingerprint. Queue dispatch, admission checks and execution are shared with the UI. Credentials are not printed by these commands.

The `accounts` action applies the current fictional username/password format to manifest-owned users in an inactive, unwiped dataset. It preserves the users' IDs and existing company, office, role and workflow relationships, and leaves unrelated accounts unchanged.

## Tests

Prepare a unique separate database from the installed schema. This copies schema and shared geography/metadata definitions only, preserves the application's database, and writes a temporary test marker.

```powershell
php -d xdebug.mode=off packages/gov-store/experimentation/tests/prepare-database.php
php -d xdebug.mode=off -d memory_limit=512M vendor/phpunit/phpunit/phpunit packages/gov-store/experimentation/tests/ExperimentTest.php
```

The test boot guard accepts only the exact database in the marker with the `govstore_experiment_test_YYYYMMDDHHMMSS` pattern. It avoids root refresh migrations and destructive seeders. Full reset and encrypted backup tests run in that separate database. Remove only the explicitly created test database and marker after testing; do not clean other databases by wildcard.

Detailed implementation, package coverage, profiles and validation: `docs/plans/super-admin-experiment-data-plan.md`.
