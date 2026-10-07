<?php

namespace GovStore\Theming;

use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\Theming\Access\ThemeAccess;
use GovStore\Theming\Assets\AssetRegistry;
use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Build\ThemeCompiler;
use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Compiler\LayoutHook;
use GovStore\Theming\Themes\Manifest;
use GovStore\Theming\Themes\ThemeRepository;
use GovStore\Theming\Themes\ThemeResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ThemingServiceProvider extends ServiceProvider
{
    public const PACKAGE_PATH = __DIR__.'/..';

    public function register(): void
    {
        $this->mergeConfigFrom(self::PACKAGE_PATH.'/config/gs-theme.php', 'gs-theme');
        // Merged in register() so tenant-scope (boot) defines the Gates and lists them in the Access matrix.
        $this->mergeConfigFrom(self::PACKAGE_PATH.'/config/abilities.php', 'govstore-abilities');
        // booting() runs after every register(), so tenant-scope's profiles are present whatever the provider order.
        $this->app->booting(fn () => $this->mergePermissionProfiles());

        $this->app->singleton(AssetRegistry::class);
        $this->app->singleton(FontRegistry::class, fn () => new FontRegistry(self::PACKAGE_PATH.'/fonts'));
        $this->app->singleton(ThemeRepository::class, fn () => new ThemeRepository(config('gs-theme.themes_path')));
        $this->app->singleton(ThemeResolver::class);
        $this->app->singleton(ThemeAccess::class);
        $this->app->singleton(Manifest::class, fn () => new Manifest(config('gs-theme.build_path'), config('gs-theme.build_url')));
        $this->app->bind(ThemeValidator::class, fn ($app) => new ThemeValidator($app->make(ThemeRepository::class), $app->make(FontRegistry::class)));
        $this->app->bind(ThemeCompiler::class, fn ($app) => new ThemeCompiler(
            $app->make(ThemeRepository::class), $app->make(FontRegistry::class), $app->make(AssetRegistry::class), realpath(self::PACKAGE_PATH) ?: self::PACKAGE_PATH,
        ));
        $this->app->singleton(ThemeManager::class);
        $this->app->alias(ThemeManager::class, 'gs.theme');
    }

    public function boot(): void
    {
        Blade::precompiler(new LayoutHook);

        $this->loadViewsFrom(self::PACKAGE_PATH.'/resources/views', 'gs-theme');
        Blade::anonymousComponentPath(self::PACKAGE_PATH.'/resources/views/components', 'gs');
        $this->loadTranslationsFrom(self::PACKAGE_PATH.'/lang', 'gs-theme');
        $this->loadMigrationsFrom(self::PACKAGE_PATH.'/database/migrations');
        $this->loadRoutesFrom(self::PACKAGE_PATH.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\BuildThemes::class,
                Console\ValidateThemes::class,
                Console\MakeTheme::class,
                Console\ComplianceReport::class,
                Console\PruneReferences::class,
                Console\GeneratePreviews::class,
            ]);
        }

        // Ability labels for the Access matrix live with the others in tenant-scope's lang files.
        $this->app->booted(fn () => $this->registerMenu());
    }

    private function mergePermissionProfiles(): void
    {
        $profiles = config('govstore-permissions.profiles');
        if (! is_array($profiles)) {
            return;
        }
        foreach (config('gs-theme.permission_profiles', []) as $profile => $permissions) {
            // Only choose-only strings may be granted through a profile; Lab access never has a permission path.
            $permissions = array_intersect((array) $permissions, ThemeAccess::PERMISSION_ABILITIES);
            $profiles[$profile] = array_values(array_unique(array_merge($profiles[$profile] ?? [], $permissions)));
        }
        config(['govstore-permissions.profiles' => $profiles]);
    }

    private function registerMenu(): void
    {
        if (! class_exists(MenuRegistry::class) || ! $this->app->bound(MenuRegistry::class)) {
            return;
        }
        $registry = $this->app->make(MenuRegistry::class);
        try {
            $registry->register([
                'id' => 'gs-theme-appearance',
                'parent' => 'gov-store',
                'title' => __('gs-theme::appearance.menu_appearance'),
                'icon' => 'fas fa-palette',
                'route' => 'gs-theme.appearance',
                'permission' => 'theming.appearance.self',
                'order' => 96,
            ]);
            $registry->register([
                'id' => 'gs-theme-assignments',
                'parent' => 'gov-store',
                'title' => __('gs-theme::appearance.menu_assignments'),
                'icon' => 'fas fa-swatchbook',
                'route' => 'gs-theme.assignments',
                'permission' => ['theming.assign.organization', 'theming.assign.company', 'theming.assign.office', 'office_admin', 'company_admin'],
                'order' => 97,
            ]);
            if (config('gs-theme.lab_enabled')) {
                $registry->register([
                    'id' => 'gs-theme-lab',
                    'parent' => 'gov-tenantscope-root',
                    'title' => __('gs-theme::appearance.menu_lab'),
                    'icon' => 'fas fa-flask',
                    'route' => 'gs-theme.lab',
                    'permission' => 'theming.lab.view',
                    'strict' => false,
                    'order' => 90,
                ]);
            }
        } catch (Throwable) {
            // Already registered (e.g. provider booted twice in tests).
        }
    }
}
