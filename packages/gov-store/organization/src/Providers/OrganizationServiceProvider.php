<?php

namespace GovStore\Organization\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Location;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Observers\IctJurisdictionObserver;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;

use GovStore\Organization\Models\CompanyAdmin;
use GovStore\Organization\Observers\CompanyAdminObserver;


class OrganizationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerGeoView();
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'organization_labels');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'govorg');

        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('organization', __DIR__.'/../resources/css/organization.css');
        }

        

        IctJurisdiction::observe(IctJurisdictionObserver::class);
        CompanyAdmin::observe(CompanyAdminObserver::class);

        Location::resolveRelationUsing('profile', function ($locationModel) {
            return $locationModel->hasOne(LocationProfile::class, 'location_id', 'id');
        });

        // Register menus in the central registry
        $this->registerNavigationMenus();
    }

    protected function registerNavigationMenus(): void
    {
        $registry = $this->app->make(MenuRegistry::class);

        // Offices & staff. Visibility comes from each route's gov.can ability (menu = gate).
        // My office setup stays strict: local admins configure their own office, super admins don't.
        $registry->register(['id' => 'gov-org-setup', 'group' => 'gov-offices', 'order' => 10, 'strict' => true,
            'label' => 'organization_labels::orglabel.nav_office_setup', 'icon' => 'fas fa-hotel', 'route' => 'gov.org.config.index']);
        foreach ([
            ['gov-org-registry', 30, 'nav_office_registry', 'fas fa-building', 'gov.org.provisioning.index', ['gov.org.provisioning.*', 'gov.org.hub.*']],
            ['gov-org-company-admins', 40, 'nav_ministry_admins', 'fas fa-university', 'gov.org.company_admins.index', []],
            ['gov-org-jurisdictions', 50, 'nav_ict_jurisdictions', 'fas fa-shield-alt', 'gov.org.jurisdictions.index', []],
            ['gov-org-directory', 60, 'nav_directory_import', 'fas fa-cloud-download-alt', 'gov.org.directory.index', []],
        ] as [$id, $order, $label, $icon, $route, $match]) {
            $registry->register(['id' => $id, 'group' => 'gov-offices', 'order' => $order,
                'label' => 'organization_labels::orglabel.'.$label, 'icon' => $icon, 'route' => $route, 'match' => $match]);
        }
    }

    public function register()
    {
        $this->app->bind(\GovStore\TenantScope\Contracts\OrganizationContextResolver::class,
            \GovStore\Organization\Services\TenantOrganizationResolver::class);
    }

    /** Office network source for the national geo monitor, only when geo-view is installed. */
    protected function registerGeoView(): void
    {
        if ($this->app->bound(\GovStore\GeoView\Registry\GeoSourceRegistry::class)) {
            $this->app->make(\GovStore\GeoView\Registry\GeoSourceRegistry::class)
                ->register(\GovStore\Organization\Geo\OrganizationOfficesGeoSource::class, 'organization.offices');
            $this->app->make(\GovStore\GeoView\Registry\GeoPresetRegistry::class)
                ->merge(require __DIR__.'/../config/geo-view-presets.php');
        }
    }
}
