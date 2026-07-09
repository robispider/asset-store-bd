# Issues Fixed — Gov-Store Custom Requests

This document records every defect found during the workflow review of `packages/gov-store/custom-requests/`, and exactly how each was resolved. For each issue: **Cause** (the root problem in code), **Effect** (what breaks for users/data), **Fix** (what changed and where), **Verification** (how the fix was confirmed).

All fixes were confirmed with `php -l` (syntax), `php artisan optimize:clear` (boots cleanly), and `php artisan route:list --path=gov-requests` (routing intact). No core Snipe-IT files were touched — all changes are inside the custom package.

**Summary table**

| # | Issue | Severity | Status |
|---|---|---|---|
| 1 | "Test Subject" sidebar link points to a non-existent route | High (crash) | ✅ Fixed |
| 2 | Dead legacy single-item request flow (`ItemRequest` + event/listener) | Medium (dead code / runtime error if hit) | ✅ Removed |
| 3 | No separation of duties — approver can also fulfill | Medium (control weakness) | ✅ Fixed |
| 4 | Licenses half-wired (morph-mapped but unsupported) | Low (inconsistent state) | ✅ Fixed |
| 5 | Dead helper function `mountaineer_in_array()` | Low (dead code) | ✅ Removed |
| 6 | No stock re-check on issue → accessories/consumables can over-issue | Medium (data integrity) | ✅ Fixed |
| 7 | Asset checkout overwrites an existing holder (race) | High (data loss) | ✅ Fixed |
| 8 | Broken `approve`/`reject` routes + duplicate `admin` route | Medium (crash if invoked) | ✅ Fixed |
| — | Implicit-nullable deprecation in `forceClose()` | Low (PHP 8.4 deprecation) | ✅ Fixed |
| 9 | `php artisan migrate`: `OrganizationServiceProvider` class not found | High (blocks migrate/boot) | ✅ Fixed |
| 10 | `php artisan migrate`: FK to `gov_geo_areas` fails (migration ordering) | High (blocks migrate) | ✅ Fixed |

> **Issues 9–10** are setup/infra defects in the sibling packages (`organization`, `geo-areas`, `tenant-scope`), surfaced while running `php artisan migrate`. They are distinct from the `custom-requests` code-review findings (Issues 1–8) but recorded here for a single source of truth.

---

## Issue 1 — "Test Subject" sidebar link crashes admin pages

**Cause.** The zero-touch menu injector `resources/views/hooks/menu-injection.blade.php` rendered a third sidebar item, "Test Subject", whose URL was `{{ route('gov.requests.test-subject.index') }}`. No route with that name is defined in the package's `routes/web.php`.

**Effect.** Laravel's `route()` helper throws `Symfony\Component\Routing\Exception\RouteNotFoundException` when a name doesn't exist. Because this Blade is compiled on **every HTML page** an admin/superuser loads (the injector runs on the whole `web` middleware group), the exception fired during page render — potentially a **500 on every admin screen**, or a broken injected menu.

**Fix.** Removed the entire "Test Subject" `<li>` block and its `testSubjectActive` helper variable from `menu-injection.blade.php`. Only the two real items remain: **Gov Approvals** and **Fulfillment Queue**.

- `packages/gov-store/custom-requests/src/resources/views/hooks/menu-injection.blade.php`

**Verification.** No remaining reference to `test-subject` in the codebase; `route:list` confirms no such route was ever needed; admin pages render the two valid links.

---

## Issue 2 — Dead legacy single-item request flow removed

**Cause.** An earlier design (single-item requests) still lived alongside the current basket/Service-Request flow:
- `Models/ItemRequest.php` (table `custom_item_requests`)
- `Services/RequestService.php`
- `Events/ItemApproved.php` + `Listeners/ProcessItemCheckout.php` (registered in the service provider)
- Route `POST gov-requests/submit` → `GovRequestController::store()`

The table `custom_item_requests` is **dropped** at the top of the newer migration `2024_01_02_000000_create_service_requests_tables.php`. So the legacy code referenced a table that no longer exists after migration, and the current flow (Basket → Approval → Fulfillment) never dispatches `ItemApproved`.

**Effect.** Pure dead weight, plus a live trap: hitting `POST gov-requests/submit` would call `ItemRequest::create()` against a **missing table** → SQL error 500. Confusing for maintainers (two parallel systems, one non-functional).

