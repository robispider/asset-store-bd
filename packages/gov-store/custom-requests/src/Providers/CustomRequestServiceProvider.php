<?php

namespace GovStore\CustomRequests\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use GovStore\CustomRequests\Events\ItemApproved;
use GovStore\CustomRequests\Listeners\ProcessItemCheckout;

class CustomRequestServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 2. Load Web Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // 3. Load Views (NEW) - Registers the 'govstore::' namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govstore');

        // 4. Register Event Listeners
        Event::listen(
            ItemApproved::class,
            ProcessItemCheckout::class
        );
    }

    public function register()
    {
        //
    }
}