<?php

namespace GovStore\TenantScope\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\TenantScope\Scopes\TenantScope;
use GovStore\TenantScope\Scopes\UserScope;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\InjectTenantScopeUi;
use GovStore\TenantScope\Observers\TenantMutationObserver;

class TenantScopeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });
    }

    public function boot()
    {
        // =========================================================================
        // 1. RESTORED PACKAGE RESOURCES
        // =========================================================================
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govscope');

        // =========================================================================
        // 2. MIDDLEWARE REGISTRATION
        // =========================================================================
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectTenantScopeUi::class);
        $router->pushMiddlewareToGroup('web', InitializeTenantContext::class);
        $router->pushMiddlewareToGroup('api', InitializeTenantContext::class);

        // =========================================================================
        // 3. SCOPE ARRAYS
        // =========================================================================
        $operationalModels = [
            \App\Models\Asset::class,
            \App\Models\Consumable::class,
            \App\Models\Accessory::class,
            \App\Models\Component::class,
            \App\Models\License::class,
        ];

        $referenceModels = [
            'categories'    => \App\Models\Category::class,
            'models'        => \App\Models\AssetModel::class,
            'suppliers'     => \App\Models\Supplier::class,
            'manufacturers' => \App\Models\Manufacturer::class,
            'locations'     => \App\Models\Location::class,
        ];

        // =========================================================================
        // 4. ATTACH SCOPES AT RUNTIME
        // =========================================================================
        
        // Identity Scope (Hierarchical visibility)
        if (class_exists(\App\Models\User::class)) {
            \App\Models\User::addGlobalScope(new UserScope());
        }

        // Operational Scopes
        foreach ($operationalModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new MinistryLocationScope());
            }
        }

        // Reference Scopes
        foreach ($referenceModels as $type => $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new TenantScope($type));
            }
        }

        // =========================================================================
        // 5. OBSERVERS
        // =========================================================================
        $allProtectedModels = array_merge($operationalModels, array_values($referenceModels), [\App\Models\User::class]);
        foreach ($allProtectedModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(TenantMutationObserver::class);
            }
        }
    }
}