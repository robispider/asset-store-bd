<?php

namespace GovStore\Tracking\Providers;

use GovStore\Metadata\Registry\MetadataRegistry;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
use GovStore\Tracking\Console\Commands\TrackingAuditCommand;
use GovStore\Tracking\Console\Commands\TrackingRebuildDeliveryFactsCommand;
use GovStore\Tracking\Console\Commands\TrackingRebuildProjectionsCommand;
use GovStore\Tracking\Events\AssetsReceivedViaGRN;
use GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme;
use GovStore\Tracking\Http\Middleware\InjectTrackingUi;
use GovStore\Tracking\Http\Middleware\SafeTrackingResponse;
use GovStore\Tracking\Listeners\AssociateAssetsToProgramme;
use GovStore\Tracking\Listeners\AssociateInventoryToProgramme;
use GovStore\Tracking\Metadata\TrackingMetadataProvider;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingTarget;
use GovStore\Tracking\Observers\ProjectionSourceObserver;
use GovStore\Tracking\Observers\TrackingAssociationObserver;
use GovStore\Tracking\Repositories\CachedTrackingProjectionRepository;
use GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tracking.php', 'tracking');
        $this->mergeConfigFrom(__DIR__.'/../config/abilities.php', 'govstore-abilities');
        $this->app->bind(
            TrackingProjectionRepositoryInterface::class,
            CachedTrackingProjectionRepository::class
        );
    }

    public function boot(): void
    {
        // 1. Corrected migration loading path to point to 'src/database/migrations'
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 2. Corrected view loading path to point to 'src/resources/views'
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govtracking');

        // 3. FIXED: Load Translations Namespace: 'govtracking'
        // This resolves 'govtracking::general.key' beautifully across both English and Bengali!
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'govtracking');

        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerObservers();
        $this->registerConsoleCommands();
        $this->registerMetadataBridge();
        $this->registerEvents();
        $this->registerThemeAssets();

        if ($this->app->bound(MenuRegistry::class)) {
            $this->registerTrackingMenuStructure();
        }
    }

    protected function registerRoutes(): void
    {
        $webRoutePath = __DIR__.'/../routes/web.php';
        $apiRoutePath = __DIR__.'/../routes/api.php';

        // Crash-proof Web UI Routes Loader
        if (file_exists($webRoutePath)) {
            Route::middleware(['web', 'auth', SafeTrackingResponse::class])
                ->prefix('gov-store/admin/tracking')
                ->name('gov.tracking.')
                ->group($webRoutePath);
        }

        // FIXED (Session-Auth API Loader): We load the Handshake API routes
        // under the 'web' and 'auth' session middleware instead of 'api/auth:api'.
        // This allows browser AJAX calls to seamlessly authenticate using the
        // storekeeper's session cookies, completely resolving the 403 Forbidden errors!
        if (file_exists($apiRoutePath)) {
            Route::middleware(['web', 'auth', SafeTrackingResponse::class])
                ->prefix('gov-store/api/tracking')
                ->name('gov.tracking.api.')
                ->group($apiRoutePath);
        }
    }

    protected function registerEvents(): void
    {
        $events = $this->app['events'];

        // 1. Dynamic, decoupled, polymorphic handler (The Future Standard)
        $events->listen(
            InventoryMaterializedAgainstProgramme::class,
            [AssociateInventoryToProgramme::class, 'handle']
        );

        // 2. Backward-compatible assets-only handler (The Current GRN Standard)
        $events->listen(
            AssetsReceivedViaGRN::class,
            [AssociateAssetsToProgramme::class, 'handle']
        );
    }

    protected function registerThemeAssets(): void
    {
        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('tracking', __DIR__.'/../resources/css/tracking.css');
            GsTheme::assets()->css('tracking', __DIR__.'/../resources/css/portfolio.css');
            GsTheme::assets()->css('tracking', __DIR__.'/../resources/css/report.css');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/matrix.js');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/task-form.js');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/workspace.js');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/monitor.js');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/portfolio.js');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/report.js');
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectTrackingUi::class);
    }

    protected function registerObservers(): void
    {
        TrackingAssociation::observe(TrackingAssociationObserver::class);
        TrackingCode::observe(ProjectionSourceObserver::class);
        TrackingTarget::observe(ProjectionSourceObserver::class);
    }

    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TrackingAuditCommand::class,
                TrackingRebuildDeliveryFactsCommand::class,
                TrackingRebuildProjectionsCommand::class, // Added Rebuilder Command
            ]);
        }
    }

    protected function registerMetadataBridge(): void
    {
        if ($this->app->bound(MetadataRegistry::class)) {
            $registry = $this->app->make(MetadataRegistry::class);
            $registry->register(new TrackingMetadataProvider);
        }
    }

    protected function registerTrackingMenuStructure(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // Programme tracking: assigned team members enter and manage their initiative workspaces.
        $registry->register([
            'id' => 'gov-tracking-initiatives',
            'group' => 'gov-tracking',
            'label' => 'govtracking::general.nav_initiatives',
            'icon' => 'fas fa-folder-open',
            'route' => 'gov.tracking.initiatives.index',
            'order' => 10,
            'match' => ['gov.tracking.initiatives.*', 'gov.tracking.tracking-codes.*', 'gov-store/admin/tracking/initiatives*'],
        ]);

        if (config('tracking.office_directory_enabled')) {
            $registry->register([
                'id' => 'gov-tracking-offices', 'group' => 'gov-tracking',
                'label' => 'govtracking::general.monitor.offices', 'icon' => 'fas fa-building',
                'route' => 'gov.tracking.offices.index', 'order' => 20,
                'match' => ['gov.tracking.offices.*', 'gov-store/admin/tracking/offices*'],
            ]);
        }

        // Rules & settings: tracking dictionaries.
        $registry->register([
            'id' => 'gov-tracking-config',
            'group' => 'gov-rules',
            'label' => 'govtracking::general.nav_tracking_settings',
            'icon' => 'fas fa-cog',
            'route' => 'gov.tracking.funding-types.index',
            'order' => 50,
            'match' => ['gov.tracking.funding-types.*', 'gov-store/admin/tracking/funding-types*'],
        ]);
    }
}
