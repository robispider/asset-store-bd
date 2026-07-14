<?php

namespace GovStore\StoreOperations\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Services\SystemGoodsIssueService;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Listeners\UpdateSnipeQuantity;
use GovStore\StoreOperations\Http\Middleware\InjectStoreOperationsUi;

class StoreOperationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(StockIssuingServiceInterface::class, SystemGoodsIssueService::class);
    }

    public function boot()
    {
        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        // 2. Load Package Routes
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        // 3. Load Views
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'storeops');

        // 4. Register Zero-Touch UI Injection Middleware (web routing group)
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectStoreOperationsUi::class);

        // 5. Register Events (The Projection Engine)
        Event::listen(
            InventoryMovementCreated::class,
            [UpdateSnipeQuantity::class, 'handle']
        );
    }
}