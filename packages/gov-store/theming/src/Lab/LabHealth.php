<?php

namespace GovStore\Theming\Lab;

use GovStore\Theming\Build\ColorMath;
use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Themes\Theme;
use Throwable;

/**
 * Theme Lab health data: the contract's contrast pairs per theme × mode, the nearest passing shade
 * for every failure, and the validator's other findings. Read-only; nothing is written.
 */
final class LabHealth
{
    public const MODES = ['light', 'dark'];

    /** Contrast pair (fg|bg) → translation slug under gs-theme::appearance.lab.checks. */
    public const SLUGS = [
        'color.text|color.surface' => 'text-surface',
        'color.text|color.bg' => 'text-bg',
        'color.text-muted|color.surface' => 'muted-surface',
        'color.on-primary|color.primary' => 'on-primary',
        'color.on-accent|color.accent' => 'on-accent',
        'header.fg|header.bg' => 'header',
        'sidebar.fg|sidebar.bg' => 'sidebar',
        'sidebar.active-fg|sidebar.active-bg' => 'sidebar-active',
        'color.on-success|color.success' => 'on-success',
        'color.on-warning|color.warning' => 'on-warning',
        'color.on-danger|color.danger' => 'on-danger',
        'color.on-info|color.info' => 'on-info',
        'color.link|color.surface' => 'link',
        'color.border-strong|color.surface' => 'border-strong',
        'color.focus-ring|color.surface' => 'focus-ring',
    ];

    public function __construct(private FontRegistry $fonts) {}

    public static function slug(string $fg, string $bg): string
    {
        return self::SLUGS[$fg.'|'.$bg] ?? str_replace('.', '-', $fg.'--'.$bg);
    }

    /** Validator findings other than contrast (which the matrix shows) for one mode. */
    public static function otherIssues(array $issues, string $mode): array
    {
        return array_values(array_filter($issues, fn ($i) => $i['check'] !== 'contrast' && ($i['mode'] === null || $i['mode'] === $mode)));
    }

    /**
     * One theme's contrast rows per mode, plus counts for the Workspace health card.
     *
     * @return array<string, array{rows: array, pass: int, total: int, failing: array, other: array}>
     */
    public function summary(Theme $theme, array $issues): array
    {
        $summary = [];
        foreach (self::MODES as $mode) {
            $rows = $this->rows($theme, $mode);
            $failing = array_values(array_filter($rows, fn ($r) => $r['ratio'] !== null && ! $r['pass']));
            $summary[$mode] = [
                'rows' => $rows,
                'pass' => count(array_filter($rows, fn ($r) => $r['pass'])),
                'total' => count($rows),
                'failing' => $failing,
                'other' => self::otherIssues($issues, $mode),
            ];
        }

        return $summary;
    }

    /**
     * Matrix for the Health report: checks × themes per mode, with details for the side panel.
     *
     * @param  array<string, Theme>  $themes
     * @param  array<string, array>  $validation  ThemeValidator::validateAll()
     */
    public function report(array $themes, array $validation): array
    {
        $modes = [];
        $details = [];
        foreach (self::MODES as $mode) {
            $rows = [];
            $perTheme = array_fill_keys(array_keys($themes), 0);
            $pass = $total = 0;
            foreach ($themes as $key => $theme) {
                foreach ($this->rows($theme, $mode) as $row) {
                    $rows[$row['slug']] ??= ['slug' => $row['slug'], 'fg' => $row['fg'], 'bg' => $row['bg'], 'min' => $row['min'], 'cells' => []];
                    $rows[$row['slug']]['cells'][$key] = $row;
                    $total++;
                    $row['pass'] ? $pass++ : $perTheme[$key]++;
                    $details[$mode.'|'.$key.'|'.$row['slug']] = $row + ['theme' => $key, 'mode' => $mode];
                }
            }
            foreach ($rows as &$row) {
                $row['failing'] = count(array_filter($row['cells'], fn ($c) => ! $c['pass']));
            }
            unset($row);
            $other = [];
            foreach ($themes as $key => $theme) {
                $other[$key] = self::otherIssues($validation[$key] ?? [], $mode);
            }
            $modes[$mode] = ['rows' => array_values($rows), 'perTheme' => $perTheme, 'pass' => $pass, 'total' => $total, 'other' => $other];
        }
        $clean = array_filter(array_keys($themes), fn ($key) => ($validation[$key] ?? []) === []);

        return ['modes' => $modes, 'details' => $details, 'clean' => count($clean)];
    }

    /** Contrast rows for one theme × mode, each failure with the nearest passing shade. */
    public function rows(Theme $theme, string $mode): array
    {
        $tokens = $theme->tokens($mode, $this->fonts);
        $rows = [];
        foreach (ThemeValidator::contrastTable($tokens) as $row) {
            $row['slug'] = self::slug($row['fg'], $row['bg']);
            $row['fgValue'] = $tokens[$row['fg']] ?? null;
            $row['bgValue'] = $tokens[$row['bg']] ?? null;
            $row['fix'] = $row['ratio'] !== null && ! $row['pass'] ? $this->fix($theme, $mode, $row) : null;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Walk the adjustable colour's OKLCH lightness away from its partner (chroma and hue kept) until
     * the pair passes. "on-*" pairs adjust the role colour; every other pair adjusts the foreground.
     */
    public function fix(Theme $theme, string $mode, array $row): ?array
    {
        $adjustBg = str_starts_with(substr($row['fg'], strpos($row['fg'], '.') + 1), 'on-');
        $token = $adjustBg ? $row['bg'] : $row['fg'];
        $value = $adjustBg ? $row['bgValue'] : $row['fgValue'];
        $partner = $adjustBg ? $row['fgValue'] : $row['bgValue'];
        try {
            [$l, $c, $h, $a] = ColorMath::toOklch($value);
            $direction = ColorMath::lightness($partner) > 0.6 ? -1 : 1;
            for ($step = 1; $step <= 100; $step++) {
                $candidate = ColorMath::fromOklch($l + $direction * $step * 0.01, $c, $h, $a);
                $ratio = $adjustBg ? ColorMath::contrast($partner, $candidate) : ColorMath::contrast($candidate, $partner);
                if ($ratio >= $row['min'] + 0.05) {
                    [$group, $name] = explode('.', $token, 2);

                    return [
                        'token' => $token,
                        'value' => strtoupper($candidate),
                        'ratio' => round($ratio, 2),
                        'file' => 'themes/'.$theme->key.'/tokens.'.$mode.'.json',
                        'json' => json_encode([$group => [$name => ['$value' => strtoupper($candidate)]]], JSON_UNESCAPED_SLASHES),
                    ];
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /** Readable font family names for the theme's slots (first family of each stack). */
    public function fontNames(Theme $theme): array
    {
        $names = [];
        foreach (['sans', 'display', 'bengali', 'mono'] as $slot) {
            $key = $theme->fonts[$slot] ?? null;
            $stack = $key ? $this->fonts->stack($key) : null;
            if ($stack) {
                $names[] = trim(explode(',', $stack)[0], " '\"");
            }
        }

        return array_values(array_unique($names));
    }
}
