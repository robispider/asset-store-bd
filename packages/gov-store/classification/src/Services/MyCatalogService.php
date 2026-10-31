<?php

namespace GovStore\Classification\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class MyCatalogService
{
    public const TYPES = ['asset', 'component', 'accessory', 'consumable', 'license'];

    /**
     * Retrieve the grid of operational categories scoped by intent-driven tabs.
     * $filters: q (name or code contains) and type (category_type).
     */
    public function getLocalGrid(int $companyId, int $locationId, string $tab = 'active', int $perPage = 50, array $filters = []): LengthAwarePaginator
    {
        $query = $this->localGridQuery($companyId, $locationId, $tab);

        if (($filters['q'] ?? '') !== '') {
            $query->where('categories.name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%');
        }
        if (in_array($filters['type'] ?? null, self::TYPES, true)) {
            $query->where('categories.category_type', $filters['type']);
        }

        return $query->orderBy('categories.name', 'asc')->paginate($perPage);
    }

    /**
     * Everything the catalog summary needs in one pass: the active and archived rows (light columns),
     * their health counts, item totals and the most used category.
     */
    public function getLocalOverview(int $companyId, int $locationId): array
    {
        $active = $this->localGridQuery($companyId, $locationId, 'active')->get();
        $archived = $this->localGridQuery($companyId, $locationId, 'archived')->get();
        $top = $active->sortByDesc('total_usage_count')->first();

        return [
            'active' => $active,
            'archived' => $archived,
            'total_active' => $active->count(),
            'needs_cleanup' => $active->where('total_usage_count', 0)->count(),
            'in_use' => $active->where('total_usage_count', '>', 0)->count(),
            'archived_count' => $archived->count(),
            'items' => (int) $active->sum('total_usage_count'),
            'families' => $active->map(fn ($c) => self::label($c->name)['family'])->unique()->count(),
            'unmapped' => $active->whereNull('unspsc_code')->count(),
            'top' => ($top && $top->total_usage_count > 0) ? ['name' => self::label($top->name)['en'], 'count' => (int) $top->total_usage_count] : null,
        ];
    }

    /**
     * Category names read "English / বাংলা · CODE". Split them so the grid can show each part on its own
     * and group the codes that share one title.
     */
    public static function label(string $name): array
    {
        $code = null;
        $title = $name;
        if (preg_match('/^(.*?)\s+·\s+(\S+)$/u', $name, $m)) {
            [$title, $code] = [$m[1], $m[2]];
        }
        [$en, $bn] = array_pad(array_map('trim', explode(' / ', $title, 2)), 2, null);

        return ['en' => $en, 'bn' => $bn, 'code' => $code, 'family' => $title];
    }

    /**
     * Group a grid page into families: consecutive rows (the grid is name-ordered) sharing one title.
     */
    public static function families(iterable $categories): array
    {
        $families = [];
        foreach ($categories as $category) {
            $label = self::label($category->name);
            $key = $label['family'].'|'.$category->category_type;
            if (! isset($families[$key])) {
                $families[$key] = ['en' => $label['en'], 'bn' => $label['bn'], 'type' => $category->category_type, 'rows' => []];
            }
            $category->catalog_label = $label;
            $families[$key]['rows'][] = $category;
        }

        return array_values(array_map(function (array $family) {
            $rows = collect($family['rows']);

            return $family + [
                'items' => (int) $rows->sum('total_usage_count'),
                'unused' => $rows->where('total_usage_count', 0)->count(),
                'codes' => $rows->map(fn ($r) => $r->catalog_label['code'] ?? $r->name)->all(),
            ];
        }, $families));
    }

