<?php

namespace GovStore\Classification\Services\Importers;

use Illuminate\Support\Facades\DB;

class DefinitionImporter
{
    public function import(string $path): int
    {
        $handle = fopen($path, 'r');
        fgetcsv($handle); // Skip header

        $buffer = [];
        $count = 0;
        $chunkSize = 500;
        $now = now()->toDateTimeString();

        while (($row = fgetcsv($handle, 4000, ",")) !== false) {
            if (empty($row) || empty(trim($row[0]))) continue;

            $buffer[] = [
                'code'          => trim($row[0]),
                'definition_en' => trim($row[1]),
                'updated_at'    => $now
            ];
            $count++;

            if (count($buffer) >= $chunkSize) {
                DB::table('gov_catalog_definitions')->upsert($buffer, ['code'], ['definition_en', 'updated_at']);
                $buffer = [];
            }
        }

        if (count($buffer) > 0) {
            DB::table('gov_catalog_definitions')->upsert($buffer, ['code'], ['definition_en', 'updated_at']);
        }

        fclose($handle);
        return $count;
    }
}