<?php

namespace GovStore\CustomRequests\Services;

use Illuminate\Support\Facades\DB;

class RequestNumberService
{
    public function generate(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            // A no-op upsert acquires the exclusive row lock directly. INSERT IGNORE on an
            // existing year takes shared locks that can deadlock when every caller upgrades.
            DB::table('custom_request_sequences')->upsert(['year' => $year, 'last_number' => 0], ['year'], ['year']);
            $sequence = DB::table('custom_request_sequences')->where('year', $year)->lockForUpdate()->first();
            // Include soft-deleted records and pre-sequence numbers on first use.
            $latest = DB::table('custom_service_requests')->where('request_number', 'like', "SR-{$year}-%")
                ->orderByDesc('request_number')->value('request_number');
            $next = max($sequence->last_number, $latest ? (int) substr($latest, 8) : 0) + 1;
            DB::table('custom_request_sequences')->where('year', $year)->update(['last_number' => $next]);

            return sprintf('SR-%d-%06d', $year, $next);
        }, 3);
    }
}
