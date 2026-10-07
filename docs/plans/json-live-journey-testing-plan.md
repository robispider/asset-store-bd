# JSON-driven live feature and user journey testing

Date: 6 October 2026 (Asia/Dhaka)  
Status: implementation plan; the runner and JSON capabilities below are proposed.  
Initial target: the authorized local application at `http://snipeit.local/`.

## 1. Outcome and scope

Build one reusable runner that reads a collection of JSON files under `tests/live/subtests/`, operates a real browser against the running application, and records trustworthy results. Agents normally add or edit JSON journeys rather than writing a browser program for every feature. Execution, waiting, input generation, observations and reporting require no language-model calls.

The runner must support both existing seeded data and records created through a journey. A complete journey can discover an employee and approvers in the same office, log in normally, submit a request, capture its identity, switch actors, approve it, and verify the resulting UI and persisted state.

The normal JSON authoring contract is **requirements only**: declare what kind of office is needed and which capabilities each user needs. Dataset IDs, office IDs, usernames, passwords and exact role names are optional refinements, not prerequisites. The runner finds a complete matching seeded context automatically and starts the journey without asking the agent or user to select accounts. The existing seeder's shared default password is available to the private credential provider through the seeder implementation, so ordinary seeded tests require no credential entry.

Two useful run types are first-class:

- **Seeded observation:** read existing fictional records through the UI and compare them with fresh, narrowly scoped observations. No business mutation or fixture repair.
- **Journey execution:** create or change identifiable test records through ordinary application screens, then verify their results across pages and actors. Record every retained or consumed record and clean up only through supported workflows.

Each passing result means its explicit assertions passed on the recorded application revision, environment and fixture state. It does not certify every feature, production readiness, WCAG compliance, remote CI, or the real-world shadow observation period.

The first delivery is a local command-line runner with private reports. A web dashboard or Start Tests button is a later, separately secured convenience layer. Creating a public testing endpoint is outside the initial scope.

## 2. Findings from the current repository

| Current implementation | Consequence for the design |
| --- | --- |
| Root `package.json` declares `playwright`; no reusable JSON journey runner was found in the inspected test/tool paths | Reuse the browser foundation, add Playwright Test and the declarative layer after checking installed/locked versions |
| `ExperimentRun` stores an encrypted password and hides it from model serialization; the seeder provides a shared default | Automatically resolve and verify a credential from the run/default provider; JSON does not need a password or provider reference |
| `gov_experiment_records` stores run ownership, table, structured record key and optional logical key | Discover current seeded records by manifest identity, then join actual live records; never infer ownership from a name prefix |
| `ExperimentAccess::authorize()` requires an active native superuser, enabled experiments and a nonproduction application | Preserve privileged provider authorization in the local environment bootstrap; routine test JSON needs no administrator identity or administrator-login step |
| The protected account download is available; `govstore:experiment accounts` updates usernames/passwords | Account discovery must not call the update command as a read operation |
| `ExperimentVerifier::verify()` can expand/write the ownership manifest | Build an independent read-only observation service; do not invoke this verifier for passive checks |
| Current responsibility and membership tables, office profiles, company assignments, jurisdictions and temporary grants define role/context | Discover actual current assignments and distinguish permanent roles from temporary cover; do not trust job titles or old CSVs |
| `GovAccess` distinguishes ability decisions and shadow/enforce behavior | Record effective mode and declare the expected result explicitly for each mode; object/type/state boundaries still apply |
| Existing fixture generation suppresses outbound notifications only while its population registry is active | Ordinary browser journeys can trigger real notifications; preflight the configured mail/notification sinks before such journeys |
| Experiment population/wipe uses an installation advisory lock | Coordinate live-run fixture leases with that lifecycle; a separate runner lock alone cannot prevent a dataset wipe |

The local guide's old accounts, IDs, totals and package limitations are dated examples. Rediscover them on every run. No live database, account, password or UI behavior was verified while preparing this plan.

## 3. Architecture and code ownership

```text
JSON suite + journeys + flows + UI contracts
                  |
         schema and semantic validation
                  |
         preflight and fixture discovery
                  |
        Playwright Test orchestration
          /                     \
 real browser actions       read-only observation adapter
          \                     /
       explicit expected-versus-actual assertions
                  |
       private events, results and evidence
                  |
       supported cleanup and retained-data report
```

Use Playwright Test for browser isolation, lifecycle, assertions and reporter integration. Add a small JSON interpreter, schema validation, generators and domain adapters. Playwright does not natively execute the proposed JSON format; that layer is our implementation.

Proposed layout:

```text
scripts/live-tests/
  cli.mjs
  playwright.config.mjs
  runner.spec.mjs
  core/                     # loader, validator, interpreter, variables, policy
  actions/                  # browser operations and assertions
  adapters/                 # Select2, Bootstrap tables, dates, uploads
  reporters/                # redacted events, summary and HTML attachments
  bridge/                   # local PHP process client; no direct Node DB access
  tests/                    # runner contract tests against a miniature local app
tests/live/
  schemas/                  # versioned suite, journey, flow and observation schemas
  suites/                   # smoke, seeded-readonly, journeys, authorization
  subtests/<feature>/        # independently executable JSON journeys
  flows/                    # parameterized reusable login/context/business steps
  ui-contracts/             # semantic locator aliases and language-specific labels
  observations/             # named observation contracts; no arbitrary SQL
  fixtures/                 # synthetic upload inputs; no credentials or real data
  feature-catalog.json       # features, required cases and coverage status
docs/testing/
  live-json-tests.md         # operator guide
  live-tests-agent-guide.md   # agent authoring contract and examples (live-tests-user-guide.md for operators)
storage/app/private/live-tests/<run-id>/
  events.jsonl
  results.json
  report.html
  provenance.json
  ownership.json
  cleanup.json
  artifacts/                # private screenshots, traces and downloads
```

