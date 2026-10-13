<?php

namespace GovStore\TenantScope\Providers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\User;
use GovStore\TenantScope\Compiler\NavigationHook;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\RequireGovAbility;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\TenantScope\Observers\TenantMutationObserver;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use GovStore\TenantScope\Scopes\TenantScope;
use GovStore\TenantScope\Scopes\UserScope;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\Theming\Facades\GsTheme;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TenantScopeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/abilities.php', 'govstore-abilities');
        $this->mergeConfigFrom(__DIR__.'/../config/execution.php', 'govstore-execution');
        $this->mergeConfigFrom(__DIR__.'/../config/navigation.php', 'govstore-navigation');
        $this->app->singleton(\GovStore\TenantScope\Services\SchemaKnowledge::class);
        $this->app->singleton(\GovStore\TenantScope\Services\TenantWorkerLifecycle::class);
        // Register the Central Navigation Registry as a shared singleton across all packages
        $this->app->singleton(MenuRegistry::class, function () {
            return new MenuRegistry;
        });

        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        // Merge the new capability configuration file cleanly
        $this->mergeConfigFrom(
            __DIR__.'/../config/permissions.php', 'govstore-permissions'
        );
    }

    public function boot()
    {
        \Illuminate\Support\Facades\Bus::pipeThrough([\GovStore\TenantScope\Http\Middleware\ExecuteTenantJob::class]);
        $lifecycle = $this->app->make(\GovStore\TenantScope\Services\TenantWorkerLifecycle::class);
        $this->app['events']->listen(\Illuminate\Queue\Events\JobProcessing::class,
            fn ($event) => $lifecycle->begin(str_starts_with($event->job->resolveName(), 'GovStore\\'), $event->connectionName === 'sync'));
        foreach ([\Illuminate\Queue\Events\JobProcessed::class, \Illuminate\Queue\Events\JobExceptionOccurred::class] as $event) {
            $this->app['events']->listen($event, fn () => $lifecycle->finish());
        }
        $this->app['events']->listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) use ($lifecycle) {
            app(\GovStore\TenantScope\Services\SchemaKnowledge::class)->clear();
            if (preg_match('/^(govstore:|gov-store:|gov-requests:|committee:)/', $event->command ?? '')
                && ! in_array($event->command, config('govstore-execution.global_commands', []), true)) {
                app(TenantContext::class)->reset();
                auth()->forgetUser();
                app(TenantContext::class)->isActive = true;
                throw new \Illuminate\Auth\Access\AuthorizationException('Declare the GovStore command execution boundary before running it.');
            }
            $lifecycle->begin(false, true);
        });
        $this->app['events']->listen(\Illuminate\Console\Events\CommandFinished::class, fn () => $lifecycle->finish());
        $this->app['router']->aliasMiddleware('gov.can', RequireGovAbility::class);
        foreach (array_keys(config('govstore-abilities', [])) as $ability) {
            Gate::define($ability, fn ($user) => app(GovAccess::class)->decide($user, $ability)->toGateResponse());
        }
        Blade::component('govscope::components.action', 'gov-action');
        // Attaches the navigation zones to Snipe-IT's layout at compile time (no core edits).
        Blade::precompiler(new NavigationHook);
        $this->loadRoutesFrom(__DIR__.'/../routes/access.php');
        // 0. Load Translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'tenantops');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govscope');

        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('tenant-scope', __DIR__.'/../resources/css/tenant-scope.css');
        }

        // Publish the configuration so it can be overridden in the root config directory if necessary
        $this->publishes([
            __DIR__.'/../config/permissions.php' => config_path('govstore-permissions.php'),
        ], 'config');

        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InitializeTenantContext::class);
        $router->pushMiddlewareToGroup('api', InitializeTenantContext::class);

        // Register the foundational GovStore navigation structure
        $this->registerBaseMenuStructure();

        // 1. Operational Models (Strict Physical Scoping ONLY)
        $operationalModels = [
            Asset::class,
            Consumable::class,
            Accessory::class,
            Component::class,
            License::class,
        ];

        // 2. Reference Models (Catalog Mapping Scoping)
        $referenceModels = [
            'categories' => Category::class,
            'models' => AssetModel::class,
            'suppliers' => Supplier::class,
            'manufacturers' => Manufacturer::class,
            'locations' => Location::class,
            'companies' => Company::class,
        ];

        // 3. Register Custom Hierarchical User Scope
        if (class_exists(User::class)) {
            User::addGlobalScope(new UserScope);
        }

        // 4. Register Operational Scopes
        foreach ($operationalModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new MinistryLocationScope);
            }
        }

        // 5. Register Reference Scopes
        foreach ($referenceModels as $type => $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope(new TenantScope($type));
            }
        }

        // =========================================================================
        // 6. OBSERVERS (REMOVED [\App\Models\User::class] from protected observers)
        // =========================================================================
        $allProtectedModels = array_merge($operationalModels, array_values($referenceModels));
        foreach ($allProtectedModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(TenantMutationObserver::class);
            }
        }
    }

    /**
     * Tenant-scope's own leaves and page slots. Groups are declared in config/navigation.php.
     * Labels are translation keys, resolved at render time in the user's language.
     */
    protected function registerBaseMenuStructure(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // Personal: top-right user menu.
        $registry->register(['id' => 'gov-access-my_access', 'zone' => 'user', 'order' => 10,
            'label' => 'tenantops::access.my_access', 'icon' => 'fas fa-key', 'route' => 'gov.access.index']);

        // Work queue.
        $registry->register(['id' => 'gov-access-requests', 'group' => 'gov-queues', 'order' => 40,
            'label' => 'tenantops::access.requests', 'icon' => 'fas fa-user-clock', 'route' => 'gov.access.requests.index',
            'match' => ['gov.access.requests.*', 'gov.access.national-review']]);

        // Access & security.
        foreach ([
            ['gov-access-matrix', 10, 'tenantops::access.matrix', 'fas fa-table', 'gov.access.matrix'],
            ['gov-access-audit', 20, 'tenantops::access.audit', 'fas fa-history', 'gov.access.audit'],
            ['gov-access-shadow', 30, 'tenantops::nav.enforcement_preview', 'fas fa-eye', 'gov.access.shadow'],
            ['gov-tenantscope-dashboard', 50, 'tenantops::nav.boundaries', 'fas fa-tachometer-alt', 'gov.scope.dashboard'],
            ['gov-tenantscope-config', 60, 'tenantops::nav.boundary_policy', 'fas fa-sliders-h', 'gov.scope.config'],
            ['gov-tenantscope-mappings', 70, 'tenantops::nav.boundary_explorer', 'fas fa-search-plus', 'gov.scope.mappings'],
        ] as [$id, $order, $label, $icon, $route]) {
            $registry->register(['id' => $id, 'group' => 'gov-access-security', 'order' => $order,
                'label' => $label, 'icon' => $icon, 'route' => $route]);
        }

        // Page slots.
        $registry->register(['id' => 'gov-access-rollout', 'zone' => 'content', 'view' => 'govscope::hooks.rollout']);
        $registry->register(['id' => 'gov-access-inline-denials', 'zone' => 'scripts', 'view' => 'govscope::hooks.unified-menu']);
    }
}
