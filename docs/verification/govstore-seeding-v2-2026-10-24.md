# GovStore sample data v2 execution

24 October 2026, Asia/Dhaka.

## Implementation

Implemented the [seeding gap mitigation plan](../gap-analysis/govstore-sample-data-seeding-gap-analysis-2026-10-23.md)
in the experimentation package and its configuration, with ignore rules for newly
generated private programme files. Other GovStore packages, core seeders,
migrations, access mode and core PHPUnit configuration are unchanged.

- New runs use `bd-government-v2` with saved module versions, request recipe targets and compact display
  aliases. Meaningful names precede the marker; full UUIDs remain internal.
  Existing v1 datasets are not renamed or silently upgraded.
- Admission and queued execution check current schema and the BBS dataset.
  Every installed GovStore table must have an ownership/preservation disposition;
  unknown tables block population rather than being silently ignored.
- Scoped actions use current `TenantExecution` identity/membership/ability checks,
  preserve the shared context object, and restore authentication/context on failure.
- Each operational office opens with nonzero consumables, accessories, components
  and one existing desktop before ordinary receipts/issues. Opening movements do
  not change native stock again.
- All profiles execute named request scenarios for asset models, consumables and
  accessories: pending, primary, two-stage, automatic, rejected, reduced,
  threshold-escalated, partial/final issue, acknowledgement, withdrawal and
  closing a remainder. Bulk returns and substitutions and mixed-policy submission
  batches are included. These add 42 requests to each profile's base target.
- Stock actions include validated supplier-attributed purchase receipts, positive
  and negative adjustments, paired inter-office transfer and draft takeover with
  distinct original drafter/actual poster and a notice.
- Additional fixtures cover office hierarchy/types/levels, actual tracking
  activation and private programme PDFs, typed company/office/product metadata,
  scoped appearance, ministry-owned committee types/purposes, draft/active/
  suspended/dissolved committees, internal/external appointments, private fictional
  orders and declarations, unfinished order intake, accepted/rejected/cancelled
  handshakes, completed role assignments, onboarding decisions and access reviews.
- Ownership and cleanup include the newer business/metadata/theme records and
  private evidence. A signed private file manifest survives database rollback.
  Shared masters, sequence counters, security/replay evidence and committee hash
  history are retained. Existing permanent-tag deletion blocks are preserved.
- Read-only CLI `audit` evaluates current facts without updating the dataset,
  ownership or saved historical verification. Old datasets show a bilingual stale
  version notice. Counts include friendly labels for newer package records.
  Verification registers only newly discovered dependencies, avoiding redundant
  per-record ownership writes on large runs.
- Optional `rehearse-tags` uses existing installation/office gates and compatible
  reviewed Finance master data, posts one Govt. Tag and one standard tag, disables
  further issuance afterward, and is idempotent. Ordinary population keeps
  issuance disabled. No national Finance codes or installation gates are changed.
- The dedicated worker has a configurable memory budget, default 2 GB for Large
  fixtures; job/queue isolation and the existing timeout/recovery controls remain.

## Verification

All automated database mutations use guarded separate MySQL schemas or the
GovStore suites' isolated SQLite setup. The existing development database is not
used as automated test setup. Passwords, tokens and account exports are excluded
from this evidence.

| Check | Result |
| --- | --- |
| Focused G1 authorization suite | Passed: 29 tests, 1,707 assertions |
| All GovStore feature suites | Passed: 717 tests, 69,480 assertions; peak 338 MB |
| Geo JavaScript engine/bundle tests | Passed: 20 tests |
| `geo-view:doctor` | Passed: contracts, published boundaries, 3 sources and 3 presets |
| Government and Large action/opening/reconciliation/wipe exercise | Passed: 2 tests, 16 assertions; 65 minutes combined, peak 852 MB |
| Final expanded experiment suite | Passed: 17 tests, 510 assertions; peak 598 MB |
| Local UI | Historical notice rendered in Bengali; fresh dataset shows Ready and all 21 checks in English |
| Fresh local read-only audit | Passed all 21 checks; saved report and ownership unchanged |
| Fictional account credentials | All 480 hashes verified against the requested fixture password; zero mismatches |
| Blade syntax / bilingual keys / PHP syntax / whitespace checks | Passed |

