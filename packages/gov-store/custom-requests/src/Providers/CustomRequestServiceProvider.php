<?php

namespace GovStore\CustomRequests\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use GovStore\CustomRequests\Http\Middleware\InjectGovStoreUi;

class CustomRequestServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 2. Load Web Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // 3. Load Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govstore');

        // 4. Zero-Touch UI Injection Middleware
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectGovStoreUi::class);

        // 5. Polymorphic Abstraction Maps (Hides Laravel model namespaces from Database)
        //    Only types with a working Adapter + catalog source are mapped. Licenses are
        //    intentionally omitted until a LicenseAdapter and catalog source are implemented.
        Relation::morphMap([
            'asset' => \App\Models\Asset::class,
            'accessory' => \App\Models\Accessory::class,
            'consumable' => \App\Models\Consumable::class,
        ]);
    }

    public function register()
    {
        //
    }
}
