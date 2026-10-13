<?php

namespace GovStore\Experimentation\Providers;

use GovStore\Experimentation\Console\ExperimentCommand;
use GovStore\Experimentation\Console\ExperimentWorkerCommand;
use GovStore\Experimentation\Services\ExperimentAccess;
use GovStore\Experimentation\Services\RecordRegistry;
use GovStore\TenantScope\Navigation\MenuRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ExperimentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecordRegistry::class);
        config(['queue.connections.govstore-experiments' => ['driver' => 'database', 'connection' => null,
            'table' => 'gov_experiment_jobs', 'queue' => config('govstore-experiments.queue'), 'retry_after' => 3900, 'after_commit' => true]]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'experiments');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'experiments');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        Gate::define('experiments.manage', function ($user) {
            try {
                app(ExperimentAccess::class)->authorize($user);

                return true;
            } catch (AuthorizationException $e) {
                return false;
            }
        });
        Event::listen('eloquent.created: *', function ($event, $payload) {
            app(RecordRegistry::class)->created($payload[0]);
        });
        // Suppress outbound fixture notifications only while this package is populating.
        foreach ([NotificationSending::class, MessageSending::class] as $event) {
            Event::listen($event, fn () => app(RecordRegistry::class)->current ? false : null);
        }
        app(MenuRegistry::class)->register(['id' => 'gov-experiments', 'group' => 'gov-system', 'order' => 30,
            'label' => 'experiments::ui.title', 'icon' => 'fas fa-vial', 'route' => 'gov.experiments.index',
            'match' => ['gov.experiments.*'], 'permission' => 'superuser',
            'visible' => fn () => config('govstore-experiments.enabled') && ! app()->environment('production')]);
        if ($this->app->runningInConsole()) {
            $this->commands([ExperimentCommand::class, ExperimentWorkerCommand::class]);
        }
    }
}
