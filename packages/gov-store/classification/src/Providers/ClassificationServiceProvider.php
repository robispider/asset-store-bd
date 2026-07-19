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
     
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Load translations (Namespace: classification)
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'classification');

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
      // Register in GovStore Navigation as a Root-Level Node
        $this->app->booted(function () {
            $registry = $this->app->make(MenuRegistry::class);

            // 1. Root Folder: Global Catalog (No parent, parallel to Gov Store & Office Provisioning)
            $registry->register([
                'id'              => 'gov-catalog',
                'title'           => 'Global Catalog',
                'icon'            => 'fas fa-globe text-blue',
                'route'           => null, // Acts purely as an expandable root folder
                'permission'      => ['storekeeper', 'office_admin', 'admin'], // Gated for administrators
                'order'           => 45, // Rendered parallel to Gov Store (10) and Office Provisioning (50)
                'active_patterns' => ['admin/catalog*'],
            ]);

            // 2. Child Nodes nested directly under the Global Catalog Root
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
                'id'         => 'gov-catalog-governance',
                'parent'     => 'gov-catalog',
                'title'      => 'Category Governance',
                'icon'       => 'fas fa-landmark text-orange',
                'route'      => 'gov.catalog.governance.index',
                'permission' => 'admin',
                'order'      => 15,
            ]);

        

          // Renamed and organized
            $registry->register([
                'id'              => 'storeops-my-catalog',
                'parent'          => 'gov-catalog',
                'title'           => 'My Organization Category Catalog', // New consistent title
                'icon'            => 'fas fa-folder-open text-blue',
                'route'           => 'gov.catalog.my_catalog.index',
                'permission'      => ['storekeeper', 'office_admin', 'admin'],
                'order'           => 15,
            ]);

            // Ensure the Search Master Catalog sits directly above it
            $registry->register([
                'id'         => 'gov-catalog-search',
                'parent'     => 'gov-catalog',
                'title'      => 'Search Master Category Catalog',
                'icon'       => 'fas fa-search text-purple',
                'route'      => 'gov.catalog.search',
                'permission' => ['admin', 'storekeeper', 'office_admin', 'ict_officer'],
                'order'      => 14, 
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

            // Import Catalog — positioned between Dashboard and Governance
            $registry->register([
                'id'         => 'gov-catalog-import',
                'parent'     => 'gov-catalog',
                'title'      => 'Import Catalog',
                'icon'       => 'fas fa-upload text-green',
                'route'      => 'gov.catalog.import',
                'permission' => 'admin',
                'order'      => 12,
            ]);
        });
    }
}
