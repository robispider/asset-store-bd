<?php

namespace GovStore\Theming\Console;

use GovStore\Theming\Build\ThemeCompiler;
use GovStore\Theming\Build\ThemeValidator;
use GovStore\Theming\Themes\ThemeRepository;
use Illuminate\Console\Command;

class BuildThemes extends Command
{
    protected $signature = 'gs-theme:build {--output= : Output directory (default: public/vendor/gs-theme)}';

    protected $description = 'Validate themes, resolve inheritance and derivations, and write hashed CSS/JS bundles with manifest.json';

    public function handle(ThemeRepository $themes, ThemeValidator $validator, ThemeCompiler $compiler): int
    {
        $report = $validator->validateAll();
        $buildable = [];
        $blocking = [];
        foreach ($report as $key => $issues) {
            $theme = $themes->find($key);
            if (! ThemeValidator::hasErrors($issues)) {
                $buildable[] = $key;
            } elseif ($theme?->isPublished()) {
                // An unfinished published theme (scaffold dark mode, failing contrast…) cannot be released.
                $blocking[$key] = $issues;
            } else {
                $this->warn("Skipping {$key} (".($theme?->status() ?? 'invalid').') — fails validation');
            }
        }
        if ($blocking) {
            foreach ($blocking as $key => $issues) {
                $this->error("✗ {$key} is published but fails validation:");
                foreach (array_filter($issues, fn ($i) => $i['level'] === 'error') as $issue) {
                    $this->line("   [{$issue['check']}".($issue['mode'] ? "/{$issue['mode']}" : '')."] {$issue['message']}");
                }
            }
            $this->error('Build aborted. Run `php artisan gs-theme:validate` for the full report.');

            return self::FAILURE;
        }

        $output = $this->option('output') ?: config('gs-theme.build_path');
        $result = $compiler->build($output, $buildable);
        $this->info('Built '.count($result['manifest']['themes']).' theme(s) → '.$output);
        $this->table(['Bundle', 'File'], array_merge(
            [['kit', $result['manifest']['kit']], ['packages', $result['manifest']['packages']], ['js', $result['manifest']['js']]],
            array_map(fn ($key, $file) => ["theme: {$key}", $file], array_keys($result['manifest']['themes']), $result['manifest']['themes']),
        ));
        if (empty($result['manifest']['fonts'])) {
            $this->warn('No woff2 files found in theming/fonts — themes use installed fallback fonts (see fonts/README.md).');
        }

        return self::SUCCESS;
    }
}
