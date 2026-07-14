<?php

namespace GovStore\StoreOperations\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Listeners\UpdateSnipeQuantity;

class StoreOperationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Bind interfaces here in future phases
    }

    public function boot()
    {
        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        // 2. Load Package Routes (UNCOMMENTED)
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        // 3. Load Views (UNCOMMENTED)
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'storeops');

        // 4. Register Events (The Projection Engine)
        Event::listen(
            InventoryMovementCreated::class,
            [UpdateSnipeQuantity::class, 'handle']
        );
    }
}