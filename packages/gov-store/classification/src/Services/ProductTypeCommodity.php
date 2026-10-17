<?php

namespace GovStore\Classification\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single path that attaches a UNSPSC commodity to a product type (category). A product type has
 * exactly one code: assigning a different one replaces it (a change of commodity), never adds one.
 */
class ProductTypeCommodity
{
    public const INDEX = 'catalog_mapping_category';

    public function assign(int $categoryId, string $code): void
    {
        DB::transaction(function () use ($categoryId, $code) {
            DB::table('gov_catalog_snipe_mappings')->where('category_id', $categoryId)->lockForUpdate()->get(['id']);
            // One commodity per type: drop every other code this type carried (reviewed under D-06). A Finance
            // default that no longer fits shows as needs_review (ProductTypeFinanceDefault::resolve()).
            DB::table('gov_catalog_snipe_mappings')->where('category_id', $categoryId)->where('code', '!=', $code)->delete();
            DB::table('gov_catalog_snipe_mappings')->updateOrInsert(['code' => $code],
                ['category_id' => $categoryId, 'updated_at' => now()]);
        });
    }

    public function codeFor(int $categoryId): ?string
    {
        $codes = DB::table('gov_catalog_snipe_mappings')->where('category_id', $categoryId)->orderBy('id')->pluck('code');

        return $codes->count() === 1 ? $codes->first() : null;
    }

    /** Categories that still carry more than one code (reported by preflight, reconciled by a reviewer). */
    public function categoriesWithSeveralCodes(): array
    {
        return DB::table('gov_catalog_snipe_mappings')->select('category_id', DB::raw('count(*) as total'))
            ->groupBy('category_id')->havingRaw('count(*) > 1')->pluck('total', 'category_id')->all();
    }

    public function hasUniqueIndex(): bool
    {
        return collect(Schema::getIndexes('gov_catalog_snipe_mappings'))->contains(fn ($index) => $index['name'] === self::INDEX);
    }

    /** Applies UNIQUE(category_id) once no category carries several codes. Returns false when blocked. */
    public function enforceUniqueIndex(): bool
    {
        if ($this->hasUniqueIndex()) {
            return true;
        }
        if ($this->categoriesWithSeveralCodes()) {
            return false;
        }
        Schema::table('gov_catalog_snipe_mappings', fn ($table) => $table->unique('category_id', self::INDEX));

        return true;
    }
}
