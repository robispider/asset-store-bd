<?php

namespace GovStore\Metadata\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use GovStore\Metadata\Registry\MetadataRegistry;
use GovStore\Metadata\Compiler\MetadataCompiler;
use GovStore\Metadata\Services\HierarchicalResolver;
use GovStore\Metadata\Services\ConvergenceEngine;
use GovStore\Metadata\Services\MetadataHealthService;
use GovStore\Metadata\Providers\GovStoreBaselineProvider;
use GovStore\Metadata\Providers\LaptopCategoryProvider;
use GovStore\Metadata\Providers\PoliceDepartmentProvider;
use GovStore\Metadata\Console\Commands\SyncMetadataCommand;
use GovStore\Metadata\Console\Commands\ConvergeMetadataCommand;
use GovStore\Metadata\Console\Commands\HealthMetadataCommand;
use GovStore\Metadata\Events\AssetModelCreated;
use GovStore\Metadata\Listeners\ConfigureModelMetadata;
use GovStore\Metadata\Observers\AssetModelObserver;
use GovStore\TenantScope\Navigation\MenuRegistry;

class MetadataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetadataRegistry::class, function () {
            return new MetadataRegistry();
        });

        $this->app->singleton(MetadataCompiler::class, function () {
            return new MetadataCompiler();
        });

        $this->app->singleton(HierarchicalResolver::class, function ($app) {
            return new HierarchicalResolver($app->make(MetadataRegistry::class));
        });

        $this->app->singleton(ConvergenceEngine::class, function ($app) {
            return new ConvergenceEngine(
                $app->make(HierarchicalResolver::class),
                $app->make(MetadataCompiler::class),
                $app->make(MetadataRegistry::class)
            );
        });

        $this->app->singleton(MetadataHealthService::class, function ($app) {
            return new MetadataHealthService(
                $app->make(MetadataRegistry::class),
                $app->make(HierarchicalResolver::class)
            );
        });
    }

    public function boot(): void
    {
        // Traverses up one level from 'src/Providers' to 'src'
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'govmeta');

        $registry = $this->app->make(MetadataRegistry::class);
        $registry->register(new GovStoreBaselineProvider());
        $registry->register(new LaptopCategoryProvider());
        $registry->register(new PoliceDepartmentProvider());

        if (class_exists(\App\Models\AssetModel::class)) {
            \App\Models\AssetModel::observe(AssetModelObserver::class);
        }

        Event::listen(AssetModelCreated::class, ConfigureModelMetadata::class);

        // Zero-Touch UI Integration
        if ($this->app->bound(MenuRegistry::class)) {
            $menuRegistry = $this->app->make(MenuRegistry::class);
            $menuRegistry->register([
                'id'         => 'gov-metadata-health',
                'parent'     => 'gov-tenantscope-root',
                'title'      => 'Metadata Health Diagnostics',
                'icon'       => 'fas fa-heartbeat text-red',
                'route'      => 'gov.meta.health',
                'permission' => 'admin',
                'order'      => 40,
            ]);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncMetadataCommand::class,
                ConvergeMetadataCommand::class,
                HealthMetadataCommand::class,
            ]);
        }
    }
}