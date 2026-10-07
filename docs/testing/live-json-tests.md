# Live JSON journey tests — operator guide

Runner for JSON-defined browser journeys against the **local, nonproduction** application. Design: [json-live-journey-testing-plan.md](../plans/json-live-journey-testing-plan.md). Authoring: [live-json-authoring.md](live-json-authoring.md).

## Commands

```bash
npm run test:live -- validate                     # schemas, flows, aliases, observation names, policy
npm run test:live -- list [--feature <id>]
npm run test:live -- plan --suite smoke           # expanded plan; no browser
npm run test:live -- discover                     # ready, unwiped datasets (no credentials printed)
npm run test:live -- run --suite seeded-readonly [--seed N] [--dataset ID] [--baseUrl URL] [--retry-readonly N]
npm run test:live -- run --suite journeys --allow-writes [--allow-external-mail]
npm run test:live -- report --run RUN_ID [--html]
npm run test:live -- replay --run RUN_ID [--failed-only] [--allow-writes]
npm run test:live -- coverage                     # defined / executed / passed / blocked / missing
npm run test:live:contract                        # runner self-tests against a miniature local app (no DB)
npm run test:live:bridge                          # read-only PHP bridge tests (needs local DB)
```

## Suites

| Suite | Kind | Notes |
| --- | --- | --- |
| `seeded-readonly` | observation | catalog progress counts vs persisted data; employee authorization boundaries |
| `smoke` | observation | fast subset |
| `journeys` | mutation | `requests.basket-validation`, `requests.submit-approve`; need `--allow-writes` |
| `native-inventory` | observation | blocked today: no seeded actor holds native `consumables.view` |

## Environment and policy

- Target host must be local (`localhost`, `127.0.0.1`, `*.local`, `*.localhost`, `*.test`); otherwise exit 2. The bridge preflight also refuses a production environment.
- PHP: `LIVE_TEST_PHP`, else `C:/wamp64/bin/php/php8.4.15/php.exe` if present, else `php`.
- Accounts and credentials resolve automatically from ready, unwiped experiment datasets (least-privileged matching user, never a superuser unless `allowSuperuser`). Passwords never appear in JSON, events or results; resolved passwords are registered and masked everywhere.
- `write-fixtures` journeys need `--allow-writes`, and a local mail sink (`log`/`array`/`null`). With a real mail driver the run is blocked (`preflight.mail-sink-unsafe`) unless you pass `--allow-external-mail`, which is recorded in `provenance.json`. Delivery is never tested. Seeded accounts use undeliverable `example.invalid` addresses.
- Journeys can require an access mode (`execution.accessMode`); steps can be limited with `"modes": ["enforce"]`. The installation's mode (currently `shadow`) is recorded in provenance.
- Write journeys never retry. `--retry-readonly N` re-runs failed read-only journeys up to N times; a retried pass is reported as `flaky`, not a clean pass.

## Output

`storage/app/private/live-tests/<run-id>/` (git-ignored): `events.jsonl`, `results.json`, `report.html`, `provenance.json`, `ownership.json`, `cleanup.json`, `artifacts/` (failure screenshots, never taken during login).

## Exit codes

| Code | Meaning |
| --- | --- |
| 0 | every selected case passed |
| 1 | observed failure (including cleanup failure) |
| 2 | invalid input, non-local target, preflight/fixture block |
| 3 | interrupted or inconclusive (highest priority) |

## Retained effects

Submitted and approved requests have no reversal lifecycle. They are listed in `ownership.json` / `cleanup.json` by request number and are never deleted or repaired by the runner. Draft basket lines are removed through the basket UI in journey cleanup and verified.

## Known limits

Not covered yet (see `tests/live/feature-catalog.json`): fulfillment/issue and stock ledger effects, final-approver branch, rejection/partial approval, bn-BD labels, foreign-office/company actors, enforce-mode live runs, lifecycle leases against population/wipe (mutating journeys are serialized and need a quiet fixture environment), and a dashboard. The runner drives Playwright directly rather than through Playwright Test.

## Dashboard, visible browser and server URL

```bash
npm run test:live:dashboard            # prints http://127.0.0.1:4780/?t=<token>
npm run test:live -- run --suite smoke --headed --slow-mo 300 --baseUrl http://snipeit.local
```

The dashboard is local only: it binds to `127.0.0.1`, requires the per-start token on every request, checks the Host header and only launches this repository's CLI with validated arguments. It lets you pick a suite or journey, enter the **server URL**, tick **Show the browser** (opens a visible Chromium window, optional slow motion), and watch per-journey status, live steps, console output, failure screenshots, retained records and the HTML report. One run at a time; **Stop run** terminates it.

Server URL rules: local hosts (`*.local`, `*.test`, `localhost`) are accepted. For any other host start the dashboard/CLI with `LIVE_TEST_ALLOWED_HOSTS=host1,host2`. The PHP bridge always reads the **local** database, so the target server must use that same database; pointing at a server with a different database makes fixture resolution wrong.