Prefer a small local read-only PHP adapter in the experimentation package for seeded discovery and domain projections. Register any command through the existing provider and preserve its authorization. The Node runner calls it through a private parent/child protocol; browser actions remain browser actions. Avoid a new HTTP bridge in the first release.

The bridge is not an impersonation or mutation API. It must not post documents, approve requests, replace the HTTP `TenantContext`, disable scopes on ordinary routes, or manufacture permissions. Resolve a fresh diagnostic context per request without copying the fixture population context-binding technique into HTTP authorization.

## 4. Preflight and environment policy

Before opening an authenticated page or starting a mutation:

1. Validate every selected JSON file and all dependencies, aliases and observation names. Expand flows into a reviewable execution plan.
2. Check the configured target against a local environment allowlist. Match the adapter's effective application URL, environment and database identity to that target. Do not treat a command-line base URL as authorization for a different installation.
3. Verify runtime/library/browser availability and record versions. Check the repository's Node/build compatibility before adding dependencies; keep Playwright Test and its browser runtime versions compatible and locked. Do not upgrade unrelated frontend libraries.
4. Use the local environment's already configured provider authorization and authorized scope. Resolve any required privileged provider identity automatically from that configuration/current authorized local execution context; no per-test administrator prompt or extra browser admin login. A numeric operator ID is a lookup input, not proof of human authorization. Existing server-side privilege checks remain in force.
5. Inspect effective experiment configuration, selected run status, current dataset ownership and relevant schema. Admit ready, unwiped datasets by default. Partial/failed datasets require explicit suites for those states.
6. Record effective access mode and required abilities. An enforce-only journey is blocked when the installation is in shadow mode; do not silently flip configuration.
7. Resolve actors, offices and feature fixtures as a consistent set. Search alternative eligible users/offices/datasets automatically when a candidate is unsuitable. Verify preconditions and available balances/states. Do not stop for account, office, dataset or password selection; only report unsatisfied requirements after the bounded eligible search is exhausted.
8. Check relevant workers and bounded queue readiness. Record mail/notification sinks, external integrations and allowed artifact policy. If a test requires a safe sink that is unavailable, block that test.
9. Acquire runner resource leases, and dataset lifecycle protection when required. Recheck critical assignments, status and fixture fingerprints before mutations.
10. Establish bounded baselines and private artifact storage permissions. Reject sensitive evidence capture when its required protection cannot be established.

Environment profiles select `observe` or `write-fixtures` capability. Initial supported execution is local/nonproduction. Later remote staging support requires an explicitly authorized remote discovery/observation mechanism and target profile; a local DB adapter must never be paired with a remote website.

Population, account rewriting, migrations, dataset wipe, installation reset, temporary grants and configuration changes are not implicit setup. They require separately declared operations within the task's authorization and existing review controls.

## 5. Common seeded dataset and account capabilities

### 5.1 Read-only dataset discovery

Provide named operations equivalent to:

- `seed.datasets`: list minimal run metadata and status, excluding credentials.
- `seed.describe`: inspect current owned record availability, actual schema/capabilities and relevant feature readiness.
- `seed.selectOffice`: choose a manifest-owned office satisfying declared company, geography, operational role and inventory requirements.
- `seed.selectActors`: resolve related users satisfying effective capability requirements for that office/company/jurisdiction; role constraints are optional.
- `seed.selectRecords`: resolve named fixture kinds with explicit ownership, type, office, company, state and quantity predicates.

Default to automatic discovery across ready, unwiped datasets. Prefer the latest suitable dataset, then stable office/user/record ordering, and record the selection policy. Explicit dataset selection and custom tie-breaks are optional overrides. Multiple suitable candidates are normal and never require a manual choice. Select the first complete matching context, then pin its dataset ID and actor/fixture identities before the journey. Never silently switch actors or datasets after a business mutation begins.

Resolve optional logical keys only when they exist. For other records, use registered structured predicates and stable ordering, record the exact selected identities, and reject inconsistent candidate relationships while continuing to search. Handle composite manifest keys through their actual identity columns.

### 5.2 Actors and relationships

Actor selectors normally declare `capabilities.allOf`, optional `capabilities.noneOf`, and an office/company relationship. Translate semantic capability aliases to the existing named GovStore abilities or narrower native package policies. Do not invent gates or use menu visibility as a capability decision. Exact role requirements are optional when the journey specifically tests a role. Examples of actor requirements include:

- Employee with an active owned membership in the selected office.
- Storekeeper, primary approver or final approver with the actual responsibility in that office and active membership.
- Office administrator from the current office profile plus valid membership.
- Company administrator with the actual current company assignment.
- ICT officer with the required valid geographic jurisdiction.
- Another-office or another-company actor related to a deliberately foreign fixture for boundary tests.

