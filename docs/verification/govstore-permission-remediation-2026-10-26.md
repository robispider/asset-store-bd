# GovStore permission remediation execution record

26 October 2026, Asia/Dhaka. Authorized development target: `http://snipeit.local/`.
Baseline investigation revision: `afbe5da8d6`; results below cover the corrective
working tree, not a published release or CI run.

All F01–F15 findings have corrective implementation and automated regression
coverage. Enforcement is enabled by default. The owner also authorized resolving
the ambiguous development categories as national definitions. Full cross-role
live functional acceptance remains unverified; page checks below are separate.

Source: [investigation and accepted decisions](../gap-analysis/govstore-permission-investigation-2026-10-26.md).
Current routes: [refreshed inventory](govstore-permission-route-inventory-2026-10-26.csv).

## Implemented findings

| Finding | Corrective behavior |
| --- | --- |
| F01 — Shadow/default and employee My Catalog access | Default mode is `enforce`; My access displays the effective mode. My Catalog uses `catalog.office.adopt` and a strict server check, including shadow mode. Ordinary employees cannot enter by a direct URL; the management tab/menu follows the same decision. The employee request catalog remains available according to its own policy. |
| F02 — Native location self-approval | Native user creation/location changes cannot create, revive or release office memberships. An ordinary working office requires the user's own active approved membership; `users.location_id` is not an authorization fallback. Reviewed onboarding/join services retain their approved paths. |
| F03 — Shared commodity remapping | Office creation reuses only a matching category/code/type without rewriting shared governance or commodity mappings. Remapping an existing code requires the exact reviewed national master operation. Review fingerprints include the target mappings. |
| F04 — Adoption source IDs | Adoption, stop, archive, restore and bulk paths validate the actual source, office/company boundary and target scope. National/company/office ownership is explicit; unresolved legacy sources require an existing own mapping. These checks also enforce in shadow mode. |
| F05 — Company reference mutations | Only the actual matching company administrator can mutate a company-owned reference. Ordinary office roles remain limited to references owned by their working office. A company ID alone grants no company mutation authority. |
| F06 — Combined responsibilities | One role resolver supplies the union of permanent responsibilities, office administration, applicable company/ICT assignments and unexpired grants to native permission profiles and GovStore decisions. Roles no longer disappear behind a priority role. |
| F07 — Temporary serialized-inventory cover | Serialized asset guards use current effective roles, including live cover. Custody recipients are validated independently from the issuing asset's office. Assignment type changes are checked too. Original foreign ownership still denies mutation. |
| F08 — Excessive access-request bundles | Catalog maintenance and document reading use dedicated narrow roles. Requests and approval screens disclose the full role/ability/native bundle, office, expiry and separation-of-duties notice. Requested grants expire, default to 30 days, and do not create permanent responsibilities. Existing assignments were not mass revoked or rewritten. |
| F09 — National mutations without review | Metadata maintenance uses the registered national gate, exact plan review and a durable operation. Jobs recheck the active real superuser, consumed review, plan fingerprint and queued state, process only reviewed IDs, record terminal results and reject replay. National Finance defaults, native references, provisioning/bulk and organization theme assignment enter the shared review path while preserving their existing narrower policies. |
| F10 — Infeasible approval routes | Readiness, basket previews and request screens use active, nondeleted, same-company members with current responsibilities/cover. Two-stage policies require distinct eligible primary/final actors, excluding the requester. A blocked path gives an actionable warning and recovery link. Existing pending requests are preserved; infeasible approval is not silently bypassed. |
| F11 — Secondary-office recipient visibility | Native user scope includes current active secondary memberships in the same company. Recipient mutation checks validate the actual office membership, activation and expiry. Native checkout/check-in preserves the asset's issuing office instead of treating the recipient's home office as ownership. |
| F12 — Asset-model owner key | Model references consistently use canonical `model`; explicit ownership and related object IDs use the same registry and scope checks as categories, suppliers and manufacturers. Explicitly private references do not become visible merely because they lack adoption rows. |
| F13 — Authorized office-hub update | The location boundary policy delegates to organization authorization, permits valid office administration and rejects company/parent rehoming. Native office creation still requires the internal provisioning permit. |
| F14 — First adopter becomes owner | `gov_reference_owners` records independent national/company/office ownership. Adoption never creates ownership. National governance takes precedence over adoption, and native update/delete routes now require the correct GovStore decision plus native policies. Original creator attribution survives ownership changes. Finance defaults follow the same owner boundary. |
| F15 — Native create bypass | Office roles lose native reference/office create permissions. Model creation also requires a request-scoped internal permit. Scoped GovStore forms create models, suppliers and manufacturers with explicit ownership/adoption and validated related IDs; categories use the governed category creator, offices use organization provisioning, and national creation requires review. |

Company office-creation/jurisdiction flags were removed as accepted in P01.
P02, P03 and P05 are implemented through the employee catalog boundary, explicit
ownership and canonical governance vocabulary. P04 (auditor/independent verifier)
was not selected and remains a separate future policy, not an implemented role.

## Development migrations and data resolution

Only these targeted migrations were applied to the verified development database:

