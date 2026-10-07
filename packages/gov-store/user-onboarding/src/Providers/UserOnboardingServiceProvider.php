<?php

namespace GovStore\UserOnboarding\Providers;

use App\Models\User;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\UserOnboarding\Observers\SnipeUserOnboardingObserver;
use Illuminate\Support\ServiceProvider;

class UserOnboardingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'govonboard');
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
                'id' => 'gov-onboard-queue',
                'parent' => 'gov-org', // Placed under the Office Provisioning parent directory
                'title' => 'govonboard::onboard.title',
                'icon' => 'fas fa-user-plus text-red',
                'route' => 'gov.onboard.index',
                'permission' => 'onboarding.manage',
                'order' => 35,
            ]);
            $registry->register(['id' => 'gov-onboard-mine', 'title' => 'govonboard::onboard.my_title',
                'icon' => 'fas fa-user-plus', 'route' => 'gov.onboard.mine', 'permission' => 'onboarding.self', 'order' => 36]);
        });
    }
}