Support distinct-actor requirements, secondary memberships and actors without a particular role. Do not consider every role-free user an ordinary employee if they have native elevated permissions. Record native permission/group elevation, company assignments, responsibilities and unexpired grants in the minimal private actor description.

Evaluate requested capabilities with the candidate user's actual current roles, memberships, grants and native policies in the candidate working context. Prefer ordinary seeded users with the smallest sufficient scope; a superuser must not substitute for an office actor unless explicitly requested. Shadow permission is not proof of a permanent role. Negative cases can declare denied capabilities, foreign-context relationships and absent elevation without naming specific users.

Solve office, actors and records together. For example, an office requiring catalog stock and separate submitter/approver users is eligible only if the whole set exists. If one office lacks a final approver, try another office; if one user is inactive or has a changed credential, try another eligible owned user. Keep selection deterministic, bounded and explainable. Record rejected-candidate reasons without secrets. Missing exact-role fixture candidates must not block a test that asked only for an available capability.

Some actors are not members of an operational office. Their selection must follow their real assignment model instead of inventing a home membership. All actors used by the seeded provider must be manifest-owned unless a separately configured privileged setup/operator identity is explicitly allowed.

### 5.3 Password and session handling

Use an implicit common `seeded-auto` credential provider. It privately reads the selected run's encrypted password and the existing seeder's shared default through Laravel, checks the candidate user's hash against the approved fixture credential sources, and hands the matching secret directly to the browser login handler. Prefer the selected run's verified password and use the verified seeder default when appropriate. JSON authors supply neither a password nor a credential-provider field in ordinary seeded journeys.

- Optional credential overrides use secret references; never put literal passwords, account CSVs, cookies or tokens in JSON.
- Read the fixture default from its existing implementation, not from copied documentation, and verify it against the candidate's hash privately.
- A mismatch rejects that candidate, not the whole test. Continue the bounded search over eligible manifest-owned users and contexts. No password rewrite, credential guessing or attempts against unrelated real accounts.
- Do hash screening before browser login so alternate selection does not repeatedly trigger failed sign-ins or account lockout. An unexpected browser login failure is recorded and may try a fresh equivalent owned candidate only before business actions begin, within the configured login-attempt budget.
- Keep credential material in the controlled runner/child processes and out of command-line arguments, console output, JSON results and ordinary event streams. The private protocol must separate public metadata from secret messages and never echo raw messages into errors.
- Disable screenshots/video/tracing during credential entry and other sensitive authentication stages. Resume permitted evidence only after login; browser traces remain private because they can include session headers and protected data.
- Use a separate browser context for each actor and test. Log in through the normal UI. Prove login and selected working context through a stable post-login UI marker and a scoped observation where available.
- Clear sessions and secret buffers/references on completion. Persistent authenticated state is opt-in, private, short-lived and not the default.

Standalone `discover` output never returns passwords. The existing protected CSV remains an authorized manual option, but automation must not retain it in repository fixtures or reports.

### 5.4 Automatic resolution contract

1. Normalize office, user capability, relationship and record requirements from JSON; apply suite/environment defaults.
2. Search current ready seed datasets, with deterministic ordering and bounded work.
3. Resolve a candidate office/company/geography and all requested records.
4. Match all actor aliases to actual effective capabilities and relationship constraints, including distinct-user requirements.
5. Privately screen fixture credentials, moving to other candidates when a match fails.
6. Pin the complete context, record sanitized resolution evidence, and start ordinary browser login and context selection.

The default run requires no dataset flag, user/office IDs, account CSV download or interactive selection. If no complete match exists after exhaustive bounded eligible search, record a setup failure with code `fixtures.unsatisfied-requirements`, the exact unmet constraints and search counts; continue independent tests. Do not pause waiting for user input or pretend the requested capability was exercised. Runner extensions or separate fixture provisioning can subsequently satisfy a genuinely missing requirement.

### 5.5 Ownership and lifecycle protection

Reading an existing dataset does not give permission to reset it. Treat seeded records as shared fixtures: observe them by default; explicitly lease any record consumed or mutated by a journey.

Create a separate runner ownership ledger for new documents, requests, uploads and other results. A reference to a seeded object is not proof that its new child belongs to the seed manifest. Record verified identities, creators, expected scope, supported cleanup action and dependencies. Never automatically expand the seed ownership manifest to facilitate cleanup.

Serialize conflicting mutating journeys by dataset/office/object. Pure observations may run in parallel only when their selected scope is stable. A dedicated lifecycle lease must be honored by experimentation populate, resume, accounts, wipe and reset admission/execution; inspect current lock ordering before integrating it. Use scoped, durable leases with expiry/heartbeat and live-owner checks so a crash does not leave indefinite protection. Existing installation locks are short-lived and do not exclude ordinary application writes.

Until lifecycle coordination exists, seeded mutation suites remain serialized and require a quiet fixture environment with fresh status checks; the runner must report this weaker protection. Unexpected concurrent changes are interference/inconclusive evidence, not an application pass.

## 6. JSON contract and flexibility

The language is a versioned, validated set of known operations. Agent-authored JSON cannot run arbitrary JavaScript/PHP, shell commands, SQL or unrestricted file paths. Future capabilities are added as reviewed registered handlers with schemas and meaningful tests.

