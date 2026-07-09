<?php

namespace GovStore\TenantScope\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\TenantScope\Scopes\TenantScope;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\InjectTenantScopeUi;
use GovStore\TenantScope\Observers\TenantMutationObserver;

class TenantScopeServiceProvider extends ServiceProvider
{
    public function register()
    {
        // 1. Bind the Tenant Context Singleton to memory
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govscope');

        $router = $this->app['router'];
        
        // 2. Register Middlewares globally on the appropriate routing groups
        $router->pushMiddlewareToGroup('web', InjectTenantScopeUi::class);
        $router->pushMiddlewareToGroup('web', InitializeTenantContext::class);
        $router->pushMiddlewareToGroup('api', InitializeTenantContext::class);

        // 3. Transactional Models (Left Branch Scoping)
        $transactionalModels = [
            \App\Models\Asset::class,
            \App\Models\User::class,
            \App\Models\Consumable::class,
            \App\Models\Accessory::class,
            \App\Models\Component::class,
            \App\Models\License::class,
        ];

        // 4. Reference Models (Right Branch Scoping)
        $referenceModels = [
            'categories'    => \App\Models\Category::class,
            'models'        => \App\Models\AssetModel::class,
            'suppliers'     => \App\Models\Supplier::class,
            'manufacturers' => \App\Models\Manufacturer::class,
            'locations'     => \App\Models\Location::class,
        ];

        // 5. Register Scopes at Runtime
        foreach ($transactionalModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new MinistryLocationScope());
            }
        }
        foreach ($referenceModels as $type => $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new TenantScope($type));
            }
        }

        // 6. Register Observers for Mutations (Fires only on Eloquent saves)
        $allProtectedModels = array_merge($transactionalModels, array_values($referenceModels));
        foreach ($allProtectedModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(TenantMutationObserver::class);
            }
        }
    }
}