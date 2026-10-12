# Live testing plan for `user_stories.md`

**Purpose:** exercise each of the 31 user stories through the real local UI,
using the current fictional seeded dataset and role-appropriate accounts. This
plan is for the existing nonproduction installation only. It does not certify
production behavior.

## Current run baseline

- Every user story US-01 through US-31 now has a dedicated live journey under
  `tests/live/subtests/user-stories/`, and all 31 are included in the
  `user-stories-seeded-readonly` aggregate suite.
- Final aggregate run on 2026-10-08: **31 passed, 0 failed, 0 blocked** in
  185.41 seconds (run ID `run_1791465107058_R-3CUC`).
- The 31 journeys are seeded, read-only UI entry and access-boundary checks.
  A pass confirms the configured role can reach the tested page or receives
  the expected denial; it does not prove every write, validation branch,
  notification, cleanup path, or multi-actor business flow in the story.
- Seed discovery found **one ready government dataset**. Its ID and account
  list are intentionally not copied into tracked documentation; the runner
  resolves current fixture-owned accounts at run time.
- The user-story aggregate runs alongside the existing request and committee
  live journeys; it does not replace those focused suites. Write portions
  needing synthetic records, administrative grants, imports, lifecycle changes,
  or permanent role changes remain separate controlled scenarios.
- The broader live-test coverage report still lists four blocked non-story
  journeys (committee rules, seeded consumables, basket validation, and
  submit/approve) from their focused suites. All 31 `user-stories.us-*`
  journeys in the final aggregate run passed; these focused-suite results are
  reported separately and are not counted as user-story failures.
- No dataset population, wipe, account change, access-mode change, or role grant
  was performed. The story journeys were read-only; no business data changed.

Re-run `npm run test:live -- discover` before each run. Do not rely on historical
account names, IDs, fixture totals, or the previous access mode. Use the runner's
fixture resolver and normal login; never place a password, CSV account list,
cookie, or dataset ID in a journey or committed evidence.

## Role-based journey matrix