| Section | Contract |
| --- | --- |
| Identity | `schemaVersion`, stable `id`, title, feature IDs, tags and purpose |
| Execution | environment capability, access mode, language, bounded timeout and resource requirements |
| Fixtures | office requirements, actor capability requirements, relationships and named records; dataset/IDs/roles are optional refinements |
| Data | typed literals, generator definitions, explicit valid/invalid cases and constraints |
| Preconditions | required routes/UI contracts, fixture state, stock and actor capabilities |
| Steps | browser actions, flow calls, capture, observation and assertions |
| Postconditions | persisted state, cross-page values, history and invariant checks |
| Cleanup | supported actions or explicit retained-record policy |

A suite chooses journeys, environments, languages and overall limits, with automatic seeded dataset selection by default. Each journey can execute independently; shared state is allowed only within explicit journey dependencies. Expand matrices into separate named cases so one failure does not hide other cases. Resolve included files and artifact/upload paths beneath their designated roots, reject traversal and escaping links, and cap file size, expanded steps, matrix cases, output rows and run duration.

Variable namespaces: `run`, `actors`, `fixtures`, `data`, `captured`, `observed` and `baseline`. References preserve types; unresolved values are errors. Variables are immutable by default, captures cannot silently overwrite fixture identities, and secrets have a separate nonserializable namespace inaccessible to generic assertions/logging.

Support a small typed comparison/arithmetic vocabulary for quantities, totals, dates and before/after deltas. No general-purpose expression evaluation. A captured actual value cannot become its own expected value: comparisons must trace to a declared input, independent observation or separately specified rule.

Bounded repetition over declared datasets, parameterized flow calls and explicit applicability conditions are sufficient initially. Do not hide failures with fallback selectors, conditionals or unbounded loops. Unknown operations, wrong types, cyclic flow references, duplicate IDs and invalid routes fail before execution.

## 7. Browser actions and UI contracts

Initial actions: login, select/verify context, goto a registered route, click, fill, clear, select, check/uncheck, keyboard input, upload, bounded download, reload, capture text/value/URL/record identity, observe and assert.

Initial assertions: visible/hidden, exact or explicitly normalized text, input value, enabled/disabled with explanation, checked state, label/control association, URL, row count, scoped row contents, field error, form error, HTML validity flags, relevant response status/payload and bounded absence/presence.

Locators prefer role and accessible name, associated label, or deliberate `data-testid`. Locator aliases describe semantic elements; bilingual visible labels remain assertions even when actions use a language-neutral test ID. CSS selectors are an explicit scoped escape hatch. Ambiguous matches fail rather than choosing the first match. Any UI test IDs added to Blade require compiled syntax and live rendering checks.

Provide reviewed reusable adapters for the application's Select2 AJAX dropdowns, Bootstrap tables/pagination, date controls, modal confirmations, repeatable document lines, uploads and document downloads. Table checks locate the exact object across filtering/pagination rather than accepting unrelated text on the page.

Playwright actionability and retrying assertions replace fixed sleeps. For queued operations, poll a named bounded observation or visible state, with a deadline and interval. Register network waits before actions; distinguish a final redirected GET from the original POST and its validation result. Successful HTTP 200 alone is never a successful save.

Observation suites prohibit business mutations. They permit explicitly classified normal authentication, working-context/session updates and their audit effects; those are recorded and never confused with fixture changes. All HTTP methods and flow effects are classified, so an unexpected POST cannot hide inside a navigation or read operation. Flow definitions expose their effects, required actors/fixtures, output variables and expanded assertions. All expanded steps appear in the report, so a macro does not conceal how a journey was exercised.

## 8. Randomization, validation and replay

Provide registered generators for unique marked text, integers/decimals, selectable fixture references, dates, safe synthetic email addresses and bilingual text. Generators respect constraints and the run's frozen clock/timezone; do not generate real personal identifiers.

Save a run seed, per-test/per-case seeds, generator version and safe generated values. Separate deterministic random values from uniqueness suffixes. A replay can reproduce safe inputs while remapping already-used uniqueness values and obtaining fresh fixture identities; report any remapping. Library version changes and live-state changes can invalidate replay equivalence.

Explicit validation matrices include empty/whitespace, wrong format, duplicate, min-1/min/max/max+1, decimals where integers are required, Bengali Unicode and reasonable length boundaries. Generate valid random inputs in addition to these cases, not instead of them.

For browser validation, prove the relevant validity/error state and that the save did not occur. For server validation, add a separately labeled authenticated HTTP negative case with genuine session/CSRF handling; it is server evidence, not a UI journey. Use allowlisted routes and pinned fixture IDs. Verify no unexpected business mutation and distinguish denial/failure audit writes from rollback of business data.

Do not automatically retry a mutation journey in the same state. A retry needs new owned fixtures, a fresh baseline and separate attempt evidence. Transient retries inside a read-only assertion are different from resubmitting a form.

## 9. Common observation checks

Observation means gathering fresh bounded evidence and comparing it to a declared expectation. Define three channels and label every assertion by its channel:

1. **UI observation:** visible labels, rendered values, tables, history, errors, navigation and disabled explanations.
2. **HTTP observation:** requests/responses caused by the browser, plus explicit separately reported authenticated negative probes. Do not store raw bodies, headers or credentials indiscriminately.
3. **Persisted observation:** allowlisted local read-only projections for selected owned IDs and relationships. Privileged database visibility is diagnostic evidence; it does not establish that the ordinary actor can access the data.

