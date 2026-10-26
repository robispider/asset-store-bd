<?php

namespace GovStore\Classification\Http\Controllers;

use GovStore\Classification\Models\CatalogCollection;
use GovStore\Classification\Models\CatalogNode;
use GovStore\Classification\Services\CollectionMembershipService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ActionFailure;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class CollectionDiscoveryController extends Controller
{
    public function index(TenantContext $context)
    {
        $collections = CatalogCollection::where('is_active', true)
            ->withCount('nodes')
            ->get()
            ->sortBy([['nodes_count', 'desc'], ['name', 'asc']])
            ->values();

        $featured = $collections->first(fn ($collection) => $collection->nodes_count > 0);
        $others = $collections->reject(fn ($collection) => $featured && $collection->is($featured))->values();
        $sections = collect();
        $adoption = $this->adoptionState($collections->pluck('id')->all(), $context);

        if ($featured) {
            $sections = DB::table('gov_catalog_collection_nodes')
                ->where('collection_id', $featured->id)
                ->whereNotNull('section')
                ->selectRaw('section, count(*) as total')
                ->groupBy('section')
                ->orderByDesc('total')
                ->orderBy('section')
                ->pluck('total', 'section');
        }

        $masterCount = CatalogNode::count();
        $canAdopt = $this->canAdopt();

        return view('gov-classification::discover.collections.index', compact('collections', 'featured', 'others', 'sections', 'adoption', 'masterCount', 'canAdopt'));
    }

    public function show($id, TenantContext $context)
    {
        $collection = CatalogCollection::where('is_active', true)->with(['nodes.catalogNode.snipeMapping.category', 'nodes.catalogNode.enrichment'])->findOrFail($id);

        $adoptedCategoryIds = $this->adoptedCategoryIds($context);
        $mappedTypes = DB::table('gov_catalog_snipe_mappings as mapping')->join('categories as category', 'category.id', '=', 'mapping.category_id')
            ->whereIn('mapping.code', $collection->nodes->pluck('code'))->whereNull('category.deleted_at')
            ->pluck('category.category_type', 'mapping.code');

        $adoptedCount = 0;
        $unadoptedCodes = [];

        foreach ($collection->nodes as $pivot) {
            $node = $pivot->catalogNode;
            $pivot->is_adopted = false;
            $pivot->inventory_type = $mappedTypes[$pivot->code] ?? $pivot->category_type;

            if ($node && $node->snipeMapping && $node->snipeMapping->category) {
                if (in_array($node->snipeMapping->category_id, $adoptedCategoryIds)) {
                    $pivot->is_adopted = true;
                    $adoptedCount++;
                }
            }

            if (! $pivot->is_adopted) {
                $unadoptedCodes[] = $pivot->code;
            }
        }

        $progress = $collection->nodes->count() > 0
            ? round(($adoptedCount / $collection->nodes->count()) * 100)
            : 0;

        $groups = $this->memberGroups($collection);
        $canAdopt = $this->canAdopt();

        return view('gov-classification::discover.collections.show', compact('collection', 'adoptedCount', 'progress', 'unadoptedCodes', 'groups', 'canAdopt'));
    }

    private const SECTION_ICONS = [
        'furniture' => 'fa-chair', 'stationery' => 'fa-paperclip', 'ict' => 'fa-laptop', 'printing' => 'fa-print',
        'electrical' => 'fa-plug', 'cleaning' => 'fa-broom', 'general' => 'fa-users', 'networking' => 'fa-network-wired',
        'telecom' => 'fa-phone', 'av' => 'fa-video', 'pantry' => 'fa-mug-hot', 'safety' => 'fa-fire-extinguisher',
        'security' => 'fa-shield-alt', 'storage' => 'fa-boxes', 'maintenance' => 'fa-tools', 'facilities' => 'fa-building',
        'first_aid' => 'fa-first-aid', 'library' => 'fa-book', 'decor' => 'fa-leaf', 'fleet' => 'fa-car',
        'fleet_tools' => 'fa-wrench', 'fleet_parts' => 'fa-cogs',
    ];

    private const SEGMENT_ICONS = ['43' => 'fa-laptop', '44' => 'fa-paperclip', '45' => 'fa-print', '47' => 'fa-broom', '56' => 'fa-chair'];

    /**
     * Member groups, largest first: the curated section when set, else the member's catalog class.
     * Sets `group` on every pivot so the view can place each row.
     */
    private function memberGroups(CatalogCollection $collection)
    {
        $classCodes = $collection->nodes->filter(fn ($pivot) => ! $pivot->section)->map(fn ($pivot) => $pivot->catalogNode?->parent_code)->filter()->unique();
        $classes = CatalogNode::with('enrichment')->whereIn('code', $classCodes)->get()->keyBy('code');
        $locale = app()->getLocale() === 'bn-BD' ? 'bn' : 'en';

        foreach ($collection->nodes as $pivot) {
            $classCode = $pivot->catalogNode?->parent_code;
            $pivot->group = $pivot->section ?: ($classCode && $classes->has($classCode) ? 'class-'.$classCode : 'other');
        }

        return $collection->nodes
            ->groupBy('group')
            ->map(function ($members, $key) use ($classes, $locale) {
                $class = str_starts_with($key, 'class-') ? $classes->get(substr($key, 6)) : null;
                $isSection = ! $class && $key !== 'other' && Lang::has('classification::collections.sections.'.$key);

                return [
                    'label' => match (true) {
                        $class !== null => $class->getDisplayTitle($locale),
                        $isSection => __('classification::collections.sections.'.$key),
                        default => __('classification::discover.detail.other'),
                    },
                    'code' => $class ? implode(' ', str_split(substr($class->code, 0, 6), 2)) : null,
                    'icon' => $class ? (self::SEGMENT_ICONS[substr($class->code, 0, 2)] ?? 'fa-cube') : (self::SECTION_ICONS[$key] ?? 'fa-cube'),
                    'members' => $members,
                    'total' => $members->count(),
                    'adopted' => $members->where('is_adopted', true)->count(),
                ];
            })
            ->sortByDesc('total');
    }

    /**
     * Category ids available to the working office or company, including global standards.
     */
    private function adoptedCategoryIds(TenantContext $context): array
    {
        $adopted = DB::table('gov_tenant_scope_mappings')
            ->where('reference_type', 'category')
            ->where(function ($query) use ($context) {
                if ($context->companyId > 0) {
                    $query->where(fn ($scope) => $scope->where('scope_type', 'company')->where('scope_id', $context->companyId));
                }
                if (! $context->isCompanyAdmin && $context->locationId > 0) {
                    $query->orWhere(fn ($scope) => $scope->where('scope_type', 'location')->where('scope_id', $context->locationId));
                }
                if (! $context->companyId && (! $context->locationId || $context->isCompanyAdmin)) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->where('is_active', true)
            ->pluck('reference_id')
            ->toArray();
        $global = DB::table('gov_category_governance')->where('governance_type', 'global')->pluck('category_id')->all();

        return array_merge($adopted, $global);
    }

    /**
     * Adopted count and not-yet-adopted codes per collection for the working office or company.
     *
     * @return array<int, array{adopted: int, remaining: list<string>}>
     */
    private function adoptionState(array $collectionIds, TenantContext $context): array
    {
        $adopted = DB::table('gov_catalog_collection_nodes as member')
            ->join('gov_catalog_snipe_mappings as mapping', 'mapping.code', '=', 'member.code')
            ->join('categories as category', 'category.id', '=', 'mapping.category_id')
            ->whereNull('category.deleted_at')
            ->whereIn('member.collection_id', $collectionIds)
            ->whereIn('mapping.category_id', $this->adoptedCategoryIds($context))
            ->select('member.collection_id', 'member.code')
            ->distinct()
            ->get()
            ->groupBy('collection_id')
            ->map(fn ($rows) => $rows->pluck('code')->flip());

        $state = array_fill_keys($collectionIds, ['adopted' => 0, 'remaining' => []]);
        DB::table('gov_catalog_collection_nodes')
            ->whereIn('collection_id', $collectionIds)
            ->orderBy('id')
            ->get(['collection_id', 'code'])
            ->each(function ($member) use (&$state, $adopted) {
                if (isset($adopted[$member->collection_id][$member->code])) {
                    $state[$member->collection_id]['adopted']++;
                } else {
                    $state[$member->collection_id]['remaining'][] = $member->code;
                }
            });

        return $state;
    }

    private function canAdopt(): bool
    {
        // Same rule as the gov.can middleware on the bulk adoption routes (shadow mode included).
        return (bool) auth()->user() && app(GovAccess::class)->permitsRequest(auth()->user(), 'catalog.office.adopt');
    }

    /**
     * API: List active collections for dropdown components.
     */
    public function listActive()
    {
        $collections = CatalogCollection::where('is_active', true)
            ->withCount('nodes')
            ->orderBy('name')
            ->get()
            ->map(fn ($collection) => [
                'id' => $collection->id,
                'name' => $collection->display_name,
                'icon' => $collection->icon,
                'count' => $collection->nodes_count,
            ]);

        return response()->json([
            'success' => true,
            'collections' => $collections,
        ]);
    }

    /**
     * API: Bulk attach nodes (or folders) to a collection. (Gated to Admins)
     */
    public function addNodes(Request $request, CollectionMembershipService $membershipService)
    {
        $user = auth()->user();

        // Strictly block non-admin users from modifying collection memberships
        if (! $user || (! $user->isSuperUser() && ! $user->hasAccess('admin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only system administrators can modify standard collections.',
            ], 403);
        }

        $request->validate([
            'collection_id' => 'required|integer|exists:gov_catalog_collections,id',
            'codes' => 'required|array|min:1',
        ]);

        try {
            $result = $membershipService->addNodesToCollection(
                $request->collection_id,
                $request->codes
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => app(ActionFailure::class)->message($e),
            ], 500);
        }
    }
}