| Story | Seeded actor(s) | Seeded records / prerequisites | Live journey and evidence |
|---|---|---|---|
| US-01 Geography and ministry directory | Superadmin; ICT officer | Existing directory and geographic data; a reviewable directory import file is an external fixture | Superadmin opens the directory and checks existing entries; ICT officer searches an in-jurisdiction area. Treat CSV import as a controlled write and verify the resulting directory through the UI. |
| US-02 Provision an office | ICT officer; superadmin | Existing ministry, geography, and duplicate-office candidates | Read the registry and search areas first. Provision only a clearly disposable office if an approved cleanup path is available; otherwise verify form validation and out-of-jurisdiction rejection without submission. |
| US-03 Configure an office and staff | Office administrator | Seeded office profile, active staff, and duty holders | Review the hub/checklist, staff list, roles, and invite UI. Do not change existing assignments or create invitations unless a controlled write run is approved. |
| US-04 Onboard an existing user | ICT officer or authorized onboarding administrator; target user | Requires a seeded user waiting for office assignment; ready-dataset users are generally already assigned | Verify the queue and target-user eligibility. If no waiting user is found, record blocked by fixture; do not detach a seeded user's membership to manufacture the fixture. |
| US-05 Active office context | Employee; optionally superadmin for comparison | Active office membership; a second active membership is needed for switching | Check the employee's header/context and compare visible office scope with the office projection. Exercise switching only when a seeded multi-membership actor exists. |
| US-06 Receive stock | Storekeeper | Operational office, supported product, sufficient stock context, serial requirements where applicable | Inspect the operations hub and existing register read-only. Posting a receipt is a write and changes inventory; run only with a generated, unmistakable document and retain its audit trail. |
| US-07 Request from catalogue | Employee | Requestable seeded inventory in an operational office | Existing `requests.catalog-progress-observation` checks UI progress against persisted requests. Basket add/remove is a reversible write journey; request submission is retained and requires explicit write-run handling. |
| US-08 Approve/reject a request | Primary/final approver | Pending request belonging to the approver's office and applicable approval policy | Use a test request created by US-07 or an existing explicitly designated test request. Approval/rejection changes durable history; never decide a real operational request. |
| US-09 Fulfil a request | Storekeeper | Approved request and selectable seeded stock/asset | Review fulfillment queue read-only first. Issuing stock/checking out assets is an irreversible business action; use only a designated synthetic request and report the retained document/asset assignment. |
| US-10 Direct goods issue | Storekeeper | Office stock balance above zero and supported product | Read the register/kardex first. A posted issue changes stock; run only as a controlled write and verify document, ledger, and native balance independently. |
| US-11 Check an asset in | Asset manager/storekeeper; ordinary employee for denial | Seeded asset with an existing assignment, plus an employee without check-in permission | Verify assigned asset and history. Check-in mutates custody; only use an explicitly disposable seeded assignment and restore through supported UI if available. Verify employee denial without submitting a mutation. |
| US-12 Membership release and switching | Employee; home-office administrator; receiving-office administrator/colleague | Active membership, clearance state, and a second membership or eligible duty recipient | Review clearance/notices. Exercise only a membership marked for testing; release is blocked by holdings, responsibilities, cover, and unresolved requests. Do not override clearance or perform a real transfer. |
| US-13 Adopt catalogue category | Office administrator/catalog adopter | Published collection or selectable official category, office catalog | Browse and inspect the office catalog read-only. Adoption creates tenant catalog records; run only if the selected bundle is explicitly designated for live testing. |
| US-14 Product rules | Policy administrator | Existing rule/template and a test category/product | Inspect rule list and simulator read-only. Publishing/assigning changes operational validation; do not publish against a live office without an approved policy fixture. |
| US-15 Office duty handover | Current duty holder; eligible active colleague; office administrator | Current responsibility and eligible recipient in the same authorized context | Review duties and pending handovers. Create/accept only a marked test handover; verify old/new holder and notices, and use the supported cancellation/return path if needed. |
| US-16 Membership override | Superadmin; ordinary employee for denial | Existing target with a known membership/duty state | Verify the protected console and denial. Force release/role stripping is a national mutation requiring a reviewed reason and typed confirmation; do not execute against seeded users as a convenience. |
| US-17 Office/ministry isolation | Office A employee/storekeeper; Office B actor; authorized oversight actor | Two populated offices and a known foreign record | Compare normal list visibility and direct foreign-object responses. Existing committee foreign-boundary journey covers committee detail; add equivalent inventory/request checks only where a known cross-office fixture is available. |
| US-18 Register and kardex | Storekeeper or authorized inventory manager | Existing office stock and movement history | Open register and a known product kardex; compare displayed values to the read-only stock/document projection. Do not create movements just to make a chart nonempty. |
| US-19 Asset maintenance | Asset manager; employee for denial if relevant | Seeded asset and existing maintenance types | Inspect existing asset/history and maintenance screen. Creating, editing, or deleting maintenance is a write; use only a designated test asset and supported UI cleanup. |
| US-20 Bulk inventory import | Superadmin/import administrator | Supported CSV template and disposable import rows | Inspect template and validation using a deliberately invalid file or preview-only path. A valid import creates inventory records; run only in a controlled write session and verify row-level results. |
| US-21 Initiatives and funding | Programme administrator | Existing funding types and initiative records | Browse portfolio, initiative detail, and funding dictionary read-only. Creation/deletion changes shared dictionaries; mutate only a clearly marked disposable initiative/funding record. |
| US-22 Tracking-code tasks | Operation head/officer; programme administrator | Initiative, operation-unit assignments, active task policy and geography | Inspect initiative, team and task views. Creating/activating/archiving a tracking code and attaching documents are durable writes; use a marked test initiative/task and verify its scope and report. |
| US-23 Master catalogue import | Catalogue administrator/superadmin | Installed supported bundle and reviewable test CSV | Inspect dashboard/history and run analyze/review without execution. Import execution changes national reference data and requires review of the exact supported bundle; do not execute as a routine story run. |
| US-24 Mappings and collections | Catalogue administrator; office catalog adopter | Existing mappings, collections, official nodes, Snipe-IT categories | Browse governance and collections read-only. Mapping/publish/adoption changes shared or office catalogs; preview first and execute only a reviewed disposable bundle. |
| US-25 Tenant-scope policies/mappings | Superadmin or authorized scope administrator; office user | Existing policies, reference values, and mapping rows | Read dashboard/config/mappings and compare an office selector. Saving policy/mapping changes broad selector behavior; do not change a shared policy for a smoke test. |
| US-26 Metadata health | Metadata/platform administrator | Current provider/schema state | Open health page and compare reported status to read-only schema/provider data. Queue convergence only if the page reports an actionable inconsistency and the operator has approved background work. |
| US-27 Company administrators | Superadmin; company administrator/employee for denial | Existing company-admin assignment and company/user entries | Check the superuser-only page and negative role response. Assign/remove is a national role mutation; do not edit real seeded assignments. |
| US-28 Office starter catalogue | Office administrator; queue worker/operator | Populated reviewed starter collection, current office type, active office-admin membership, queue worker | Check hub status and existing catalog. Current environment may lack active populated starter collections; report fixture blocked. Retry is safe only for a saved reviewed bundle and should be used only for an identified failed test run. |
| US-29 Committee management | Registrar; appointed member; company/national catalog administrator | Existing office committee; active ministry type/purpose policy for write flows | Existing committee read-only journeys cover desk/registry/history/print/boundary/order-readiness. Current active policy and admin-role fixtures may be absent; do not enable national templates or create policy merely to pass the test. |
| US-30 Appearance preferences | Any user; authorized office/company administrator for assignments | Shipped themes and effective context defaults | Review `/gov/appearance` and assignment state. Personal preference is reversible; assignment/enforcement changes affect other users and require a marked test scope or read-only verification. |
| US-31 Office lifecycle | Office administrator; ICT officer or superadmin for relocation | Office profile, current lifecycle state, and authorized geographic areas | Inspect current state. Suspend/resume/relocate requires reason and typed confirmation and changes intake/scope; run only against a designated disposable office. Closure/merge must remain blocked until clearance exists. |

