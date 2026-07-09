<?php

namespace GovStore\OfficeMembership\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Rules\NoActiveAssetsRule;
use GovStore\OfficeMembership\Rules\NoActiveRolesRule;
use GovStore\OfficeMembership\Rules\NoPendingRequestsRule;
use GovStore\OfficeMembership\Http\Middleware\InjectMembershipUi;
use GovStore\OfficeMembership\Console\Commands\SyncInitialMemberships;

class OfficeMembershipServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govmem');

        // Inject UI
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectMembershipUi::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncInitialMemberships::class]);
        }
    }

    public function register()
    {
        // Register the Engine as a Singleton and inject the Rules!
        $this->app->singleton(ClearanceEngine::class, function ($app) {
            $engine = new ClearanceEngine();
            $engine->registerRule(new NoActiveAssetsRule());
            $engine->registerRule(new NoActiveRolesRule());
            $engine->registerRule(new NoPendingRequestsRule());
            return $engine;
        });
    }
}