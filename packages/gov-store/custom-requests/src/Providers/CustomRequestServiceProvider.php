<?php

namespace GovStore\CustomRequests\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Relations\Relation;
use GovStore\CustomRequests\Events\ItemApproved;
use GovStore\CustomRequests\Listeners\ProcessItemCheckout;
use GovStore\TenantScope\Navigation\MenuRegistry;

class CustomRequestServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 0. Load Translations (Namespace: requestlabels)
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'requestlabels');

        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 2. Load Web Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // 3. Load Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govstore');

        // 4. Register Event Listeners
        Event::listen(
            ItemApproved::class,
            ProcessItemCheckout::class
        );
 // 5. RESTORED: Register the clean Widget Injection Middleware (web routing group)
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', \GovStore\CustomRequests\Http\Middleware\InjectGovStoreUi::class);

        Relation::morphMap([
            'asset'       => \App\Models\Asset::class,
            'accessory'   => \App\Models\Accessory::class,
            'consumable'  => \App\Models\Consumable::class,
            'license'     => \App\Models\License::class,
        ]);

        // 7. Register navigation menus in the Central Menu Registry
        $this->registerNavigationMenus();
    }

    protected function registerNavigationMenus(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // 1. Public — available to all authenticated employees
        $registry->register([
            'id'     => 'gov-requests-catalog',
            'parent' => 'gov-store',
            'title'  => 'Browse Catalog',
            'icon'   => 'fas fa-store text-green',
            'route'  => 'gov.requests.catalog',
            'order'  => 5,
        ]);

        // 2. Public — track own submitted requests
        $registry->register([
            'id'     => 'gov-requests-my-requests',
            'parent' => 'gov-store',
            'title'  => 'Track My Requests',
            'icon'   => 'fas fa-clipboard-list text-blue',
            'route'  => 'gov.requests.user.index',
            'order'  => 8,
        ]);

        // 3. RESTORED — approval queue, gated to approver role in active office
        $registry->register([
            'id'         => 'gov-requests-approvals',
            'parent'     => 'gov-store',
            'title'      => 'Gov Approvals',
            'icon'       => 'fas fa-clipboard-check text-yellow',
            'route'      => 'gov.requests.admin.index',
            'permission' => 'approver',
            'order'      => 15,
        ]);

        // 4. Storekeeper — fulfillment queue
        $registry->register([
            'id'         => 'gov-requests-fulfillment-queue',
            'parent'     => 'gov-store',
            'title'      => 'Fulfillment Queue',
            'icon'       => 'fas fa-shipping-fast text-red',
            'route'      => 'gov.requests.fulfillment.index',
            'permission' => 'storekeeper',
            'order'      => 35,
        ]);

        // 5. Fulfillment Register — visible to Storekeepers, Approvers, and Office Admins
        $registry->register([
            'id'         => 'gov-requests-fulfillment-register',
            'parent'     => 'gov-store',
            'title'      => 'Fulfillment Register',
            'icon'       => 'fas fa-archive text-green',
            'route'      => 'gov.requests.fulfillment_register.index',
            'permission' => ['storekeeper', 'approver', 'office_admin'],
            'order'      => 36,
        ]);
    }

    public function register()
    {
        //
    }
}