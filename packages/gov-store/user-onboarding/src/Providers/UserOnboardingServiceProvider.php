<?php

namespace GovStore\UserOnboarding\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use GovStore\UserOnboarding\Observers\SnipeUserOnboardingObserver;
use GovStore\TenantScope\Navigation\MenuRegistry;

class UserOnboardingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // Load package routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load package views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govonboard');

        // Register the Observer to intercept core User creations
        User::observe(SnipeUserOnboardingObserver::class);

        // Register menu item in Central Sidebar Registry
        $this->app->booted(function () {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->register([
                'id'         => 'gov-onboard-queue',
                'parent'     => 'gov-org', // Placed under the Office Provisioning parent directory
                'title'      => 'Onboarding Queue',
                'icon'       => 'fas fa-user-plus text-red',
                'route'      => 'gov.onboard.index',
                'permission' => ['admin','ict_officer'], // Gated for administrators/officers
                'order'      => 35,
            ]);
        });
    }
}
