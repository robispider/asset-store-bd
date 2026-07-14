<?php

namespace GovStore\StoreOperations\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Generates a sequential document number like GR-2026-000001
     */
    public function generate(string $prefix, string $table, string $column): string
    {
        $year = date('Y');
        $fullPrefix = "{$prefix}-{$year}-";

        // Use DB facade to bypass Eloquent scopes for raw max calculation
        $latest = DB::table($table)
            ->where($column, 'like', "{$fullPrefix}%")
            ->orderBy($column, 'desc')
            ->value($column);

        if (!$latest) {
            return $fullPrefix . '000001';
        }

        // Extract the numerical part and increment
        $numberString = str_replace($fullPrefix, '', $latest);
        $nextNumber = intval($numberString) + 1;

        return $fullPrefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
