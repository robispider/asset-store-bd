<?php

namespace GovStore\UserOnboarding\Providers;

use App\Models\User;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
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

        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('user-onboarding', __DIR__.'/../resources/css/user-onboarding.css');
        }

        // Register the Observer to intercept core User creations
        User::observe(SnipeUserOnboardingObserver::class);

        // Register leaves in the GovStore navigation groups.
        $this->app->booted(function () {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->register(['id' => 'gov-onboard-queue', 'group' => 'gov-queues', 'order' => 30,
                'label' => 'govonboard::onboard.nav_onboarding_requests', 'icon' => 'fas fa-user-plus', 'route' => 'gov.onboard.index']);
            $registry->register(['id' => 'gov-onboard-mine', 'group' => 'gov-workspace', 'order' => 40,
                'label' => 'govonboard::onboard.my_title', 'icon' => 'fas fa-user-plus', 'route' => 'gov.onboard.mine']);
        });
    }
}
