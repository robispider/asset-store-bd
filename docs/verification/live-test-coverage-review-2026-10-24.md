# GovStore live-test coverage review

24 October 2026, Asia/Dhaka. Reviewed checkout `72b8d966eb` with existing local
changes preserved. This is a definition/evidence review, not a new staging run.

## Finding

The current live suite does **not** establish that all GovStore user functions
and user stories can be completed on staging. Its 31 story definitions are
read-only entry/access checks. The required scope is complete GovStore journeys
across pages and roles, with Snipe-IT checked at necessary integration points.
The [functional acceptance contract](../testing/govstore-functional-live-acceptance.md)
records that scope, all 15 current package coverage groups and completion criteria.

## Inspected definitions

| Evidence | Finding |
| --- | --- |
| `tests/live/subtests/` | 69 journey definitions: 58 observations, 11 declared write journeys |
| `tests/live/subtests/user-stories/us-01.json` through `us-31.json` | All 31 tagged `entry-check`, all observation-only, each declares a single actor and has 4–5 top-level steps |
| `tests/live/subtests/requests/submit-approve.json` | Employee submits and primary approver decides; no stock creation, final approval, fulfillment, acknowledgement or return sequence. The retained-effect text explicitly says fulfillment was not performed |
| The 11 write definitions | Eight asset-tagging/rule/setup journeys, two request/basket journeys and one declined membership handover. They are not comprehensive business coverage |
| Tracking story checks and feature catalogue | US-21/22 entry checks exist; no dedicated connected tracking lifecycle/delivery journey is defined |
| `tests/live/feature-catalog.json` | 51 feature rows, but no rows for the 31 `user-story.us-*` feature IDs used by story journeys. A static row saying missing or verified must be reconciled with current definitions and actual run provenance |
| Latest saved local tagging run | `run_1792265079893_DASH`: 8 passed, 1 failed (`asset-tagging.grn-issuance`). An earlier 9/9 pass is not a fresh resolution of that later failure |

The tagging suite includes substantive multi-actor writes and receipt checks;
the absence of a dedicated `storeops.documents` catalogue journey does not mean
there is no receipt coverage anywhere. Those specific assertions still do not
establish complete store-operations acceptance.

New fixture generation can execute package service workflows and populate many
terminal states. That is useful fixture/service evidence, separate from users
performing those transitions through staging screens. Do not count populated
approved/issued/acknowledged requests as a browser journey having completed.

## Historical staging evidence inspected

Private reports were read by selected metadata only; credentials, account exports
and screenshots are not copied into this document. These results describe their
recorded runs and are not assertions about the server's current behavior.

| Saved staging run | Recorded result | What it establishes |
| --- | --- | --- |
| `run_1791873118843_DASH`, 13 October | 42 selected: 31 passed, 11 blocked | The passes are the 31 entry checks. Committee, inventory and request tests, including submit/approve, were blocked with `fixtures.database-observation-required` |
| `run_1791914112288_DASH`, 13 October | 3 navigation cases blocked | Independent observation prerequisites unavailable |
| `run_1791966575681_DASH`, 14 October | 3 navigation cases blocked | Same prerequisite remained unavailable in that run |

The inspected private staging target currently has no database configuration.
The local target does. Staging UI checks can run without it where supported,
but existing journeys needing independent database observations remain blocked.
A configured read-only connection to the staging database, or a separately
implemented authorized observation mechanism for that installation, is needed
to satisfy those persistence checks. Do not point staging at the local database.

## Coverage-report limitations

The current `coverage` command in `scripts/live-tests/cli.mjs` combines saved
results across targets and revisions without filtering provenance. It orders
run directory names lexically, not by result time; the nonstandard
`run_target_mismatch_regression` directory can override later real US-01/02
results. Its `executedAtLeastOnce` value actually counts journeys whose selected
latest result is passed/failed, not every journey ever executed. Matrix results
also collapse to the base journey ID. These limitations prevent interpreting
its counts as staging functional acceptance.

Report missing action definitions separately from failed, blocked, never-run,
historical and current target results. Story IDs, page checks, partial mutations
and complete journeys need distinct coverage accounting before claiming full
acceptance. The review did not change the runner's reporting implementation.

## Work still required

1. Expand the current route/form/action inventory for every GovStore package;
   map every supported function, role and alternative branch to explicit cases,
   including tagging, maps, access and experimentation beyond the 31 stories.
2. Implement connected journeys carrying captured identities through actual
   roles: setup, receipt-to-request-to-acknowledgement, exception/return paths,
   programme delivery, people/access, committee lifecycle and administration.
3. Add missing target-bound fixtures, UI contracts and independent observations
   without weakening scope/state/authorization or rewriting existing records.
4. Make coverage and acceptance reports distinguish target/revision, evidence
   level and required branches; preserve unresolved failures and blocks.
5. Execute on the authorized staging installation, verify persisted effects and
   retained data, diagnose failures, and rerun affected cases on the fixed revision.

## Changes and fresh verification in this review

Recorded the product owner's scope in `AGENTS.md`, the local guide, user/agent
live guides and story plan, linked the new acceptance contract, and retained
historical evidence as history. No application, journey or access-policy behavior
was changed. No live business records were created, modified or wiped.

- `npm run test:live -- validate`: passed, 79 definitions, zero errors
  (69 journeys and 10 suites).
- `npm run test:live:contract`: passed, including synthetic browser execution,
  deliberate failure detection, actor isolation, cleanup and redaction.
- `npm run test:live:targets`: passed target/profile/discovery/identity/database
  binding tests.
- `npm run test:live -- coverage`: inspected; limitations recorded above.

These fresh checks verify runner contracts and definition validity. They do not
establish current staging workflow success, remote CI or complete acceptance.
