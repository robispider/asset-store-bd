<?php

namespace GovStore\Theming\Build;

use GovStore\Theming\Assets\AssetRegistry;
use GovStore\Theming\Themes\Theme;
use GovStore\Theming\Themes\ThemeRepository;
use RuntimeException;

/**
 * Turns themes, the adapter, the UI kit and registered package assets into CSS / JS bundles.
 * Used by gs-theme:build (hashed files + manifest.json) and by the dev asset route.
 */
final class ThemeCompiler
{
    public const LAYER_ORDER = '@layer gs-adapter, gs-components, gs-packages, gs-theme;';

    public function __construct(
        private ThemeRepository $themes,
        private FontRegistry $fonts,
        private AssetRegistry $assets,
        private string $packagePath,
    ) {}

    /** Token CSS for one theme: light + dark blocks, print forcing light, @font-face, overrides. */
    public function themeCss(Theme $theme, callable $fontUrl): string
    {
        $light = $theme->tokens('light', $this->fonts);
        // Only tokens that differ in dark mode are emitted there, so variant rules (density,
        // radius…) keep their specificity advantage over the light block in both modes.
        $dark = array_diff_assoc($theme->tokens('dark', $this->fonts), $light);
        $selector = '[data-skin="'.$theme->key.'"]';
        $css = "/* gs-theme {$theme->key} v{$theme->version()} — generated, do not edit */\n";
        $css .= $this->fonts->fontFaceCss(array_values($theme->fonts), $fontUrl);
        $css .= $selector."{color-scheme:light;\n".$this->declarations($light)."}\n";
        $css .= $selector.'[data-theme="dark"]'."{color-scheme:dark;\n".$this->declarations($dark)."}\n";
        // Prints always use the light tokens of the active theme.
        $css .= "@media print{{$selector}[data-theme=\"dark\"]{color-scheme:light;\n".$this->declarations(array_intersect_key($light, $dark))."}}\n";
        if (trim($theme->overridesCss) !== '') {
            $css .= $theme->overridesCss."\n";
        }

        return $css;
    }

    /** Adapter (variable mapping + layered overrides + variants) and the UI kit, shared by all themes. */
    public function kitCss(): string
    {
        $css = "/* gs-theme kit — generated, do not edit */\n".self::LAYER_ORDER."\n";
        foreach (['adapter/snipeit-vars.css', 'adapter/snipeit.css', 'adapter/variants.css'] as $file) {
            $css .= $this->read($file);
        }
        $components = glob($this->packagePath.'/components/*.css') ?: [];
        sort($components);
        foreach ($components as $file) {
            $css .= "/* ".basename($file)." */\n".file_get_contents($file)."\n";
        }

        return $css;
    }

    public function packagesCss(): string
    {
        $css = "/* gs-theme packages — generated, do not edit */\n".self::LAYER_ORDER."\n";
        foreach ($this->assets->stylesheets() as $package => $files) {
            foreach ($files as $file) {
                $css .= "/* {$package}: ".basename($file)." */\n@layer gs-packages{\n".file_get_contents($file)."\n}\n";
            }
        }

        return $css;
    }

    public function js(): string
    {
        $js = $this->read('resources/js/gs-theme.js');
        foreach ($this->assets->scripts() as $package => $files) {
            foreach ($files as $file) {
                $js .= "\n/* {$package}: ".basename($file)." */\n".file_get_contents($file)."\n";
            }
        }

        return $js;
    }

    /** Source files whose changes invalidate compiled assets (dev mode). */
    public function sourceFingerprint(): string
    {
        $files = array_merge(
            glob($this->packagePath.'/adapter/*.css') ?: [],
            glob($this->packagePath.'/components/*.css') ?: [],
            [$this->packagePath.'/resources/js/gs-theme.js', $this->packagePath.'/fonts/fonts.json'],
            $this->assets->files(),
        );
        $parts = [$this->themes->fingerprint()];
        foreach ($files as $file) {
            $parts[] = is_file($file) ? $file.':'.filemtime($file) : $file;
        }

        return substr(sha1(implode('|', $parts)), 0, 12);
    }