- `2026_10_26_000001_create_gov_reference_owners.php`
- `2026_10_26_000002_create_gov_reviewed_operations.php`

The initial legacy review found 81 `global` categories, 58 `local` categories with
recorded company origin and 644 `location` categories without a recorded owning
office. The migration normalized these to national, company and unresolved
respectively. It did not infer owners from adoption. The sample-data creator was
updated to write canonical scope and explicit owners for new records.

The owner then instructed: “this is a development site. so either discard it or
make them national if needed . no need any extra”. The 644 ambiguous categories
were made national, preserving their names, codes, original attribution, adoptions
and inventory links. No category or stock data was discarded. The operation locked
and revalidated each exact category/owner against the frozen private bundle,
updated ownership/governance transactionally and recorded a success audit.

| Final verified aggregate | Count |
| --- | ---: |
| Registered national category owners | 725 |
| Registered company category owners | 58 |
| Registered office category owners | 0 |
| Unresolved registered owners | 0 |
| Existing adoption mappings | 4,938 (unchanged) |

These are ownership-registry totals; legacy references without a registry row
retain the separately defined legacy national behavior. The generic migration
still protects ambiguous records as unresolved on other installations; this
owner-authorized development correction is not automatic national publication
elsewhere.

Private retained evidence (gitignored):

- `storage/app/private/govstore-ownership-review-2026-10-26.csv` — exact 644-record bundle.
- `storage/app/private/govstore-ownership-normalization-2026-10-26.json` — completion record.
- Bundle SHA-256: `d33528b245fcb1a38f5979e883327802e3eea1bb13b1c913edf9f0b034751853`.
- Success audit reference: `1ede5496-4dd7-4408-9692-87af3a9f22a8`.
- Completed: 26 October 2026, 21:31:50 Asia/Dhaka.

Earlier read-only inspection found no native action-log entries for the 70 shared
national categories identified by F14. Absence of those entries is not proof that
no historical edit occurred; no speculative historical rollback was performed.

## Automated verification

Installed PHP 8.4.15, Xdebug disabled. GovStore feature tests use isolated SQLite
memory. The separate experimentation tests used a dedicated disposable MySQL
schema; the development database was never reset or broadly seeded.

| Check | Result |
| --- | --- |
| Complete `tests/Feature/GovStore` suite | **765 tests, 77,980 assertions; zero failures/errors/skips**, 4m55s, 340 MB |
| Required standalone `G1AuthorizationTest.php` | **30 tests, 1,756 assertions passed** |
| New `PermissionRemediationTest.php` | **20 tests, 108 assertions passed**, also included in the complete suite |
| Focused experimentation lifecycle suite | **4 tests, 393 assertions passed** in isolated MySQL |
| Changed/new PHP source syntax | **79 files passed** |
| Changed/new Blade compilation and compiled PHP syntax | **14 templates passed** |
| Diff whitespace | Passed |
| Refreshed route inventory | **332 routes, 160 mutation routes, 69 registered abilities**; includes eight native reference update/delete routes |

The regression coverage includes approved positive paths as well as direct denied
requests, foreign IDs, role combinations and expiry, active membership, native
admin versus superuser, shared national ownership, exact review/tampering/replay,
scoped creation, Finance ownership and metadata operation completion.

The focused MySQL cases cover schema admission, quick populate/verify/rerun/wipe
while preserving unrelated records, dependent/stale-preview refusal, and actor
revalidation/context restoration. An earlier all-profile experimentation run was
stopped during the optional large-volume scenario; it is **not** recorded as a
complete package/stress pass. The owned temporary schemas and marker were removed.
No MySQL concurrency result is claimed.

Reproduction commands:

```text
php -d xdebug.mode=off vendor/bin/phpunit tests/Feature/GovStore/G1AuthorizationTest.php
php -d xdebug.mode=off -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore
```

The experimentation suite requires the separately prepared isolated database
described by its test setup. Do not recreate its marker pointing to development.
The root `phpunit.xml` was not changed.

## Live page verification

Used the existing authorized local administrator browser session. These are page
checks, not completed employee/approver/storekeeper workflow journeys:

- My access showed the effective enforcement status and translated role labels.
- Ownership administration rendered the protected review page and, after the
  owner-directed correction, showed national 725 / company 58 / review needed 0.
- Scoped reference creation rendered named category/manufacturer choices and
  labels in English and Bangla. Language was restored to English; no reference
  was created by this page check. No console errors were observed on that form.

Evidence: [ownership page](govstore-permission-ownership-2026-10-26.jpg) and
[reference form](govstore-permission-reference-form-2026-10-26.jpg).
No passwords, role assignments or real user grants were changed for these checks.

## Remaining verification and retained state

Full cross-role live acceptance, the required real-world observation period,
production rollout, CI and accessibility compliance are not established by these
local tests. They remain separate acceptance work. No individual ownership
decision is pending for the 644 development categories.

The two migration tables, explicit category ownership, national normalization and
audit evidence are retained intentionally. Existing users, responsibilities,
temporary grants, adoption mappings, inventory and audit records are preserved.
Task-created database clones, marker and temporary test-result/helper files were
removed; restricted application/test logs were not indiscriminately deleted.
