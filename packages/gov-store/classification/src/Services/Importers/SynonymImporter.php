<?php

namespace GovStore\Classification\Services\Importers;

use Illuminate\Support\Facades\DB;

class SynonymImporter
{
    public function import(string $path): int
    {
        if (!$this->hasDataRows($path)) {
            return 0;
        }

        // Clean out reference synonyms safely before streaming new ones
        DB::table('gov_catalog_synonyms')->where('language', 'en')->whereIn('type', ['common', 'acronym'])->delete();

        $handle = fopen($path, 'r');
        fgetcsv($handle); // Skip header

        $buffer = [];
        $count = 0;
        $chunkSize = 1000;
        $now = now()->toDateTimeString();

        while (($row = fgetcsv($handle, 2000, ",")) !== false) {
            if (empty($row) || empty(trim($row[0]))) continue;

            $buffer[] = [
                'code'       => trim($row[0]),
                'synonym'    => trim($row[1]),
                'type'       => trim($row[2]),
                'language'   => 'en',
                'created_at' => $now,
                'updated_at' => $now
            ];
            $count++;

            if (count($buffer) >= $chunkSize) {
                DB::table('gov_catalog_synonyms')->insert($buffer);
                $buffer = [];
            }
        }

        if (count($buffer) > 0) {
            DB::table('gov_catalog_synonyms')->insert($buffer);
        }

        fclose($handle);
        return $count;
    }

    protected function hasDataRows(string $path): bool
    {
        $hasData = false;
        if (($handle = fopen($path, 'r')) !== false) {
            fgetcsv($handle);
            $firstRow = fgetcsv($handle);
            if ($firstRow !== false && isset($firstRow[0]) && !empty(trim($firstRow[0]))) {
                $hasData = true;
            }
            fclose($handle);
        }
        return $hasData;
    }
}