# Instructions for agents working in this repository

## Read before security-sensitive work

Read [GovStore security rules and lessons](docs/security/govstore-agent-security.md)
before changing authorization, routes, tenant context, roles, documents, catalog
administration, uploads/downloads, error handling, or their UI and tests. Read the
current implementation and any deeper directory instructions as well.

The [G1 execution record](docs/gap-analysis/g1-unprotected-actions-analysis.md)
separates completed implementation from pending rollout work. Historical risk
descriptions and test counts are dated evidence, not the current access policy.

## Required security practices

- Authorize on the server before returning protected data or changing it. A hidden
  menu, disabled button, `auth` middleware, or comment saying "Superadmin Only"
  does not authorize an action.
- On G1 surfaces, use a declared `gov.can:<ability>` and the existing `GovAccess`
  decision. Register only named GovStore gates. Preserve other packages' narrower
  authorization; do not replace it with a blanket gate or native-admin bypass.
- Check the object's working office/company, ownership where applicable, URL type,
  and allowed state. Validate every supplied object ID, including IDs in POST bodies.
  Tenant boundaries and state checks apply in shadow mode too.
- Preserve the shared `TenantContext` object when resetting it. Memberships must
  belong to the current user and be active. Native admin access is not superuser
  access. Ordinary office users must not receive company-wide mutation scope.
- Lock document rows and recheck state inside the mutation transaction. Enforce
  posting requirements on the server. Preserve original creator/drafter attribution
  when taking over a draft, and record the actual poster separately.
- Keep national changes behind review, reason, typed confirmation, expiry and replay
  protection. Catalog execution requires review of the exact supported bundle.
- Keep evidence files private and deliver them through an authorized document route.
  New upload protection does not secure old files already in public storage.
- Return safe failures with reference IDs. Keep SQL, traces, paths and snapshots in
  restricted logs. Do not re-enable the debug toolbar on protected GovStore responses.
- Do not remove protection or weaken tests to make a failing test or live exercise pass.
  Diagnose the authorization, scope, state, response and rollback behavior instead.

## Verification and local development

For live UI and database verification of **any package or feature**, read
[Local live testing and database guide](docs/testing/local-live-testing.md).
It covers the authorized local host, runtime tools, current fixture discovery,
role and geography checks, read-only database inspection, isolated tests and cleanup.
Recheck live state; its dated examples are not permanent accounts or totals.

For changes to these protections, run the focused suite using installed PHP:

```text
php vendor/bin/phpunit tests/Feature/GovStore/G1AuthorizationTest.php
```

This suite uses isolated SQLite in memory. Add meaningful coverage for the changed
invariant and update route coverage when a new protected surface is introduced.
Also verify compiled Blade syntax and relevant live behavior when changing the UI.

Use an isolated database for automated tests. Never run `migrate:fresh`, `db:wipe`,
`migrate:refresh`, or broad seeding against the existing development database as
test setup. Apply only the targeted migrations needed for an authorized dev change.

Credentials supplied in a conversation belong to that authorized environment and
task. Do not put passwords, tokens, cookies or environment dumps in documentation,
commits, screenshots or test evidence. Restore temporary access-mode settings,
expire test grants and remove task helper files after live testing. Preserve audit
records and report retained test data and existing data problems.

Report separately what was implemented, what was verified and what remains open.
Local tests do not establish CI success, WCAG compliance, production readiness or
completion of the required real-world shadow observation period.
