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
     * Seeds the Central Menu Registry with the root GovStore tree node
     * and the superadmin-only Tenant Scoping item.
     */
    protected function registerBaseMenuStructure(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // Root tree folder — all package sub-items nest under this parent
        $registry->register([
            'id'    => 'gov-store',
            'title' => 'Government Store',
            'icon'  => 'fas fa-shopping-cart text-aqua',
            'order' => 10,
        ]);

        // Superadmin-only Tenant Scoping link (replaces the old raw JS injection)
        $registry->register([
            'id'             => 'gov-tenantscope',
            'parent'         => 'gov-store',
            'title'          => 'Tenant Scoping',
            'icon'           => 'fas fa-user-lock fa-fw',
            'route'          => 'gov.scope.index',
            'permission'     => 'superuser',
            'order'          => 1,
            'active_patterns' => ['gov-store/admin/scope*'],
        ]);
    }
}