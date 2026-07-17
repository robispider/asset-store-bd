<?php

namespace GovStore\TenantScope\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\TenantScope\Scopes\TenantScope;
use GovStore\TenantScope\Scopes\UserScope;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\InjectTenantScopeUi;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\TenantScope\Observers\TenantMutationObserver;

class TenantScopeServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register the Central Navigation Registry as a shared singleton across all packages
        $this->app->singleton(MenuRegistry::class, function () {
            return new MenuRegistry();
        });

        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });

        // Merge the new capability configuration file cleanly
        $this->mergeConfigFrom(
            __DIR__.'/../config/permissions.php', 'govstore-permissions'
        );
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govscope');

        // Publish the configuration so it can be overridden in the root config directory if necessary
        $this->publishes([
            __DIR__.'/../config/permissions.php' => config_path('govstore-permissions.php'),
        ], 'config');

        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectTenantScopeUi::class);
        $router->pushMiddlewareToGroup('web', InitializeTenantContext::class);
        $router->pushMiddlewareToGroup('api', InitializeTenantContext::class);

        // Register the foundational GovStore navigation structure
        $this->registerBaseMenuStructure();

        // 1. Operational Models (Strict Physical Scoping ONLY)
        $operationalModels = [
            \App\Models\Asset::class,
            \App\Models\Consumable::class,
            \App\Models\Accessory::class,
            \App\Models\Component::class,
            \App\Models\License::class,
        ];

        // 2. Reference Models (Catalog Mapping Scoping)
        $referenceModels = [
            'categories'    => \App\Models\Category::class,
            'models'        => \App\Models\AssetModel::class,
            'suppliers'     => \App\Models\Supplier::class,
            'manufacturers' => \App\Models\Manufacturer::class,
            'locations'     => \App\Models\Location::class,
        ];

        // 3. Register Custom Hierarchical User Scope
        if (class_exists(\App\Models\User::class)) {
            \App\Models\User::addGlobalScope(new UserScope());
        }

        // 4. Register Operational Scopes
        foreach ($operationalModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new MinistryLocationScope());
            }
        }

        // 5. Register Reference Scopes
        foreach ($referenceModels as $type => $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new TenantScope($type));
            }
        }

        // =========================================================================
        // 6. OBSERVERS (REMOVED [\App\Models\User::class] from protected observers)
        // =========================================================================
        $allProtectedModels = array_merge($operationalModels, array_values($referenceModels));
        foreach ($allProtectedModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(TenantMutationObserver::class);
            }
        }
    }

   

    /**
     * Seeds the Central Menu Registry with the base Government Store structure,
     * and the flat, two-level Multitenant Administration dashboard items.
     */
    protected function registerBaseMenuStructure(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // 1. ROOT FOLDER: Government Store (For standard operational modules)
        $registry->register([
            'id'    => 'gov-store',
            'title' => 'Government Store',
            'icon'  => 'fas fa-shopping-cart text-aqua',
            'order' => 10,
        ]);

        // 2. ROOT FOLDER: Multitenant Administration (Gated strictly to Superadmins)
        $registry->register([
            'id'         => 'gov-tenantscope-root',
            'title'      => 'Multitenant Administration',
            'icon'       => 'fas fa-user-shield text-red',
            'permission' => 'admin',
            'order'      => 60, // Rendered lower down, separate from everyday operations
            'active_patterns' => ['gov-store/admin/scope*'], // Maintains open state on child routes
        ]);

        // 3. Child 1: Scoping Dashboard (Nested directly under Multitenant Administration)
        $registry->register([
            'id'         => 'gov-tenantscope-dashboard',
            'parent'     => 'gov-tenantscope-root',
            'title'      => 'Scoping Dashboard',
            'icon'       => 'fas fa-tachometer-alt text-aqua',
            'route'      => 'gov.scope.dashboard',
            'permission' => 'admin',
            'order'      => 10,
        ]);

        // 4. Child 2: Policy Configurator (Nested directly under Multitenant Administration)
        $registry->register([
            'id'         => 'gov-tenantscope-config',
            'parent'     => 'gov-tenantscope-root',
            'title'      => 'Policy Configurator',
            'icon'       => 'fas fa-sliders-h text-orange',
            'route'      => 'gov.scope.config',
            'permission' => 'admin',
            'order'      => 20,
        ]);

        // 5. Child 3: Boundary Explorer Grid (Nested directly under Multitenant Administration)
        $registry->register([
            'id'         => 'gov-tenantscope-mappings',
            'parent'     => 'gov-tenantscope-root',
            'title'      => 'Boundary Explorer',
            'icon'       => 'fas fa-search-plus text-green',
            'route'      => 'gov.scope.mappings',
            'permission' => 'admin',
            'order'      => 30,
        ]);
    }
}