<?php

namespace GovStore\Classification\Console\Commands;

use GovStore\Classification\Models\FinanceItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the prepared Finance item list as HELD rows. Existing codes are never overwritten, so the
 * command can be re-run safely; activation is a separate, reviewed publisher action.
 */
class ImportFinanceItems extends Command
{
    protected $signature = 'catalog:finance-import {--path= : CSV with gov_code,group_name,subgroup_name,item_name,… (defaults to FinanceDivData/tagging-design/finance_items.import-draft.csv)} {--dry-run}';

    protected $description = 'Import the Finance Division item list as held rows (never overwrites existing codes).';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('FinanceDivData/tagging-design/finance_items.import-draft.csv');
        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $required = ['gov_code', 'group_name', 'subgroup_name', 'item_name'];
        if (! $header || array_diff($required, $header)) {
            $this->error('The CSV needs the columns: '.implode(', ', $required));

            return self::FAILURE;
        }
        [$rows, $invalid] = [[], 0];
        while (($line = fgetcsv($handle)) !== false) {
            $row = array_combine($header, array_pad($line, count($header), null));
            if (! FinanceItem::isCode($row['gov_code'] ?? null) || trim((string) $row['item_name']) === '') {
                $invalid++;

                continue;
            }
            $rows[$row['gov_code']] = ['gov_code' => $row['gov_code'], 'group_name' => trim($row['group_name']),
                'subgroup_name' => trim($row['subgroup_name']), 'item_name' => trim($row['item_name']),
                'unspsc_code' => null, 'status' => 'held', 'origin' => $row['origin'] ?: 'manual_extract',
                'source_reference' => $row['source_reference'] ?? null, 'authority_reference' => null,
                'created_at' => now(), 'updated_at' => now()];
        }
        fclose($handle);
        $existing = FinanceItem::whereIn('gov_code', array_keys($rows))->pluck('gov_code')->all();
        $new = array_diff_key($rows, array_flip($existing));
        $this->info(sprintf('%d rows read, %d new, %d already present, %d invalid.', count($rows), count($new), count($existing), $invalid));
        if ($this->option('dry-run') || ! $new) {
            return self::SUCCESS;
        }
        DB::transaction(function () use ($new) {
            foreach (array_chunk(array_values($new), 200) as $chunk) {
                DB::table('gov_finance_items')->insertOrIgnore($chunk);
            }
        });
        $this->info(count($new).' Finance items imported as held.');

        return self::SUCCESS;
    }
}
