# Live tests — user guide

For people who run the automated browser tests and read the results. To write new tests, see the [agent guide](live-tests-agent-guide.md). Command reference: [live-json-tests.md](live-json-tests.md).

## What this does

It opens a real browser, logs in as seeded fictional users, clicks through the application and compares what the screen shows with what is stored in the database. Each test is a JSON file ("journey"); a **suite** is a named list of journeys. No AI is used while tests run.

A **pass** means the checks listed in that journey passed on this code revision, this server and this seeded data. It does not mean the whole feature is proven.

## Before you start

1. The application is running (default `http://snipeit.local`) and the database is the local one.
2. Seeded data exists: `npm run test:live -- discover` shows at least one ready dataset.
3. Node dependencies and Chromium are installed (`npm install`, then `npx playwright install chromium` once).

## Run from the dashboard (recommended)

```bash
npm run test:live:dashboard
```

Open the printed link (`http://127.0.0.1:4780/?t=...`). The link contains a one-time access token; the dashboard only listens on your own computer.

1. **Suite** — pick a suite, or "All journeys".
2. **Journeys to run** — tick the ones you want. *Select all*, *Deselect all* and *Invert* help; the button shows how many will run.
3. **Server URL** — where the application is. Local hosts only (`*.local`, `*.test`, `localhost`).
4. Options:
   - **Show the browser while it runs** — opens a visible window; add *slow motion* to follow each click.
   - **Retry failed read-only journeys once** — a retried pass is shown as *flaky*.
   - **Allow journeys that create data** — required for write journeys (see below).
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

## Do not

- Do not run against production, or use real people's accounts.
- Do not edit expected values in a journey to make a red test green; ask whether the application or the expectation is wrong.
- Do not publish `storage/app/private/live-tests/` — screenshots may contain protected data.
