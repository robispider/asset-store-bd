# Live tests — user guide

For people who run the automated browser tests and read the results. To write new tests, see the [agent guide](live-tests-agent-guide.md). Command reference: [live-json-tests.md](live-json-tests.md).

## What this does

It opens a real browser, logs in as seeded fictional users, clicks through the application and compares what the screen shows with what is stored in the database. Each test is a JSON file ("journey"); a **suite** is a named list of journeys. No AI is used while tests run.

A **pass** means the checks listed in that journey passed on this code revision, this server and this seeded data. It does not mean the whole feature is proven.

The acceptance goal is **complete GovStore workflows across all user functions
and stories**, including Snipe-IT where the workflow depends on it. The existing
`user-stories-seeded-readonly` suite contains page-entry checks; passing all 31
does not establish completion of those stories. See the
[functional acceptance requirements](govstore-functional-live-acceptance.md) and
[24 October coverage review](../verification/live-test-coverage-review-2026-10-24.md).
Selecting **All journeys** runs all existing definitions, not every application
function. Missing journeys and blocked prerequisites remain coverage gaps.

## Before you start

1. The selected local or staging application is running (local default `http://snipeit.local`).
2. For the local bridge, `npm run test:live -- discover` shows a ready dataset. For configured targets, the runner discovers that server's datasets through its UI and can prepare a missing Quick dataset when fixture writes are enabled.
3. Node dependencies and Chromium are installed (`npm install`, then `npx playwright install chromium` once).

## Configure selectable target servers

Copy `scripts/live-tests/targets.example.json` to `storage/app/private/live-tests/targets.json` and replace its placeholders privately. This destination is git-ignored. Alternatively set `LIVE_TEST_TARGETS_FILE` to a private JSON file outside the repository. Add one entry per authorized local or staging installation, each with a unique `id`, display `label`, `baseUrl`, nonproduction `environment`, and `bootstrap.username`/`bootstrap.password` for that server's superadmin. The username is the browser login; numeric IDs use `bootstrap.userId` with database configuration as described below. Keep the real credentials out of the example and committed files. Restart the dashboard after upgrading runner code; target file changes are loaded for subsequent runs.

An optional `database` object contains `driver` (`mysql`), `host`, `port`, `database`, `username`, and `password`; `sslCa` can name a local certificate file. Use a read-only database account. The database can be remote or reached through an existing tunnel. These settings apply only to the runner's PHP subprocess and do not change the application's `.env` or its database connection. The runner sends connection credentials over private process input, not command arguments. With database configuration, `bootstrap.userId` can replace `bootstrap.username`: the runner verifies the supplied numeric ID/password and resolves the login name from that target database before browser login. It also checks the bootstrap identity against My access before preparing any dataset.

Select an entry under **Target server** in the dashboard. Its URL is fixed to that entry. A configured target is explicit authorization for that installation; the ad-hoc local bridge option still uses its host allowlist and origin check. The dashboard exposes only the label, URL, environment and whether a database connection is configured, never the credentials. Target configuration is read again for each run; keep it unchanged while a run is starting.

The runner behaves like a tester starting with a supplied account: it signs in on the selected server, verifies access to protected experiment management, discovers ready datasets, downloads that server's account CSV and permission matrix, then checks candidate users through normal login and **My access**. It picks distinct users with the required abilities and keeps ordinary journeys away from the bootstrap superadmin. Deploy the current My access view on the target for language-independent role, office and numeric identity checks; older views can be read when their labels match the permission matrix language.

With a database connection configured, the runner also matches the downloaded dataset/account/password and office against that database, verifies the signed-in identity, and performs the journey's independent database observations there. A mismatched dataset, user or office blocks the journey. It never falls back to the local database or resets a user's password to make login succeed.

Without database configuration, supported UI journeys still run. Journeys requiring independent database observations or an unsupported record/empty-basket discovery adapter are blocked with a specific reason. A browser-only pass does not prove database state. `accessMode` (`shadow` or `enforce`) is an operator declaration used for mode-specific expectations; without it those expectations are blocked. The environment declaration is also operator-provided, while access to experiment management is verified on the actual target. The runner cannot derive all remote server settings from a login screen.

If the target has no ready dataset, the runner can create a marked **Quick** dataset through the Populate UI when **Allow journeys that create data** is enabled. It waits for the target's existing experiment worker and retains the new dataset, recording it in the report. It does not wipe existing datasets, change roles, start server workers or change access mode. With writes disabled, missing fixture data is reported as blocked. Privileged journeys must explicitly declare `role: "superuser"` and `allowSuperuser: true` to use the supplied bootstrap account; office-bound superadmin context selection remains an unsupported fixture case.

For the same target from the command line:

```bash
npm run test:live -- run --target staging --tests user-stories.us-01,user-stories.us-02
npm run test:live -- run --target staging --suite seeded-readonly
```

Use `--targets-file /path/to/private/targets.json` to select another configuration file. Account exports and passwords are not added to run reports; browser download files are temporary and removed with their browser context. Reports record the target ID, fixture source, database availability, chosen identities and retained effects.

