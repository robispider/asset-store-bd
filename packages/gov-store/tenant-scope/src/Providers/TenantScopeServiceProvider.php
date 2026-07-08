<?php

namespace GovStore\TenantScope\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\TenantScope\Scopes\TenantScope;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\InjectTenantScopeUi;

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
        
        // 2. Push middlewares to HTTP execution pipeline
        $router->pushMiddlewareToGroup('web', InjectTenantScopeUi::class);
        $router->pushMiddlewareToGroup('web', InitializeTenantContext::class);

        // 3. Register Global Scopes (now pure readers)
        if (class_exists(\App\Models\Asset::class)) {
            \App\Models\Asset::addGlobalScope(new MinistryLocationScope());
        }
        if (class_exists(\App\Models\User::class)) {
            \App\Models\User::addGlobalScope(new MinistryLocationScope());
        }
        if (class_exists(\App\Models\Category::class)) {
            \App\Models\Category::addGlobalScope(new TenantScope('categories'));
        }
        if (class_exists(\App\Models\AssetModel::class)) {
            \App\Models\AssetModel::addGlobalScope(new TenantScope('models'));
        }
        if (class_exists(\App\Models\Supplier::class)) {
            \App\Models\Supplier::addGlobalScope(new TenantScope('suppliers'));
        }
        if (class_exists(\App\Models\Manufacturer::class)) {
            \App\Models\Manufacturer::addGlobalScope(new TenantScope('manufacturers'));
        }
        if (class_exists(\App\Models\Location::class)) {
            \App\Models\Location::addGlobalScope(new TenantScope('locations'));
        }
    }
}