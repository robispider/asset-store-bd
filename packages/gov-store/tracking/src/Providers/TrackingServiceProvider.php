<?php

namespace GovStore\Tracking\Providers;

use App\Models\Asset;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Observers\AssetObserver;
use GovStore\Tracking\Observers\TrackingAssociationObserver;
use GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface;
use GovStore\Tracking\Repositories\CachedTrackingProjectionRepository;
use GovStore\Tracking\Repositories\EloquentTrackingProjectionRepository;
use GovStore\TenantScope\Navigation\MenuRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TrackingProjectionRepositoryInterface::class,
            CachedTrackingProjectionRepository::class
        );
    }

    public function boot(): void
    {
        // 1. Corrected migration loading path to point to 'src/database/migrations'
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // 2. Corrected view loading path to point to 'src/resources/views'
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'govtracking');

        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerObservers();
        $this->registerConsoleCommands();
        $this->registerMetadataBridge();

        if ($this->app->bound(MenuRegistry::class)) {
            $this->registerTrackingMenuStructure();
        }
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('gov-store/admin/tracking')
            ->name('gov.tracking.')
            ->group(__DIR__ . '/../routes/web.php');
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', \GovStore\Tracking\Http\Middleware\InjectTrackingUi::class);
    }

    protected function registerObservers(): void
    {
        Asset::observe(AssetObserver::class);
        TrackingAssociation::observe(TrackingAssociationObserver::class);
    }

    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \GovStore\Tracking\Console\Commands\TrackingAuditCommand::class,
            ]);
        }
    }

    protected function registerMetadataBridge(): void
    {
        if ($this->app->bound(\GovStore\Metadata\Registry\MetadataRegistry::class)) {
            $registry = $this->app->make(\GovStore\Metadata\Registry\MetadataRegistry::class);
            $registry->register(new \GovStore\Tracking\Metadata\TrackingMetadataProvider());
        }
    }

    protected function registerTrackingMenuStructure(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        $registry->register([
            'id'    => 'gov-tracking-root',
            'title' => 'Program & Reference Tracking',
            'icon'  => 'fas fa-map-signs text-orange',
            'order' => 35,
            'permission' => ['admin', 'company_admin', 'ict_officer', 'office_admin', 'storekeeper', 'approver'],
        ]);

        $registry->register([
            'id'         => 'gov-tracking-dashboard',
            'parent'     => 'gov-tracking-root',
            'title'      => 'Operational Dashboards',
            'icon'       => 'fas fa-tachometer-alt text-aqua',
            'route'      => 'gov.tracking.references.index',
            'order'      => 10,
            'permission' => ['admin', 'company_admin', 'ict_officer', 'office_admin', 'storekeeper', 'approver'],
            'active_patterns' => [
                'gov-store/admin/tracking/references*',
            ],
        ]);

        $registry->register([
            'id'         => 'gov-tracking-types-config',
            'parent'     => 'gov-tracking-root',
            'title'      => 'Configure Reference Types',
            'icon'       => 'fas fa-sliders-h text-yellow',
            'route'      => 'gov.tracking.types.index',
            'order'      => 20,
            'permission' => ['admin', 'company_admin'],
            'active_patterns' => [
                'gov-store/admin/tracking/types*',
            ],
        ]);
    }
}