The PHP adapter exposes named contracts, such as `actor.assignments`, `office.context`, `inventory.consumable`, `inventory.balance`, `document.state`, `request.progress`, `tracking.scope`, `audit.outcome`, `mutation.effects` and `queue.operation`. Each handler defines typed inputs, ownership/scope validation, allowed output fields, row limit, timeout and secret filtering.

Implement projections with read-only query operations. Check for side effects in any service they reuse: verification services, model observers, convergence, lazy writes and context mutation can invalidate a read-only promise. Use parameterized registered queries; no JSON-authored SQL or arbitrary table/column access. Prefer a separately provisioned read-only DB connection where supported, and otherwise enforce/test the operation allowlist and read-only transaction behavior appropriate to the driver. Never expose connection credentials to JSON or Node.

Before/after observations use pinned IDs and a bounded affected-row set. Check changes to the target and relevant untouched related objects. Avoid application-wide counts that drift with unrelated work. Read a coherent database snapshot for related rows, poll asynchronous state with bounded fresh snapshots, and record source/time/fingerprint. Outdated baseline evidence must not produce a pass.

Expected values come from submitted data or independent rules. For example: requested quantity equals the declared input; a stock delta equals the issued quantity with the domain's signed movement semantics; creator remains the original drafter; actual poster is the posting actor. Do not call the mutation's own calculator and label its answer independent verification. Record the business/policy source of expectations in the feature contract. Current implementation helps locate behavior but cannot automatically redefine an intended requirement; changing an expectation requires an explained requirement change, not acceptance of whatever the page currently returns.

Seeded observation suites check current record existence/ownership, current role/membership consistency, company/office/geography relations, native stock versus ledger, visible UI versus the applicable scoped projection, and existing document/request state. Compare quantities and totals using current semantics: license records and license seats are distinct measures, and historical fixture counts are not fixed universal expectations.

Record `preexisting-data-problem` separately from a journey regression. A missing owned row, credential mismatch or corrupt fixture rejects the candidate and triggers automatic alternatives before any business actions. Exhausted alternatives produce a precise setup failure and allow independent tests to continue; they do not trigger a manual selection prompt. Observation automation does not satisfy the real-world G1 shadow observation requirement.

## 10. Illustrative seeded observation JSON

This example defines the proposed format. Handler, route and selector aliases are symbolic contracts to implement and validate against current code; this file is not presently runnable.

```json
{
  "schemaVersion": 1,
  "id": "inventory.seeded-consumable-observation",
  "title": "A storekeeper sees the current seeded consumable correctly",
  "features": ["inventory.consumables"],
  "tags": ["seeded", "readonly", "smoke"],
  "execution": {
    "capability": "observe",
    "language": "en-US",
    "timeoutMs": 60000
  },
  "fixtures": {
    "office": {"requirements": {"operational": true}},
    "actors": {
      "storekeeper": {
        "capabilities": {"allOf": ["inventory.consumables.view"]},
        "office": "office",
        "activeMembership": true
      }
    },
    "records": {
      "item": {
        "kind": "consumable",
        "office": "office",
        "ownedByDataset": true,
        "minimumAvailable": 1,
        "selection": "lowest-id"
      }
    }
  },
  "steps": [
    {
      "action": "observe",
      "check": "inventory.consumable",
      "args": {"id": "${fixtures.item.id}"},
      "as": "before"
    },
    {"use": "auth.login", "with": {"actor": "storekeeper"}},
    {"use": "context.select-and-verify", "with": {"office": "office"}},
    {
      "action": "goto",
      "route": "inventory.consumable.show",
      "params": {"id": "${fixtures.item.id}"}
    },
    {"action": "assert", "target": "consumable.nameLabel", "test": "text", "equals": "Name"},
    {"action": "assert", "target": "consumable.name", "test": "text", "equals": "${observed.before.name}"},
    {"action": "assert", "target": "consumable.quantity", "test": "number", "equals": "${observed.before.quantity}"},
    {"action": "reload"},
    {"action": "assert", "target": "consumable.name", "test": "text", "equals": "${observed.before.name}"},
    {
      "action": "observe",
      "check": "inventory.consumable",
      "args": {"id": "${fixtures.item.id}"},
      "as": "after"
    },
    {"action": "compare", "actual": "${observed.after.businessFingerprint}", "equals": "${observed.before.businessFingerprint}"}
  ],
  "cleanup": {"policy": "no-business-records-created"}
}
```

The contract must specify whether `quantity` means total, available or another measure, and map the selector to that exact measure. Numeric formatting/parsing is locale-aware and fails on missing/invalid values. The business fingerprint excludes incidental login/session activity but includes the defined invariant fields.

`inventory.consumables.view` is a semantic requirement alias to map to the actual current native authorization policy; it is not a proposed new GovStore gate. The `storekeeper` key is a descriptive actor alias here, not an exact-role constraint. A role-focused test can additionally require `role: storekeeper`. No dataset, account, password or credential-provider field is needed.

For a minimal context-only declaration, the same fixture contract can be as small as:

```json
{
  "office": {"requirements": {"operational": true}},
  "actors": {
    "tester": {
      "office": "office",
      "capabilities": {"allOf": ["inventory.consumables.view"]}
    }
  }
}
```

The runner supplies default active-membership, owned-fixture and private-credential resolution requirements. Additional stock, geography, distinct-user or denied-capability constraints are included only when the journey needs them.

