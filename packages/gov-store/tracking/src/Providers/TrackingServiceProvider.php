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


           // 3. FIXED: Load Translations Namespace: 'govtracking'
        // This resolves 'govtracking::general.key' beautifully across both English and Bengali!
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'govtracking');
        
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerObservers();
        $this->registerConsoleCommands();
        $this->registerMetadataBridge();
        $this->registerEvents();

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
            Route::middleware(['web', 'auth'])
                ->prefix('gov-store/admin/tracking')
                ->name('gov.tracking.')
                ->group($webRoutePath);
        }

        // FIXED (Session-Auth API Loader): We load the Handshake API routes 
        // under the 'web' and 'auth' session middleware instead of 'api/auth:api'.
        // This allows browser AJAX calls to seamlessly authenticate using the 
        // storekeeper's session cookies, completely resolving the 403 Forbidden errors!
        if (file_exists($apiRoutePath)) {
            Route::middleware(['web', 'auth'])
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
        $registry = $this->app->make(\GovStore\TenantScope\Navigation\MenuRegistry::class);

        // 1. ROOT CATEGORY
        $registry->register([
            'id'    => 'gov-tracking-root',
            'title' => 'Programme Tracking',
            'icon'  => 'fas fa-map-signs text-orange',
            'order' => 35,
            'permission' => ['admin', 'company_admin', 'ict_officer', 'office_admin', 'storekeeper', 'approver'],
        ]);

        // 2. ACTIVE INITIATIVES (The Workspace Entry)
        $registry->register([
            'id'         => 'gov-tracking-initiatives',
            'parent'     => 'gov-tracking-root',
            'title'      => 'Active Initiatives',
            'icon'       => 'fas fa-folder-open text-aqua',
            'route'      => 'gov.tracking.initiatives.index',
            'order'      => 10,
            'permission' => ['admin', 'company_admin', 'ict_officer', 'office_admin', 'storekeeper', 'approver'],
            'active_patterns' => ['gov-store/admin/tracking/initiatives*'],
        ]);

        // 3. SYSTEM CONFIGURATION (Dictionaries)
        $registry->register([
            'id'         => 'gov-tracking-config',
            'parent'     => 'gov-tracking-root',
            'title'      => 'System Configuration',
            'icon'       => 'fas fa-cog text-yellow',
            'route'      => 'gov.tracking.funding-types.index',
            'order'      => 40,
            'permission' => ['admin', 'company_admin'],
            'active_patterns' => ['gov-store/admin/tracking/funding-types*'],
        ]);
    }
}