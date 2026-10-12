<?php

namespace GovStore\CustomRequests\Providers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use GovStore\CustomRequests\Console\Commands\MaintainRequests;
use GovStore\CustomRequests\Console\Commands\RequestReconciliation;
use GovStore\CustomRequests\Models\BasketItem;
use GovStore\CustomRequests\Rules\NoPendingRequestsRule;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class CustomRequestServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->callAfterResolving(ClearanceEngine::class, fn ($engine) => $engine->registerRule(new NoPendingRequestsRule));

        // 0. Load Translations (Namespace: requestlabels)
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'requestlabels');

        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 2. Load Web Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // 3. Load Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govstore');

        // Register package styles with the shared theme build. The package still works
        // without theming installed, as with other optional GovStore integrations.
        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('custom-requests', __DIR__.'/../resources/css/custom-requests.css');
        }

        // Compose only pages using the shared application layout.
        View::composer('layouts/default', function ($view) {
            $count = auth()->check() ? BasketItem::whereHas('basket', fn ($q) => $q
                ->where('user_id', auth()->id())->where('status', 'draft')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())))->count() : 0;
            $view->with('govBasketCount', $count);
        });
        Relation::morphMap([
            'asset' => Asset::class,
            'assetmodel' => AssetModel::class,
            'asset_model' => AssetModel::class,
            'accessory' => Accessory::class,
            'consumable' => Consumable::class,
            'license' => License::class,
            'component' => Component::class,
        ]);

        // 7. Register navigation menus in the Central Menu Registry
        $this->registerNavigationMenus();
    }

    protected function registerNavigationMenus(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        $registry->register(['id' => 'gov-requests-policies', 'parent' => 'gov-store',
            'title' => __('requestlabels::requests.policies_title'), 'icon' => 'fas fa-tags',
            'route' => 'gov.requests.admin.policies.index', 'permission' => 'requests.configure', 'order' => 37]);

        // 1. Public — available to all authenticated employees
        $registry->register([
            'id' => 'gov-requests-catalog',
            'parent' => 'gov-store',
            'title' => __('requestlabels::requests.serviceprovider_menu_browse_catalog'),
            'icon' => 'fas fa-store text-green',
            'route' => 'gov.requests.catalog',
            'order' => 5,
        ]);

        // 2. Public — track own submitted requests
        $registry->register([
            'id' => 'gov-requests-my-requests',
            'parent' => 'gov-store',
            'title' => __('requestlabels::requests.serviceprovider_menu_track_my_requests'),
            'icon' => 'fas fa-clipboard-list text-blue',
            'route' => 'gov.requests.user.index',
            'order' => 8,
        ]);

        // 3. RESTORED — approval queue, gated to approver role in active office
        $registry->register([
            'id' => 'gov-requests-approvals',
            'parent' => 'gov-store',
            'title' => __('requestlabels::requests.serviceprovider_menu_gov_approvals'),
            'icon' => 'fas fa-clipboard-check text-yellow',
            'route' => 'gov.requests.admin.index',
            'permission' => 'approver',
            'order' => 15,
        ]);

        // 4. Storekeeper — fulfillment queue
        $registry->register([
            'id' => 'gov-requests-fulfillment-queue',
            'parent' => 'gov-store',
            'title' => __('requestlabels::requests.serviceprovider_menu_fulfillment_queue'),
            'icon' => 'fas fa-shipping-fast text-red',
            'route' => 'gov.requests.fulfillment.index',
            'permission' => 'storekeeper',
            'order' => 35,
        ]);

        // 5. Fulfillment Register — visible to Storekeepers, Approvers, and Office Admins
        $registry->register([
            'id' => 'gov-requests-fulfillment-register',
            'parent' => 'gov-store',
            'title' => __('requestlabels::requests.serviceprovider_menu_fulfillment_register'),
            'icon' => 'fas fa-archive text-green',
            'route' => 'gov.requests.fulfillment_register.index',
            'permission' => ['storekeeper', 'approver', 'office_admin'],
            'order' => 36,
        ]);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/requests.php', 'govstore-requests');
        $this->commands([MaintainRequests::class,
            RequestReconciliation::class]);
    }
}
