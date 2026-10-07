<?php

namespace GovStore\Theming\Console;

use GovStore\Theming\Build\ColorMath;
use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Themes\ThemeRepository;
use Illuminate\Console\Command;

/**
 * Scaffold a theme folder. The dark file is a *scaffold* derived from the light seeds and
 * marked "gs.scaffold": the developer designs dark mode and removes the marker before publishing.
 */
class MakeTheme extends Command
{
    protected $signature = 'gs-theme:make {key : kebab-case theme key}
        {--extends=default : Parent theme}
        {--from= : Copy seeds and variants from an existing theme}';

    protected $description = 'Scaffold a new file-based theme (draft) with light tokens and a generated dark-mode scaffold';

    public function handle(ThemeRepository $themes, FontRegistry $fonts): int
    {
        $key = $this->argument('key');
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key)) {
            $this->error('The key must be kebab-case.');

            return self::FAILURE;
        }
        $dir = $themes->path().DIRECTORY_SEPARATOR.$key;
        if (is_dir($dir)) {
            $this->error("Theme [{$key}] already exists.");

            return self::FAILURE;
        }
        $parent = $this->option('extends') ?: 'default';
        if (! $themes->find($parent)) {
            $this->error("Parent theme [{$parent}] does not exist.");

            return self::FAILURE;
        }
        $source = $this->option('from') ? $themes->find($this->option('from')) : null;
        if ($this->option('from') && ! $source) {
            $this->error('Source theme ['.$this->option('from').'] does not exist.');

            return self::FAILURE;
        }
        $base = $source ?? $themes->find($parent);
        $light = $base->tokens('light', $fonts);
        $seeds = [];
        foreach (['primary', 'accent', 'neutral', 'danger'] as $seed) {
            $seeds[$seed] = $light['seed.'.$seed] ?? '#475467';
        }

        mkdir($dir, 0775, true);
        $title = ucwords(str_replace('-', ' ', $key));
        $manifest = [
            'key' => $key,
            'version' => '0.1.0',
            'status' => 'draft',
            'extends' => $parent,
            'label' => ['en-US' => $title, 'bn-BD' => $title],
            'description' => ['en-US' => 'Describe the intent and audience of this theme.', 'bn-BD' => 'এই থিমের উদ্দেশ্য ও ব্যবহারকারী লিখুন।'],
            'authors' => ['UI/UX Team'],
            'fonts' => $source?->fonts ?? ['sans' => 'noto-sans', 'bengali' => 'noto-sans-bengali', 'mono' => 'ibm-plex-mono'],
            'variants' => $source?->variants ?? $base->variants,
            'swatches' => array_values($seeds),
        ];
        $this->writeJson($dir.'/theme.json', $manifest);
        $this->writeJson($dir.'/tokens.light.json', ['seed' => $this->seedTree($seeds)]);
        $this->writeJson($dir.'/tokens.dark.json', $this->darkScaffold($seeds));
        file_put_contents($dir.'/README.md', "# {$title}\n\nIntent, audience and design notes.\n\n- Design **both** modes; `tokens.dark.json` starts as a generated scaffold.\n- Remove `\"gs.scaffold\": true` once dark mode is designed.\n- Review in the Theme Lab: `/gov/theme-lab/{$key}`.\n- Add `preview.light.png` / `preview.dark.png` (`php artisan gs-theme:previews {$key}`).\n");

        $this->info("Created themes/{$key} (draft, extends {$parent}).");
        $this->line('Next: edit tokens.light.json seeds, design tokens.dark.json, then `php artisan gs-theme:validate '.$key.'`.');

        return self::SUCCESS;
    }

    private function seedTree(array $seeds): array
    {
        $tree = ['$type' => 'color'];
        foreach ($seeds as $name => $value) {
            $tree[$name] = ['$value' => $value];
        }

        return $tree;
    }

    /** Lowered lightness, reduced chroma, inverted surfaces. */
    private function darkScaffold(array $seeds): array
    {
        $at = fn (string $color, float $l, float $chromaScale = 0.6) => ColorMath::derive($color, ['lightness' => $l, 'c_scale' => $chromaScale]);
        $neutral = $seeds['neutral'];
        $primary = $seeds['primary'];

        return [
            '$extensions' => ['gs.scaffold' => true],
            'seed' => $this->seedTree([
                'primary' => $at($primary, 0.74, 0.9),
                'accent' => $at($seeds['accent'], 0.74, 0.9),
                'neutral' => $at($neutral, 0.16, 0.4),
                'danger' => $at($seeds['danger'], 0.72, 0.9),
            ]),
            'color' => [
                '$type' => 'color',
                'bg' => ['$value' => $at($neutral, 0.17, 0.3)],
                'surface' => ['$value' => $at($neutral, 0.22, 0.3)],
                'surface-raised' => ['$value' => $at($neutral, 0.26, 0.3)],
                'border' => ['$value' => $at($neutral, 0.34, 0.3)],
                'border-strong' => ['$value' => $at($neutral, 0.56, 0.3)],
                'text' => ['$value' => $at($neutral, 0.95, 0.1)],
                'text-muted' => ['$value' => $at($neutral, 0.78, 0.15)],
                'text-subtle' => ['$value' => $at($neutral, 0.66, 0.15)],
                'text-inverse' => ['$value' => $at($neutral, 0.2, 0.3)],
                'on-primary' => ['$value' => $at($primary, 0.2, 0.5)],
                'on-accent' => ['$value' => $at($seeds['accent'], 0.2, 0.5)],
                'on-danger' => ['$value' => $at($seeds['danger'], 0.2, 0.5)],
                'success' => ['$value' => '#47CD89'], 'on-success' => ['$value' => '#052E16'],
                'warning' => ['$value' => '#FDB022'], 'on-warning' => ['$value' => '#2B1700'],
                'info' => ['$value' => '#84CAFF'], 'on-info' => ['$value' => '#0B1F3A'],
            ],
            'header' => ['$type' => 'color', 'bg' => ['$value' => $at($primary, 0.3, 0.7)], 'fg' => ['$value' => '#FFFFFF']],
            'sidebar' => ['$type' => 'color', 'bg' => ['$value' => $at($neutral, 0.14, 0.3)], 'fg' => ['$value' => $at($neutral, 0.82, 0.15)],
                'active-bg' => ['$value' => $at($primary, 0.3, 0.6)], 'active-fg' => ['$value' => '#FFFFFF']],
            'chart' => ['$type' => 'color', 'grid' => ['$value' => 'rgba(255, 255, 255, 0.12)']],
            'shadow' => ['$type' => 'shadow', '1' => ['$value' => '0 1px 2px rgba(0, 0, 0, 0.5)'], '2' => ['$value' => '0 8px 24px rgba(0, 0, 0, 0.5)']],
        ];
    }

    private function writeJson(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }
}
