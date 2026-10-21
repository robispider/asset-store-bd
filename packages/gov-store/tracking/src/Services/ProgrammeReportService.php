<?php

namespace GovStore\Tracking\Services;

use App\Models\Asset;
use Carbon\CarbonImmutable;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\Organization\Models\LocationProfile;
use GovStore\StoreOperations\Services\StockLedgerReader;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** A fresh, authorized read model. No monitor cache or legacy delivery facts. */
final class ProgrammeReportService
{
    public function __construct(private TrackingOfficeAccess $access, private TrackingReceiptResolver $receipts,
        private TrackingMonitorMetrics $metrics) {}

    public function programme(Initiative $initiative): array
    {
        // Required even for direct service calls; model binding alone is insufficient.
        $this->access->programme($initiative);

        return DB::transaction(fn () => $this->read($initiative, CarbonImmutable::now(config('app.timezone'))));
    }

    private function read(Initiative $initiative, CarbonImmutable $generated): array
    {
        $codes = TrackingCode::with(['targets.allocations', 'fundingType', 'documents', 'scopes'])
            ->where('initiative_id', $initiative->id)->orderBy('tracking_code')->get();
        $included = $codes->whereIn('status', ['ACTIVE', 'ARCHIVED']);
        $targetPlans = [];
        foreach ($included as $code) {
            $plan = $this->metrics->plan($code->specificity_level);
            foreach ($code->targets as $target) {
                $targetPlans[$target->id] = in_array($plan, ['2_CATEGORY', '3_MATRIX'], true)
                    ? $this->metrics->targetLine((float) $target->planned_qty, $target->allocations, collect(), $plan === '3_MATRIX')['planned'] : null;
            }
        }
        $associations = TrackingAssociation::whereIn('tracking_code_id', $included->pluck('id'))
            ->where('status', 'ACTIVE')->where('created_at', '<=', $generated)->orderBy('id')->get();
        $duplicateAssets = $associations->where('associatable_type', Asset::class)->groupBy('associatable_id')
            ->filter(fn ($links) => $links->count() > 1)->keys()->flip();
        $ambiguous = $associations->filter(fn ($a) => $a->associatable_type === Asset::class && $duplicateAssets->has($a->associatable_id));
        $eligibleAssociations = $associations->diff($ambiguous);
        $resolved = collect($this->receipts->resolve($eligibleAssociations));
        foreach ($ambiguous as $link) {
            $resolved->push(['key' => 'ambiguous:'.$link->id, 'source' => 'unknown:'.$link->id, 'document_id' => null,
                'quantity' => (float) $link->quantity]);
        }
        // Snapshot-only evidence is useful for diagnostics, but cannot establish a posted actual.
        $rows = $this->verifiedRows($resolved, $generated);
        $acceptedKeys = $rows->keyBy('key');
        $excluded = $resolved->reject(fn ($r) => $acceptedKeys->has($r['key']))->values();
        $categoryIds = $included->flatMap(fn ($c) => $c->targets->pluck('category_id'))->merge($rows->pluck('category_id'))->unique();
        $categories = DB::table('categories')->whereIn('id', $categoryIds)->get(['id', 'name', 'category_type'])->keyBy('id');
        $locationIds = $rows->pluck('location_id')->unique();
        // Only metadata for destinations already established by this authorized programme.
        $locations = DB::table('locations')->whereIn('id', $locationIds)->get(['id', 'name', 'company_id', 'deleted_at'])->keyBy('id');
        $profiles = DB::table('gov_location_profiles')->whereIn('location_id', $locationIds)
            ->get(['location_id', 'geo_area_id', 'office_level'])->keyBy('location_id');
        $scopeIds = $included->flatMap(fn ($c) => $c->scopes->where('dimension', 'GEOGRAPHY')->where('target_type', 'GeoArea')->pluck('target_id'));
        $areas = DB::table('gov_geo_areas')->whereIn('GeoAreaId', $profiles->pluck('geo_area_id')->merge($scopeIds)->filter()->unique())->get()->keyBy('GeoAreaId');
        $prefixes = [];
        foreach ($areas as $area) {
            $parts = array_values(array_filter(explode('/', $area->hid), fn ($p) => $p !== ''));
            foreach ($parts as $index => $part) {
                $prefixes[] = '/'.implode('/', array_slice($parts, 0, $index + 1)).'/';
            }
        }
        $ancestors = DB::table('gov_geo_areas')->whereIn('hid', array_unique($prefixes))->get()->keyBy('hid');
        $usage = $this->usage($eligibleAssociations, $rows, $locations);
        $offices = [];
        foreach ($locationIds as $id) {
            $profile = $profiles->get($id);
            $area = $areas->get($profile->geo_area_id ?? null);
            $level = self::level($profile->office_level ?? null, $area->geo_type ?? null);
            $path = [];
            if ($level['value'] === 'headquarters') {
                $path[] = ['id' => 'headquarters', 'label' => __('govtracking::report.levels.headquarters'), 'depth' => 1];
            } elseif ($area) {
                $parts = array_values(array_filter(explode('/', $area->hid), fn ($p) => $p !== ''));
                foreach ($parts as $index => $part) {
                    $parent = $ancestors->get('/'.implode('/', array_slice($parts, 0, $index + 1)).'/');
                    $type = $parent ? GeoAreaService::canonicalType($parent->geo_type ?? '') : '';
                    $depth = ['division' => 1, 'district' => 2, 'upazilla' => 3, 'upazila' => 3][$type] ?? null;
                    if ($depth !== null) {
                        $path[] = ['id' => (string) $parent->GeoAreaId, 'label' => $this->areaName($parent).' · '.__('govtracking::report.geo.'.$depth), 'depth' => $depth];
                    }
                }
            }
            if (! $path || ($level['value'] !== 'headquarters' && $path[0]['depth'] !== 1)) {
                array_unshift($path, ['id' => 'unmapped', 'label' => __('govtracking::report.unmapped'), 'depth' => 1]);
            }
            $officeRows = $rows->where('location_id', $id);
            $offices[] = ['id' => $id, 'name' => $locations->get($id)->name ?? __('govtracking::report.unknown'),
                'level' => $level['value'], 'level_source' => $level['source'], 'mapped' => $area !== null,
                'path' => $path, 'rows' => $officeRows] + $this->totals($officeRows, $usage);
        }
        usort($offices, fn ($a, $b) => strcmp(implode('/', array_column($a['path'], 'label')), implode('/', array_column($b['path'], 'label')))
            ?: strnatcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']));
        $levels = [];
        foreach ([...LocationProfile::OFFICE_LEVELS, 'unknown'] as $level) {
            $members = collect($offices)->where('level', $level);
            if ($members->isNotEmpty()) {
                $levelRows = collect($members->flatMap(fn ($o) => $o['rows'])->all());
                $levels[$level] = ['offices' => $members->count(), 'rows' => $levelRows] + $this->totals($levelRows, $usage);
            }
        }
        $codeRows = [];
        foreach ($included as $code) {
            $actuals = $rows->where('tracking_code_id', $code->id);
            $scope = $code->scopes->firstWhere('dimension', 'GEOGRAPHY');
            $codeRows[] = ['id' => $code->id, 'code' => $code->tracking_code, 'status' => $code->status,
                'area' => $scope && $scope->target_type === 'GeoArea' ? $this->areaName($areas->get($scope->target_id)) : __('govtracking::report.not_specified'),
                'planned' => $code->targets->isEmpty() || $code->targets->contains(fn ($t) => $targetPlans[$t->id] === null)
                    ? null : $this->sum($code->targets->map(fn ($t) => $targetPlans[$t->id])->all()),
                'fy' => $code->fiscal_year, 'funding' => $code->fundingType?->name] + $this->totals($actuals, $usage);
        }
        $items = [];
        foreach ($categoryIds as $id) {
            $targets = $included->flatMap(fn ($c) => $c->targets)->where('category_id', $id);
            $actuals = $rows->where('category_id', $id);
            $matrix = [];
            foreach ($levels as $key => $level) {
                $matrix[$key] = $this->sum($level['rows']->where('category_id', $id)->pluck('quantity')->all());
            }
            $hasUntargetedReceipts = $actuals->contains(fn ($r) => ! $targets->contains('tracking_code_id', $r['tracking_code_id']));
            $items[] = ['id' => $id, 'name' => $this->itemName($categories->get($id)->name ?? __('govtracking::report.unknown')),
                'type' => $categories->get($id)->category_type ?? '', 'planned' => $targets->isEmpty() || $hasUntargetedReceipts || $targets->contains(fn ($t) => $targetPlans[$t->id] === null)
                    ? null : $this->sum($targets->map(fn ($t) => $targetPlans[$t->id])->all()),
                'economic' => $targets->pluck('economic_code')->filter()->unique()->implode(', '), 'matrix' => $matrix]
                + $this->totals($actuals, $usage);
        }
        $months = $rows->filter(fn ($r) => $r['date'] !== null)->groupBy(fn ($r) => substr($r['date'], 0, 7))
            ->map(fn ($r, $month) => ['label' => $month, 'value' => $this->sum($r->pluck('quantity')->all())])->sortKeys()->values()->all();
        $totals = $this->totals($rows, $usage);
        $targets = $included->flatMap(fn ($c) => $c->targets);
        $required = $initiative->require_documents ? $included : collect();
        $quality = [
            $this->check('receipts', $rows->count(), $resolved->count()),
            $this->check('receipt_dates', $rows->where('date_estimated', false)->count(), $rows->count()),
            $this->check('values', $rows->filter(fn ($r) => $r['value'] !== null)->count(), $rows->count()),
            $this->check('mapping', collect($offices)->where('mapped', true)->count(), count($offices)),
            $this->check('level', collect($offices)->where('level_source', 'explicit')->count(), count($offices)),
            $this->check('orders', $required->filter(fn ($c) => $c->order_pdf_path || $c->documents->isNotEmpty())->count(), $required->count()),
            $this->check('suppliers', $rows->filter(fn ($r) => $r['supplier_id'] !== null)->count(), $rows->count()),
            // No authoritative economic-code validation registry is used by tracking today.
            $this->check('economic', $targets->filter(fn ($t) => trim((string) $t->economic_code) !== '')->count(), $targets->count(), 'unverified'),
            $this->check('activation', $included->filter(fn ($c) => $c->activated_at && ! $c->activation_is_approximate)->count(), $included->count()),
            $this->check('targets', $targets->filter(fn ($t) => $t->allocations->isNotEmpty())->count(), $targets->count()),
            $this->check('usage', $totals['received'] - $totals['unknown'], $totals['received']),
        ];

        return ['generated' => $generated, 'reference' => 'TRK-'.$initiative->id.'-'.$generated->format('Ymd-His'),
            'owner' => DB::table('companies')->where('id', $initiative->owner_company_id)->value('name'),
            'codes' => $codeRows, 'draft_count' => $codes->where('status', 'DRAFT')->count(), 'offices' => $offices,
            'levels' => $levels, 'items' => $items, 'months' => $months, 'totals' => $totals,
            'hierarchy' => $this->hierarchy($offices, $usage), 'quality' => $quality,
            'excluded_quantity' => $this->sum($excluded->pluck('quantity')->all()),
            'inferred_levels' => collect($offices)->where('level_source', 'inferred')->count(),
            'unknown_levels' => collect($offices)->where('level_source', 'unknown')->count()];
    }

    public static function level(?string $explicit, ?string $type): array
    {
        if (in_array($explicit, LocationProfile::OFFICE_LEVELS, true)) {
            return ['value' => $explicit, 'source' => 'explicit'];
        }
        $value = ['division' => 'divisional', 'district' => 'district', 'upazilla' => 'upazila', 'upazila' => 'upazila'][GeoAreaService::canonicalType($type ?? '')] ?? 'unknown';

        return ['value' => $value, 'source' => $value === 'unknown' ? 'unknown' : 'inferred'];
    }

    /** Resolve a real posted line, not a snapshot price, and reject over-attribution. */
    private function verifiedRows(Collection $resolved, CarbonImmutable $generated): Collection
    {
        $documents = DB::table('gov_documents')->whereIn('id', $resolved->pluck('document_id')->filter()->unique())
            ->where('type', 'receipt')->where('status', 'POSTED')
            ->where(fn ($q) => $q->where('posted_at', '<=', $generated)
                ->orWhere(fn ($legacy) => $legacy->whereNull('posted_at')->where('created_at', '<=', $generated)))
            ->get(['id', 'posted_at', 'created_at', 'supplier_id', 'location_id'])->keyBy('id');
        $items = DB::table('gov_document_items')->whereIn('document_id', $documents->keys())->get()->keyBy('id');
        $categories = [];
        foreach (['assetmodel' => 'models', 'accessory' => 'accessories', 'consumable' => 'consumables', 'component' => 'components'] as $type => $table) {
            $ids = $items->filter(fn ($item) => StockLedgerReader::typeKey($item->product_type) === $type)->pluck('product_id')->unique();
            if ($ids->isNotEmpty()) {
                $categories[$type] = DB::table($table)->whereIn('id', $ids)->pluck('category_id', 'id');
            }
        }
        $candidates = [];
        foreach ($resolved as $row) {
            $doc = $documents->get($row['document_id']);
            $item = $items->get($row['document_item_id'] ?? null);
            if (! $doc || ! $item || $item->document_id !== $doc->id || (int) $doc->location_id !== $row['location_id']
                || (int) ($categories[StockLedgerReader::typeKey($item->product_type)][$item->product_id] ?? 0) !== $row['category_id'] || $row['quantity'] <= 0) {
                continue;
            }
            $row['line_id'] = $item->id;
            $row['line_quantity'] = (float) $item->quantity;
            $row['unit_cost'] = $item->unit_cost === null || $item->unit_cost < 0 ? null : (float) $item->unit_cost;
            // Preserve sub-cent shares until aggregation; rounding every split first
            // can inflate one receipt line when it is attributed to several codes.
            $row['value'] = $row['unit_cost'] === null ? null : round($row['quantity'] * $row['unit_cost'], 8);
            // Existing posted legacy receipts may predate posting attribution. Their
            // state and line establish receipt, but creation is only an estimated date.
            $row['date'] = $doc->posted_at ?? $doc->created_at;
            $row['date_estimated'] = $doc->posted_at === null;
            $row['supplier_id'] = $doc->supplier_id;
            $candidates[] = $row;
        }
        $candidates = collect($candidates);
        $lineTotals = $candidates->groupBy('line_id')->map(fn ($group) => $this->sum($group->pluck('quantity')->all()));

        return $candidates->filter(fn ($r) => $lineTotals[$r['line_id']] <= $r['line_quantity'])->values();
    }

    private function usage(Collection $associations, Collection $rows, Collection $locations): array
    {
        $assetLinks = $associations->where('associatable_type', Asset::class);
        // Only minimal state, never tags, serials or assignee identities in the returned model.
        $assets = DB::table('assets')->leftJoin('status_labels', 'status_labels.id', '=', 'assets.status_id')
            ->whereIn('assets.id', $assetLinks->pluck('associatable_id'))->get(['assets.id', 'assets.location_id', 'assets.company_id',
                'assets.deleted_at', 'assets.assigned_to', 'status_labels.archived', 'status_labels.deployable'])->keyBy('id');
        $registrations = DB::table('gov_asset_registrations')->join('gov_document_items', 'gov_document_items.id', '=', 'gov_asset_registrations.intake_item_id')
            ->whereIn('gov_asset_registrations.asset_id', $assetLinks->pluck('associatable_id'))
            ->get(['gov_asset_registrations.asset_id', 'gov_document_items.id as item_id', 'gov_document_items.document_id'])->groupBy('asset_id');
        $result = [];
        $seen = [];
        foreach ($assetLinks as $link) {
            $asset = $assets->get($link->associatable_id);
            $regs = $registrations->get($link->associatable_id, collect());
            if (! $asset || $regs->count() !== 1 || (float) $link->quantity !== 1.0 || isset($seen[$asset->id])) {
                continue;
            }
            $reg = $regs->first();
            $row = $rows->first(fn ($r) => $r['tracking_code_id'] == $link->tracking_code_id && $r['location_id'] == $link->location_id
                && $r['category_id'] == $link->category_id && $r['document_id'] === $reg->document_id && $r['line_id'] === $reg->item_id
                && ($r['source'] === 'line:'.$reg->item_id || ($link->delivery_id && $r['source'] === 'delivery:'.$link->delivery_id)));
            $destination = $locations->get($link->location_id);
            if (! $row || ! $destination || ! $this->access->inventoryKey((int) $link->location_id, (int) $destination->company_id)
                || ! $this->access->inventoryKey((int) $asset->location_id, (int) $asset->company_id)) {
                continue;
            }
            if (! $asset->deleted_at && ($asset->archived === null || $asset->deployable === null)) {
                continue;
            }
            $seen[$asset->id] = true;
            $state = $asset->deleted_at || $asset->archived || ! $asset->deployable ? 'other'
                : ((int) $asset->location_id !== (int) $link->location_id || (int) $asset->company_id !== (int) $destination->company_id
                    ? 'other' : ($asset->assigned_to ? 'used' : 'store'));
            $result[$row['key']][$state] = ($result[$row['key']][$state] ?? 0) + 1;
        }

        return $result;
    }

    private function totals(Collection $rows, array $usage): array
    {
        $last = $rows->pluck('date')->filter()->sort()->last();
        $result = ['received' => $this->sum($rows->pluck('quantity')->all()), 'known_value' => $this->sum($rows->pluck('value')->all(), 2),
            'value_complete' => ! $rows->contains(fn ($r) => $r['value'] === null),
            'grns' => $rows->pluck('document_id')->filter()->unique()->count(), 'last' => $last,
            'last_estimated' => $rows->where('date', $last)->contains(fn ($r) => $r['date_estimated']),
            'estimated_dates' => $rows->where('date_estimated', true)->count(),
            'used' => 0, 'store' => 0, 'other' => 0];
        $prices = $rows->pluck('unit_cost')->filter(fn ($price) => $price !== null);
        $result['price_min'] = $prices->min();
        $result['price_max'] = $prices->max();
        foreach ($rows as $row) {
            foreach (['used', 'store', 'other'] as $state) {
                $result[$state] += $usage[$row['key']][$state] ?? 0;
            }
        }
        $result['unknown'] = max(0, round($result['received'] - $result['used'] - $result['store'] - $result['other'], 4));

        return $result;
    }

    private function hierarchy(array $offices, array $usage): array
    {
        $tree = [];
        foreach ($offices as $office) {
            $branch = &$tree;
            foreach ($office['path'] as $part) {
                $branch[$part['id']] ??= ['part' => $part, 'offices' => [], 'children' => []];
                $branch[$part['id']]['offices'][$office['id']] = $office;
                $branch = &$branch[$part['id']]['children'];
            }
            unset($branch);
        }
        $result = [];
        $walk = function (array $nodes, array $trail = []) use (&$walk, &$result, $usage) {
            foreach ($nodes as $node) {
                $path = [...$trail, $node['part']['label']];
                if ($node['part']['depth'] !== 3 || count($node['offices']) > 1) {
                    $rows = collect($node['offices'])->flatMap(fn ($o) => $o['rows']);
                    $result[] = ['band' => true, 'depth' => $node['part']['depth'], 'name' => $node['part']['label'],
                        'path' => implode(' › ', $path), 'offices' => count($node['offices'])] + $this->totals($rows, $usage);
                }
                foreach ($node['offices'] as $office) {
                    if (count($office['path']) === count($path)) {
                        $result[] = ['band' => false, 'depth' => count($path), 'path' => implode(' › ', $path)] + $office;
                    }
                }
                $walk($node['children'], $path);
            }
        };
        $walk($tree);

        return $result;
    }

    private function check(string $key, float $good, float $total, ?string $status = null): array
    {
        return ['key' => $key, 'good' => round($good, 4), 'total' => round($total, 4),
            'status' => $total == 0 ? 'na' : ($status ?? ($good == $total ? 'complete' : 'incomplete'))];
    }

    private function sum(array $numbers, int $precision = 4): float
    {
        return round(array_sum($numbers), $precision);
    }

    private function areaName(?object $area): string
    {
        return $area ? (str_starts_with(app()->getLocale(), 'bn') && $area->bn_name ? $area->bn_name : $area->en_name) : __('govtracking::report.not_specified');
    }

    private function itemName(string $name): string
    {
        $parts = preg_split('/\s+\/\s+(?=[\x{0980}-\x{09FF}])/u', $name, 2);

        return count($parts) === 2 ? (trim($parts[str_starts_with(app()->getLocale(), 'bn') ? 1 : 0]) ?: $name) : $name;
    }
}
