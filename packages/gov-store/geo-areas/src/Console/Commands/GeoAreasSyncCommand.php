<?php

namespace GovStore\GeoAreas\Console\Commands;

use GovStore\GeoAreas\Services\GeoAreaDatasetSync;
use Illuminate\Console\Command;

final class GeoAreasSyncCommand extends Command
{
    protected $signature = 'govstore:geo-areas-sync {--dry-run : Count changes without writing} {--without-localities : Skip mauza and village rows}';

    protected $description = 'Load the bundled geo seed: add missing areas and write BBS geocodes (never deletes)';

    public function handle(GeoAreaDatasetSync $sync): int
    {
        $this->line('Installed dataset: '.($sync->installedVersion() ?? 'none'));
        $report = $sync->run((bool) $this->option('dry-run'), ! $this->option('without-localities'));

        $this->table(['Item', 'Rows'], [
            ['Bundled dataset', $report['version']],
            ['Areas inserted', $report['admin_inserted']],
            ['Existing areas whose codes change', $report['codes_written']],
            ['English names fixed', $report['english_fixed']],
            ['Bangla names filled', $report['bangla_filled']],
            ['Localities inserted', $report['localities_inserted']],
            ['Localities updated', $report['localities_updated']],
        ]);
        if ($report['dry_run']) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }
        if ($report['rows_without_code'] > 0) {
            $this->error($report['rows_without_code'].' areas still have no geocode, e.g. GeoAreaId '.implode(', ', $report['rows_without_code_sample']).'.');

            return self::FAILURE;
        }
        $this->info('Every area has a geocode.');

        return self::SUCCESS;
    }
}
