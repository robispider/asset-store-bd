<?php

namespace GovStore\Organization\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Location;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Observers\IctJurisdictionObserver;
use GovStore\TenantScope\Navigation\MenuRegistry;

use GovStore\Organization\Models\CompanyAdmin;
use GovStore\Organization\Observers\CompanyAdminObserver;


class OrganizationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'organization_labels');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govorg');

        

        IctJurisdiction::observe(IctJurisdictionObserver::class);
        CompanyAdmin::observe(CompanyAdminObserver::class);

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

        // 1. Root Folder: Office Provisioning
        $registry->register([
            'id' => 'gov-org',
            'title' => __('organization_labels::orglabel.menu_provisioning_root'),
            'icon' => 'fas fa-sitemap fa-fw text-aqua',
            // Added company_admin so Ministry Administrators can see the provisioning folder
            'permission' => ['office_admin', 'ict_officer', 'company_admin'],
            'order' => 50,
        ]);

        // 2. Office Registry
        $registry->register([
            'id' => 'gov-org-registry',
            'parent' => 'gov-org',
            'title' => __('organization_labels::orglabel.menu_office_registry'),
            'icon' => 'fas fa-building fa-fw',
            'route' => 'gov.org.provisioning.index',
            // Added company_admin so Ministry Administrators can oversee all offices in their bounds
            'permission' => ['ict_officer', 'company_admin'],
            'order' => 10,
        ]);

        // 3. ICT Jurisdictions (Superadmin Only)
        $registry->register([
            'id' => 'gov-org-jurisdictions',
            'parent' => 'gov-org',
            'title' => __('organization_labels::orglabel.menu_ict_jurisdictions'),
            'icon' => 'fas fa-shield-alt fa-fw',
            'route' => 'gov.org.jurisdictions.index',
            'permission' => 'admin',
            'order' => 20,
        ]);

        // 4. Local Office Setup Checklist
        $registry->register([
            'id' => 'gov-org-setup',
            'parent' => 'gov-org',
            'title' => __('organization_labels::orglabel.menu_office_setup'),
            'icon' => 'fas fa-hotel fa-fw',
            'route' => 'gov.org.config.index',
            // Keep this scoped to office_admin only (Company admins oversee, but local admins configure their own staff)
            'permission' => 'office_admin',
            'order' => 30,
        ]);

        // 5. Government Directory
        $registry->register([
            'id' => 'gov-org-directory',
            'parent' => 'gov-org',
            'title' => __('organization_labels::orglabel.menu_gov_directory'),
            'icon' => 'fas fa-cloud-download-alt fa-fw text-green',
            'route' => 'gov.org.directory.index',
            'permission' => 'admin',
            'order' => 40,
        ]);

        // 6. Company Admins (Superadmin Only)
        $registry->register([
            'id' => 'gov-org-company-admins',
            'parent' => 'gov-org',
            'title' => __('organization_labels::orglabel.menu_company_admins'),
            'icon' => 'fas fa-university fa-fw text-purple',
            'route' => 'gov.org.company_admins.index',
            'permission' => 'admin',
            'order' => 25, 
        ]);
    }

    public function register()
    {
        //
    }
}