## Run from the dashboard (recommended)

```bash
npm run test:live:dashboard
```

Open the printed link (`http://127.0.0.1:4780/?t=...`). The link contains a one-time access token; the dashboard only listens on your own computer.

1. **Suite** — pick a suite, or "All journeys".
2. **Journeys to run** — tick the ones you want. *Select all*, *Deselect all* and *Invert* help; the button shows how many will run.
3. **Target server** — select a private configured target, or the local app/database bridge. **Server URL** is fixed for a configured target; ad-hoc local URLs must match the local bridge.
4. Options:
   - **Show the browser while it runs** — opens a visible window; add *slow motion* to follow each click.
   - **Retry failed read-only journeys once** — a retried pass is shown as *flaky*.
   - **Allow journeys that create data** — required for write journeys and for preparing a missing Quick dataset (see below).
   - **Allow external mail driver** — required if the server's mail driver is a real one. The tests check the UI only; mail delivery is never tested.
   - **Seed** — repeat the same generated inputs.
5. **Start run**. Watch the journey table, *Live steps* and *Console*. **Stop run** ends it.

Afterwards open **Open full HTML report**. Past runs are listed under *Recent runs*.

## Run from the command line

```bash
npm run test:live -- run --suite seeded-readonly
npm run test:live -- run --tests requests.catalog-progress-observation,requests.authorization-boundaries
npm run test:live -- run --suite journeys --allow-writes --allow-external-mail
npm run test:live -- run --suite smoke --headed --slow-mo 300 --baseUrl http://snipeit.local
```

## Reading results

| Status | Meaning |
| --- | --- |
| passed | All checks passed. *flaky* means it passed only after a retry. |
| failed | A check did not match. The error shows expected and actual, with the failing step and a screenshot (never during login). |
| blocked | The test could not start; nothing was checked. The *code* says why (table below). |
| interrupted | The run was stopped. |

**Blocked is not a pass.** A run with blocked journeys is not fully green; the command exits with code 2.

| Blocked code | What to do |
| --- | --- |
| `policy.writes-not-allowed` | Tick *Allow journeys that create data* (or `--allow-writes`). |
| `preflight.mail-sink-unsafe` | Tick *Allow external mail driver*, or point the server's mail driver at `log`. |
| `preflight.access-mode` | The journey needs shadow or enforce mode and the installation is in the other. |
| `preflight.target-mismatch` | The browser URL differs from the application URL reported by the fixture bridge. Use the bridge's application URL or run the bridge on the target installation. Changing the dashboard URL does not change its database. |
| `fixtures.unsatisfied-requirements` | No seeded users/records match. The report lists how many offices/users were searched and why they were rejected. Re-seed or relax the journey's requirements. |
| `fixtures.unknown-capability` | The journey names a capability that is not mapped (agent task). |
| `schema.invalid` | The journey JSON is invalid (agent task). Run `validate`. |

Exit codes: `0` all passed, `1` a failure, `2` blocked or invalid, `3` interrupted.

## Journeys that create data

Write journeys create real records through the normal screens (a draft basket line, a submitted and approved request). They:

- run only with *Allow journeys that create data*;
- use a unique, marked purpose text per run;
- clean up through the application where it supports it (basket lines are removed and verified);
- **retain** anything that cannot be reversed. Submitted and approved requests are listed under *Retained records* by request number. They are never deleted or repaired by the runner.

Seeded users have undeliverable `example.invalid` email addresses.

## Where files are

`storage/app/private/live-tests/<run-id>/` (not committed, keep private):

| File | Content |
| --- | --- |
| `report.html` | Human-readable report |
| `results.json` | Machine-readable results |
| `events.jsonl` | Step-by-step log, secrets masked |
| `provenance.json` | Revision, server, access mode, seed, chosen users/office |
| `ownership.json`, `cleanup.json` | Records created and what was retained |
| `artifacts/` | Failure screenshots |

Other useful commands: `report --run <id> --html`, `replay --run <id> [--failed-only]`, `coverage`.

## Common problems

- **Everything blocked with `fixtures.…`** — no ready dataset (`discover`), or the dataset was wiped.
- **A page returns 500 once** — transient server errors have occurred (missing application key). Re-run, or use the retry option for read-only journeys; the report marks it flaky.
- **"Host … is not allowed"** — only local servers are accepted. For another host start with `LIVE_TEST_ALLOWED_HOSTS=host`, and make sure that server uses the same database.
- **Seeded accounts changed after a re-seed** — nothing to do; accounts are found automatically on each run.
- **A staging login uses an account that exists only locally** — select that staging installation's configured target, including its bootstrap account and optional database. Changing an ad-hoc browser URL alone does not change account discovery. The local bridge blocks an origin mismatch before login.

## Do not

- Do not run against production, or use real people's accounts.
- Do not edit expected values in a journey to make a red test green; ask whether the application or the expectation is wrong.
- Do not publish `storage/app/private/live-tests/` — screenshots may contain protected data.
