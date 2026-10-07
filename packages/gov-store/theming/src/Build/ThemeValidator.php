<?php

namespace GovStore\Theming\Build;

use GovStore\Theming\Themes\Theme;
use GovStore\Theming\Themes\ThemeRepository;
use Throwable;

/**
 * Implements the checks in §8.4. Each issue: [level => error|warning, check, mode?, message].
 */
final class ThemeValidator
{
    public const VARIANTS = [
        'header' => ['brand', 'neutral', 'dark'],
        'sidebar' => ['dark', 'light', 'brand'],
        'nav_icons' => ['shown', 'hidden'],
        'density' => ['compact', 'regular', 'comfortable'],
        'table' => ['grid', 'rules', 'ledger'],
        'surfaces' => ['card', 'flat'],
        'radius' => ['none', 'sm', 'md', 'lg'],
        'type' => ['sans', 'serif-display'],
        'status_style' => ['tinted', 'chip', 'text'],
    ];

    public const STATUSES = ['draft', 'published', 'deprecated'];

    public const LOCALES = ['en-US', 'bn-BD'];

    private const MANIFEST_FIELDS = ['key', 'version', 'status', 'extends', 'label', 'description', 'authors', 'fonts', 'variants', 'swatches'];

    public function __construct(private ThemeRepository $repository, private FontRegistry $fonts) {}

    /** @return array<string, array<int, array{level: string, check: string, mode: ?string, message: string}>> */
    public function validateAll(?string $only = null): array
    {
        $report = [];
        $keys = array_unique(array_merge(array_keys($this->repository->raw()), array_keys($this->repository->loadErrors())));
        sort($keys);
        foreach ($keys as $key) {
            if ($only === null || $only === $key) {
                $report[$key] = $this->validate($key);
            }
        }

        return $report;
    }

