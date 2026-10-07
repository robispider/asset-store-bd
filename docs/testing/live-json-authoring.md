# Authoring live JSON journeys

Add a file under `tests/live/subtests/<feature>/`, list its id in a suite in `tests/live/suites/`, then run `npm run test:live -- validate` and `plan`. Reference: `tests/live/subtests/inventory/seeded-consumable-observation.json`.

## Rules

- Declare **requirements only**: office requirements, actor `capabilities.allOf` / `noneOf`, and records. Do not put dataset IDs, user names, passwords or IDs in JSON. `role` is optional and only for role-specific tests.
- Capability aliases resolve through `tests/live/capabilities.json` (GovStore ability or native permission). Add the mapping there when you introduce a new alias; unmapped aliases fail with `fixtures.unknown-capability`.
- The resolver prefers the least-privileged matching user and never uses a superuser unless the actor sets `"allowSuperuser": true`.
- Steps are registered actions only (`goto`, `click`, `fill`, `assert`, `observe`, `compare`, `capture`, `use`, ...). Routes come from the route map in `actions/browser.mjs`; locators from `tests/live/ui-contracts/`; observations from `tests/live/observations/contracts.json`.
- Variable namespaces: `run`, `actors`, `fixtures`, `data`, `captured`, `observed`, `baseline`.
- Expected values must come from declared inputs or an independent observation, never from the page being tested.
- Do not weaken assertions or change expectations to obtain a green run.

## Adding a capability

New action, adapter or observation = a reviewed handler plus schema entry, an example JSON and a case in `scripts/live-tests/tests/contract.spec.mjs`.
