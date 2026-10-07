<?php

namespace GovStore\Theming\Console;

use GovStore\Theming\Build\ThemeValidator;
use Illuminate\Console\Command;

class ValidateThemes extends Command
{
    protected $signature = 'gs-theme:validate {key? : Validate one theme only} {--json : Machine-readable output for CI}';

    protected $description = 'Validate shipped themes: schema, both modes, token contract, references, WCAG contrast, fonts, overrides and previews';

    public function handle(ThemeValidator $validator): int
    {
        $report = $validator->validateAll($this->argument('key'));
        if ($this->argument('key') && $report === []) {
            $this->error('Unknown theme ['.$this->argument('key').']');

            return self::FAILURE;
        }
        $failed = array_filter($report, fn ($issues) => ThemeValidator::hasErrors($issues));

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $failed === [], 'themes' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $failed === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($report as $key => $issues) {
            $errors = count(array_filter($issues, fn ($i) => $i['level'] === 'error'));
            $warnings = count($issues) - $errors;
            $errors ? $this->error("✗ {$key}: {$errors} error(s), {$warnings} warning(s)") : $this->info("✓ {$key}".($warnings ? " ({$warnings} warning(s))" : ''));
            if ($issues) {
                $this->table(['Level', 'Check', 'Mode', 'Message'], array_map(fn ($i) => [$i['level'], $i['check'], $i['mode'] ?? '—', $i['message']], $issues));
            }
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
