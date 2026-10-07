# NIAR committee registry

NIAR is the **Inventory and Asset Register**. This package records official
committees for goods receiving and inspection, technical asset inspection,
stock verification, survey/condemnation and disposal. It does not evaluate
tenders. `TOC`, `TEC` and `TSC` are excluded from the seed catalogue and rejected
by catalogue validation.

See [DESIGN.md](DESIGN.md) for the architecture and future consumer integration
design, and [the implementation/verification record](../../../docs/verification/committee-implementation-2026-10-05.md)
for the delivered scope, live evidence and remaining work.

## Delivered registry

- UUID committees, office ownership, fiscal-year numbers, dated terms and version
  lineages; incomplete drafts can be saved but cannot be activated.
- Configurable national/ministry types and purpose bindings. National changes
  require an exact proposal review, reason, `CHANGE`, actor-bound expiry and a
  single-use token. National catalogue mutexes serialize concurrent changes.
- Named, ex officio and external seats; appointment snapshots, replacements,
  releases, immutable correction chains, personal declarations and an atomic
  transfer workbench. Committee identity lookup never grants office membership.
- Constitution, amendment, reconstitution, extension, suspension, resumption,
  dissolution and corrigendum orders. Files are MIME checked, SHA-256 verified
  and served through authorized private routes.
- Purpose resolution with ministry override and optional ancestor fallback;
  historical queries, health findings, ambiguous results and roster snapshots.
- Transactional hash-chained history, after-commit events and native activity
  summaries attached to the owning office. The chained ledger is authoritative.
- Bangla/English workspaces, private evidence links, search, My committees,
  printable rosters and draft order text.
- Daily expiry and health commands, deduplicated 30/7-day expiry events, and an
  office-membership clearance rule (advisory by default).

The five national seed templates (`GRIC`, `TIC`, `SVC`, `BOS`, `DSP`) are
**inactive illustrative templates**. Their figures do not establish legal
composition. An authorized inventory/asset authority must review them before
enabling a template, bind its purposes and constitute an actual committee with
the applicable signed office order.

## Local installation and checks

The root autoload mapping and provider are registered in this repository.
Install the additive package migrations on an explicitly authorized database:

```text
php artisan migrate --path=packages/gov-store/committee/src/Database/migrations
php artisan db:seed --class="GovStore\Committee\Database\seeders\CommitteeSeeder"
```

Do not reset or broadly seed the existing development database. Follow
[the local testing guide](../../../docs/testing/local-live-testing.md).
This checkout uses the installed PHP 8.4 runtime documented there.

The browser entry is `/gov-store/committees`. Existing office administrators
manage their owning office's committees; the existing access-request workflow
can grant the `committee_registrar` responsibility. Being a committee member
does not itself grant registrar, document, inventory or office access.

The standard Mix build includes `resources/assets/js/committee.js`. When Node
dependencies are unavailable, the standalone dependency-free entry can be
built and versioned with:

```text
node scripts/build-committee-assets.cjs
```

Focused automated checks use an isolated SQLite database:

```text
php vendor/bin/phpunit tests/Feature/GovStore/CommitteeTest.php tests/Feature/GovStore/G1AuthorizationTest.php
php artisan view:cache
php artisan committee:verify-ledger
```

The provider schedules `committee:expire` at 00:05 and `committee:health` at
01:00 in `Asia/Dhaka`. The application's scheduler must be running. Private
evidence lives under `storage/app/private/committee`; include it in controlled
backups along with the database.

## Consumer contract

Consumers use `Contracts`, `DTOs` and `Events`. The package never imports a
consumer namespace or reads consumer tables. Contract calls are trusted
in-process calls: the consumer must authorize its own operation and supply a
verified scope before calling them. HTTP endpoints additionally enforce the
committee office/company boundary, even when GovAccess runs in shadow mode.

```php
use GovStore\Committee\Contracts\{CommitteeResolver, RosterSnapshotProvider};
use GovStore\Committee\DTOs\ScopeRef;
use GovStore\Committee\Enums\ResolutionStatus;

$resolution = app(CommitteeResolver::class)->resolve(
    'storeops.receipt.inspection',
    new ScopeRef('office', (string) $authorizedOfficeId),
    $inspectionDate,
);

if ($resolution->status === ResolutionStatus::FOUND) {
    $snapshot = app(RosterSnapshotProvider::class)->snapshot(
        $resolution->committee->id,
        $inspectionDate,
    );
    // The consumer stores $snapshot->roster and $snapshot->fingerprint.
}
```

Handle `NOT_FOUND`, `INOPERABLE` and `AMBIGUOUS` explicitly. A fingerprint proves
integrity of the supplied roster; the consuming record's own authorization and
provenance still matter. Subsequent replacement, release or account linking
does not rewrite earlier appointment evidence. A corrigendum can legitimately
make verification return `CHANGED_SINCE`.

Store-operations declares five inventory/asset purposes through its own
integration registration. It does not yet select inspection panels or alter
receipt posting. Optional consumers can register scope resolvers and tabs.
Bind a `SnapshotUsageReporter` and tag it `committee.snapshot_usage_reporters`
to expose reliance counts in backdated-change review. The default
`PostHolderDirectory` returns no suggestion; an HR integration can replace it.

## Release boundaries

Consumer inspection workflows in design §15, tracking integrations, notification
delivery, CSV exports, full dashboard analytics and measured performance/cache
targets remain future work. Current dissolution impact is a conservative list
of bound purposes and coverage, rather than an exact simulation of fallback
loss. Transfer workbench currently handles replacements, while leaving a seat
vacant uses its separate release action. Catalogue duty additions and complex
policy changes are supported by the API; the UI edits existing duty findings.
Advanced corrigendum, external-account linking and declaration-on-behalf
operations currently use the authorized API; their dedicated UI forms are
future workspace enhancements.

Local evidence does not establish CI success, WCAG AA conformance, legal policy
approval, production readiness or completion of a real ministry observation
period.
