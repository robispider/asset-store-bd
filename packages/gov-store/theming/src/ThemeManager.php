<?php

namespace GovStore\Theming;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\Theming\Access\ThemeAccess;
use GovStore\Theming\Assets\AssetRegistry;
use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Build\ThemeCompiler;
use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Themes\Manifest;
use GovStore\Theming\Themes\Theme;
use GovStore\Theming\Themes\ThemeRepository;
use GovStore\Theming\Themes\ThemeResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The `gs.theme` service used by the layout hook, head/foot includes and gov-store views.
 * Resolution is lazy (first use during render, after InitializeTenantContext) and memoised per request.
 */
class ThemeManager
{
    private ?array $memo = null;

    private ?string $memoKey = null;

    public function __construct(
        private ThemeRepository $themes,
        private ThemeResolver $resolver,
        private AssetRegistry $assets,
        private FontRegistry $fonts,
        private Manifest $manifest,
        private ThemeAccess $access,
    ) {}

    public function assets(): AssetRegistry
    {
        return $this->assets;
    }

    public function repository(): ThemeRepository
    {
        return $this->themes;
    }

    public function resolver(): ThemeResolver
    {
        return $this->resolver;
    }

    public function fonts(): FontRegistry
    {
        return $this->fonts;
    }

    public function compiler(): ThemeCompiler
    {
        return app(ThemeCompiler::class);
    }

    /** @return array{key: string, mode: string, source: string, enforced_by: ?string, preference: ?array, variants: array} */
    public function resolution(): array
    {
        $user = auth()->user();
        $context = $this->context();
        $request = app()->bound('request') ? request() : null;
        $memoKey = implode('|', [$request ? spl_object_id($request) : 0, $user?->id, $context['company'], $context['location']]);
        if ($this->memo !== null && $this->memoKey === $memoKey) {
            return $this->memo;
        }
        $preview = null;
        $variants = [];
        if ($request && ($request->filled('gs_preview') || $request->filled('gs_variant')) && $user && $this->access->canViewLab($user)) {
            if ($request->filled('gs_preview')) {
                $preview = ['key' => (string) $request->query('gs_preview'), 'mode' => $request->query('gs_mode')];
            }
            foreach ((array) $request->query('gs_variant', []) as $name => $value) {
                if (in_array($value, ThemeValidator::VARIANTS[$name] ?? [], true)) {
                    $variants[$name] = $value;
                }
            }
        }
        $resolution = $this->resolver->resolve($user, $context['company'], $context['location'], $preview);
        if (! $this->themes->find($resolution['key'])) {
            $resolution['key'] = 'default';
        }
        $resolution['variants'] = $variants;
        $this->memoKey = $memoKey;

        return $this->memo = $resolution;
    }

    public function forget(): void
    {
        $this->memo = null;
        $this->memoKey = null;
    }

    public function key(): string
    {
        return $this->resolution()['key'];
    }

    public function theme(): Theme
    {
        return $this->themes->find($this->key()) ?? $this->themes->find('default');
    }

    /** Saved mode: light | dark | system. */
    public function mode(): string
    {
        return $this->resolution()['mode'];
    }

    public function isClassic(): bool
    {
        return $this->key() === 'default';
    }

    /** Attributes for <html>, inserted by the layout hook. Never throws. */
    public function htmlAttributes(): string
    {
        try {
            $theme = $this->theme();
            $mode = $this->mode();
            $attributes = [
                'data-skin' => $theme->key,
                // Explicit modes render server-side; `system` is corrected by the head script before first paint.
                'data-theme' => $mode === 'dark' ? 'dark' : 'light',
                'data-gs-mode' => $mode,
            ] + $theme->variantAttributes($this->resolution()['variants']);
            $html = '';
            foreach ($attributes as $name => $value) {
                $html .= ' '.$name.'="'.e($value).'"';
            }

            return ltrim($html);
        } catch (Throwable $e) {
            Log::warning('gs-theme: could not resolve theme, rendering stock Snipe-IT', ['error' => $e->getMessage()]);

            return 'data-theme="light"';
        }
    }

