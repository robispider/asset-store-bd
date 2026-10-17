<?php

namespace GovStore\AssetTagging\Providers;

use App\Models\Asset;
use App\Models\Setting;
use GovStore\AssetTagging\Capabilities\GovtTagCapability;
use GovStore\AssetTagging\Console\Commands\CountersCommand;
use GovStore\AssetTagging\Console\Commands\PreflightCommand;
use GovStore\AssetTagging\Guards\ManagedTagGuard;
use GovStore\AssetTagging\Metadata\CompanyCodeListsSchema;
use GovStore\AssetTagging\Metadata\EnablementSchema;
use GovStore\AssetTagging\Metadata\OfficeFactsSchema;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\AssetTagging\Services\TagIssuanceService;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\StoreOperations\Services\CapabilityRegistry;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Facades\GsTheme;
use Illuminate\Support\ServiceProvider;

class AssetTaggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/asset-tagging.php', 'asset-tagging');
        // Merged in register() so tenant-scope (boot) defines the Gates and lists them in the Access matrix.
        $this->mergeConfigFrom(__DIR__.'/../config/abilities.php', 'govstore-abilities');
        // One posting's reservations live on the issuance service for the length of the request.
        $this->app->singleton(TagIssuanceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'asset-tagging');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'asset-tagging');

        if ($this->app->bound('gs.theme')) {
            GsTheme::assets()->css('asset-tagging', __DIR__.'/../resources/css/asset-tagging.css');
        }

        // Typed metadata namespaces (gov_metadata_values).
        $store = $this->app->make(MetadataValueStore::class);
        $store->register(OfficeTagFacts::CODELISTS, CompanyCodeListsSchema::class);
        $store->register(OfficeTagFacts::OFFICE, OfficeFactsSchema::class);
        $store->register(OfficeTagFacts::ENABLEMENT, EnablementSchema::class);

        // The GRN capability, enforced by default for asset models; it does nothing unless the office is enabled.
        CapabilityRegistry::extend(GovtTagCapability::CODE, GovtTagCapability::class, [
            'name' => 'Issue Govt. Tags', 'group' => 'Identification',
            'name_key' => 'asset-tagging::tagging.capability.name', 'desc_key' => 'asset-tagging::tagging.capability.desc',
        ], ['assetmodel']);

        // Guards cover the native UI, API and importer, like store-operations' GRN-only intake guards.
        Asset::saving(fn (Asset $asset) => app(ManagedTagGuard::class)->asset($asset));
        Setting::saving(fn (Setting $setting) => app(ManagedTagGuard::class)->setting($setting));

        if ($this->app->runningInConsole()) {
            $this->commands([PreflightCommand::class, CountersCommand::class]);
        }

        $this->app->booted(fn () => $this->registerMenu());
    }

    private function registerMenu(): void
    {
        $registry = $this->app->make(MenuRegistry::class);
        // Visibility follows each route's gov.can ability.
        $registry->register(['id' => 'asset-tags-settings', 'group' => 'gov-rules', 'order' => 40,
            'label' => 'asset-tagging::tagging.nav.settings', 'icon' => 'fas fa-tags',
            'route' => 'asset-tags.office.show', 'match' => ['asset-tags.office.*']]);
        // Company administrators keep the code lists with the other organisation settings.
        $registry->register(['id' => 'asset-tags-codes', 'group' => 'gov-offices', 'order' => 70,
            'label' => 'asset-tagging::tagging.nav.codes', 'icon' => 'fas fa-list-ol',
            'route' => 'asset-tags.codes.show', 'match' => ['asset-tags.codes.*']]);
        $registry->register(['id' => 'asset-tags-lookup', 'group' => 'gov-store-ops', 'order' => 50,
            'label' => 'asset-tagging::tagging.nav.lookup', 'icon' => 'fas fa-magnifying-glass',
            'route' => 'asset-tags.lookup', 'match' => ['asset-tags.lookup']]);
        $registry->register(['id' => 'asset-tags-enablement', 'group' => 'gov-system', 'order' => 40,
            'label' => 'asset-tagging::tagging.nav.enablement', 'icon' => 'fas fa-toggle-on',
            'route' => 'asset-tags.enablement.index', 'match' => ['asset-tags.enablement.*']]);
    }
}
