<?php

namespace GovStore\OfficeMembership\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Services\OfficeMembershipService;
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

        // Inject UI Middlewares & Session Context Loader
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectMembershipUi::class);
        $router->pushMiddlewareToGroup('web', \GovStore\OfficeMembership\Http\Middleware\SetWorkingContext::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncInitialMemberships::class]);
        }

        // DYNAMIC RELATIONSHIP SHIELD (Corrected variable inside closure)
        \App\Models\User::resolveRelationUsing('memberships', function ($userModel) {
            return $userModel->hasMany(\GovStore\OfficeMembership\Models\OfficeMembership::class, 'user_id', 'id');
        });
    }

    public function register()
    {
        // 1. Bind the decoupled membership service
        $this->app->singleton(OfficeMembershipService::class, function ($app) {
            return new OfficeMembershipService();
        });

        // 2. Bind the Clearance Engine & Rules
        $this->app->singleton(ClearanceEngine::class, function ($app) {
            $engine = new ClearanceEngine();
            $engine->registerRule(new NoActiveAssetsRule());
            $engine->registerRule(new NoActiveRolesRule());
            $engine->registerRule(new NoPendingRequestsRule());
            return $engine;
        });
    }
}