<?php

namespace GovStore\GeoView\Providers;

use GovStore\GeoView\Config\GeoViewConfigResolver;
use GovStore\GeoView\Registry\GeoPresetRegistry;
use GovStore\GeoView\Registry\GeoSourceRegistry;
use GovStore\GeoView\Services\GeoAggregator;
use GovStore\GeoView\Services\GeoDataService;
use GovStore\GeoView\Services\OfficeLocator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Geo view wiring (plan §8): registries that domain packages fill, the config resolver, the data API and
 * <x-geoview::map>. Packages register sources only `if ($this->app->bound(GeoSourceRegistry::class))`.
 */
class GeoViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/geo-view.php', 'geo-view');
        $this->app->singleton(GeoSourceRegistry::class);
        $this->app->singleton(GeoPresetRegistry::class);
        $this->app->singleton(OfficeLocator::class);
        $this->app->singleton(GeoAggregator::class);
        $this->app->singleton(GeoDataService::class);
        $this->app->bind(GeoViewConfigResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'geoview');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'geoview');
        Blade::componentNamespace('GovStore\\GeoView\\View\\Components', 'geoview');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        RateLimiter::for('geo-view', fn (Request $request) => Limit::perMinute((int) config('geo-view.throttle', 120))
            ->by('geo-view:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([\GovStore\GeoView\Console\GeoViewDoctorCommand::class]);
        }
    }
}
