# GovStore Store Operations Diagnostic Report

**Generated:** 2026-07-15  
**Target Issue:** 500 Internal Server Error on `/gov-store/operations/kardex/consumable/{id}?ajax=1`  
**Module:** `packages/gov-store/store-operations/`

---

## Executive Summary

The 500 error is caused by **two distinct bugs**:

1. **CRITICAL — Missing View File:** The controller references `storeops::register.kardex-table`, but this Blade view file does not exist on disk. This causes an immediate `InvalidArgumentException: View [register.kardex-table] not found.`
2. **LATENT — Directory Casing Mismatch:** The views directory is named `Resources/` (capital R) on disk, but Laravel's `loadViewsFrom()` references `resources/` (lowercase r). This works on Windows (case-insensitive filesystem) but will **fail on Linux production servers** (case-sensitive).

---

## 1. SYSTEM ERROR LOG

**Source:** `storage/logs/laravel.log`  
**Timestamps:** 2026-07-14 18:10:08 and 2026-07-14 18:19:59 (repeated)

### Full Stack Trace (Latest — 18:19:59)

```
[2026-07-14 18:19:59] local.ERROR: InvalidArgumentException: View [register.kardex-table] not found. 
in D:\git repo\asset-store-bd\vendor\laravel\framework\src\Illuminate\View\FileViewFinder.php:138

Stack trace:
#0 D:\git repo\asset-store-bd\vendor\laravel\framework\src\Illuminate\View\FileViewFinder.php(91): 
   Illuminate\View\FileViewFinder->findInPaths('register.kardex...', Array)
#1 D:\git repo\asset-store-bd\vendor\laravel\framework\src\Illuminate\View\FileViewFinder.php(75): 
   Illuminate\View\FileViewFinder->findNamespacedView('storeops::regis...')
#2 D:\git repo\asset-store-bd\vendor\laravel\framework\src\Illuminate\View\Factory.php(150): 
   Illuminate\View\FileViewFinder->find('storeops::regis...')
#3 D:\git repo\asset-store-bd\vendor\laravel\framework\src\Illuminate\Foundation\helpers.php(1100): 
   Illuminate\View\Factory->make('storeops::regis...', Array, Array)
#4 D:\git repo\asset-store-bd\packages\gov-store\store-operations\src\Http\Controllers\StockRegisterController.php(69): 
   view('storeops::regis...', Array)
#5 D:\git repo\asset-store-bd\vendor\laravel\framework\src\Illuminate\Routing\Controller.php(54): 
   GovStore\StoreOperations\Http\Controllers\StockRegisterController->kardex(Object(Illuminate\Http\Request), 'consumable', '3')
#6 D:\git repo\asset-store-bd\vendor\laravel\framework\src\Illuminate\Routing\ControllerDispatcher.php(43): 
   Illuminate\Routing\Controller->callAction('kardex', Array)
#7 D:\git repo\asset-store-bd\vendor\laravel\framework\src\Illuminate\Routing\Route.php(265): 
   Illuminate\Routing\Route->runController()
... [pipeline continues through 20+ middleware layers] ...
```

**Root Cause:** Line 69 of `StockRegisterController.php` calls `view('storeops::register.kardex-table', ...)` but no file exists at the expected path.

---

## 2. ABSOLUTE DIRECTORY CASING CHECK

### On-Disk Directory Structure

```
packages/gov-store/store-operations/src/
├── Adapters/
├── Config/
├── Contracts/
├── Database/
├── Events/
├── Factories/
├── Http/
├── Listeners/
├── Models/
├── Policies/
├── Providers/
├── Resources/          ← NOTE: Capital 'R'
│   └── views/
│       ├── hooks/
│       │   └── menu-injection.blade.php
│       ├── issues/
│       │   └── create.blade.php
│       ├── receipts/
│       │   └── create.blade.php
│       └── register/
│           ├── index.blade.php
│           └── kardex.blade.php
├── Routes/
├── Services/
└── UI/
```

### Critical Finding: Casing Mismatch

| What Laravel Expects (code) | What Exists on Disk (Windows) | What Linux Would See |
|---|---|---|
| `resources/views` (lowercase) | `Resources/views` (capital R) | **FAILS** — case-sensitive FS |

**Service Provider Declaration (line 47):**
```php
$this->loadViewsFrom(__DIR__.'/../resources/views', 'storeops');
```

