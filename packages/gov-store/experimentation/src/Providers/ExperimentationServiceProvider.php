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
use Illuminate\Support\Facades\View;
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
        $registered = false;
        View::composer('*', function () use (&$registered) {
            if (! $registered && config('govstore-experiments.enabled') && ! app()->environment('production') && auth()->check() && auth()->user()->isSuperUser()) {
                app(MenuRegistry::class)->register(['id' => 'gov-experiments', 'parent' => 'gov-tenantscope-root', 'title' => __('experiments::ui.title'),
                    'icon' => 'fas fa-flask fa-fw', 'route' => 'gov.experiments.index', 'order' => 95]);
                $registered = true;
            }
        });
        if ($this->app->runningInConsole()) {
            $this->commands([ExperimentCommand::class, ExperimentWorkerCommand::class]);
        }
    }
}