## 11. Representative mutation journey

Implement a separate, independently executable request journey with these explicit stages:

1. Declare a suitable office, same-office user capabilities for submitting/approving/fulfilling, and distinct actors where needed. Automatically resolve matching seeded users, a supported requestable item and actual policy. Choose a supported branch rather than assuming all item kinds use the same fulfillment workflow; explicit role names are necessary only for role-specific tests.
2. Observe stock/request baselines and verify actors, office, policy and available quantity.
3. Log in as the employee, verify working context, navigate catalog, select the pinned item, enter a generated valid quantity and identifiable test purpose, and verify basket labels/totals.
4. Submit through the UI, capture the exact request identity from a verified result, and assert requester, delivery office, submitted quantity, purpose and initial state independently.
5. Reopen the request as the employee and compare fields with original inputs, not recaptured UI text.
6. Use a separate primary-approver session. Verify queue visibility and open that exact request. Approve through the UI; assert quantities, actual actor, history and resulting state.
7. If the selected policy requires a final approver, use that actor and verify that stage. The alternative policy branch is explicitly applicable and separately reported.
8. For a currently supported item/policy branch, fulfill as the storekeeper and assert document/request links, quantities, ledger/native state, poster attribution and employee-visible outcome. An unavailable adapter/workflow is reported as uncovered or blocked, not silently passed.
9. Exercise separate negative scenarios with another office/company actor, inappropriate state and invalid quantities. Verify current expected HTTP outcomes and unchanged business data. Keep national review/replay cases behind their actual review controls.
10. Use supported cleanup when available. Posted or historically significant data without a reversal lifecycle remains retained with its exact IDs and effects. No manual ledger repair, arbitrary row deletion or seed dataset wipe.

This flow is a pilot for the engine, not a promise that every package branch currently supports fulfillment. Before implementation, trace current routes, controllers, validators and services to establish each stage's current contract.

## 12. Results, evidence and failure behavior

Test statuses: `passed`, `failed`, `blocked`, `skipped`, `cancelled`, `interrupted` and `inconclusive`. Track retry/flaky information separately; a retry pass is not a clean first-attempt pass. Cleanup has its own status and can prevent an otherwise passing run from being considered clean.

Classify failures as assertion, application response/rendering, fixture/credential, preexisting data, environment/worker, schema/authoring, adapter/runner, interference or cleanup. Classification must preserve the actual observation and reference rather than guess the root cause.

Each step/event records run/test/case/attempt IDs, actor alias and minimal context, expanded flow location, observation channel, timing, action, status and redacted failure. Results include expected/actual values, safe application reference IDs, baseline/postcondition evidence references and private artifact locations. Exclude raw account hashes, passwords, sessions, headers, SQL bindings and environment dumps.

Append redacted JSONL events while execution proceeds; checkpoint results atomically. Record provenance: application revision and relevant dirty-file fingerprints, test/flow/schema hashes, runtime versions, language/timezone/clock, seed, environment identifier, selected fixture IDs, baseline timestamps, effective mode, relevant queue state and cleanup outcomes. No credential-bearing environment dump.

On a failed state-changing step, stop dependent steps; mark them unexecuted. Continue independent cases only after ownership/cleanup state is understood. Handle browser crashes, timeouts and interruption with partial evidence and no false success. An interrupted posting cannot simply be replayed: inspect persisted state and report/recover deliberately.

Default exit contract: 0 only when every selected required case passed cleanly and required cleanup succeeded; 1 for observed failures or cleanup failure; 2 for invalid/preflight-blocked required coverage; 3 for interrupted/inconclusive execution. Mixed runs prioritize interruption, then observed failure, then blocked coverage. Optional skips must carry declared applicability reasons and never count as executed coverage.

Artifacts are local and private. Verify restrictive storage permissions on Windows and other supported hosts, ignore generated files in Git, enforce retention and size limits, and never store reports under public web storage. Screenshots are masked where appropriate; credential-containing or sensitive pages can disable evidence entirely. Traces can contain protected payloads/session information and remain restricted, even when failure text is redacted. Redaction of text does not make an image or trace safe to publish.

## 13. Cleanup, cancellation and external effects

Maintain an append-only ownership journal as soon as each new identity is verified. Cleanup works in dependency order through registered supported UI/domain lifecycles, with fresh ownership, state and authorization checks. It must not remove unrelated records or shared seeded data.

Cancellation stops new actions, checks uncertain completed writes, releases sessions/leases and runs permitted cleanup. Cleanup cannot reverse an unsupported posted workflow. Report every retained document, request, upload, consumed fixture and inventory delta; preserve audit history.

Private local artifacts have a separate bounded retention policy and path validation. Remove only verified paths owned by that run. Temporary helper/credential files, if unavoidable, use restrictive permissions and are removed promptly. Persisting credentials is outside the normal design.

An explicit suite may test grant expiry, access-mode changes or reviewed national controls, but must snapshot/restore settings using the supported mechanism and report restoration failure. Routine suites never enable grants or bypass approval to reach a screen.

Observe application-side emails/jobs rather than suppressing them in the browser and claiming delivery was tested. Require a configured local sink for delivery tests, record whether only enqueue or actual delivery was observed, and keep external integrations disabled or explicitly authorized by the environment profile.

## 14. Agent authoring workflow and proposed commands

Agent workflow:

