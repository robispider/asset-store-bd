<?php

namespace GovStore\Tracking\Providers;

use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Observers\TrackingAssociationObserver;
use GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface;
use GovStore\Tracking\Repositories\CachedTrackingProjectionRepository;
use GovStore\Tracking\Repositories\EloquentTrackingProjectionRepository;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
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


           // 3. FIXED: Load Translations Namespace: 'govtracking'
        // This resolves 'govtracking::general.key' beautifully across both English and Bengali!
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'govtracking');
        
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
        $webRoutePath = __DIR__ . '/../routes/web.php';
        $apiRoutePath = __DIR__ . '/../routes/api.php';

        // Crash-proof Web UI Routes Loader
        if (file_exists($webRoutePath)) {
            Route::middleware(['web', 'auth', \GovStore\Tracking\Http\Middleware\SafeTrackingResponse::class])
                ->prefix('gov-store/admin/tracking')
                ->name('gov.tracking.')
                ->group($webRoutePath);
        }

        // FIXED (Session-Auth API Loader): We load the Handshake API routes 
        // under the 'web' and 'auth' session middleware instead of 'api/auth:api'.
        // This allows browser AJAX calls to seamlessly authenticate using the 
        // storekeeper's session cookies, completely resolving the 403 Forbidden errors!
        if (file_exists($apiRoutePath)) {
            Route::middleware(['web', 'auth', \GovStore\Tracking\Http\Middleware\SafeTrackingResponse::class])
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
            \GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme::class,
            [\GovStore\Tracking\Listeners\AssociateInventoryToProgramme::class, 'handle']
        );

        // 2. Backward-compatible assets-only handler (The Current GRN Standard)
        $events->listen(
            \GovStore\Tracking\Events\AssetsReceivedViaGRN::class,
            [\GovStore\Tracking\Listeners\AssociateAssetsToProgramme::class, 'handle']
        );
    }
    protected function registerThemeAssets(): void
    {
        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('tracking', __DIR__.'/../resources/css/tracking.css');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/matrix.js');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/task-form.js');
            GsTheme::assets()->js('tracking', __DIR__.'/../resources/js/workspace.js');
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', \GovStore\Tracking\Http\Middleware\InjectTrackingUi::class);
    }

    protected function registerObservers(): void
    {
        TrackingAssociation::observe(TrackingAssociationObserver::class);
        \GovStore\Tracking\Models\TrackingCode::observe(\GovStore\Tracking\Observers\ProjectionSourceObserver::class);
        \GovStore\Tracking\Models\TrackingTarget::observe(\GovStore\Tracking\Observers\ProjectionSourceObserver::class);
    }

   protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \GovStore\Tracking\Console\Commands\TrackingAuditCommand::class,
                \GovStore\Tracking\Console\Commands\TrackingRebuildProjectionsCommand::class, // Added Rebuilder Command
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

        // Programme tracking: assigned team members enter and manage their initiative workspaces.
        $registry->register([
            'id' => 'gov-tracking-initiatives',
            'group' => 'gov-tracking',
            'label' => 'govtracking::general.nav_initiatives',
            'icon' => 'fas fa-folder-open',
            'route' => 'gov.tracking.initiatives.index',
            'order' => 10,
            'permission' => ['admin', 'company_admin', 'project_member'],
            'match' => ['gov.tracking.initiatives.*', 'gov.tracking.tracking-codes.*', 'gov-store/admin/tracking/initiatives*'],
        ]);

        // Rules & settings: tracking dictionaries.
        $registry->register([
            'id' => 'gov-tracking-config',
            'group' => 'gov-rules',
            'label' => 'govtracking::general.nav_tracking_settings',
            'icon' => 'fas fa-cog',
            'route' => 'gov.tracking.funding-types.index',
            'order' => 50,
            'permission' => 'admin',
            'strict' => true,
            'match' => ['gov.tracking.funding-types.*', 'gov-store/admin/tracking/funding-types*'],
        ]);
    }
}
