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
use GovStore\CustomRequests\Services\RequestQueues;
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
        $queues = fn () => app(RequestQueues::class);

        // My workspace — every employee who may request items.
        $registry->register(['id' => 'gov-requests-catalog', 'group' => 'gov-workspace', 'order' => 10,
            'label' => 'requestlabels::requests.nav_request_items', 'icon' => 'fas fa-store',
            'route' => 'gov.requests.catalog', 'match' => ['gov.requests.catalog*', 'gov.requests.basket.*']]);
        $registry->register(['id' => 'gov-requests-my-requests', 'group' => 'gov-workspace', 'order' => 20,
            'label' => 'requestlabels::requests.nav_my_requests', 'icon' => 'fas fa-clipboard-list',
            'route' => 'gov.requests.user.index', 'match' => ['gov.requests.user.*']]);

        // Waiting for me — counted queues.
        $registry->register(['id' => 'gov-requests-approvals', 'group' => 'gov-queues', 'order' => 10,
            'label' => 'requestlabels::requests.nav_approvals', 'icon' => 'fas fa-clipboard-check',
            'route' => 'gov.requests.admin.index', 'match' => ['gov.requests.admin.show'],
            'badge' => fn () => $queues()->awaitingApproval(auth()->user())->count()]);
        $registry->register(['id' => 'gov-requests-fulfillment-queue', 'group' => 'gov-queues', 'order' => 20,
            'label' => 'requestlabels::requests.nav_to_fulfil', 'icon' => 'fas fa-shipping-fast',
            'route' => 'gov.requests.fulfillment.index', 'match' => ['gov.requests.fulfillment.*'],
            'badge' => fn () => $queues()->awaitingFulfilment(auth()->user())->count()]);

        // Store.
        $registry->register(['id' => 'gov-requests-fulfillment-register', 'group' => 'gov-store-ops', 'order' => 30,
            'label' => 'requestlabels::requests.nav_fulfilment_register', 'icon' => 'fas fa-archive',
            'route' => 'gov.requests.fulfillment_register.index', 'match' => ['gov.requests.fulfillment_register.*']]);

        // Rules & settings.
        $registry->register(['id' => 'gov-requests-policies', 'group' => 'gov-rules', 'order' => 10,
            'label' => 'requestlabels::requests.nav_request_policies', 'icon' => 'fas fa-tags',
            'route' => 'gov.requests.admin.policies.index', 'match' => ['gov.requests.admin.policies.*']]);

        // Top bar basket and its page widget.
        $registry->register(['id' => 'gov-requests-basket-nav', 'zone' => 'topbar', 'view' => 'govstore::hooks.basket-nav']);
        $registry->register(['id' => 'gov-requests-basket-widget', 'zone' => 'body-end', 'view' => 'govstore::hooks.basket-widget']);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/requests.php', 'govstore-requests');
        $this->commands([MaintainRequests::class,
            RequestReconciliation::class]);
    }
}