    public function devMode(): bool
    {
        $configured = config('gs-theme.dev_assets');
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return app()->environment(['local', 'staging', 'testing']);
    }

    /** URL of a shared bundle: kit | packages | js. Null when unavailable (stock look). */
    public function asset(string $name): ?string
    {
        if ($this->devMode()) {
            $file = ['kit' => 'kit.css', 'packages' => 'packages.css', 'js' => 'gs-theme.js'][$name] ?? null;

            return $file ? route('gs-theme.dev', ['file' => $file, 'v' => $this->devVersion()]) : null;
        }

        return $this->manifest->entry($name);
    }

    public function themeAsset(?string $key = null): ?string
    {
        $key ??= $this->key();
        if ($this->devMode()) {
            return $this->themes->find($key) ? route('gs-theme.dev', ['file' => 'theme-'.$key.'.css', 'v' => $this->devVersion()]) : null;
        }

        return $this->manifest->theme($key);
    }

    /** Picker preview image (preview.{mode}.png) for a theme. */
    public function previewUrl(string $key, string $mode): ?string
    {
        $theme = $this->themes->find($key);
        if (! $theme || ! $theme->previewPath($mode)) {
            return null;
        }
        if ($this->devMode()) {
            return route('gs-theme.dev', ['file' => "previews/{$key}.{$mode}.png", 'v' => $theme->version()]);
        }
        $file = $this->manifest->data()['previews'][$key][$mode] ?? null;

        return $file ? asset(trim(config('gs-theme.build_url'), '/').'/'.$file) : null;
    }

    /** Only the active theme's primary (sans) font is preloaded. */
    public function preloadFonts(): array
    {
        $key = $this->theme()->fonts['sans'] ?? null;
        if (! $key) {
            return [];
        }
        if ($this->devMode()) {
            return array_map(fn ($file) => route('gs-theme.dev', ['file' => 'fonts/'.$file['file']]), $this->fonts->availableFiles([$key]));
        }

        return $this->manifest->fonts($key);
    }

    public function fontUrl(string $file): string
    {
        return $this->devMode() ? route('gs-theme.dev', ['file' => 'fonts/'.$file]) : asset(trim(config('gs-theme.build_url'), '/').'/fonts/'.$file);
    }

    public function devVersion(): string
    {
        static $version = null;

        return $version ??= $this->compiler()->sourceFingerprint();
    }

    /** Snipe-IT's fallback chart palette, read through the core helper (never edited). */
    public function chartFallbackPalette(): array
    {
        $colors = [];
        if (class_exists(\App\Helpers\Helper::class)) {
            for ($i = 0; $i < (int) config('gs-theme.chart_fallback_count', 40); $i++) {
                $colors[] = strtoupper((string) \App\Helpers\Helper::defaultChartColors($i));
            }
        }

        return array_values(array_unique($colors));
    }

    /** status-map.php entry for a status key, with its translated label. */
    public function status(string $status): array
    {
        static $map = null;
        $map ??= require dirname(__DIR__).'/status-map.php';
        $key = \Illuminate\Support\Str::snake(str_replace('-', '_', $status));
        $entry = $map[$key] ?? ['tone' => 'doc-draft', 'icon' => 'fa-circle'];
        $translationKey = 'gs-theme::appearance.status.'.$key;
        $label = __($translationKey);

        return $entry + ['key' => $key, 'label' => $label === $translationKey ? \Illuminate\Support\Str::headline($key) : $label];
    }

    /** @return array{company: ?int, location: ?int} */
    public function context(): array
    {
        if (class_exists(TenantContext::class) && app()->bound(TenantContext::class)) {
            $context = app(TenantContext::class);

            return ['company' => $context->companyId ? (int) $context->companyId : null, 'location' => $context->locationId ? (int) $context->locationId : null];
        }

        return ['company' => null, 'location' => null];
    }
}
