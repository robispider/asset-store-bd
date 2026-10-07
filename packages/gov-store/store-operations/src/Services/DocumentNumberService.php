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
        $year = (int) now()->format('Y');
        $fullPrefix = "{$prefix}-{$year}-";

        return DB::transaction(function () use ($prefix, $table, $column, $year, $fullPrefix) {
            DB::table('gov_store_document_sequences')->insertOrIgnore([
                'prefix' => $prefix, 'sequence_year' => $year, 'last_number' => 0,
            ]);
            $sequence = DB::table('gov_store_document_sequences')
                ->where('prefix', $prefix)->where('sequence_year', $year)->lockForUpdate()->first();

            if ((int) $sequence->last_number === 0) {
                $last = 0;
                foreach ([['gov_documents', 'document_number'], ['gov_goods_issues', 'issue_no']] as [$sourceTable, $sourceColumn]) {
                    $value = DB::table($sourceTable)->where($sourceColumn, 'like', "{$fullPrefix}%")
                        ->orderByDesc($sourceColumn)->value($sourceColumn);
                    if ($value) {
                        $last = max($last, (int) substr($value, strrpos($value, '-') + 1));
                    }
                }
            } else {
                $last = (int) $sequence->last_number;
            }

            $next = $last + 1;
            DB::table('gov_store_document_sequences')->where('prefix', $prefix)->where('sequence_year', $year)
                ->update(['last_number' => $next]);

            return $fullPrefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        }, 3);
    }
}