**Actual directory on disk:** `Resources/` (capital R)

**Impact:** Works on Windows (case-insensitive NTFS). Will **fail on Linux production** with "View not found" errors for ALL storeops views.

### Files in `register/` Sub-folder

| File | Status |
|---|---|
| `index.blade.php` | EXISTS |
| `kardex.blade.php` | EXISTS |
| `kardex-table.blade.php` | **MISSING** ← CAUSES THE 500 ERROR |

---

## 3. ACTIVE ROUTE VERIFICATION

### Routes Containing `kardex` or `storeops.register`

All routes are defined in `packages/gov-store/store-operations/src/Routes/web.php`:

| URI | Method(s) | Name | Controller | Middleware |
|---|---|---|---|---|
| `gov-store/operations/register` | GET | `storeops.register.index` | `StockRegisterController@index` | `web`, `auth`, `InitializeTenantContext` |
| **`gov-store/operations/kardex/{type}/{id}`** | **GET** | **`storeops.register.kardex`** | **`StockRegisterController@kardex`** | **`web`, `auth`, `InitializeTenantContext`** |

### Full Route File Content (`Routes/web.php`)

```php
<?php

use Illuminate\Support\Facades\Route;
use GovStore\StoreOperations\Http\Controllers\GoodsReceiptController;
use GovStore\StoreOperations\Http\Controllers\GoodsIssueController;
use GovStore\StoreOperations\Http\Controllers\StockRegisterController;

Route::group([
    'prefix' => 'gov-store/operations', 
    'middleware' => ['web', 'auth', \GovStore\TenantScope\Http\Middleware\InitializeTenantContext::class]
], function () {
    
    // Goods Receipt Workflows
    Route::get('/receipts/create', [GoodsReceiptController::class, 'create'])->name('storeops.receipts.create');
    Route::post('/receipts/store', [GoodsReceiptController::class, 'store'])->name('storeops.receipts.store');
    Route::post('/receipts/{id}/submit', [GoodsReceiptController::class, 'submit'])->name('storeops.receipts.submit');

    // Stock Register & Kardex (Audit Trail)
    Route::get('/register', [StockRegisterController::class, 'index'])->name('storeops.register.index');
    Route::get('/kardex/{type}/{id}', [StockRegisterController::class, 'kardex'])->name('storeops.register.kardex');

    // Goods Issue Workflows
    Route::get('/issues/create', [GoodsIssueController::class, 'create'])->name('storeops.issues.create');
    Route::post('/issues/store', [GoodsIssueController::class, 'store'])->name('storeops.issues.store');

});
```

### Tab Registry (Frontend AJAX URL Generation)

In `StoreOperationsServiceProvider::registerKardexTabs()`:
```php
$kardexTabUrl = '/gov-store/operations/kardex/{type}/{id}';
```
This is used by the frontend JavaScript to generate AJAX URLs like:
```
/gov-store/operations/kardex/consumable/3?ajax=1
```

---

## 4. DATABASE COLUMN VERIFICATION

### Table: `gov_inventory_movements`

#### Initial Schema (migration `2024_02_01_000000_create_gov_store_operations_tables.php`)

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | UUID (PRIMARY) | NO | |
| `stockable_type` | STRING | YES | Polymorphic type |
| `stockable_id` | UNSIGNED INT | YES | Polymorphic ID |
| `movement_type` | STRING | YES | Values: `IN`, `OUT` |
| `quantity` | INTEGER | YES | Absolute value |
| `document_type` | STRING | YES | Polymorphic doc type |
| `document_id` | UUID | YES | Polymorphic doc ID |
| `company_id` | UNSIGNED INT | YES | |
| `location_id` | UNSIGNED INT | YES | |
| `created_by` | UNSIGNED INT | YES | |
| `created_at` | TIMESTAMP | YES | Manual `useCurrent()`, no `updated_at` |

#### Additional Column (migration `2024_02_02_000000_add_balance_after_to_gov_inventory_movements.php`)

| Column | Type | Position | Nullable | Notes |
|---|---|---|---|---|
| **`balance_after`** | **INTEGER** | **AFTER `quantity`** | **YES** | Pre-computed running balance |

### Answer: YES, the column `balance_after` exists in the schema.

**However**, it is nullable and has a default of `NULL`. The controller's "Self-Healing Ledger" logic (lines 48-62 of `StockRegisterController.php`) writes to this column on first read — meaning existing records will have `NULL` values until accessed.

