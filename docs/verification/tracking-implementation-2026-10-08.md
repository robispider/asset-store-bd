# Tracking implementation and verification

8 October 2026 · Asia/Dhaka · local implementation evidence

The original Tracking gaps TR-1/2/3/4/5/7 are mitigated locally; TR-6 remains partial for legacy messages and broader UI review. The package scorecard records these separately from deployment and data reconciliation.

## Implemented

- Projection refresh resolves initiatives through task IDs, covers association creation/move/delete and task/target changes, invalidates inside the transaction, and dispatches after commit. Progress quantities use active associations. Cache rebuilding preserves immutable historical financial facts.
- Receipt posting emits the unified tracking event with the ledger movement ID. Serialized native assets and bulk delivery markers have real associations. Replay, nullable dimensions, task state/scope and transaction rollback are enforced. Legacy asset events delegate to the same listener.
- HTTP evaluation and Store Operations use one session actor/working-office verifier, preserving the package's narrower management roles. Native admin is not a programme administrator. Nested task/document and submitted matrix dimensions are checked on the server; safe failures preserve HTTP status and references.
- Supporting evidence belongs to a task, uses private storage and nested authorized download routes, and is editable only by HEAD/OFFICER on a mutable parent and draft task. Supporting/monitoring members can read their programme evidence. Upload failure removes its file; deletion follows commit.
- Historical creation migrations preserve existing tables on rerun. The new additive migration creates delivery markers and task evidence. It retains historical reference-only evidence rather than guessing ownership.
- The misleading completed workbook-feature claims are corrected. Clipboard grid editing is available; file import/export and templates are proposals.
- Namespace/provider casing is consistent; package licence is AGPL-3.0-or-later as explicitly approved by the user.
- Matrix/task/workspace scripts are bundled. Saved office matrices appear in edit, category fields serialize once, negative quantities disable saving, action menus work and dynamic labels are escaped. Main controls and reports are bilingual. Legacy controller/status/event presentation still needs completion.

## Verified

- `TrackingWorkflowTest`: **18 tests / 172 assertions**, isolated SQLite in memory. Covers inherited stock lifecycle, projection move/delete/commit/rollback, delivery replay and distinct movements, nullable facts, narrower actor/working-office checks (including shadow mode), geography plus participants, evaluation totals, forged nested task, private evidence roles/state/ownership, invalid matrix dimensions, safe rendered-failure rollback, serialized native receipt pipeline and listener failure rollback.
- `G1AuthorizationTest`: **29 tests / 1,246 assertions**, isolated SQLite in memory. No protection or role mode was weakened.
- Isolated MySQL schema `govstore_tracking_test_20261008063944`: duplicate delivery counts once, separate movements aggregate one nullable fact row, delivery markers roll back, and a second PDO connection waits on the task row lock and receives timeout 1205. This checks the competing lock, not a load/stress test. Schema was removed in `finally`; failed first fixture schema was also removed.
- PHP lint passed for **85 package PHP/compiled files**, including **21 compiled Tracking views**. Recursive English/Bangla parity passed for **407 keys**, with every literal view translation resolving. No executable inline view scripts or handlers remain. JavaScript syntax checks and theme bundle build passed.
- Live authenticated Bengali programme workspace and task editor rendered without browser warnings/errors. The saved matrix has three offices, two categories and total nine. An unsaved negative value displayed the translated error and disabled save; restoring one restored total nine and enabled save. Category action menu rendered. No real draft was submitted. [Editor screenshot](tracking-editor-2026-10-08.png).

## Local data and cleanup

Resolved database was checked as `snipeit`. Only `2026_10_08_000004_add_tracking_documents_and_deliveries.php` was applied; no broad migration, reset or seed ran. Ten lifecycle caches were rebuilt without rewriting ledger/facts. Baseline/final business counts are unchanged: 10 initiatives, 33 task codes, 1,327 association rows, 292 fact rows. New evidence and delivery-marker tables each contain zero rows.

Existing active association quantity is **1,347**, while historical fact quantity is **1,349**. Programme `ictd` contains duplicated historical receipt facts/timeline evidence: dashboard association progress is two while the historical financial snapshot remains four. These records were preserved for reviewed reconciliation. New replay markers do not retroactively repair old data. Some existing targets also reference unavailable categories; the UI retains an unknown-category label rather than inventing replacements.

No business receipt, evidence file, test grant, permission or temporary access-mode change was made in the live database. Source/UI/MySQL helpers were removed after verification. The screenshot contains no credential. Existing authenticated sessions were used; the actor changed during shared local work, so no single fixed live-role claim is made.

## Remaining

TR-6 legacy messages/status/event localization, broader role-based browser mutations, keyboard/tablet usability and accessibility assessment remain. Linux/remote CI, deployment migration/worker restart, production data quality and the required real shadow observation period are unverified. Historical fact reconciliation requires a reviewed data plan. These local checks do not establish production readiness or WCAG compliance.
