<?php

namespace GovStore\GeoAreas\Providers;

use Illuminate\Support\ServiceProvider;

class GeoAreasServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'geo_areas');
        // Load library migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load library routing endpoints (NEW)
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \GovStore\GeoAreas\Console\Commands\GeoAreasSyncCommand::class,
                \GovStore\GeoAreas\Console\Commands\PublishBoundariesCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/geo-areas.php', 'geo-areas');
        $this->app->singleton(\GovStore\GeoAreas\Services\GeoTree::class);
        $this->app->singleton(\GovStore\GeoAreas\Services\BoundarySet::class);
        $this->app->singleton(\GovStore\GeoAreas\Services\GeoAreaService::class, function ($app) {
            return new \GovStore\GeoAreas\Services\GeoAreaService();
        });
    }
}