1. Read repository security/live-testing guidance and affected current implementation.
2. Identify the feature contract, relevant roles, states and negative cases; inspect sanitized discovery output.
3. Reuse or add JSON under the feature's `subtests` directory. Prefer shared flows and existing UI/observation contracts.
4. Validate and inspect the expanded execution plan before running.
5. Start the selected suite against the authorized environment. The runner automatically supplies matching seeded context and credentials; optionally specify a dataset or random seed to reproduce a particular context.
6. Read the machine summary first. Inspect page evidence only for failed/uncertain steps.
7. Diagnose the actual application, fixture, runner or authoring issue. Do not weaken assertions, disable security, or silently change expected results to obtain green tests.
8. Report exact verified journeys, blocked/uncovered cases, cleanup and retained effects separately.

Proposed commands; none exists yet:

```text
npm run test:live -- validate
npm run test:live -- list --feature requests
npm run test:live -- discover --environment local
npm run test:live -- plan --suite seeded-readonly
npm run test:live -- run --suite seeded-readonly
npm run test:live -- run --test requests.submit-approve --seed 20261006
npm run test:live -- run --suite seeded-readonly --dataset DATASET_ID
npm run test:live -- report --run RUN_ID
npm run test:live -- replay --run RUN_ID --failed-only
```

`discover` is metadata-only; `plan` executes no browser/business mutations and shows resolved requirements, effects and coverage. Replay rechecks authorization, fixture freshness and cleanup policy. It does not blindly re-submit a partially completed mutation. Do not implement credential-export flags.

## 15. Feature coverage and quality rules

Maintain a feature catalog with current route/package references, supported capabilities, required journey IDs, languages, roles, state branches and negative cases. Suggested groups: authentication/context, native inventory, membership/onboarding, classifications/metadata, stock documents, requests/approvals/fulfillment, tracking, access governance, private evidence and safe failures.

Every important mutating feature should eventually include happy path, input validation, cross-page persistence, correct actor/context, appropriate-state checks, and relevant denied/rollback cases. Read-only features include meaningful labels, data accuracy, scope, filtering/pagination and authorized detail/download access where relevant.

Report distinct counts: implemented test definitions, executed cases, passed cases, blocked cases, applicable skipped cases, unavailable capability branches and missing coverage. Discovering routes does not automatically prove features work, and visiting a page is not an acceptance assertion.

Contract/schema version changes are explicit. Capability extensions include documentation, an example JSON and meaningful handler tests. A new custom widget may require one reusable handler; JSON-only authoring is the normal path, not a claim that no future runner code is ever needed.

## 16. Implementation phases and acceptance gates

| Phase | Deliverables | Gate to proceed |
| --- | --- | --- |
| 1. Contracts and core | Runtime/dependency check, schemas, loader, semantic validation, dry plan, typed variables, browser actions, event/results reporter, small local test app | Malformed/unknown/cyclic definitions fail before execution; a real-browser synthetic journey records an accurate pass and deliberate failure without an AI call |
| 2. Seeded access | Read-only PHP adapter, configured provider authorization, automatic complete-context resolver, capability selectors, verified run/default credential provider, normal UI login/context flows | Requirements-only JSON runs without account/dataset/password prompts; isolated tests prove discovery performs no business/manifest/account writes, preserves authorization, rejects foreign data, tries eligible alternatives and does not leak credentials |
| 3. Observations and readonly pilot | Named projections, independent comparisons, bilingual contracts, seeded inventory observation suite, restricted artifact storage | On the authorized live local server, pinned seeded records match the scoped UI and persisted evidence; a deliberately wrong expected label/value produces a precise failure; sensitive evidence stays private |
| 4. Controlled journeys | Generator/matrix support, captures, Select2/tables/modal adapters, ownership ledger, supported cleanup, notification preflight, lifecycle lease integration | A supported create/reopen and multi-actor journey runs repeatedly with fresh identities; assertions prove persistence and correct actors; controlled failure/cancellation reports exact retained effects |
| 5. Security and resilience | Relevant validation/boundary/direct-request suites, shadow/enforce expectations, queue waits, interrupted-posting handling, redaction and crash tests | Wrong office/company/state is tested through the current server contract; no business write follows denial; failures and cleanup errors cannot become success; existing required security suites stay green |
| 6. Coverage expansion and operator experience | Feature catalog, documentation, suite filters, replay, report history and optional separately secured dashboard proposal | Agents can author representative journeys from the guide without per-feature browser code; missing coverage and limitations remain visible; dashboard work has its own authorization review |

Phases 1–3 are the first useful release. Phases 4–5 add trustworthy mutation/security coverage. Expand coverage only after those contracts are reliable; do not count draft JSON as verified features.

No initial dependency on a dashboard, CI scheduler, cloud testing account, broad reseeding or application database migration. Lifecycle coordination may require a targeted lease schema change; design and test it in isolation before any authorized targeted dev migration.

## 17. Verification strategy and final acceptance

Test the runner with a miniature HTTP app and real browser: action/variable semantics, captured value reuse, client/server validation, asynchronous waits, deliberate mismatch, ambiguity, timeout, partial failure and cancellation. These checks must expose meaningful false-pass risks rather than merely repeat implementation details.