    public function validate(string $key): array
    {
        $issues = [];
        foreach ($this->repository->loadErrors()[$key] ?? [] as $message) {
            $issues[] = $this->issue('error', 'schema', null, $message);
        }
        $raw = $this->repository->raw()[$key] ?? null;
        if (! $raw) {
            return $issues;
        }
        $manifest = $raw['manifest'];
        $published = ($manifest['status'] ?? null) === 'published';

        // Schema
        foreach (['key', 'version', 'status', 'label', 'description'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                $issues[] = $this->issue('error', 'schema', null, "theme.json is missing [{$field}]");
            }
        }
        if (! array_key_exists('extends', $manifest)) {
            $issues[] = $this->issue('error', 'schema', null, 'theme.json is missing [extends] (use null only for default)');
        } elseif ($manifest['extends'] === null && $key !== 'default') {
            $issues[] = $this->issue('error', 'schema', null, 'only the default theme may have extends: null');
        }
        foreach (array_diff(array_keys($manifest), self::MANIFEST_FIELDS) as $unknown) {
            $issues[] = $this->issue('error', 'schema', null, "unknown theme.json key [{$unknown}]");
        }
        if (($manifest['key'] ?? null) !== $key) {
            $issues[] = $this->issue('error', 'schema', null, "key must equal the folder name [{$key}]");
        }
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key)) {
            $issues[] = $this->issue('error', 'schema', null, 'key must be kebab-case');
        }
        if (isset($manifest['version']) && ! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', (string) $manifest['version'])) {
            $issues[] = $this->issue('error', 'schema', null, 'version must be semver');
        }
        if (isset($manifest['status']) && ! in_array($manifest['status'], self::STATUSES, true)) {
            $issues[] = $this->issue('error', 'schema', null, 'status must be one of '.implode(', ', self::STATUSES));
        }
        foreach (['label', 'description'] as $field) {
            foreach (self::LOCALES as $locale) {
                if (isset($manifest[$field]) && empty($manifest[$field][$locale])) {
                    $issues[] = $this->issue('error', 'schema', null, "{$field} requires [{$locale}]");
                }
            }
        }
        foreach ($manifest['variants'] ?? [] as $variant => $value) {
            if (! isset(self::VARIANTS[$variant])) {
                $issues[] = $this->issue('error', 'schema', null, "unknown variant [{$variant}]");
            } elseif (! in_array($value, self::VARIANTS[$variant], true)) {
                $issues[] = $this->issue('error', 'schema', null, "variant {$variant} must be one of ".implode(', ', self::VARIANTS[$variant]));
            }
        }
        foreach (['tokens.light.json', 'tokens.dark.json'] as $file) {
            if (! is_file($raw['dir'].'/'.$file)) {
                $issues[] = $this->issue('error', 'modes', null, "missing {$file}");
            }
        }

        $theme = $this->repository->find($key);
        if (! $theme) {
            return $issues;
        }

        // Variants after inheritance must be complete.
        foreach (array_keys(self::VARIANTS) as $variant) {
            if (! isset($theme->variants[$variant])) {
                $issues[] = $this->issue('error', 'schema', null, "variant [{$variant}] unresolved after inheritance");
            }
        }

        // Fonts
        foreach ($theme->fonts as $slot => $fontKey) {
            if (! $this->fonts->has($fontKey)) {
                $issues[] = $this->issue('error', 'fonts', null, "unknown font key [{$fontKey}] for slot {$slot}");
            }
        }
        if (empty($theme->fonts['bengali'])) {
            $issues[] = $this->issue('error', 'fonts', null, 'a bengali font slot is required');
        } elseif ($this->fonts->has($theme->fonts['bengali']) && ! $this->fonts->isBengali($theme->fonts['bengali'])) {
            $issues[] = $this->issue('error', 'fonts', null, 'the bengali slot must use a Bengali font');
        }

        foreach (['light', 'dark'] as $mode) {
            $tree = $mode === 'light' ? $theme->lightTokens : $theme->darkTokens;
            if ($tree === []) {
                $issues[] = $this->issue('error', 'modes', $mode, "tokens.{$mode}.json is missing or empty after inheritance");

                continue;
            }
            $tokens = $theme->tokens($mode, $this->fonts);
            foreach ($theme->tokenErrors($mode, $this->fonts) as $error) {
                $issues[] = $this->issue('error', 'references', $mode, $error);
            }
            foreach (Contract::tokens() as $path) {
                if (! isset($tokens[$path])) {
                    $issues[] = $this->issue('error', 'contract', $mode, "token [{$path}] unresolved");
                }
            }
            foreach ($this->contrastIssues($tokens, $mode) as $issue) {
                $issues[] = $issue;
            }
            foreach ($this->distinguishabilityIssues($tokens, $mode) as $issue) {
                $issues[] = $issue;
            }
        }

        // Dark mode authored
        $darkLevel = $published ? 'error' : 'warning';
        if ($theme->darkIsScaffold) {
            $issues[] = $this->issue($darkLevel, 'dark-authored', 'dark', 'tokens.dark.json is still the generated scaffold (remove "gs.scaffold")');
        }
        $dark = $theme->tokens('dark', $this->fonts);
        foreach (Contract::SURFACE_TOKENS as $path) {
            if (isset($dark[$path]) && $this->safeLightness($dark[$path]) > 0.35) {
                $issues[] = $this->issue($darkLevel, 'dark-authored', 'dark', "{$path} in dark mode has OKLCH lightness > 0.35");
            }
        }

        // overrides.css lint
        foreach ($this->lintOverrides($raw['overrides']) as $message) {
            $issues[] = $this->issue('error', 'overrides', null, $message);
        }

        // Previews
        if ($published) {
            foreach (['light', 'dark'] as $mode) {
                if (! $theme->previewPath($mode)) {
                    $issues[] = $this->issue('error', 'previews', $mode, "missing preview.{$mode}.png");
                }
            }
        }

        return $issues;
    }

    public static function hasErrors(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue['level'] === 'error') {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array{fg: string, bg: string, min: float, ratio: ?float, pass: bool}> */
    public static function contrastTable(array $tokens): array
    {
        $rows = [];
        foreach (Contract::CONTRAST_PAIRS as [$fg, $bg, $min]) {
            $ratio = null;
            if (isset($tokens[$fg], $tokens[$bg])) {
                try {
                    $ratio = round(ColorMath::contrast($tokens[$fg], $tokens[$bg]), 2);
                } catch (Throwable) {
                    $ratio = null;
                }
            }
            $rows[] = ['fg' => $fg, 'bg' => $bg, 'min' => $min, 'ratio' => $ratio, 'pass' => $ratio !== null && $ratio >= $min];
        }

        return $rows;
    }

    public function lintOverrides(string $css): array
    {
        $messages = [];
        if (trim($css) === '') {
            return [];
        }
        $stripped = preg_replace('#/\*.*?\*/#s', '', $css);
        if (stripos($stripped, '!important') !== false) {
            $messages[] = 'overrides.css must not use !important';
        }
        if (preg_match('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(|\boklch\(/', $stripped)) {
            $messages[] = 'overrides.css must not contain literal colours (use var(--gs-*))';
        }
        if (! preg_match('/@layer\s+gs-theme\b/', $stripped)) {
            $messages[] = 'overrides.css must be wrapped in @layer gs-theme';
        }
        $body = preg_replace('/@layer\s+gs-theme\s*\{/', '', $stripped, 1);
        preg_match_all('/([^{}]+)\{/', (string) $body, $matches);
        foreach ($matches[1] as $selectorList) {
            foreach (explode(',', $selectorList) as $selector) {
                $selector = trim($selector);
                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }
                if (! str_starts_with($selector, '.gs-') && ! str_starts_with($selector, '[data-gs-')) {
                    $messages[] = "overrides.css selector [{$selector}] must start with .gs- or [data-gs-";
                }
            }
        }

        return array_values(array_unique($messages));
    }

    private function contrastIssues(array $tokens, string $mode): array
    {
        $issues = [];
        foreach (self::contrastTable($tokens) as $row) {
            if ($row['ratio'] !== null && ! $row['pass']) {
                $issues[] = $this->issue('error', 'contrast', $mode, sprintf('%s on %s is %.2f:1 (needs %.1f:1)', $row['fg'], $row['bg'], $row['ratio'], $row['min']));
            }
        }

        return $issues;
    }

    private function distinguishabilityIssues(array $tokens, string $mode): array
    {
        $issues = [];
        foreach (Contract::DISTINGUISHABLE as $family) {
            $present = array_values(array_filter($family, fn ($path) => isset($tokens[$path])));
            for ($i = 0; $i < count($present); $i++) {
                for ($j = $i + 1; $j < count($present); $j++) {
                    try {
                        [$l1, $c1, $h1] = ColorMath::toOklch($tokens[$present[$i]]);
                        [$l2, $c2, $h2] = ColorMath::toOklch($tokens[$present[$j]]);
                    } catch (Throwable) {
                        continue;
                    }
                    $hueOnly = abs($l1 - $l2) < 0.08 && abs($c1 - $c2) < 0.04;
                    if ($hueOnly) {
                        // Badges always carry an icon and text, so this is advisory.
                        $issues[] = $this->issue('warning', 'distinguishability', $mode, "{$present[$i]} and {$present[$j]} differ only in hue (ΔL < 0.08)");
                    }
                }
            }
        }

        return $issues;
    }

    private function safeLightness(string $color): float
    {
        try {
            return ColorMath::lightness($color);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function issue(string $level, string $check, ?string $mode, string $message): array
    {
        return compact('level', 'check', 'mode', 'message');
    }
}