**Fix.** Deleted the four dead classes and removed all wiring:
- Deleted `Services/RequestService.php`, `Models/ItemRequest.php`, `Events/ItemApproved.php`, `Listeners/ProcessItemCheckout.php`.
- `Providers/CustomRequestServiceProvider.php` — removed the `Event::listen(ItemApproved → ProcessItemCheckout)` registration and the now-unused imports (`Event`, `ItemApproved`, `ProcessItemCheckout`).
- `routes/web.php` — removed the `gov-requests/submit` route.
- `Http/Controllers/GovRequestController.php` — removed the `store()` method and the now-unused `RequestService`, `Log`, and `Request` imports. The controller now cleanly exposes only `catalog()` and `index()`.

> **Migration files were intentionally left in place.** Deleting migrations that may already be recorded as run is bad practice; the old table is harmlessly re-dropped by the newer migration on fresh installs.

**Verification.** Repo-wide search shows no remaining references to `ItemRequest`, `RequestService`, `ItemApproved`, `ProcessItemCheckout`, or `gov.requests.store`. `optimize:clear` boots with no "class not found" error from the removed listener.

---

## Issue 3 — Separation of duties enforced in fulfillment

**Cause.** `GovFulfillmentController::checkStorekeeperAccess()` used the **same** check as approval (`isSuperUser() || hasAccess('admin')`). Nothing stopped the exact admin who **approved** a request from also **issuing** its goods.

**Effect.** No separation of duties: a single non-super admin could both authorize and physically release inventory — a governance/audit weakness for a government-store workflow.

**Fix.** Added a guard in `Services/FulfillmentService.php`, applied to **both** `issueItems()` and `forceClose()`:

```php
protected function assertSeparationOfDuties(ServiceRequest $request, User $storekeeper): void
{
    if (
        $request->approved_by
        && (int) $request->approved_by === (int) $storekeeper->id
        && ! $storekeeper->isSuperUser()
    ) {
        throw new Exception("Separation of duties: the administrator who approved this request cannot also fulfill it. A different storekeeper must issue the items.");
    }
}
```

The approver-equals-fulfiller case is blocked for ordinary admins. **Super-users are exempt** so the top authority (and single-admin test setups) is never locked out. The thrown exception is caught by the controller and shown as an error banner.

- `packages/gov-store/custom-requests/src/Services/FulfillmentService.php`

**To relax this control:** remove the guard call, or grant the actor super-user rights.

**Verification.** `php -l` clean; logic path: same-user + non-super → exception before any checkout runs (inside/around the DB transaction), so no partial state.

---

## Issue 4 — Licenses no longer half-wired

**Cause.** The service provider's polymorphic `morphMap` aliased `'license' => License::class`, but there is **no `LicenseAdapter`**, `RequestableFactory` throws on `license`, and `CatalogService` never lists licenses. The type was reachable in the map but unusable everywhere else.

**Effect.** Inconsistent, misleading state: a `license` morph string could be stored/resolved yet could never actually be requested, approved, or fulfilled. A latent trap for future code.

**Fix.** Removed `'license'` from the `morphMap` in `Providers/CustomRequestServiceProvider.php`, leaving only the three fully-supported types (`asset`, `accessory`, `consumable`). Added a comment stating licenses are intentionally omitted until a `LicenseAdapter` and catalog source exist.

- `packages/gov-store/custom-requests/src/Providers/CustomRequestServiceProvider.php`

**Verification.** No `license` rows exist (catalog never produced them, factory always rejected them), so removal is safe; app boots clean.

> **Future work (not a bug):** to actually support licenses, add a `LicenseAdapter` (checking out `LicenseSeat`s), a catalog source in `CatalogService`, and re-add the morph-map entry.

---

## Issue 5 — Dead helper function removed

**Cause.** `Services/ApprovalService.php` ended with a stray global function `mountaineer_in_array($val, $arr) { return in_array($val, $arr); }`, never called anywhere.

**Effect.** Dead code; also risks a "cannot redeclare function" fatal if the file were ever loaded twice or the name collided.

**Fix.** Deleted the function; the file now ends cleanly at the class closing brace.

- `packages/gov-store/custom-requests/src/Services/ApprovalService.php`

**Verification.** `php -l` clean; repo search shows no callers.

---

## Issue 6 — Stock re-checked at issue time (accessories & consumables)

**Cause.** `AccessoryAdapter::checkout()` and `ConsumableAdapter::checkout()` attached the item to the user via the native pivot **without checking remaining stock**. Approval records `approved_qty` but reserves nothing; nothing revalidated availability at the moment of physical issue.

**Effect.** Two approved requests competing for the last units, or stock consumed elsewhere between approval and fulfillment, could be **over-issued** — driving `numRemaining()` negative and corrupting inventory counts.

**Fix.** Added an availability guard at the top of each adapter's `checkout()`; returns `false` when stock is insufficient. `FulfillmentService::issueItems()` already treats a `false` return as a hard failure and rolls back the whole transaction, so no partial issue occurs.

