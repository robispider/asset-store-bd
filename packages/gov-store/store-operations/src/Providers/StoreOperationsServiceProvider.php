<?php

namespace GovStore\StoreOperations\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Relations\Relation;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Services\SystemGoodsIssueService;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Listeners\UpdateSnipeQuantity;
use GovStore\StoreOperations\Listeners\WriteNativeAuditLogs;
use GovStore\StoreOperations\Console\Commands\RepairLedgerBalances;
use GovStore\StoreOperations\UI\TabRegistry;
use GovStore\StoreOperations\UI\Tab;
use GovStore\TenantScope\Navigation\MenuRegistry;

class StoreOperationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        // 1. Register Tab Registry Singleton
        $this->app->singleton(TabRegistry::class, function () {
            return new TabRegistry();
        });

        // 2. Register Stock Issuing Service Interface Binding
        $this->app->singleton(StockIssuingServiceInterface::class, SystemGoodsIssueService::class);
    }

    public function boot()
    {
        // 0. Load Translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'storeops');

        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        // 4. Load Routes
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        
        // 5. Load Views (Case-sensitivity safe)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'storeops');

        // 6. Register CLI Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                RepairLedgerBalances::class,
            ]);
        }

        // 8. Register Event Listeners (SRP compliant)
        Event::listen(
            InventoryMovementCreated::class,
            [UpdateSnipeQuantity::class, 'handle']
        );

        Event::listen(
            InventoryMovementCreated::class,
            [WriteNativeAuditLogs::class, 'handle']
        );

        // 9. Polymorphic Database Mapping (Bypasses class namespace changes from DB)
    // 9. Polymorphic Database Mapping (Bypasses class namespace changes from DB)
        Relation::morphMap([
            'consumable' => \App\Models\Consumable::class,
            'accessory'  => \App\Models\Accessory::class,
            'component'  => \App\Models\Component::class,
            'assetmodel'  => \App\Models\AssetModel::class,
            
            // REDIRECT LEGACY RECORDS: Automatically instantiates the generic Document class
            'GovStore\StoreOperations\Models\GoodsReceipt' => \GovStore\StoreOperations\Models\Document::class,
            'GovStore\StoreOperations\Models\GoodsIssue'   => \GovStore\StoreOperations\Models\Document::class,
        ]);
        // 10. Register navigation menus in the Central Menu Registry
        $this->registerNavigationMenus();

        // 11. Register the Kardex Workspace Tab to target entities
        $this->registerKardexTabs();


        // 12. Register Passive Sync Observer on native Snipe-IT Category Model
        \App\Models\Category::observe(\GovStore\StoreOperations\Observers\SnipeCategoryObserver::class);

    }

    protected function registerNavigationMenus(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // 1. Immutable Ledger / Stock Register Dashboard
        $registry->register([
            'id'              => 'storeops-register',
            'parent'          => 'gov-store',
            'title'           => __('storeops::storeops.stock_register_dashboard'),
            'icon'            => 'fa fa-cube text-aqua',
            'route'           => 'storeops.register.index',
            'permission'      => 'storekeeper',
            'order'           => 20,
            'active_patterns' => ['gov-store/operations/kardex/*'],
        ]);

        // 2. The Unified Document Operations Hub (Replaces separate Receipt/Issue links)
        $registry->register([
            'id'              => 'storeops-hub',
            'parent'          => 'gov-store',
            'title'           => 'Store Documents Hub', // Fallback if no translation exists yet
            'icon'            => 'fa fa-folder-open text-yellow',
            'route'           => 'storeops.hub',
            'permission'      => 'storekeeper',
            'order'           => 30,
            // Keeps the sidebar active when inside ANY document workspace (Receipt, Issue, etc.)
            'active_patterns' => [
                'gov-store/operations/hub',
                'gov-store/operations/documents/*'
            ],
        ]);

        $registry->register([
            'id'              => 'storeops-admin-rules',
            'parent'          => 'gov-store',
            'title'           => 'Product Rules Studio',
            'icon'            => 'fas fa-cogs text-purple',
            'route'           => 'storeops.admin.rules.index',
            'permission'      => 'superuser', // Admin only
            'order'           => 90,
        ]);
    }

    protected function registerKardexTabs()
    {
        $registry = $this->app->make(TabRegistry::class);
        $kardexTabUrl = '/gov-store/operations/kardex/{type}/{id}';

        // Register Kardex to all 3 target counter-based categories
        $registry->registerTab('consumable', new Tab('govstore-ledger-tab', __('storeops::storeops.stock_card_title'), $kardexTabUrl, 'fa fa-book text-aqua'));
        $registry->registerTab('accessory',  new Tab('govstore-ledger-tab', __('storeops::storeops.stock_card_title'), $kardexTabUrl, 'fa fa-book text-aqua'));
        $registry->registerTab('component',  new Tab('govstore-ledger-tab', __('storeops::storeops.stock_card_title'), $kardexTabUrl, 'fa fa-book text-aqua'));
    }
}