The expanded tests cover nonzero openings, real workflow outcomes and attribution,
reservation release, current request history, return posting, running balances,
private cleanup, audit retention, duplicate prevention, read-only revalidation,
corrupt outcome rejection, live-role rechecks, context restoration, unknown-schema
admission and signed-file tampering. Tag issuance has a separate disposable schema
because the normal application must retain issued-tag evidence permanently.

## Local rollout and retained data

The original ready v1 dataset is preserved. Initial read-only preflight confirmed the local installation
as `snipeit`, environment `local`, with experimentation enabled, access mode
`shadow`, no active experiment operation and no queued experiment job.
The current Government dataset `test4` (`D6`), ID
`01a16a56-5fc8-71bd-8121-eeca008a8b4a`, was already queued through the local UI.
That pending v2 run was reused rather than creating a duplicate; local population
and final audit both completed successfully. Open
[test4](http://snipeit.local/gov-store/admin/experiments/01a16a56-5fc8-71bd-8121-eeca008a8b4a)
to inspect the dataset and download its current fictional account list.

| Fresh dataset | Verified count |
| --- | ---: |
| Offices / operational offices with posted openings | 48 / 44 |
| Fictional users | 480 |
| Assets | 2,400 |
| Consumable / accessory / component stock records | 528 / 352 / 176 |
| Requests, including the named action cases | 306 |
| Documents | 201 |
| Committees | 4 |
| Tracking codes / supporting documents | 24 / 24 |
| Owned records including dependent history | 42,169 |

Names were checked locally, including `A4 Paper / এ৪ কাগজ · D6-01` and
`Desktop Computers / ডেস্কটপ কম্পিউটার Model · D6-01`.

The original v1 run's saved report hash, update timestamp and ownership counts
across all 63 owned tables match the pre-population snapshot. Both datasets remain
Ready. The dedicated single-operation worker exited; there are zero pending
experiment jobs and zero active operations. Access mode remains `shadow`.
One historical failed experiment job dated 3 October remains intact.

All 48 automatic starter-catalogue attempts retained failure records because the
configured named collections have no active library entries. This existing setup
prerequisite was confirmed by read-only inspection; its failures were not erased
or reported as successful adoption. The experimentation fixtures provide their
own catalogue and inventory. Repair/review of the installation's starter library
and its normal retry process remain open.

The guarded isolated schema and test helper files have been removed after the
suites completed. The permanent-tag test's separate schema is dropped by its test
cleanup. The fresh local dataset and private supporting files are intentionally
retained; newly generated tracking uploads are ignored by Git. Existing tracked
files were not removed. No permanent managed-tag rehearsal ran on this dataset.

## Supported limits

- Laptop receipt CPU materialization and a model-level serialized-issue ledger
  integration remain current consumer limitations. Native serialized checkouts and
  unit history are verified; fictitious ledger movements are not manufactured.
- Component/license requests are unsupported by the current request type contract;
  their inventory/master fixtures remain included.
- Managed tags are an explicit permanent rehearsal, not an automatic side effect
  of ordinary population. Existing gates and wipe restrictions remain authoritative.
- National master import/publication history, replay consumption, caches, mutexes
  and technical failure queues arise only from their real operations. They are not
  filled with invented successes to obtain nonzero table counts.
- The committee registry is populated; the unimplemented receipt inspection
  consumer is not advertised. National illustrative templates stay inactive.
- Starter catalogue jobs depend on the installation's configured template library.
  Durable setup failures remain available to its normal retry/setup process.
- Local evidence does not establish remote CI, production rollout, legal approval,
  WCAG compliance or completion of a real shadow-observation period.

Operational usage and the current fixture contract are in the
[experimentation README](../../packages/gov-store/experimentation/README.md).