```php
// AccessoryAdapter / ConsumableAdapter
if ($this->accessory->numRemaining() < $quantity) {   // (consumable: $this->consumable)
    return false;
}
```

- `packages/gov-store/custom-requests/src/Adapters/AccessoryAdapter.php`
- `packages/gov-store/custom-requests/src/Adapters/ConsumableAdapter.php`

**Verification.** `php -l` clean; on insufficient stock the adapter returns `false` → service throws `"Snipe-IT failed to checkout the item…"` → transaction rolls back, quantities unchanged.

---

## Issue 7 — Asset checkout no longer overwrites an existing holder

**Cause.** `AssetAdapter::checkout()` unconditionally set `assigned_to = targetUser->id` and saved, without checking whether the asset was **already assigned** to someone. Assets can be approved while free and issued later; the holder could change in between.

**Effect.** **Silent data loss** — reassigning an already-checked-out asset would blow away the current holder with no log of the original checkout being reversed, breaking the "who has this?" source of truth.

**Fix.** Added a guard at the top of `AssetAdapter::checkout()` that returns `false` if `assigned_to` is already set, so fulfillment fails loudly and rolls back instead of overwriting.

```php
// AssetAdapter
if ($this->asset->assigned_to) {
    return false;
}
```

- `packages/gov-store/custom-requests/src/Adapters/AssetAdapter.php`

**Verification.** `php -l` clean; an already-assigned asset now yields `false` → `FulfillmentService` throws and rolls back; the original assignment is preserved.

---

## Issue 8 — Broken `approve`/`reject` routes and duplicate `admin` route removed

**Cause.** `routes/web.php` declared `POST gov-requests/admin/{request_id}/approve` and `.../reject` pointing at `GovApprovalController@approve` / `@reject` — **methods that do not exist** (the controller only has `index`, `show`, `process`). It also declared `GET gov-requests/admin` **twice**. These were leftovers from the pre-refactor approval design; the live UI uses `admin/{id}/process`.

**Effect.** Any POST to the approve/reject URLs would 500 with a "method does not exist" error; the duplicate route was redundant. Dead surface that could be hit by stale links or crawlers.

**Fix.** Rewrote `routes/web.php`: removed the broken `approve`/`reject` routes and the duplicate `admin` index; normalized all controller imports to proper `use` statements. Result: 14 clean, live routes (catalog, my-requests, basket ×5, admin index/show/process, fulfillment index/show/issue/close).

- `packages/gov-store/custom-requests/src/routes/web.php`

**Verification.** `php artisan route:list --path=gov-requests` shows 14 routes and **no** `approve`/`reject` entries.

---

## Minor — PHP 8.4 implicit-nullable deprecation

**Cause.** `FulfillmentService::forceClose(..., string $reason = null)` used an implicitly-nullable typed parameter, deprecated in PHP 8.4.

**Effect.** A deprecation notice emitted at load under PHP 8.4+.

**Fix.** Changed the signature to explicit nullable: `?string $reason = null`.

- `packages/gov-store/custom-requests/src/Services/FulfillmentService.php`

**Verification.** `php -l` no longer reports the deprecation for this file.

---

## Not changed (by design)

**UI injection via HTML rewriting.** `Http/Middleware/InjectGovStoreUi` string-splices the injected script before `</body>` on every HTML response. This is intentional "zero-touch" design so no core Snipe-IT Blade files are edited. It is robust for standard responses; left as-is. Flagged here only so maintainers know it is deliberate, not an oversight.

---

## Issue 9 — `OrganizationServiceProvider` class not found on migrate

**Cause.** `composer.json` declares PSR-4 autoload maps for all four Gov-Store packages:

```
"GovStore\\CustomRequests\\" => "packages/gov-store/custom-requests/src/",
"GovStore\\Organization\\"   => "packages/gov-store/organization/src/",
"GovStore\\GeoAreas\\"       => "packages/gov-store/geo-areas/src/",
"GovStore\\TenantScope\\"    => "packages/gov-store/tenant-scope/src/",
```

and `config/app.php` registers all four service providers. But the **generated autoloader was never regenerated** after the last three maps were added — `vendor/composer/autoload_psr4.php` contained only the `CustomRequests` prefix. Composer maps namespaces to directories at dump time, not at runtime, so the `GovStore\Organization\…`, `GeoAreas\…`, and `TenantScope\…` classes had no known location.

**Effect.** As soon as Laravel booted the provider list (which happens for **every** artisan command, including `migrate`), it tried to instantiate `GovStore\Organization\Providers\OrganizationServiceProvider` and the autoloader couldn't find it → fatal `Class "…OrganizationServiceProvider" not found`. The whole application — CLI and web — was blocked.