    private function localGridQuery(int $companyId, int $locationId, string $tab)
    {
        $query = Category::withoutGlobalScopes()
            ->select('categories.id', 'categories.name', 'categories.category_type')
            ->join('gov_tenant_scope_mappings as usage', function ($join) use ($companyId, $locationId) {
                $join->on('categories.id', '=', 'usage.reference_id')
                     ->where('usage.reference_type', '=', 'category')
                     ->where(function ($q) use ($companyId, $locationId) {
                         if ($companyId > 0) {
                             $q->where(function ($sq) use ($companyId) {
                                 $sq->where('usage.scope_type', 'company')->where('usage.scope_id', $companyId);
                             });
                         }
                         if ($locationId > 0) {
                             $q->orWhere(function ($sq) use ($locationId) {
                                 $sq->where('usage.scope_type', 'location')->where('usage.scope_id', $locationId);
                             });
                         }
                     });
            })
            ->leftJoin('gov_category_governance as gov', 'categories.id', '=', 'gov.category_id')
            ->leftJoin('gov_catalog_snipe_mappings as map', 'categories.id', '=', 'map.category_id')
            ->addSelect(
                'usage.updated_at as adopted_at',
                'usage.is_active as is_adopted_active',
                'usage.scope_type as active_adoption_scope',
                'gov.governance_type',
                'map.code as unspsc_code'
            );

        // Core usage metric for Health Status (Filters correctly by the physical location)
        $usageSubquery = "
            (SELECT COUNT(*) FROM assets INNER JOIN models ON assets.model_id = models.id WHERE models.category_id = categories.id AND assets.location_id = {$locationId} AND assets.deleted_at IS NULL) +
            (SELECT COUNT(*) FROM consumables WHERE category_id = categories.id AND location_id = {$locationId} AND deleted_at IS NULL) +
            (SELECT COUNT(*) FROM components WHERE category_id = categories.id AND location_id = {$locationId} AND deleted_at IS NULL) +
            (SELECT COUNT(*) FROM accessories WHERE category_id = categories.id AND location_id = {$locationId} AND deleted_at IS NULL)
        ";
        
        $query->selectRaw("($usageSubquery) as total_usage_count");

        // Intent-Driven Tab Filters
        if ($tab === 'archived') {
            $query->where('usage.is_active', false);
        } elseif ($tab === 'cleanup') {
            $query->where('usage.is_active', true)
                  ->whereRaw("($usageSubquery) = 0"); // 0 Items = Needs Cleanup
        } else {
            // Default: 'active'
            $query->where('usage.is_active', true);
        }

        return $query;
    }
    /**
     * Get detailed analytics for a single category scoped specifically to the active context.
     */
    public function getLocalDetails(int $categoryId, string $scopeType, int $scopeId, int $locationId)
    {
        $category = Category::withoutGlobalScopes()->findOrFail($categoryId);

        // Verify adoption against the active scope
        $adoption = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where('reference_id', $categoryId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();

        if (!$adoption) {
            return null; // Category not adopted by this scope
        }

        $governance = DB::table('gov_category_governance as gov')
            ->leftJoin('companies', 'gov.created_by_company_id', '=', 'companies.id')
            ->where('gov.category_id', $categoryId)
            ->select('gov.governance_type', 'companies.name as company_name')
            ->first();

        // Localized physical usage counts (Always filtered by the physical active location)
        $stats = [
            'assets'      => DB::table('assets')->where('location_id', $locationId)
                             ->whereIn('model_id', function ($query) use ($categoryId) {
                                $query->select('id')->from('models')->where('category_id', $categoryId)->whereNull('deleted_at');
                             })->whereNull('deleted_at')->count(),
            'consumables' => DB::table('consumables')->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'accessories' => DB::table('accessories')->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'components'  => DB::table('components')->where('location_id', $locationId)->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
            'licenses'    => DB::table('licenses')->where('category_id', $categoryId)->whereNull('deleted_at')->count(),
        ];

        return [
            'category'   => $category,
            'adoption'   => $adoption,
            'governance' => $governance,
            'stats'      => $stats,
            'scopeNoun'  => ($scopeType === 'company') ? 'organization' : 'office location'
        ];
    }

    /**
     * Retrieve all Globally Available Categories (Implicit shared standards with zero scoping restrictions).
     */
    public function getGlobalStandardsGrid(int $perPage = 50): LengthAwarePaginator
    {
        return Category::query()
            ->select('categories.id', 'categories.name', 'categories.category_type')
            ->whereNotIn('categories.id', function ($query) {
                $query->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', 'category');
            })
            ->leftJoin('gov_catalog_snipe_mappings as map', 'categories.id', '=', 'map.category_id')
            ->addSelect('map.code as unspsc_code')
            ->orderBy('categories.name', 'asc')
            ->paginate($perPage);
    }
}