<?php

namespace GovStore\Organization\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Location;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Observers\IctJurisdictionObserver;
use GovStore\TenantScope\Navigation\MenuRegistry;

class OrganizationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govorg');

        IctJurisdiction::observe(IctJurisdictionObserver::class);

        Location::resolveRelationUsing('profile', function ($locationModel) {
            return $locationModel->hasOne(LocationProfile::class, 'location_id', 'id');
        });

        Location::resolveRelationUsing('roles', function ($locationModel) {
            return $locationModel->hasOne(LocationRole::class, 'location_id', 'id');
        });

        // Register menus in the central registry
        $this->registerNavigationMenus();
    }

    protected function registerNavigationMenus(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // 1. Root Folder: Office Provisioning (Visible if user is Admin, ICT Officer, or Office Admin)
        $registry->register([
            'id' => 'gov-org',
            'title' => 'Office Provisioning',
            'icon' => 'fas fa-sitemap fa-fw text-aqua',
            'permission' => ['office_admin', 'ict_officer'], // Multi-role support
            'order' => 50,
        ]);

        // 2. Office Registry
        $registry->register([
            'id' => 'gov-org-registry',
            'parent' => 'gov-org',
            'title' => 'Office Registry',
            'icon' => 'fas fa-building fa-fw',
            'route' => 'gov.org.provisioning.index',
            'permission' => 'ict_officer',
            'order' => 10,
        ]);

        // 3. ICT Jurisdictions (Superadmin Only)
        $registry->register([
            'id' => 'gov-org-jurisdictions',
            'parent' => 'gov-org',
            'title' => 'ICT Jurisdictions',
            'icon' => 'fas fa-shield-alt fa-fw',
            'route' => 'gov.org.jurisdictions.index',
            'permission' => 'admin',
            'order' => 20,
        ]);

        // 4. Local Office Setup Checklist
        $registry->register([
            'id' => 'gov-org-setup',
            'parent' => 'gov-org',
            'title' => 'My Office Setup',
            'icon' => 'fas fa-hotel fa-fw',
            'route' => 'gov.org.config.index',
            'permission' => 'office_admin',
            'order' => 30,
        ]);
    }

    public function register()
    {
        //
    }
}