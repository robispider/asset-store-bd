# Tenant-scope implementation and verification

Date: **7 October 2026 (Asia/Dhaka)**. Evidence applies to the local working tree and `http://snipeit.local/`. This records local implementation closure for TS-1 through TS-7 in [the package gap analysis](../gap-analysis/gov-store-package-gap-analysis.md). It does not establish production closure or completion of the [G1 rollout gates](../gap-analysis/g1-unprotected-actions-analysis.md).

The same-day [ICT boundary correction](ict-jurisdiction-inventory-boundary-2026-10-07.md) supersedes the initial geographic inventory-read behavior and updates the regression evidence to 123 tests / 3,120 assertions. ICT jurisdiction grants support access to offices/users, not their inventory.

## Implemented

| Gap | Delivered behavior |
| --- | --- |
| TS-1 | Validated superuser global overview reads all stock; explicit office selection narrows reads. Global operational inventory mutations are refused. |
| TS-2 | Physical stock reads honor inventory-specific offices and applicable companies, separately from support jurisdictions. Pure ICT officers cannot list or view inventory; an independent office inventory role remains confined to its working office. Eloquent model and bulk mutations retain working-office ownership, including original persisted values, quiet saves, counters, force deletion and caller OR predicates. Direct bulk inserts/upserts are refused in active actor context; normal validated model creation remains available. |
| TS-3 | Tenant-owned organization/membership resolver interfaces are bound by their owning packages. Context resets preserve the shared object. Membership selection requires a positive integer, current-user ownership, active membership and a nondeleted office; stale selections cannot fall back to native office access. |
| TS-4 | Permission union is idempotent and preserves local role attribution. Company overlays are injected once. Native `admin` permission does not imply superuser or company-wide mutation access. |
| TS-5 | Scoped background execution rechecks live actor, target, membership and named ability, including in shadow mode. All four current GovStore jobs declare scoped execution or explicit global maintenance. Bus/worker lifecycle clears stale context and restores synchronous callers. Commands have an explicit reviewed execution registry; ledger opening additionally validates its actor and office through the scoped runner. |
| TS-6 | Schema columns are cached per connection/database/table; authorization is re-resolved. Menu rendering uses one fresh role snapshot per tree. No persistent permission cache was introduced. |
| TS-7 | Shared layout partials render the sidebar, My access and rollout banner. Response-body rewriting is removed. Eight tenant-administration routes declare named superuser abilities; mutations use national review, reason, typed confirmation, expiry, state fingerprint and replay protection. Failures retain safe messages/reference IDs without protected-response debug output. |

Primary code is in [tenant-scope](../../packages/gov-store/tenant-scope/src), with resolver adapters in [organization](../../packages/gov-store/organization/src/Services/TenantOrganizationResolver.php) and [office-membership](../../packages/gov-store/office-membership/src/Services/TenantMembershipResolver.php), inventory builder hooks on the five native stock models, shared exception handling and layout integration. Existing narrower native/package authorization remains applicable.

Native licenses have a company owner and no physical-office column. Independently authorized inventory users retain company-bound reads/mutations; ICT support alone grants no license or other stock visibility. This implementation does not invent office ownership for licenses. Raw database queries remain trusted server callers' responsibility and require explicit boundaries; Eloquent safeguards are not database row-level security.

## Verified locally

Automated tests use **isolated SQLite in memory**, never the existing development database. Final command, using installed PHP 8.4.15:

```text
php -d xdebug.mode=off -d memory_limit=512M vendor/bin/phpunit tests/Feature/GovStore
```

Initial result before the ICT correction: **120 tests, 2,999 assertions, passed**, elapsed 29.798 seconds. This included 21 tenant-scope tests, the required G1 authorization suite, and Store Operations, Custom Requests and Committee regressions. The focused G1 run also passed independently: 29 tests, 1,003 assertions. Current regression evidence is in the linked ICT correction record.

Coverage in [TenantScopeTest](../../tests/Feature/GovStore/TenantScopeTest.php) verifies positive own-office creation alongside rejected cross-office mutations, global/selected-office reads, company/geography boundaries, malformed/foreign/revoked memberships, native-admin denial, permission union, grant freshness, delayed actor revalidation, actual synchronous bus execution, context restoration, unknown command denial, national review fingerprint/replay behavior, safe failure responses, escaped menu rendering and compiled Blade syntax. G1 route coverage now includes all eight tenant-administration routes.

Query checks verify initialization at no more than 12 queries, a 30-item menu at no more than five role queries, and five warm stock counts at exactly five SELECTs. Changed PHP syntax checks and whitespace checks passed; shared layout and tenant hook Blade compilation/lint passed. These checks do not establish WCAG compliance.

Read-only live database/context checks are recorded in [sanitized live results](tenant-scope-live-2026-10-07.json):

| Stock | Global / raw nondeleted total | Selected office 4 |
| --- | ---: | ---: |
| Assets | 2,433 | 1 |
| Consumables | 533 | 1 |
| Accessories | 352 | 0 |
| Components | 176 | 0 |
| Licenses | 48 | 0 |

All global counts matched the raw nondeleted database totals. Selecting office 4 changed `isGlobal` to false and returned the same bounded counts as the live storekeeper context (company 412). Its consumable row had quantity 50, remaining 46.

Live browser verification used the existing authenticated storekeeper session: the rendered GovStore menu opened My access, the consumables list displayed its one office row, and opening tenant administration returned a bilingual denial with a reference ID. No browser warning/error was observed during those checks. The denied admin visit retained its normal access audit record.

No live inventory, membership, grant, business fixture, access-mode or schema changes were made. Office access mode remained **shadow**. The temporary read-only verification helper was removed. No credentials, cookies or environment dumps are included in this evidence.

## Remaining deployment and rollout work

- Deploy the resolver bindings, execution contracts, layout and native model changes together. Refresh deployment caches and restart long-running queue workers so they load the new execution boundary. Clear schema knowledge/restart workers after future schema changes.
- CI and production behavior remain unverified. No live ledger opening, catalog adoption or mutation exercise was performed for this tenant-scope task.
- Complete the existing G1 assignment review, real-world shadow observation, rollback and enforcement approval requirements before changing office enforcement mode.
- Classification provisioning event/payload gaps (CL-2/CL-5), other package workflow cycles and the remaining package gap inventory are still open under their respective records.

The seven tenant-scope rows are mitigated as implementation findings. Deployment, whole-workflow validation and G1 rollout remain separate obligations.
