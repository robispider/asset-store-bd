<?php

namespace GovStore\CustomRequests\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Relations\Relation;
use GovStore\CustomRequests\Events\ItemApproved;
use GovStore\CustomRequests\Listeners\ProcessItemCheckout;
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

        // 4. Register Event Listeners
        Event::listen(
            ItemApproved::class,
            ProcessItemCheckout::class
        );

        // 5. Zero-Touch UI Injection Middleware
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectGovStoreUi::class);

        // 6. Polymorphic Abstraction Maps (Hides Laravel model namespaces from Database)
        Relation::morphMap([
            'asset' => \App\Models\Asset::class,
            'accessory' => \App\Models\Accessory::class,
            'consumable' => \App\Models\Consumable::class,
            'license' => \App\Models\License::class,
        ]);
    }

    public function register()
    {
        //
    }
}