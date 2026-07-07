<?php

namespace GovStore\Organization\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Location;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Http\Middleware\InjectOrganizationUi;
use GovStore\Organization\Http\Middleware\EnsureOfficeIsOperational;

class OrganizationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 1. Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 2. Load Package Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // 3. Load Views (Registers 'govorg::' namespace)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govorg');

        // 4. ZERO-TOUCH ORGANIZATION UI INJECTION
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', InjectOrganizationUi::class);

        // 5. THE INTEGRATION HANDSHAKE
        $router->pushMiddlewareToGroup('web', EnsureOfficeIsOperational::class);

        // 6. DYNAMIC COUPLING SHIELD (The runtime relations)
        // Dynamically injects 'profile' relationship into Snipe-IT's core Location model
        Location::resolveRelationUsing('profile', function ($locationModel) {
            return $locationModel->hasOne(LocationProfile::class, 'location_id', 'id');
        });

        // Dynamically injects 'roles' relationship into Snipe-IT's core Location model
        Location::resolveRelationUsing('roles', function ($locationModel) {
            return $locationModel->hasOne(LocationRole::class, 'location_id', 'id');
        });
    }

    public function register()
    {
        //
    }
}