---

## 5. CONTROLLER & SERVICE PROVIDER CODE

### 5a. StockRegisterController.php (Full Code)

**Path:** `packages/gov-store/store-operations/src/Http/Controllers/StockRegisterController.php`

```php
<?php

namespace GovStore\StoreOperations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use GovStore\StoreOperations\Models\InventoryMovement;
use App\Models\Consumable;
use App\Models\Accessory;
use App\Models\Component;

class StockRegisterController extends Controller
{
    /**
     * Dashboard listing all items in the storekeeper's warehouse
     */
    public function index()
    {
        // Globally scoped: MinistryLocationScope applies automatically
        $consumables = Consumable::with('category')->get();
        $accessories = Accessory::with('category')->get();
        $components  = Component::with('category')->get();

        return view('storeops::register.index', compact('consumables', 'accessories', 'components'));
    }

    /**
     * Displays the Immutable Stock Card (Kardex) for a specific item
     */
    public function kardex(Request $request, $type, $id)
    {
        // Resolve model class safely
        $modelClass = match (strtolower($type)) {
            'consumable' => Consumable::class,
            'accessory'  => Accessory::class,
            'component'  => Component::class,
            default      => abort(404, 'Invalid stockable type')
        };

        $item = $modelClass::findOrFail($id);

        // Retrieve pre-computed, immutable balances instantly (O(1) lookups per row)
        $movements = InventoryMovement::with('document', 'creator')
            ->where('stockable_type', $modelClass)
            ->where('stockable_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Self-Healing Ledger: Calculate and write back legacy NULL values
        $runningBalance = 0;
        foreach ($movements as $movement) {
            if (is_null($movement->balance_after)) {
                if ($movement->movement_type === 'IN') {
                    $runningBalance += $movement->quantity;
                } else {
                    $runningBalance -= $movement->quantity;
                }
                
                // Write back to database instantly so calculation runs only once
                $movement->update(['balance_after' => $runningBalance]);
                $movement->running_balance = $runningBalance;
            } else {
                $runningBalance = $movement->balance_after;
                $movement->running_balance = $runningBalance;
            }
        }

        if ($request->has('ajax')) {
            return view('storeops::register.kardex-table', compact('movements'));
        }

        return view('storeops::register.kardex', compact('item', 'movements', 'type'));
    }
}
```

### 5b. StoreOperationsServiceProvider.php (Full Code)

**Path:** `packages/gov-store/store-operations/src/Providers/StoreOperationsServiceProvider.php`

```php
<?php

namespace GovStore\StoreOperations\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Services\SystemGoodsIssueService;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Listeners\UpdateSnipeQuantity;
use GovStore\StoreOperations\Listeners\WriteNativeAuditLogs;
use GovStore\StoreOperations\Http\Middleware\InjectStoreOperationsUi;
use GovStore\StoreOperations\UI\TabRegistry;
use GovStore\StoreOperations\UI\Tab;

class StoreOperationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        // 1. Register Tab Registry Singleton
        $this->app->singleton(TabRegistry::class, function () {
            return new TabRegistry();
        });

        $this->app->singleton(StockIssuingServiceInterface::class, SystemGoodsIssueService::class);
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        // 3. Load Views (Fixed: lowercase resources/views path for case-sensitivity on servers)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'storeops');

        // 2. Register Zero-Touch UI Injection Middleware
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectStoreOperationsUi::class);

        // 3. Register Split Single Responsibility Event Listeners (SRP compliant)
        Event::listen(
            InventoryMovementCreated::class,
            [UpdateSnipeQuantity::class, 'handle']
        );

        Event::listen(
            InventoryMovementCreated::class,
            [WriteNativeAuditLogs::class, 'handle']
        );

        // 4. Register the Kardex Workspace Tab to target entities
        $this->registerKardexTabs();
    }

    protected function registerKardexTabs()
    {
        $registry = $this->app->make(TabRegistry::class);

        $kardexTabUrl = '/gov-store/operations/kardex/{type}/{id}';

        // Register Kardex to all 3 target counter-based categories
        $registry->registerTab('consumable', new Tab('govstore-ledger-tab', 'Kardex Ledger', $kardexTabUrl, 'fa fa-book text-aqua'));
        $registry->registerTab('accessory',  new Tab('govstore-ledger-tab', 'Kardex Ledger', $kardexTabUrl, 'fa fa-book text-aqua'));
        $registry->registerTab('component',  new Tab('govstore-ledger-tab', 'Kardex Ledger', $kardexTabUrl, 'fa fa-book text-aqua'));
    }
}
```