Test the PHP bridge and lifecycle integration against isolated SQLite or a uniquely named isolated MySQL database as appropriate. MySQL is required to establish advisory-lock/lease/concurrency properties. Assert unchanged users, passwords, membership, ownership manifest and relevant business rows after discovery/observation. Test expiry, crash recovery, conflicting actors/resources, foreign IDs, missing schema and secret filtering. Use synthetic canary secrets to check console/events/results/errors/artifact filenames and reports, and test sensitive-stage capture suppression; do not use real credentials as leak-test evidence.

Add meaningful resolver tests: several matching offices/users need no prompt; an office missing one requested actor is skipped for a complete context; a wrong-credential/inactive candidate is skipped without a failed browser sign-in; capability-only tests do not require exact role names; a least-privilege actor is selected rather than a superuser; same-office and distinct-user constraints hold across all actors; and exhausted candidates emit `fixtures.unsatisfied-requirements` while independent tests execute.

For changes touching repository protections, run the required installed-PHP suite:

```text
php vendor/bin/phpunit tests/Feature/GovStore/G1AuthorizationTest.php
```

Run relevant isolated package tests and update route coverage if any protected HTTP surface is introduced. UI changes require compiled Blade/PHP syntax checks and representative live rendering. Never reset the existing development database as test setup.

Live verification must record the actual host, application revision, dataset/actor/context, selected IDs, before/after effects, browser evidence and cleanup. Verify multiple languages and roles where declared. Read-only discovery should precede live mutations.

Final acceptance requires all of the following:

- An agent can add a journey JSON using existing capabilities, validate it, run it, and read structured results without manual per-page browsing.
- Seeded users and credentials resolve from current owned data without old IDs/password literals, account rewriting or manifest updates.
- A JSON test specifying only office requirements and user capability requirements selects its entire seeded context automatically and runs without dataset/account/password input. Several candidates, a stale user or one unsuitable office cause deterministic alternative selection, not a manual blocking question.
- One read-only seeded observation suite and one supported multi-page mutation journey have fresh live evidence.
- A generated input is verified on a later reopened page; a captured identity is used by another actor in a separate session.
- Explicit valid/invalid/boundary cases prove appropriate UI/server behavior and persisted invariants.
- A wrong assertion yields a precise failed step with expected/actual evidence; failed prerequisites, blocked fixtures, retries and unavailable branches cannot be reported as clean passes.
- Credentials and protected evidence remain private; setup, execution and reporting require no language-model API.
- Cleanup is verified or exact retained effects are reported. Existing datasets, audit history and unrelated business data remain intact.
- Coverage reports state what was implemented, executed, verified, blocked and still missing. Required isolated security checks pass when affected.

## 18. Risks, decisions and references

| Risk | Planned response |
| --- | --- |
| Incorrect actor or stale seed account | Fresh constrained resolution, hash check, pinned identity and context proof |
| Wipe/account update during a journey | Lifecycle-coordinated lease, rechecks and interference reporting |
| UI layout/translation drift | Semantic contracts, strict ambiguity failures, explicit label assertions and reusable widget adapters |
| HTTP 200 with an application error | Check business outcome, current response/validation semantics and persisted effects |
| Random data cannot be replayed | Recorded seed/version/clock/inputs with explicit uniqueness and fixture remapping |
| Retry creates duplicates or double-posts | No automatic mutation retry; fresh fixtures or deliberate recovery |
| Observer or service writes during observation | Dedicated read-only projections, isolated no-write tests and constrained DB execution |
| Screenshots/traces leak secrets | Sensitive-stage capture disabled, private artifact permissions, bounded retention and no automatic publishing |
| Cleanup has no supported reversal | Retain and report exact records/effects; never repair balances or delete arbitrary rows |
| A passing test compares the page with itself | Independent expected sources and provenance; review assertion quality |
| Broad coverage claims hide unsupported workflows | Catalog unavailable/missing/blocked branches separately from executed passes |

Defaults chosen by this plan: local CLI first, Playwright Test, versioned JSON, normal browser login, read-only seeded discovery, independent observation projections, explicit mutation ownership, serial conflicting journeys, private artifacts and no AI during execution. Resolve exact route aliases, supported fulfillment branches, observation fields and cleanup adapters from current code during their implementation phase.

Repository references:

- [Agent instructions](../../AGENTS.md)
- [Security rules](../security/govstore-agent-security.md)
- [Local live testing and database guide](../testing/local-live-testing.md)
- [Experimentation operations](../../packages/gov-store/experimentation/README.md)
- [Experiment model](../../packages/gov-store/experimentation/src/Models/ExperimentRun.php)
- [Experiment access](../../packages/gov-store/experimentation/src/Services/ExperimentAccess.php)
- [Ownership registry](../../packages/gov-store/experimentation/src/Services/RecordRegistry.php)
- [Account update service](../../packages/gov-store/experimentation/src/Services/ExperimentAccounts.php)
- [Existing verifier and its side effects](../../packages/gov-store/experimentation/src/Services/ExperimentVerifier.php)
- [Current access decisions](../../packages/gov-store/tenant-scope/src/Services/GovAccess.php)

Tool documentation consulted for the recommendation:

- [Playwright locators](https://playwright.dev/docs/locators)
- [Playwright waiting and actionability](https://playwright.dev/docs/actionability)
- [Playwright reporters](https://playwright.dev/docs/test-reporters)
- [Playwright traces](https://playwright.dev/docs/trace-viewer)
- [Faker generation and seeds](https://fakerjs.dev/guide/usage.html)
