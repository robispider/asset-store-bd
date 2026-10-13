<?php

namespace GovStore\OfficeMembership\Providers;

use App\Models\User;
use GovStore\OfficeMembership\Console\Commands\DeliverMembershipMail;
use GovStore\OfficeMembership\Console\Commands\SyncInitialMemberships;
use GovStore\OfficeMembership\Http\Middleware\SetWorkingContext;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Observers\MembershipActivityLogObserver;
use GovStore\OfficeMembership\Observers\UserSyncObserver;
use GovStore\OfficeMembership\Rules\NoActiveAssetsRule;
use GovStore\OfficeMembership\Rules\NoActiveRolesRule;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Services\OfficeMembershipService;
use GovStore\OfficeMembership\Services\TenantMembershipResolver;
use GovStore\TenantScope\Contracts\MembershipContextResolver;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
use Illuminate\Support\ServiceProvider;

class OfficeMembershipServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'office_membership');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govmem');

        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('office-membership', __DIR__.'/../resources/css/office-membership.css');
        }

        // Inject UI Middlewares & Session Context Loader into global routing group
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', SetWorkingContext::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncInitialMemberships::class, DeliverMembershipMail::class]);
        }

        // Register Eloquent Observers for Compliance logging
        OfficeMembership::observe(MembershipActivityLogObserver::class);

        // Attach the Thin Observer to the Core User Model
        User::observe(UserSyncObserver::class);

        // Dynamic Relationship mapping directly into core Snipe-IT User model
        User::resolveRelationUsing('memberships', function ($userModel) {
            return $userModel->hasMany(OfficeMembership::class, 'user_id', 'id');
        });

        // Register menus in the central registry
        $this->registerNavigationMenus();
    }

    protected function registerNavigationMenus(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        $registry->register(['id' => 'govmem-staff', 'group' => 'gov-offices', 'order' => 20,
            'label' => 'office_membership::member.nav_staff', 'icon' => 'fas fa-users-cog',
            'route' => 'gov.membership.admin.index', 'match' => ['gov.membership.admin.*']]);
        $registry->register(['id' => 'govmem-override', 'group' => 'gov-access-security', 'order' => 40,
            'label' => 'office_membership::member.nav_overrides', 'icon' => 'fas fa-shield-alt',
            'route' => 'gov.membership.override.console', 'match' => ['gov.membership.override.*']]);

        // Navbar: the working-office chip, which shows and switches the working office.
        $registry->register(['id' => 'govmem-office-chip', 'zone' => 'context', 'view' => 'govmem::hooks.office-chip']);
        // User menu: my memberships.
        $registry->register(['id' => 'govmem-memberships', 'zone' => 'user', 'order' => 90,
            'label' => 'office_membership::member.menu_my_memberships', 'icon' => 'fas fa-id-badge',
            'route' => 'gov.membership.index']);
        // ＋ New: add a staff member to the working office (staff hub, invitation code or token).
        $registry->register(['id' => 'govmem-add-staff', 'zone' => 'actions', 'order' => 50,
            'label' => 'office_membership::member.nav_add_staff', 'icon' => 'fas fa-user-plus',
            'route' => 'gov.membership.admin.index']);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/notices.php', 'membership-notices');
        $this->app->bind(MembershipContextResolver::class,
            TenantMembershipResolver::class);
        // Bind the decoupled membership service helper
        $this->app->singleton(OfficeMembershipService::class, function ($app) {
            return new OfficeMembershipService;
        });

        // Bind the Clearance Engine & seed validation rules
        $this->app->singleton(ClearanceEngine::class, function ($app) {
            $engine = new ClearanceEngine;
            $engine->registerRule(new NoActiveAssetsRule);
            $engine->registerRule(new NoActiveRolesRule);

            return $engine;
        });
    }
}
