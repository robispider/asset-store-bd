<?php

namespace GovStore\OfficeMembership\Providers;

use Illuminate\Support\ServiceProvider;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Services\OfficeMembershipService;
use GovStore\OfficeMembership\Rules\NoActiveAssetsRule;
use GovStore\OfficeMembership\Rules\NoActiveRolesRule;
use GovStore\OfficeMembership\Rules\NoPendingRequestsRule;
use GovStore\OfficeMembership\Http\Middleware\InjectMembershipUi;
use GovStore\OfficeMembership\Http\Middleware\SetWorkingContext;
use GovStore\OfficeMembership\Console\Commands\SyncInitialMemberships;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Observers\MembershipActivityLogObserver;
use GovStore\OfficeMembership\Observers\UserSyncObserver;
use GovStore\TenantScope\Navigation\MenuRegistry;
use App\Models\User;

class OfficeMembershipServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govmem');

        // Inject UI Middlewares & Session Context Loader into global routing group
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectMembershipUi::class);
        $router->pushMiddlewareToGroup('web', SetWorkingContext::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncInitialMemberships::class]);
        }

        // Register Eloquent Observers for Compliance logging
        OfficeMembership::observe(MembershipActivityLogObserver::class);

          // Attach the Thin Observer to the Core User Model
        User::observe(UserSyncObserver::class);

        // Dynamic Relationship mapping directly into core Snipe-IT User model
        \App\Models\User::resolveRelationUsing('memberships', function ($userModel) {
            return $userModel->hasMany(OfficeMembership::class, 'user_id', 'id');
        });

        // Register menus in the central registry
        $this->registerNavigationMenus();
    }

    protected function registerNavigationMenus(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // 1. Staff Management (Visible to active Office Admins, placed inside the core root folder)
        $registry->register([
            'id' => 'govmem-staff',
            'parent' => 'gov-store',
            'title' => 'Staff Management',
            'icon' => 'fas fa-users-cog fa-fw',
            'route' => 'gov.membership.admin.index',
            'permission' => 'office_admin',
            'order' => 25, // Appears cleanly alongside operational menus
        ]);

        // 2. Emergency Membership Overrides Console (Superadmin Only)
        $registry->register([
            'id' => 'govmem-override',
            'parent' => 'gov-store',
            'title' => 'Membership Overrides',
            'icon' => 'fas fa-shield-alt fa-fw',
            'route' => 'gov.membership.override.console',
            'permission' => 'admin',
            'order' => 90,
        ]);
    }

    public function register()
    {
        // Bind the decoupled membership service helper
        $this->app->singleton(OfficeMembershipService::class, function ($app) {
            return new OfficeMembershipService();
        });

        // Bind the Clearance Engine & seed validation rules
        $this->app->singleton(ClearanceEngine::class, function ($app) {
            $engine = new ClearanceEngine();
            $engine->registerRule(new NoActiveAssetsRule());
            $engine->registerRule(new NoActiveRolesRule());
            $engine->registerRule(new NoPendingRequestsRule());
            return $engine;
        });
    }
}