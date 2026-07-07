<?php

namespace GovStore\Organization\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\Organization\Http\Middleware\InjectOrganizationUi;
use GovStore\Organization\Http\Middleware\EnsureOfficeIsOperational;

class OrganizationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 2. Load Package Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // 3. Load Views (Registers 'govorg::' namespace)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govorg');

        // 4. ZERO-TOUCH ORGANIZATION UI INJECTION: Register the Organization Menu Injector
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectOrganizationUi::class);

        // 5. THE INTEGRATION HANDSHAKE: Register the Scoped Location Status Guard
        $router->pushMiddlewareToGroup('web', EnsureOfficeIsOperational::class);
    }

    public function register()
    {
        //
    }
}