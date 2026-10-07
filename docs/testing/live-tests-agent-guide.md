# Live tests — agent guide: creating suites and journeys

For agents (and developers) who add tests. Operators: see the [user guide](live-tests-user-guide.md). Design background: [the plan](../plans/json-live-journey-testing-plan.md).

You normally write **JSON only**. The runner (`scripts/live-tests/`) executes it with a real browser and no language-model calls. Code changes are needed only for a brand-new action, widget adapter or observation (section 9).

## 1. Workflow

1. Read the feature's current code (routes, controller, view, policy). Do not rely on old reports. Decide what is **expected** from the business rule, not from what the page currently shows.
2. Check what exists: `npm run test:live -- list`, `coverage`, `tests/live/feature-catalog.json`.
3. Write the journey in `tests/live/subtests/<feature>/<name>.json`. Reuse flows, UI contracts and observations.
4. Add its id to a suite in `tests/live/suites/<suite>.json` (or create one).
5. `npm run test:live -- validate`, then `plan --test <id>` to read the expanded steps.
6. Run it: `run --test <id>` (add `--headed --slow-mo 300` to watch; `--allow-writes --allow-external-mail` for write journeys).
7. On failure find the cause: application bug, wrong locator, wrong expectation, or fixture. Never weaken an assertion, skip a check or disable security to get green. Report real application defects.
8. Update `tests/live/feature-catalog.json` (journey, status, what is still missing) and run `npm run test:live:contract`.

## 2. Files and where things live

| Path | Purpose |
| --- | --- |
| `tests/live/subtests/<feature>/*.json` | Journeys (found recursively by `id`) |
| `tests/live/suites/*.json` | Suites: `{schemaVersion, id, title, description, journeys:[ids]}` |
| `tests/live/flows/*.json` | Reusable step lists with `params` (e.g. `auth.login`) |
| `tests/live/ui-contracts/<name>.json` | Locator aliases: `target: "<name>.<key>"` |
| `tests/live/observations/contracts.json` | Names of read-only database projections |
| `tests/live/capabilities.json` | Capability alias → GovStore ability or native permission |
| `scripts/live-tests/actions/browser.mjs` | Route map (`goto` `route` names) |
| `scripts/live-tests/bridge/live-test-adapter.php` | PHP side: fixture resolution and observations |
| `tests/live/feature-catalog.json` | Coverage status |

## 3. Journey skeleton

```json
{
  "schemaVersion": 1,
  "id": "requests.my-journey",
  "title": "One sentence: who does what and what is proven",
  "features": ["requests.catalog"],
  "tags": ["seeded", "readonly"],
  "execution": { "capability": "observe", "language": "en-US", "timeoutMs": 60000, "accessMode": "any" },
  "fixtures": {
    "office": { "requirements": { "operational": true } },
    "actors": {
      "employee": { "capabilities": { "allOf": ["requests.submit"], "noneOf": ["requests.approve"] }, "office": "office", "activeMembership": true }
    },
    "records": {
      "item": { "kind": "consumable", "office": "office", "ownedByDataset": true, "minimumAvailable": 10, "selection": "lowest-id" }
    }
  },
  "data": {
    "qty": { "generate": "integer", "min": 1, "max": 3 },
    "purpose": { "generate": "markedText", "prefix": "LIVE-PURPOSE", "length": 6 }
  },
  "steps": [ ],
  "cleanup": { "policy": "no-business-records-created" }
}
```

- `execution.capability`: `observe` (no business data created; may not use `ledger`) or `write-fixtures` (must declare `cleanup`).
- `execution.accessMode`: `shadow`, `enforce` or `any`. The installation is currently **shadow**; journeys that need enforce are blocked.
- A journey runs once per entry of an optional `matrix` array (each entry merges into `data` and becomes a separate case).

## 4. Fixtures: declare requirements, never identities

The runner finds a complete matching office, users and records in a ready seeded dataset, checks passwords privately, and pins the result. Do **not** put usernames, ids, passwords or dataset ids in JSON.

