<?php

namespace GovStore\Classification\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\TenantScope\Navigation\MenuRegistry;

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
            \GovStore\Organization\Events\OfficeProvisioned::class,
            \GovStore\Classification\Listeners\ProvisionStarterCatalog::class
        );
        
        // Load translations (Namespace: classification)
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'classification');

        // Load migrations (Reference/Operational split)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'gov-classification');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Publish views for customization
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/gov-classification'),
        ], 'gov-classification-views');

        // Register in GovStore Navigation Menu Registry (Strictly 2-Level Compatible)
        $this->app->booted(function () {
            $registry = $this->app->make(MenuRegistry::class);

            // ==========================================
            // LEVEL 1: MASTER ROOT FOLDER (No Route)
            // ==========================================
            $registry->register([
                'id'              => 'gov-catalog-root',
                'title'           => 'Global Catalog',
                'icon'            => 'fas fa-globe text-blue',
                'permission'      => ['storekeeper', 'office_admin', 'admin', 'ict_officer'],
                'order'           => 40,
                'active_patterns' => ['admin/catalog*', 'gov-store/operations/catalog*'],
            ]);

            // ==========================================
            // LEVEL 2: DIRECT LEAF LINKS (Under Master Root)
            // ==========================================

            // 1. Discover: Collections
            $registry->register([
                'id'         => 'gov-catalog-collections',
                'parent'     => 'gov-catalog-root',
                'title'      => 'Discover Collections',
                'icon'       => 'fas fa-layer-group text-purple',
                'route'      => 'gov.catalog.discover.collections',
                'permission' => ['storekeeper', 'office_admin', 'admin', 'ict_officer'],
                'order'      => 10,
            ]);

            // 2. Discover: Explorer
            $registry->register([
                'id'         => 'gov-catalog-explorer',
                'parent'     => 'gov-catalog-root',
                'title'      => 'Catalog Explorer',
                'icon'       => 'fas fa-folder-tree text-yellow',
                'route'      => 'gov.catalog.discover.explorer',
                'permission' => ['storekeeper', 'office_admin', 'admin', 'ict_officer'],
                'order'      => 20,
            ]);

            // 3. Discover: Universal Search
            $registry->register([
                'id'         => 'gov-catalog-search',
                'parent'     => 'gov-catalog-root',
                'title'      => 'Universal Search',
                'icon'       => 'fas fa-search text-green',
                'route'      => 'gov.catalog.search',
                'permission' => ['storekeeper', 'office_admin', 'admin', 'ict_officer'],
                'order'      => 30,
            ]);

            // 4. Operations: My Organization Catalog
            $registry->register([
                'id'         => 'storeops-my-catalog',
                'parent'     => 'gov-catalog-root',
                'title'      => 'My Organization Catalog',
                'icon'       => 'fas fa-book text-aqua',
                'route'      => 'gov.catalog.my_catalog.index',
                'permission' => ['storekeeper', 'office_admin', 'admin'],
                'order'      => 40,
            ]);

            // 5. Admin: Collection Library (Gated)
            $registry->register([
                'id'         => 'gov-catalog-builder',
                'parent'     => 'gov-catalog-root',
                'title'      => 'Collection Library',
                'icon'       => 'fas fa-boxes text-orange',
                'route'      => 'gov.catalog.collections.index',
                'permission' => 'admin',
                'order'      => 50,
            ]);

            // 6. Admin: Governance Registry (Gated)
            $registry->register([
                'id'         => 'gov-catalog-governance',
                'parent'     => 'gov-catalog-root',
                'title'      => 'Governance Registry',
                'icon'       => 'fas fa-landmark',
                'route'      => 'gov.catalog.governance.index',
                'permission' => 'admin',
                'order'      => 60,
            ]);

            // 7. Admin: Catalog Import (Gated)
            $registry->register([
                'id'         => 'gov-catalog-import',
                'parent'     => 'gov-catalog-root',
                'title'      => 'Catalog Import',
                'icon'       => 'fas fa-upload text-green',
                'route'      => 'gov.catalog.import',
                'permission' => 'admin',
                'order'      => 70,
            ]);
        });
    }
}