### 5c. InjectStoreOperationsUi.php (Full Code)

**Path:** `packages/gov-store/store-operations/src/Http/Middleware/InjectStoreOperationsUi.php`

```php
<?php

namespace GovStore\StoreOperations\Http\Middleware;

use Closure;
use GovStore\StoreOperations\UI\TabRegistry;

class InjectStoreOperationsUi
{
    protected TabRegistry $tabRegistry;

    public function __construct(TabRegistry $tabRegistry)
    {
        $this->tabRegistry = $tabRegistry;
    }

    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();

            if (str_contains($content, '</body>')) {
                // 1. Sidebar menu injection
                $viewHtml = view('storeops::hooks.menu-injection')->render();
                $content = str_replace('</body>', $viewHtml . '</body>', $content);

                // 2. Tab Registry injection
                $tabScript = $this->compileRegistryScript();
                if ($tabScript) {
                    $content = str_replace('</body>', $tabScript . '</body>', $content);
                }

                $response->setContent($content);
            }
        }

        return $response;
    }

    protected function compileRegistryScript(): ?string
    {
        $path = request()->getPathInfo();
        $match = preg_match('/\/(consumables|accessories|components)\/(\d+)/', $path, $matches);

        if (!$match) {
            return null;
        }

        $type = $matches[1];
        $singularType = strtolower(substr($type, 0, -1)); // e.g., "consumable"
        $id = $matches[2];

        $registeredTabs = $this->tabRegistry->getTabsFor($singularType);
        if (empty($registeredTabs)) {
            return null;
        }

        // Format tabs array to pass directly to JavaScript engine
        $tabsJson = json_encode(array_map(function ($tab) use ($singularType, $id) {
            return [
                'id' => $tab->id,
                'title' => $tab->title,
                'icon' => $tab->icon,
                'ajaxUrl' => str_replace(['{type}', '{id}'], [$singularType, $id], $tab->ajaxUrl),
            ];
        }, $registeredTabs));

        return '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            let registeredTabs = ' . $tabsJson . ';
            let tabContainer = document.querySelector(".nav-tabs");
            let paneContainer = document.querySelector(".tab-content");

            if (tabContainer && paneContainer && registeredTabs.length > 0) {
                registeredTabs.forEach(function(tab, index) {
                    // Inject Header (Fixed single quote mismatch)
                    let iconHtml = tab.icon ? \'<i class="\' + tab.icon + \'"></i> \' : \'\';
                    let tabHtml = \'<li><a href="#\' + tab.id + \'" data-toggle="tab">\' + iconHtml + tab.title + \'</a></li>\';
                    tabContainer.insertAdjacentHTML("afterbegin", tabHtml);

                    // Inject Body Frame
                    let paneHtml = \'<div class="tab-pane" id="\' + tab.id + \'"><div id="target-\' + tab.id + \'" style="padding: 15px;"><i class="fa fa-spinner fa-spin"></i> Loading...</div></div>\';
                    paneContainer.insertAdjacentHTML("afterbegin", paneHtml);

                    // Asynchronously load the data fragment via Fetch API
                    fetch(tab.ajaxUrl + "?ajax=1")
                        .then(res => {
                            if (!res.ok) throw new Error();
                            return res.text();
                        })
                        .then(html => {
                            document.getElementById("target-" + tab.id).innerHTML = html;
                        })
                        .catch(() => {
                            document.getElementById("target-" + tab.id).innerHTML = \'<span class="text-danger">Failed to load content.</span>\';
                        });
                });

                // Shift default active tab focus to the newly injected workspace tab
                document.querySelectorAll(".nav-tabs li").forEach(el => el.classList.remove("active"));
                document.querySelectorAll(".tab-content .tab-pane").forEach(el => el.classList.remove("active"));

                tabContainer.querySelector("li:first-child").classList.add("active");
                paneContainer.querySelector(".tab-pane:first-child").classList.add("active");
            }
        });
        </script>
        ';
    }
}
```

---

## 6. ROOT CAUSE ANALYSIS & REQUIRED FIXES

