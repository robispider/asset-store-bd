<?php

namespace GovStore\GeoAreas\Providers;

use Illuminate\Support\ServiceProvider;

class GeoAreasServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register library migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function register()
    {
        // Register shared singleton service
        $this->app->singleton(\GovStore\GeoAreas\Services\GeoAreaService::class, function ($app) {
            return new \GovStore\GeoAreas\Services\GeoAreaService();
        });
    }
}