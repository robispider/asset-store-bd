# ICT jurisdiction inventory boundary correction

Date: **7 October 2026 (Asia/Dhaka)**. Applies to the working tree, following the user's report that the tenant-scope update exposed jurisdiction offices' inventory to ICT officers.

## Implemented

The prior implementation reused `allowedLocationIds` for both office/user support and inventory reads. The `ict_operations` profile also included native stock-view and inventory-report permissions. Together these exposed inventory beyond the intended support role. The earlier geographic stock-read test encoded the wrong policy and has been replaced with a stricter regression invariant.

ICT jurisdiction is **support-only**: office provisioning/onboarding and user setup remain available, while asset, consumable, accessory, component, license and inventory-report permissions are removed from the ICT profile. Separate `allowedInventoryLocationIds` / `allowedInventoryCompanyIds` prevent office/user support scope from becoming stock scope. Model/bulk mutation boundaries also reject the empty inventory bounds.

The three Store Operations document abilities declare their inventory boundary. `GovAccess` enforces that object boundary in shadow mode too, covering direct stock register, Kardex, document, print, attachment and product requests through the existing named middleware and document policy. Ordinary actors with a valid inventory office retain the existing role shadow behavior.

An independently assigned office inventory role remains authorized only for its working office, while the ICT support jurisdiction stays available for offices/users. A separately assigned company administrator retains the existing company role bounds. Native licenses retain their existing company ownership because their schema has no office column. No wider access is derived from an ICT jurisdiction or an alias using the `ict_operations` profile.

## Tests retained for future changes

[TenantScopeTest](../../tests/Feature/GovStore/TenantScopeTest.php) now includes durable checks for:

- ICT jurisdiction offices/users remaining visible for support, with provisioning/user setup permissions retained, and out-of-jurisdiction offices/users excluded.
- Empty inventory lists and missing direct-ID results across all five stock models, including the actor's native office and another office in its jurisdiction.
- Actual native gates and list/detail controller denials using real user permissions in both shadow and enforce modes; inventory reports are denied too. Asset detail authorization and direct-ID model isolation are checked separately.
- Direct named middleware for stock/document view, draft and post returns 403 with a reference ID before its handler runs in both modes; document policy also denies the support actor's own-office stock document.
- Rejected bulk inventory mutation and unchanged row quantity for a support-only ICT actor.
- An independent storekeeper responsibility staying local, and loss of that responsibility removing inventory access on the next initialization.
- A similar role mapped to the ICT support profile gaining no inventory visibility from an active office membership.
- Support geography alone never expanding physical stock scope; existing company/global/selected-office and mutation regressions remain.

Installed PHP 8.4.15, isolated SQLite in memory:

```text
php -d xdebug.mode=off -d memory_limit=512M vendor/bin/phpunit tests/Feature/GovStore
```

Result: **123 tests, 3,120 assertions, passed**. This includes 24 tenant-scope tests, the required G1 authorization suite, and the existing Store Operations, Custom Requests and Committee regressions. Shared layout/hook compiled Blade checks run within this suite. Changed PHP syntax and whitespace checks also passed.

## Live read-only confirmation

Using an existing non-superuser ICT officer, actor ID **1976**, in the authorized local `snipeit` database, fresh context initialization in **shadow** mode retained six jurisdiction offices and all checked provisioning/onboarding/user setup permissions. Each stock model returned **zero visible records**, and native list/detail gates returned false. Inventory reports were denied. See [sanitized results](ict-jurisdiction-inventory-boundary-live-2026-10-07.json).

The same live actor's three Store Operations document abilities were also denied with boundary enforcement active despite shadow mode. This used read-only permission decisions, without creating access audit events or invoking live mutation handlers.

This was an application/database check, not a browser login or full provisioning exercise. No live roles, grants, business data, credentials, access modes or schema were changed. The temporary helper was removed. Automated mutations occurred only in isolated SQLite fixtures.

CI, production deployment and G1 observation/enforcement rollout remain unverified. Restart long-running workers and refresh deployment caches when deploying the corrected code/configuration.