    /**
     * Write hashed bundles + manifest.json to $output. Themes that fail validation are skipped
     * and reported; the caller decides whether that fails the build.
     *
     * @return array{manifest: array, skipped: string[]}
     */
    public function build(string $output, array $buildable): array
    {
        if (! is_dir($output) && ! mkdir($output, 0775, true) && ! is_dir($output)) {
            throw new RuntimeException("Cannot create [{$output}]");
        }
        foreach (['themes', 'fonts'] as $dir) {
            if (! is_dir($output.'/'.$dir)) {
                mkdir($output.'/'.$dir, 0775, true);
            }
        }
        $previous = is_file($output.'/manifest.json') ? (json_decode((string) file_get_contents($output.'/manifest.json'), true) ?: []) : [];
        $fontUrl = fn (string $file) => '../fonts/'.$file;
        $manifest = ['generated_at' => date(DATE_ATOM), 'themes' => [], 'fonts' => [], 'theme_versions' => []];
        $manifest['kit'] = $this->writeHashed($output, 'kit', 'css', $this->kitCss());
        $manifest['packages'] = $this->writeHashed($output, 'packages', 'css', $this->packagesCss());
        $manifest['js'] = $this->writeHashed($output, 'gs-theme', 'js', $this->js());
        $skipped = [];
        $fontKeys = [];
        foreach ($this->themes->all() as $key => $theme) {
            if (! in_array($key, $buildable, true)) {
                $skipped[] = $key;

                continue;
            }
            $manifest['themes'][$key] = $this->writeHashed($output, 'themes/'.$key, 'css', $this->themeCss($theme, $fontUrl));
            $manifest['theme_versions'][$key] = $theme->version();
            foreach (['light', 'dark'] as $mode) {
                if ($preview = $theme->previewPath($mode)) {
                    if (! is_dir($output.'/previews')) {
                        mkdir($output.'/previews', 0775, true);
                    }
                    $relative = 'previews/'.$key.'.'.$mode.'.'.substr(sha1_file($preview), 0, 8).'.png';
                    copy($preview, $output.'/'.$relative);
                    $manifest['previews'][$key][$mode] = $relative;
                }
            }
            $fontKeys = array_merge($fontKeys, array_values($theme->fonts));
        }
        foreach ($this->fonts->availableFiles($fontKeys) as $file) {
            copy($file['path'], $output.'/fonts/'.$file['file']);
            $manifest['fonts'][$file['key']][] = 'fonts/'.$file['file'];
        }
        file_put_contents($output.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->removeStale($output, $previous, $manifest);

        return ['manifest' => $manifest, 'skipped' => $skipped];
    }

    private function writeHashed(string $output, string $name, string $extension, string $contents): string
    {
        $relative = $name.'.'.substr(sha1($contents), 0, 10).'.'.$extension;
        file_put_contents($output.'/'.$relative, $contents);

        return $relative;
    }

    private function removeStale(string $output, array $previous, array $current): void
    {
        $keep = array_merge([$current['kit'], $current['packages'], $current['js']], array_values($current['themes']));
        $old = array_merge(array_filter([$previous['kit'] ?? null, $previous['packages'] ?? null, $previous['js'] ?? null]), array_values($previous['themes'] ?? []));
        foreach (array_diff($old, $keep) as $stale) {
            if (is_file($output.'/'.$stale)) {
                @unlink($output.'/'.$stale);
            }
        }
    }

    private function declarations(array $tokens): string
    {
        $lines = '';
        foreach ($tokens as $path => $value) {
            $lines .= Contract::cssVar($path).':'.$value.";\n";
        }

        return $lines;
    }

    private function read(string $relative): string
    {
        $file = $this->packagePath.'/'.$relative;

        return is_file($file) ? "/* {$relative} */\n".file_get_contents($file)."\n" : '';
    }
}
