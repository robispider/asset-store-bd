<?php

namespace GovStore\Theming\Console;

use GovStore\Theming\Models\ThemeAssignment;
use GovStore\Theming\Models\ThemePreference;
use GovStore\Theming\Themes\ThemeRepository;
use GovStore\Theming\Themes\ThemeResolver;
use Illuminate\Console\Command;

class PruneReferences extends Command
{
    protected $signature = 'gs-theme:prune-references {--dry-run : Report without changing anything}';

    protected $description = 'Clear user preferences and scope assignments that point at themes removed in a release';

    public function handle(ThemeRepository $themes): int
    {
        $known = array_keys($themes->all());
        $preferences = ThemePreference::query()->whereNotNull('theme')->whereNotIn('theme', $known);
        $assignments = ThemeAssignment::query()->whereNotIn('theme', $known);
        $this->info($preferences->count().' preference(s) and '.$assignments->count().' assignment(s) reference removed themes.');
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        $preferences->update(['theme' => null]);
        foreach ($assignments->get() as $assignment) {
            ThemeResolver::forgetAssignment($assignment->scope_type, $assignment->scope_id);
            $assignment->delete();
        }
        $this->info('Pruned.');

        return self::SUCCESS;
    }
}