Actor fields:

| Field | Meaning |
| --- | --- |
| `capabilities.allOf` / `noneOf` | Aliases from `capabilities.json` (or a GovStore ability name). Evaluated against the user's roles in the chosen office. |
| `role` | Optional exact responsibility slug, only for role-specific tests. |
| `emptyBasket: true` | User has no draft basket lines (required before submitting a basket — a submit sends *all* lines). |
| `allowSuperuser: true` | Allow a superuser. Default: never; the least-privileged matching user is chosen. |

Actors in one journey are distinct users in the same office. Unmapped alias → `fixtures.unknown-capability`; no match → `fixtures.unsatisfied-requirements` with search counts. Add aliases to `tests/live/capabilities.json` (`{"ability": "requests.submit"}` or `{"native": "consumables.view"}`).

Records: `kind: "consumable"` currently (name, id, quantity are exposed as `${fixtures.item.*}`). Seeded users already own seeded draft basket lines — never assume an empty basket.

## 5. Variables

Namespaces: `run`, `actors` (`.employee.id`, `.username`), `fixtures` (`.office.id`, `.item.name`), `data`, `captured`, `observed`, `baseline`. Written `${observed.before.pending}`. A whole-string reference keeps its type; unknown paths are errors. Passwords are never printed (they are masked automatically).

Generators in `data`: `markedText` (prefix, length), `integer` (min, max), `decimal`, `date` (offsetDays), `email`. Values are deterministic per `--seed`; `markedText` also gets a per-run unique suffix so reruns never collide.

## 6. Steps

Every step is `{"action": ...}` or `{"use": "<flow>", "with": {...}}`. Optional on any step: `"modes": ["enforce"]` (apply only in that access mode; otherwise recorded as skipped).

| Action | Fields |
| --- | --- |
| `goto` | `route` (name from the route map) or `url`; `query: {k: v}` |
| `click` | `target`; `waitForResponse: "basket/add"` registers a non-GET response wait before clicking and fails on status ≥ 400 |
| `fill`, `clear`, `select`, `check`, `uncheck` | `target`, `value` |
| `submitForm` | `target` (the submit control; waits for navigation) |
| `keyboard`, `reload`, `wait` | `wait`: `timeMs`, or `target`+`state`. Prefer `waitObserve`/assertions over sleeping |
| `upload` | `target`, `file` |
| `capture` | `target` + `as`; `value: true` (input value), `attribute`, `count: true`; or `url: true` |
| `assert` | see below |
| `compare` | `actual`, `equals` or `contains` (no page needed) |
| `observe` | `check` (registered name), `args`, `as` |
| `waitObserve` | `check`, `args`, `path`, `equals`, `timeoutMs`, `as` — polls the database projection |
| `actor` | `name` — switch to that actor's separate browser session (created on first use) |
| `probe` | `path` (GET only; under `/gov-requests` or `/gov-store`), `expectStatus: [..]` or `expectStatusByMode: {shadow:[..], enforce:[..]}`, `as` — authenticated HTTP check without following redirects |
| `ledger` | `kind`, `id`, `effect`, `cleanup: "removed"|"retained"` — records a created record (write journeys) |

`assert` tests (`test`): `visible`, `hidden`, `text` (`equals`/`contains`), `number`, `value`, `checked`, `enabled`, `disabled`, `url` (`equals`/`contains`, no target), `valid` / `invalid` (HTML constraint validity), `count` (`equals`). Ambiguous locators **fail** (strict mode) — make the locator specific instead.

`use: "auth.login"` with `{"actor": "employee"}` switches to that actor's session and logs in through the real login page. Do this once per actor. `context.select-and-verify` checks the signed-in header.

## 7. Locators (UI contracts)

`tests/live/ui-contracts/requests.json`:

```json
{ "schemaVersion": 1, "id": "requests",
  "locators": {
    "basketPurpose": { "css": "#purpose", "description": "Purpose field" },
    "myRowByNumber": { "css": "tr:has-text(\"${captured.requestNumber}\")", "description": "Row of the captured request" }
  },
  "labels": { "en-US": {}, "bn-BD": {} } }
```

