<?php

namespace GovStore\TenantScope\Providers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\User;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\InjectTenantScopeUi;
use GovStore\TenantScope\Http\Middleware\RequireGovAbility;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\TenantScope\Observers\TenantMutationObserver;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use GovStore\TenantScope\Scopes\TenantScope;
use GovStore\TenantScope\Scopes\UserScope;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TenantScopeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/abilities.php', 'govstore-abilities');
        // Register the Central Navigation Registry as a shared singleton across all packages
        $this->app->singleton(MenuRegistry::class, function () {
            return new MenuRegistry;
        });

        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        // Merge the new capability configuration file cleanly
        $this->mergeConfigFrom(
            __DIR__.'/../config/permissions.php', 'govstore-permissions'
        );
    }

    public function boot()
    {
        $this->app['router']->aliasMiddleware('gov.can', RequireGovAbility::class);
        foreach (array_keys(config('govstore-abilities', [])) as $ability) {
            Gate::define($ability, fn ($user) => app(GovAccess::class)->decide($user, $ability)->toGateResponse());
        }
        Blade::component('govscope::components.action', 'gov-action');
        $this->loadRoutesFrom(__DIR__.'/../routes/access.php');
        // 0. Load Translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'tenantops');

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
            Asset::class,
            Consumable::class,
            Accessory::class,
            Component::class,
            License::class,
        ];

        // 2. Reference Models (Catalog Mapping Scoping)
        $referenceModels = [
            'categories' => Category::class,
            'models' => AssetModel::class,
            'suppliers' => Supplier::class,
            'manufacturers' => Manufacturer::class,
            'locations' => Location::class,
            'companies' => Company::class,
        ];

        // 3. Register Custom Hierarchical User Scope
        if (class_exists(User::class)) {
            User::addGlobalScope(new UserScope);
        }

        // 4. Register Operational Scopes
        foreach ($operationalModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new MinistryLocationScope);
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
        foreach (['index' => 'my_access', 'requests.index' => 'requests', 'matrix' => 'matrix', 'shadow' => 'shadow', 'audit' => 'audit'] as $route => $label) {
            $registry->register(['id' => 'gov-access-'.$label, 'parent' => 'gov-store', 'title' => __('tenantops::access.'.$label),
                'route' => 'gov.access.'.$route, 'order' => 95, 'permission' => 'access.view']);
        }

        // 1. ROOT FOLDER: Government Store (For standard operational modules)
        $registry->register([
            'id' => 'gov-store',
            'title' => __('tenantops::ops.menu_gov_store'),
            'icon' => 'fas fa-shopping-cart text-aqua',
            'order' => 10,
        ]);

        // 2. ROOT FOLDER: Multitenant Administration (Gated strictly to Superadmins)
        $registry->register([
            'id' => 'gov-tenantscope-root',
            'title' => __('tenantops::ops.menu_multitenant_admin'),
            'icon' => 'fas fa-user-shield text-red',
            'permission' => 'admin',
            'order' => 60,
            'active_patterns' => ['gov-store/admin/scope*'],
        ]);

        // 3. Child 1: Scoping Dashboard (Nested directly under Multitenant Administration)
        $registry->register([
            'id' => 'gov-tenantscope-dashboard',
            'parent' => 'gov-tenantscope-root',
            'title' => __('tenantops::ops.menu_scoping_dashboard'),
            'icon' => 'fas fa-tachometer-alt text-aqua',
            'route' => 'gov.scope.dashboard',
            'permission' => 'admin',
            'order' => 10,
        ]);

        // 4. Child 2: Policy Configurator (Nested directly under Multitenant Administration)
        $registry->register([
            'id' => 'gov-tenantscope-config',
            'parent' => 'gov-tenantscope-root',
            'title' => __('tenantops::ops.menu_policy_configurator'),
            'icon' => 'fas fa-sliders-h text-orange',
            'route' => 'gov.scope.config',
            'permission' => 'admin',
            'order' => 20,
        ]);

        // 5. Child 3: Boundary Explorer Grid (Nested directly under Multitenant Administration)
        $registry->register([
            'id' => 'gov-tenantscope-mappings',
            'parent' => 'gov-tenantscope-root',
            'title' => __('tenantops::ops.menu_boundary_explorer'),
            'icon' => 'fas fa-search-plus text-green',
            'route' => 'gov.scope.mappings',
            'permission' => 'admin',
            'order' => 30,
        ]);
    }
}
