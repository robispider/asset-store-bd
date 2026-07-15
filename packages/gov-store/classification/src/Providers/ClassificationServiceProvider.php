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
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
          // Load migrations (Reference/Operational split)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views (search/, dashboard/, livewire/, manager/)
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'gov-classification');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Publish views for customization
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/gov-classification'),
        ], 'gov-classification-views');


        // Register in GovStore Navigation — bypasses Tenant Context for Superadmins
        $this->app->booted(function () {
            $registry = $this->app->make(MenuRegistry::class);

            // 1. Workspace Node: Global Catalog (Parent changed to the valid root 'gov-store')
            $registry->register([
                'id'              => 'gov-catalog',
                'parent'          => 'gov-store',
                'title'           => 'Global Catalog',
                'icon'            => 'fas fa-globe text-blue',
                'route'           => 'gov.catalog.dashboard',
                'permission'      => 'admin',
                'order'           => 60,
                'active_patterns' => ['admin/catalog*'],
            ]);

            // 2. Child Nodes nested under the Global Catalog Workspace
            $registry->register([
                'id'         => 'gov-catalog-dashboard',
                'parent'     => 'gov-catalog',
                'title'      => 'Dashboard',
                'icon'       => 'fas fa-tachometer-alt',
                'route'      => 'gov.catalog.dashboard',
                'permission' => 'admin',
                'order'      => 10,
            ]);

            $registry->register([
                'id'         => 'gov-catalog-search',
                'parent'     => 'gov-catalog',
                'title'      => 'Search Catalog',
                'icon'       => 'fas fa-search',
                'route'      => 'gov.catalog.search',
                'permission' => 'admin',
                'order'      => 20,
            ]);

            $registry->register([
                'id'         => 'gov-catalog-import',
                'parent'     => 'gov-catalog',
                'title'      => 'Import Catalog',
                'icon'       => 'fas fa-cloud-upload-alt',
                'route'      => 'gov.catalog.import',
                'permission' => 'admin',
                'order'      => 30,
            ]);

            $registry->register([
                'id'         => 'gov-catalog-mapping',
                'parent'     => 'gov-catalog',
                'title'      => 'Category Mapping',
                'icon'       => 'fas fa-link',
                'route'      => 'gov.catalog.mapping',
                'permission' => 'admin',
                'order'      => 40,
            ]);

            $registry->register([
                'id'         => 'gov-catalog-external',
                'parent'     => 'gov-catalog',
                'title'      => 'External Mappings',
                'icon'       => 'fas fa-exchange-alt',
                'route'      => 'gov.catalog.external',
                'permission' => 'admin',
                'order'      => 50,
            ]);

            $registry->register([
                'id'         => 'gov-catalog-history',
                'parent'     => 'gov-catalog',
                'title'      => 'Import History',
                'icon'       => 'fas fa-history',
                'route'      => 'gov.catalog.history',
                'permission' => 'admin',
                'order'      => 60,
            ]);
        });
    }
}
