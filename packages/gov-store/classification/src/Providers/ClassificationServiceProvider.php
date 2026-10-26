<?php

namespace GovStore\Classification\Providers;

use GovStore\Classification\Listeners\ProvisionStarterCatalog;
use GovStore\Organization\Events\OfficeProvisioned;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
use Illuminate\Support\ServiceProvider;

class ClassificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Register the new Starter Templates configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/starter_templates.php', 'starter_templates'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Allow publishing of the config so users can override templates
        $this->publishes([
            __DIR__.'/../config/starter_templates.php' => config_path('starter_templates.php'),
        ], 'classification-config');

        // Wire the Event Listener for automatic catalog provisioning
        $this->app['events']->listen(
            OfficeProvisioned::class,
            ProvisionStarterCatalog::class
        );

        // Load translations (Namespace: classification)
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'classification');

        // Load migrations (Reference/Operational split)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'gov-classification');

        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('classification', __DIR__.'/../resources/css/classification.css');
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // The product type's optional Finance Code is typed metadata (gov_metadata_values).
        $this->app->make(\GovStore\Metadata\Services\MetadataValueStore::class)
            ->register(\GovStore\Classification\Services\ProductTypeFinanceDefault::NAMESPACE, \GovStore\Classification\Services\ProductTypeFinanceDefault::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \GovStore\Classification\Console\Commands\ImportFinanceItems::class,
                \GovStore\Classification\Console\Commands\EnforceCommodityIndex::class,
            ]);
        }

        // Publish views for customization
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/gov-classification'),
        ], 'gov-classification-views');

        // Register leaves in the GovStore navigation groups (config/navigation.php in tenant-scope).
        $this->app->booted(function () {
            $registry = $this->app->make(MenuRegistry::class);

            // Catalogue: discovery for everyone with catalogue access.
            foreach ([
                ['gov-catalog-search', 10, 'nav_search', 'fas fa-search', 'gov.catalog.search', ['gov.catalog.search*']],
                ['gov-catalog-collections', 20, 'nav_collections', 'fas fa-layer-group', 'gov.catalog.discover.collections', ['gov.catalog.discover.collections*']],
                ['gov-catalog-explorer', 30, 'nav_browse_category', 'fas fa-folder-tree', 'gov.catalog.discover.explorer', []],
            ] as [$id, $order, $label, $icon, $route, $match]) {
                $registry->register(['id' => $id, 'group' => 'gov-catalogue', 'order' => $order,
                    'label' => 'classification::texts.'.$label, 'icon' => $icon, 'route' => $route, 'match' => $match]);
            }

            // Store: the office's adopted catalogue.
            $registry->register(['id' => 'storeops-my-catalog', 'group' => 'gov-store-ops', 'order' => 40,
                'label' => 'classification::texts.nav_office_catalogue', 'icon' => 'fas fa-book',
                'route' => 'gov.catalog.my_catalog.index', 'match' => ['gov.catalog.my_catalog.*', 'gov.catalog.adopt.*']]);
            $registry->register(['id' => 'catalog-reference-create', 'group' => 'gov-store-ops', 'order' => 41,
                'label' => 'tenantops::access.reference_create', 'icon' => 'fas fa-plus', 'route' => 'gov.catalog.references.create']);
            $registry->register(['id' => 'catalog-ownership-review', 'group' => 'gov-catalogue-master', 'order' => 40,
                'label' => 'tenantops::access.ownership_review', 'icon' => 'fas fa-shield-alt', 'route' => 'gov.scope.ownership']);

            // Catalogue master data: national administration.
            foreach ([
                ['gov-catalog-builder', 10, 'nav_collection_library', 'fas fa-boxes', 'gov.catalog.collections.index', ['gov.catalog.collections.*']],
                ['gov-catalog-governance', 20, 'nav_governance', 'fas fa-landmark', 'gov.catalog.governance.index', ['gov.catalog.governance.*']],
                ['gov-catalog-import', 30, 'nav_import', 'fas fa-upload', 'gov.catalog.import', ['gov.catalog.history']],
            ] as [$id, $order, $label, $icon, $route, $match]) {
                $registry->register(['id' => $id, 'group' => 'gov-catalogue-master', 'order' => $order,
                    'label' => 'classification::texts.'.$label, 'icon' => $icon, 'route' => $route, 'match' => $match]);
            }
            $registry->register(['id' => 'gov-catalog-finance', 'group' => 'gov-catalogue-master', 'order' => 40,
                'label' => 'classification::finance.nav_finance', 'icon' => 'fas fa-coins',
                'route' => 'gov.catalog.finance.index', 'match' => ['gov.catalog.finance.*']]);
        });
    }
}