Use `target: "requests.basketPurpose"`. Locator kinds: `testId`, `role`+`name`, `label`, `css`. `${...}` inside `css` is resolved at run time, which lets you target the exact fixture row (e.g. `:text-is("${fixtures.item.name}")`). Prefer role/label/test id; use CSS when the page offers nothing better. Do not select "the first match". Quoting an identifier that includes `${}` in the *target string* of a step also works.

## 8. Observations, independence and mutation rules

- **Expected values must be independent of the page.** Compare the UI with a database `observe`, a declared input (`${data.qty}`) or a business rule — never with a value captured from the same page you are testing.
- Available observations: `inventory.consumable`, `office.context`, `actor.assignments`, `request.progress`, `request.state`, `basket.line`. Register new ones in `contracts.json` **and** implement them (read-only, parameterized) in `live-test-adapter.php`.
- Seeded observation journeys must not create anything. Write journeys:
  - need `"capability": "write-fixtures"` and a `cleanup` policy;
  - capture the created identity from the UI (e.g. request number via the generated purpose) and `ledger` it;
  - verify persistence on a later page and as another actor;
  - clean up only through supported screens (`cleanup.steps` run even after failure; a cleanup failure fails the journey); anything irreversible stays `retained` and is reported;
  - never retry (the runner never retries write journeys);
  - add a precondition (e.g. `basket.line` quantity 0) so a dirty fixture fails clearly instead of corrupting the test.
- Shadow vs enforce: in shadow mode some screens load for everyone but show no actionable data. State both outcomes with `expectStatusByMode` / `modes`, as in `requests.authorization-boundaries`.
- Waiting: use `waitObserve`, `waitForResponse` and assertions — not fixed sleeps.
- Never touch real users, passwords, permissions or settings. Do not call population, wipe or account-update commands.

## 9. When code changes are needed

| Need | Where |
| --- | --- |
| New route name | route map in `scripts/live-tests/actions/browser.mjs` |
| New observation | `contracts.json` + `case` in `live-test-adapter.php` `observe` (read-only!) |
| New record kind | `resolveContext` records section in the adapter |
| New step action or assertion | `core/validator.mjs` (allowed list/checks), `core/interpreter.mjs` or `actions/*.mjs`, and a case in `scripts/live-tests/tests/contract.spec.mjs` |
| New suite-level policy | `cli.mjs` |

Every code extension needs a contract test against the miniature app (`npm run test:live:contract`) and, for the PHP side, a no-write check in `tests/bridge-readonly.spec.mjs` (`npm run test:live:bridge`).

## 10. Worked examples to copy

- Read-only UI vs database: `requests.catalog-progress-observation`.
- Authorization/negative, shadow vs enforce: `requests.authorization-boundaries`.
- Validation without creating a request, with cleanup: `requests.basket-validation`.
- Multi-actor mutation with captured identity: `requests.submit-approve`.

## 11. Suite template

```json
{
  "schemaVersion": 1,
  "id": "my-suite",
  "title": "My suite",
  "description": "What this suite proves and what it needs (e.g. --allow-writes).",
  "environment": { "target": "http://snipeit.local", "allowlist": ["http://snipeit.local"] },
  "journeys": ["requests.my-journey"],
  "defaults": { "language": "en-US", "timeoutMs": 60000 }
}
```

## 12. Checklist before you finish

- [ ] `validate` passes; `plan` shows the steps you expect.
- [ ] Each assertion has an independent expected source.
- [ ] No secrets, ids, usernames or dataset ids in JSON.
- [ ] Write journeys: ledger, cleanup, precondition, unique generated text.
- [ ] Ran it live at least twice; second run also passes (fresh identities).
- [ ] A deliberately wrong expectation fails with expected/actual (try once locally, then revert).
- [ ] `npm run test:live:contract` passes; catalog updated.
- [ ] Report separately: what passed, what is blocked or uncovered, and what records were retained.
