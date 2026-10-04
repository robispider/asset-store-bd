# Committee package implementation and local verification

Recorded **5 October 2026, Asia/Dhaka**, against this working tree and the
authorized local `snipeit.local` application / `snipeit` database. This is dated
evidence, not a permanent environment inventory or a production release claim.

## Implemented

The new `gov-store/committee` package implements an inventory/asset committee
registry: catalogue policy and purpose bindings, constitution and private
orders, seats and effective-dated tenures, external identity, correction and
replacement history, declaration evidence, scope assignment, purpose
resolution, health, snapshots, chained audit, versioned reconstitution and
term/state changes. It adds Bangla/English workspaces and an office registrar
responsibility through the existing access-request workflow.

NIAR means Inventory and Asset Register. The shipped templates are GRIC, TIC,
SVC, BOS and DSP. Procurement TOC/TEC/TSC are excluded and rejected by catalogue
validation. All five national templates are inactive pending an authorized
inventory/asset policy review. The design document was corrected to record this
scope and point to the delivered implementation.

Every new HTTP route declares one registered GovStore ability. Mutation
services enforce owning office/company and allowed state in transactions, even
in shadow mode. Catalogue mutations require actual role permission. National
review tokens bind actor, target, exact proposal and current catalogue state,
expire after 15 minutes and are consumed once. A catalogue mutex serializes
national changes; a type mutex, stable lineage locks and database unique slots
protect exclusivity. Files remain private; HTTP mutation results expose IDs and
navigation rather than raw model paths.

Store-operations only declares inventory/asset purposes. Its inspection
workflows and document posting have not been changed by this registry release.

## Automated verification

Installed PHP **8.4.15**, Xdebug disabled. `CommitteeTest` builds a minimal core
fixture and all package migrations in isolated SQLite memory. No automated test
connects to or resets the existing development database.

```text
php vendor/bin/phpunit tests/Feature/GovStore/CommitteeTest.php tests/Feature/GovStore/G1AuthorizationTest.php
OK (54 tests, 1885 assertions)
```

The combined result comprises **25 committee tests** and **29 G1 tests**. It
covers complete/incomplete activation, inactive inventory catalogue and
procurement exclusion, exact/ancestor/ministry resolution, ambiguity,
exclusivity, body-ID scope, overlapping tenures, one-person/one-seat, frozen
policy, replacement and correction history, reconstitution dates and discarded
versions, disabled/departed members, shadow/enforced boundaries, read-only code
lookup and external linking, private order/declaration hashes and access,
national review/replay, expiry/extension/reminders, clearance, native audit,
atomic transfer rollback, every route's ability, bilingual keys and compiled
Blade syntax. A visiting officer remains visible through an active membership
in the working office while their appointment retains the correct home office.
Withdrawing the final coverage scope requires typed `INOPERABLE` acknowledgement
and a reason; refusal rolls back the dated coverage change.

Additional validation: PHP syntax checks on **127 files**, compiled Blade
syntax, Laravel view compilation and JavaScript syntax/build verification.
The standalone committee asset was built and versioned; a full Mix build was
not run because this checkout has no installed Node dependencies.

## Live verification

Only the **16 committee migrations** and targeted `CommitteeSeeder` were applied
to the authorized database. No reset, broad seed, access-mode change, password
change, role grant or document/inventory mutation was used to prepare testing.

### Browser exercise

The existing authorized browser session was preserved. Its working context was
office **4**, company **412**. The application remained in **shadow** access
mode; this exercise does not claim that this actor has registrar authority under
enforcement. The isolated automated suite checks enforced role denials.

The browser created `CM-4-2627-0001`, explicitly named **LOCAL TEST ONLY
Inventory Committee**. It uploaded a synthetic test order through the file
chooser, created three fictional external seats and appointed holders,
assigned the owning office, checked the composition checklist and activated
through the confirmation dialog. Incomplete activation remained disabled.
The printable dated roster retained all three appointment snapshots. The
committee was then dissolved through a separate test order and typed committee
number; its roster, files and audit history remained readable.

Screenshots:

- [Activated workspace](committee-live-active.jpg)
- [Dissolved workspace](committee-live-dissolved.jpg)
- [Printable historical roster](committee-live-print.jpg)

### HTTP role and boundary exercise

Normal login and CSRF handling with discovered fictional dataset accounts:
office administrator **1528** and storekeeper **1576** in office **160**,
company **543**; outside member/other-office administrator **1529** in office
**161**, company **545**. No credential, cookie or identity lookup token is
included in evidence.

[26 HTTP checks](committee-live-http.json) exercised draft/order/composition/
scope/activation, repeat activation (409), incomplete activation (422), foreign
office detail/roster/activation/private-file rejection (404), storekeeper read,
outside member's personal view, national catalogue denial (403), exact code
lookup, memo digit normalization, SHA-256 file integrity, unchanged office
membership count, roster snapshot verification, wrong dissolution confirmation
(422) and successful dissolution. The fixture code was expired afterward.

### Simultaneous activation exercise

[Parallel MySQL evidence](committee-live-concurrency.json) records two separate
PHP processes starting activation within approximately **35 microseconds** of
one another. Their execution intervals overlap. Exactly one succeeded (**200**)
and the other returned **409**. The winner was dissolved and the losing draft
was soft-deleted afterward; no active slot remained.

Live findings fixed during implementation:

- Laravel route defaults were mistakenly injected as committee identifiers;
  controllers now obtain the actual URL identifiers explicitly.
- An officer picker subquery selected several columns; it now selects the office
  identifier, with regression coverage.
- A duplicate slot under MySQL repeatable-read could rethrow a database error
  because a subsequent ordinary read did not see the competing commit. Unique
  violations now map directly to 409, and exclusivity uses consistent mutex
  ordering and current locking reads.
- Preliminary concurrency fixtures reused a type already dissolved on the same
  inclusive effective day. Both were correctly refused for historical overlap;
  the simultaneous check then used a distinct disposable type.

## Cleanup and retained evidence

[Final database verification](committee-live-cleanup.json) confirmed:

| Check | Result |
|---|---|
| Active test committees / exclusive slots | **0 / 0** |
| Retained committee records | **8**: 4 dissolved, 4 soft-deleted drafts |
| Disabled disposable test types | **4** |
| Inactive national illustrative templates | **5** |
| Private test orders retained | **12**, all SHA-256 hashes match |
| Fictional external member records retained | **3** |
| Ledger lineages checked | **8**, no broken chains |
| Native committee activity summaries | **64** |
| Unexpired fixture identity codes | **0** |
| Access mode | **shadow**, unchanged |

The authoritative ledger covers all exercise records. Native audit summaries
were introduced during implementation and verified on subsequent live
mutations; earlier exercise entries were not backfilled into native activity.
All test legal instruments are synthetic and clearly labelled; they remain
private alongside the preserved audit records. Temporary live helpers, barrier
files and upload originals were removed after verification. The original
browser session and all existing access assignments were preserved.

## Remaining work

- Authorized review/activation of real inventory and asset policies and purpose
  bindings, real office orders, registry data and scheduled command operations.
- The consumer-owned inspection workflow in design §15, including accepted
  quantities, report concurrence, inspection inbox, posting policy and shadow
  reporting. No inspection requirement is enforced on existing receipts yet.
- Rich dashboard/export UI, measured query/cache performance targets, exact
  fallback-loss simulation, accessibility/usability testing and broader consumer
  integrations. The current package APIs support a broader policy surface than
  its initial forms.
- CI execution and production review; the required real-world observation
  period remains open. Local tests do not establish WCAG AA or production
  readiness, and G7 is not declared fully closed.