### Bug #1: Missing `kardex-table.blade.php` View File (CAUSES THE 500 ERROR)

**Location:** `StockRegisterController.php` line 69  
**Error:** `InvalidArgumentException: View [register.kardex-table] not found.`  
**Trigger:** AJAX request to `/gov-store/operations/kardex/consumable/{id}?ajax=1`

The controller at line 69 calls:
```php
return view('storeops::register.kardex-table', compact('movements'));
```

But the file **does not exist** anywhere on disk. The `register/` directory only contains:
- `index.blade.php`
- `kardex.blade.php` (full-page layout, NOT an AJAX fragment)

**Fix:** Create the missing file at:
```
packages/gov-store/store-operations/src/Resources/views/register/kardex-table.blade.php
```

This file should contain **only the table body HTML** (not a full layout extension) — it's returned as an AJAX fragment. It should iterate over `$movements` and render the same table rows that `kardex.blade.php` renders, but without the `@extends` directive or `<html>`/`<body>` wrappers.

---

### Bug #2: Directory Casing Mismatch (WILL BREAK ON LINUX)

**Location:** Service Provider line 47 + on-disk directory name  
**Issue:** Code says `resources/` (lowercase), disk has `Resources/` (capital R)

**Fix:** Rename the directory on disk to match the code:
```
Rename:  packages/gov-store/store-operations/src/Resources/
To:      packages/gov-store/store-operations/src/resources/
```

And update all internal view references in Blade files from `Resources/views/` to `resources/views/` if any absolute paths are used.

---

### Bug #3: Service Provider Comment vs Code Mismatch (MINOR)

In `StoreOperationsServiceProvider.php`, the numbered comments are out of order:
```php
// 1. Register Tab Registry Singleton     ← line 20
// 3. Load Views                           ← line 46
// 2. Register Zero-Touch UI Injection     ← line 49
// 3. Register Split Single Responsibility ← line 53 (duplicate #3)
// 4. Register the Kardex Workspace Tab    ← line 60
```

This is cosmetic but confusing for maintainers.

---

## 7. EXISTING FILES INVENTORY

### Views Namespace: `storeops::`

| View Key | On-Disk Path | Status |
|---|---|---|
| `storeops::hooks.menu-injection` | `.../resources/views/hooks/menu-injection.blade.php` | EXISTS |
| `storeops::register.index` | `.../resources/views/register/index.blade.php` | EXISTS |
| `storeops::register.kardex` | `.../resources/views/register/kardex.blade.php` | EXISTS |
| **`storeops::register.kardex-table`** | **`.../resources/views/register/kardex-table.blade.php`** | **MISSING** |

### Models

| Model | Table | Status |
|---|---|---|
| `InventoryMovement` | `gov_inventory_movements` | EXISTS |
| `GoodsReceipt` | `gov_goods_receipts` | EXISTS |
| `GoodsReceiptItem` | `gov_goods_receipt_items` | EXISTS |
| `GoodsIssue` | `gov_goods_issues` | EXISTS |
| `GoodsIssueItem` | `gov_goods_issue_items` | EXISTS |
| `StockAdjustment` | `gov_stock_adjustments` | EXISTS |
| `StockAdjustmentItem` | `gov_stock_adjustment_items` | EXISTS |

### Key Model: `InventoryMovement`

```php
// Table: gov_inventory_movements
// Columns: id (UUID PK), stockable_type, stockable_id, movement_type, quantity, 
//          balance_after (INTEGER, nullable, AFTER quantity), document_type, 
//          document_id, company_id, location_id, created_by, created_at
// NO updated_at (const UPDATED_AT = null)
// Global Scope: MinistryLocationScope
// Relationships: stockable() [morphTo], document() [morphTo], creator() [belongsTo User]
```

---

## 8. RECOMMENDED FIX PRIORITY

| Priority | Fix | Effort | Impact |
|---|---|---|---|
| **P0** | Create `kardex-table.blade.php` AJAX fragment view | Low | Fixes the 500 error immediately |
| **P1** | Rename `Resources/` → `resources/` on disk | Low | Prevents Linux production failure |
| P2 | Add null-coalescing for `$movement->document` / `$movement->creator` in kardex-table view | Low | Prevents N+1 / null reference errors |
| P3 | Fix comment numbering in Service Provider | Trivial | Code hygiene |

---

*End of Diagnostic Report*
