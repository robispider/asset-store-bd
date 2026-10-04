# Custom requests

Office-scoped request catalog, basket, independent approvals, stock reservations and fulfillment for GovStore. See the [implementation and verification record](../../../docs/verification/custom-requests-refactor.md) and [local live testing guide](../../../docs/testing/local-live-testing.md).

## Workflow boundaries

- `RequestWorkflow` defines supported types, policies and separate approval/fulfillment states. New requests always take their office from `TenantContext`; delivery is an optional destination.
- Services authorize and lock authoritative rows inside mutations. Self-approval and the same person's two-stage decision are always denied. Stage abilities use GovAccess and retain its office shadow/enforce rollout semantics.
- Approval reserves stock. MySQL mutations use current locking reads for reservations and serials, including after waiting on another transaction. Bulk issuance changes stock only through store-operations; adapters write history without checkout pivots. Serial issuance uses native checkout and records Goods Issue lines.
- Request events and durable notices commit together. `gov-requests:maintain` runs hourly through the application's scheduler, expires baskets and escalates pending stages after three weekdays. Mail is disabled by default; enable `govstore-requests.mail_enabled` only after configuring the mail transport. Delivery retries can send duplicates.
- Owner returns create an intent; authorized receipt drafting creates a linked DRAFT document. Physical receipt, evidence, metadata and posting remain in the existing document workspace. Current return scope is all issued bulk lines once; native check-in handles serialized assets.
- Historical rows without an office cannot mutate and conservatively block outstanding-request clearance. `gov-requests:reconcile --office=ID` is read-only. Reviewed repair and opening ledger reconciliation are separate tasks.

## Schema and verification

Preserve installed baseline migration identities. Apply the three additive `2026_10_04` migrations to an existing deployment after backup/review; never reset a development database. New user/office/document references restrict deletion to preserve history.

Run the isolated SQLite suite:

```text
php vendor/bin/phpunit tests/Feature/GovStore
```

The opt-in MySQL concurrency checks require a separate schema clone prepared by `packages/gov-store/experimentation/tests/prepare-database.php`. The marker must name `govstore_experiment_test_` followed by 14 digits. The bootstrap checks the effective connection and refuses the development schema. After preparing a clone from an upgraded schema:

```text
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php packages/gov-store/custom-requests/tests/RequestMysqlConcurrencyTest.php
```

Inspect and remove only the exact owned test schema, marker and generated test log after testing. Never run schema resets or broad seeding against the installed development database. Workflow test results do not demonstrate live mail, native acceptance-listener delivery, CI success or completion of the access rollout.