## Suggested execution by seeded role

Resolve each actor from the current ready dataset and verify its actual ability,
active membership, working office/company, and (for ICT) jurisdiction before
login. Job titles and historical account names are not permission evidence.

1. **Employee:** US-05, US-07, employee-side US-12, US-17, US-29 member view,
   and US-30 personal appearance. Keep request submission out of the read-only
   run unless `--allow-writes` is deliberately enabled.
2. **Storekeeper:** US-06, US-09, US-10, US-17, and US-18. Begin with read-only
   register/queue checks; stock issue, receipt posting, and fulfillment are
   separate controlled write journeys.
3. **Approver:** US-08 after a marked test request exists. Use a separate
   employee and approver browser session; verify the decision as both actors.
4. **Office administrator:** US-03, US-12, US-15, US-28, US-30 assignment,
   and US-31. Treat membership, lifecycle, theme assignment, and starter retry
   as writes with their own preconditions.
5. **ICT officer/onboarding administrator:** US-01, US-02, and US-04. Verify
   geographic jurisdiction and the target's current office/membership before
   attempting a mutation.
6. **Catalogue/programme/policy administrators:** US-13, US-14, US-21 through
   US-26, US-27, and US-29 policy setup. Prefer review/simulation/read-only
   observations because these roles can affect national reference data.
7. **Superadmin:** US-01 and negative/oversight portions of US-16, US-23, US-25,
   US-26, and US-27. Do not use superadmin access to stand in for an office role.

## Runner coverage and execution order

Run the aggregate read-only story slice first:

```powershell
npm run test:live -- validate
npm run test:live -- discover
npm run test:live -- run --suite user-stories-seeded-readonly
npm run test:live -- coverage
```

The aggregate suite contains one journey for each of the 31 story IDs. The
2026-10-08 result above is the current recorded run; rerun it after relevant
code, fixture, route, or story changes.

The suites use seeded fixture resolution and create no business records. A
runner `fixtures.unsatisfied-requirements` result is a blocked story prerequisite,
not a pass. A browser launch/preflight failure before the first journey is an
environment block, not a story failure.

Before adding write journeys, follow
[`live-tests-agent-guide.md`](live-tests-agent-guide.md): declare
`write-fixtures`, generated unique marker data, a relevant database precondition,
record ownership/ledger, supported UI cleanup or explicit retained-record
policy, and independent database observations. Do not create one journey that
posts receipt/issue documents, changes national catalog or scope policy,
releases membership, changes an office lifecycle, or changes permanent roles
without an approved disposable fixture and package-supported cleanup. Existing
ready experiment data belongs to the user and must not be wiped or rewritten to
prepare a story run.

## Recording results

For every story, record one of **passed**, **failed**, **blocked by fixture**,
or **blocked by environment** with:

- story ID, date/time, git revision and live-test run ID;
- role/capability and office/company scope (no username or credential);
- seeded record category and exact object IDs only in private run artifacts;
- route and interaction reached, UI outcome, independent database result;
- any write effects, supported cleanup result, retained documents/records and
  audit entries;
- untested role branches and why they remain open.

Keep account CSVs, private evidence, raw database dumps and runner artifacts
under ignored `storage/app/private/live-tests/`. Do not attach those files to a
commit or copy credentials/identifying data into this report. A passing seeded
journey applies only to its recorded revision, local app, dataset and scope.
