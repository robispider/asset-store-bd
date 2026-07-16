<?php

namespace GovStore\Classification\Services\Importers;

use Illuminate\Support\Facades\DB;

class NodeImporter
{
    public function import(string $path, string $scheme, string $version): int
    {
        $handle = fopen($path, 'r');
        fgetcsv($handle); // Skip header

        $buffer = [];
        $count = 0;
        $chunkSize = 500; // Reduced to 500 for low parameter-binding footprint
        $now = now()->toDateTimeString();

        while (($row = fgetcsv($handle, 2000, ",")) !== false) {
            if (empty($row) || empty(trim($row[0]))) continue;

            $buffer[] = [
                'scheme'        => $scheme,
                'version'       => $version,
                'code'          => trim($row[0]),
                'parent_code'   => trim($row[1]) ?: null,
                'level'         => (int)$row[2],
                'title_en'      => trim($row[3]),
                'hid'           => trim($row[4]),
                'is_selectable' => (int)$row[5],
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
            $count++;

            if (count($buffer) >= $chunkSize) {
                DB::table('gov_catalog_nodes')->upsert(
                    $buffer, 
                    ['code'], 
                    ['scheme', 'version', 'parent_code', 'level', 'title_en', 'hid', 'is_selectable', 'updated_at']
                );
                $buffer = []; // Free memory instantly
            }
        }

        if (count($buffer) > 0) {
            DB::table('gov_catalog_nodes')->upsert($buffer, ['code'], ['scheme', 'version', 'parent_code', 'level', 'title_en', 'hid', 'is_selectable', 'updated_at']);
        }

        fclose($handle);
        return $count;
    }
}