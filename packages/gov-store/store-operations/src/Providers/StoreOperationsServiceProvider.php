<?php

namespace GovStore\StoreOperations\Providers;

use App\Models\Accessory;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use GovStore\StoreOperations\Console\Commands\ProtectDocumentAttachments;
use GovStore\StoreOperations\Console\Commands\OpenStoreLedger;
use GovStore\StoreOperations\Console\Commands\RepairLedgerBalances;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Contracts\TrackingCodeVerifier;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Listeners\UpdateSnipeQuantity;
use GovStore\StoreOperations\Listeners\WriteNativeAuditLogs;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Observers\SnipeCategoryObserver;
use GovStore\StoreOperations\Services\SystemGoodsIssueService;
use GovStore\StoreOperations\Services\NullTrackingCodeVerifier;
use GovStore\StoreOperations\UI\Tab;
use GovStore\StoreOperations\UI\TabRegistry;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class StoreOperationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/intake.php', 'govstore-intake');
        $this->app->singleton(\GovStore\StoreOperations\Services\IntakePolicy::class);

        // 1. Register Tab Registry Singleton
        $this->app->singleton(TabRegistry::class, function () {
            return new TabRegistry;
        });

        // 2. Register Stock Issuing Service Interface Binding
        $this->app->singleton(StockIssuingServiceInterface::class, SystemGoodsIssueService::class);
        $this->app->singleton(TrackingCodeVerifier::class, NullTrackingCodeVerifier::class);
        if (class_exists(\GovStore\Tracking\Services\ScopeValidatorService::class)) {
            $this->app->singleton(TrackingCodeVerifier::class, \GovStore\StoreOperations\Integrations\Tracking\TrackingCodeVerifierAdapter::class);
        }
    }

    public function boot()
    {
        \Illuminate\Support\Facades\View::composer(['consumables/view', 'accessories/view', 'components/view'], \GovStore\StoreOperations\UI\NativeKardexComposer::class);
        \GovStore\StoreOperations\Integrations\Committee\StoreOpsCommitteeRegistrations::register();
        // 0. Load Translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'storeops');

        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        // 4. Load Routes
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        // 5. Load Views (Case-sensitivity safe)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'storeops');

        // Register store-operations styles with the shared theme build when available.
        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('storeops', __DIR__.'/../resources/css/store-operations.css');
        }

        // 6. Register CLI Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProtectDocumentAttachments::class,
                OpenStoreLedger::class,
                RepairLedgerBalances::class,
                \GovStore\StoreOperations\Console\Commands\ReconcileStoreLedger::class,
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
            'consumable' => Consumable::class,
            'accessory' => Accessory::class,
            'component' => Component::class,
            'assetmodel' => AssetModel::class,

            // REDIRECT LEGACY RECORDS: Automatically instantiates the generic Document class
            'GovStore\StoreOperations\Models\GoodsReceipt' => Document::class,
            'GovStore\StoreOperations\Models\GoodsIssue' => Document::class,
        ]);
        // 10. Register navigation menus in the Central Menu Registry
        $this->registerNavigationMenus();

        // 11. Register the Kardex Workspace Tab to target entities
        $this->registerKardexTabs();

        // 12. Register Passive Sync Observer on native Snipe-IT Category Model
        Category::observe(SnipeCategoryObserver::class);
        foreach ([Consumable::class, Accessory::class, Component::class] as $stockClass) {
            $stockClass::observe(\GovStore\StoreOperations\Services\LedgerStockGuard::class);
        }

        // 13. GRN-only intake: guard Snipe-IT's create paths by route name (super users included)
        // and check stock imports row by row while one runs. See config/intake.php.
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', \GovStore\StoreOperations\Http\Middleware\EnforceGrnIntake::class);
        $router->pushMiddlewareToGroup('api', \GovStore\StoreOperations\Http\Middleware\EnforceGrnIntake::class);
        foreach ([\App\Models\Asset::class, \App\Models\License::class, Consumable::class, Accessory::class, Component::class] as $stockClass) {
            $stockClass::saving(fn ($record) => app(\GovStore\StoreOperations\Services\IntakePolicy::class)->checkImportedRecord($record));
        }

    }

    protected function registerNavigationMenus(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // Store: the document hub covers every receipt, issue and other store document workspace.
        $registry->register(['id' => 'storeops-hub', 'group' => 'gov-store-ops', 'order' => 10,
            'label' => 'storeops::storeops.nav_receipts_issues', 'icon' => 'fas fa-folder-open',
            'route' => 'storeops.hub', 'match' => ['storeops.documents.*', 'gov-store/operations/documents/*']]);
        $registry->register(['id' => 'storeops-register', 'group' => 'gov-store-ops', 'order' => 20,
            'label' => 'storeops::storeops.nav_stock_register', 'icon' => 'fas fa-cube',
            'route' => 'storeops.register.index', 'match' => ['storeops.register.*', 'gov-store/operations/kardex/*']]);

        // ＋ New (navbar): start a store document in the working office. Visibility follows the
        // initialize route's gov.can ability; a working office is required to draft.
        foreach ([
            ['receipt', 10, 'storeops::storeops.new_receipt', 'fas fa-truck-loading'],
            ['issue', 20, 'storeops::storeops.new_issue', 'fas fa-share'],
            ['adjustment', 30, 'storeops::storeops.new_adjustment', 'fas fa-sliders-h'],
        ] as [$type, $order, $label, $icon]) {
            $registry->register(['id' => "storeops-new-{$type}", 'zone' => 'actions', 'order' => $order,
                'label' => $label, 'icon' => $icon, 'route' => 'storeops.documents.initialize',
                'form' => ['document_type' => $type],
                'visible' => fn () => (bool) app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId]);
        }

        // Rules & settings.
        $registry->register(['id' => 'storeops-admin-rules', 'group' => 'gov-rules', 'order' => 20,
            'label' => 'storeops::storeops.nav_product_rules', 'icon' => 'fas fa-cogs',
            'route' => 'storeops.admin.rules.index', 'match' => ['storeops.admin.rules.*']]);
    }

    protected function registerKardexTabs()
    {
        $registry = $this->app->make(TabRegistry::class);
        $kardexTabUrl = '/gov-store/operations/kardex/{type}/{id}';

        // Register Kardex to all 3 target counter-based categories
        $registry->registerTab('consumable', new Tab('govstore-ledger-tab', 'storeops::storeops.stock_card_title', $kardexTabUrl, 'fa fa-book'));
        $registry->registerTab('accessory', new Tab('govstore-ledger-tab', 'storeops::storeops.stock_card_title', $kardexTabUrl, 'fa fa-book'));
        $registry->registerTab('component', new Tab('govstore-ledger-tab', 'storeops::storeops.stock_card_title', $kardexTabUrl, 'fa fa-book'));
    }
}