**Fix.** Regenerated the Composer autoloader:

```bash
composer dump-autoload
```

`vendor/composer/autoload_psr4.php` now maps all four prefixes (`CustomRequests`, `Organization`, `GeoAreas`, `TenantScope`). No source change was required — only the stale generated file.

**Verification.** Confirmed all four `GovStore\\…` entries are present in `autoload_psr4.php`; `composer dump-autoload` exited 0 and Laravel `package:discover` completed clean; `php artisan migrate` proceeded past provider boot into the actual migrations (surfacing Issue 10).

> **Prevention.** Run `composer dump-autoload` whenever a new PSR-4 path package is added under `packages/`, or add each package's own `composer.json` + path repository so Composer tracks it automatically.

---

## Issue 10 — Migration ordering: foreign key to `gov_geo_areas` before the table exists

**Cause.** Laravel runs migrations in **filename (timestamp) order**. Two migrations were mis-sequenced:

- `2024_01_04_000000_create_gov_organization_tables` (Organization package) creates `gov_location_profiles` and `gov_ict_jurisdictions`, each with a **foreign key referencing `gov_geo_areas(GeoAreaId)`**.
- `2024_01_05_000000_create_gov_geo_areas_table` (GeoAreas package) creates the `gov_geo_areas` table itself.

Because `…01_04` sorts **before** `…01_05`, the FK constraint was declared against a table that did not yet exist.

**Effect.** Migration aborted:

```
SQLSTATE[HY000]: General error: 1824 Failed to open the referenced table 'gov_geo_areas'
… alter table `gov_location_profiles` add constraint `gov_location_profiles_geo_area_id_foreign`
  foreign key (`geo_area_id`) references `gov_geo_areas` (`GeoAreaId`) …
```

`migrate` stopped mid-run, leaving a partially-created `gov_location_profiles` table and the GeoAreas/TenantScope migrations unrun.

**Fix.** Renamed the GeoAreas migration so it sorts **before** the Organization migration, keeping the `create_gov_geo_areas_table` suffix (so Laravel still resolves the `CreateGovGeoAreasTable` class) and keeping it in the same directory (so its `__DIR__ . '/../data/geo_areas.csv'` importer path stays valid):

```
2024_01_05_000000_create_gov_geo_areas_table.php  →  2024_01_03_500000_create_gov_geo_areas_table.php
```

New effective order: `gov_geo_areas` (03_500000) → `gov_organization_tables` (04) → `gov_tenant_scope_tables` (06). The Organization migration's `up()` begins with `Schema::dropIfExists()` on its own tables, so it cleanly recreated the partially-built `gov_location_profiles` on retry — no manual cleanup needed.

- Renamed: `packages/gov-store/geo-areas/src/database/migrations/2024_01_03_500000_create_gov_geo_areas_table.php`

**Verification.** `php artisan migrate` ran all three to completion:

```
2024_01_03_500000_create_gov_geo_areas_table ........ DONE   (CSV dataset imported)
2024_01_04_000000_create_gov_organization_tables .... DONE
2024_01_06_000000_create_gov_tenant_scope_tables .... DONE
```

> **Prevention.** A reference table that other packages FK against (like `gov_geo_areas`) must carry an **earlier** migration timestamp than any migration that depends on it. When packages are developed independently, coordinate timestamps or make FKs deferred.

---

## Files changed at a glance

| File | Change |
|---|---|
| `resources/views/hooks/menu-injection.blade.php` | Removed broken "Test Subject" link (Issue 1) |
| `Providers/CustomRequestServiceProvider.php` | Removed dead event listener + imports (Issue 2); dropped `license` morph-map (Issue 4) |
| `routes/web.php` | Removed `/submit` (Issue 2) + broken `approve`/`reject` + duplicate `admin` (Issue 8); tidy imports |
| `Http/Controllers/GovRequestController.php` | Removed `store()` + unused imports (Issue 2) |
| `Services/ApprovalService.php` | Removed dead `mountaineer_in_array()` (Issue 5) |
| `Services/FulfillmentService.php` | Added separation-of-duties guard (Issue 3); explicit nullable param (Minor) |
| `Adapters/AssetAdapter.php` | Guard against overwriting an assigned asset (Issue 7) |
| `Adapters/AccessoryAdapter.php` | Stock re-check before issue (Issue 6) |
| `Adapters/ConsumableAdapter.php` | Stock re-check before issue (Issue 6) |
| **Deleted** | `Services/RequestService.php`, `Models/ItemRequest.php`, `Events/ItemApproved.php`, `Listeners/ProcessItemCheckout.php` (Issue 2) |
