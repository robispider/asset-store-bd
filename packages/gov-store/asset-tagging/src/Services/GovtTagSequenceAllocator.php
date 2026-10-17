<?php

namespace GovStore\AssetTagging\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reserves Govt. Tag sequences in gov_asset_tag_sequences. Counter scope is the exact first 14 digits,
 * across the installation (D-01). Rows are created race-safely, then locked in sorted order for the
 * whole posting, so interleaved postings cannot deadlock. A new counter starts above every retained
 * tag with its prefix (soft-deleted assets included).
 */
class GovtTagSequenceAllocator
{
    public const NAMESPACE = 'asset-tag:fd18';

    /**
     * @param  array<string, int>  $needs  prefix => number of tags
     * @return array<string, int> prefix => first reserved sequence
     */
    public function reserve(array $needs): array
    {
        ksort($needs, SORT_STRING);
        $capacity = (int) config('asset-tagging.sequence_capacity', 9999);
        foreach (array_keys($needs) as $prefix) {
            DB::table('gov_asset_tag_sequences')->insertOrIgnore(['namespace' => self::NAMESPACE, 'scope_key' => $prefix,
                'last_value' => $this->retainedMaximum($prefix), 'created_at' => now(), 'updated_at' => now()]);
        }
        $first = [];
        foreach ($needs as $prefix => $count) {
            $row = DB::table('gov_asset_tag_sequences')->where('namespace', self::NAMESPACE)->where('scope_key', $prefix)->lockForUpdate()->first();
            $start = (int) $row->last_value + 1;
            if ($start + $count - 1 > $capacity) {
                throw ValidationException::withMessages(['items' => __('asset-tagging::tagging.errors.sequence_full', ['prefix' => $prefix])]);
            }
            DB::table('gov_asset_tag_sequences')->where('namespace', self::NAMESPACE)->where('scope_key', $prefix)
                ->update(['last_value' => $start + $count - 1, 'updated_at' => now()]);
            $first[$prefix] = $start;
        }

        return $first;
    }

    public function retainedMaximum(string $prefix): int
    {
        $tags = DB::table('assets')->where('asset_tag', 'like', $prefix.'%')->pluck('asset_tag');
        if (\Illuminate\Support\Facades\Schema::hasTable('gov_asset_registrations')) {
            $tags = $tags->merge(DB::table('gov_asset_registrations')->where('asset_tag', 'like', $prefix.'%')->pluck('asset_tag'));
        }

        return (int) $tags->filter(fn ($tag) => strlen($tag) === 18 && ctype_digit($tag))->map(fn ($tag) => (int) substr($tag, 14))->max();
    }

    /** Counters whose stored value is below a retained tag (asset-tags:counters --check). */
    public function drift(): array
    {
        return DB::table('gov_asset_tag_sequences')->where('namespace', self::NAMESPACE)->get()
            ->filter(fn ($row) => $this->retainedMaximum($row->scope_key) > (int) $row->last_value)
            ->map(fn ($row) => ['prefix' => $row->scope_key, 'stored' => (int) $row->last_value, 'retained' => $this->retainedMaximum($row->scope_key)])
            ->values()->all();
    }
}
