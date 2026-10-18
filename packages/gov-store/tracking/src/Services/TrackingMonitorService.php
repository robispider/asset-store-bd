<?php

namespace GovStore\Tracking\Services;

use Carbon\CarbonImmutable;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use Illuminate\Support\Facades\DB;

final class TrackingMonitorService
{
    public function __construct(private TrackingMonitorMetrics $metrics, private TrackingReceiptResolver $resolver,
        private TrackingOfficeAccess $access) {}

    public function programme(Initiative $initiative, array $filters = [], ?CarbonImmutable $today = null): array
    {
        $this->access->programme($initiative);
        $today ??= CarbonImmutable::today(config('app.timezone'));
        $codes = TrackingCode::with(['targets.allocations', 'scopes', 'fundingType', 'documents'])
            ->where('initiative_id', $initiative->id)->orderBy('tracking_code')->get();
        $associations = TrackingAssociation::whereIn('tracking_code_id', $codes->pluck('id'))->where('status', 'ACTIVE')->get();
        $receipts = collect($this->resolver->resolve($associations));
        $categoryIds = $codes->flatMap(fn ($c) => $c->targets->pluck('category_id'))->merge($associations->pluck('category_id'))->unique();
        // Metadata only, for objects already authorized through their programme; never a catalog query.
        $categories = DB::table('categories')->whereIn('id', $categoryIds)->get(['id', 'name', 'category_type'])->keyBy('id');
        $locationIds = $codes->flatMap(fn ($c) => $c->targets->flatMap(fn ($t) => $t->allocations->pluck('location_id')))
            ->merge($associations->pluck('location_id'))->unique();
        $locations = DB::table('locations')->whereIn('id', $locationIds)->whereNull('deleted_at')->get(['id', 'name', 'company_id'])->keyBy('id');
        $profiles = DB::table('gov_location_profiles')->whereIn('location_id', $locationIds)->get(['location_id', 'geo_area_id', 'office_type', 'office_admin_id', 'geo_area_verified_at'])->keyBy('location_id');
        $suppliers = DB::table('suppliers')->whereIn('id', $receipts->pluck('supplier_id')->filter()->unique())->pluck('name', 'id');
        $manufacturers = DB::table('manufacturers')->whereIn('id', $receipts->pluck('manufacturer_id')->filter()->unique())->pluck('name', 'id');
        $scopeAreaIds = $codes->flatMap(fn ($code) => $code->scopes->where('dimension', 'GEOGRAPHY')->where('target_type', 'GeoArea')->pluck('target_id'))->unique();
        $scopeAreaNames = DB::table('gov_geo_areas')->whereIn('GeoAreaId', $scopeAreaIds)->pluck('en_name', 'GeoAreaId');
        $result = [];
        foreach ($codes as $code) {
            $plan = $this->metrics->plan($code->specificity_level);
            $geography = $code->scopes->firstWhere('dimension', 'GEOGRAPHY');
            $participants = $code->scopes->firstWhere('dimension', 'PARTICIPANTS');
            $geographyLabel = ! $geography || $geography->target_type === 'Inherit'
                ? __('govtracking::general.monitor.geography_anywhere')
                : ($scopeAreaNames->get($geography->target_id) ?? __('govtracking::general.monitor.unavailable'));
            $participantsLabel = match ($participants?->target_type) {
                'CrossTenant' => __('govtracking::general.monitor.participants_cross'),
                'SpecificLocations' => __('govtracking::general.monitor.participants_selected'),
                default => __('govtracking::general.monitor.participants_own'),
            };
            $rows = $receipts->where('tracking_code_id', $code->id)->map(function ($r) use ($locations, $categories, $suppliers, $manufacturers, $code, $initiative) {
                $r['code'] = $code->tracking_code;
                $r['programme'] = $initiative->title;
                $r['office'] = $locations->get($r['location_id'])->name ?? __('govtracking::general.monitor.unavailable');
                $r['item'] = $categories->get($r['category_id'])->name ?? __('govtracking::general.monitor.unavailable');
                $r['supplier'] = $suppliers->get($r['supplier_id']);
                $r['manufacturer'] = $manufacturers->get($r['manufacturer_id']);

                return $r;
            })->values()->all();
            $targets = [];
            foreach ($code->targets as $target) {
                $qty = $associations->where('tracking_code_id', $code->id)->where('category_id', $target->category_id);
                $cells = null;
                if ($plan === '3_MATRIX') {
                    $cells = [];
                    $cellIds = $target->allocations->pluck('location_id')->merge($qty->pluck('location_id'))->unique();
                    foreach ($cellIds as $id) {
                        $cells[$id] = ['allocated' => (float) $target->allocations->where('location_id', $id)->sum('allocated_qty'),
                            'received' => (float) $qty->where('location_id', $id)->sum('quantity')];
                    }
                }
                $line = $this->metrics->line((float) $target->planned_qty, (float) $qty->sum('quantity'), $cells);
                $line += ['id' => $target->id, 'category_id' => $target->category_id,
                    'name' => $categories->get($target->category_id)->name ?? __('govtracking::general.monitor.unavailable'),
                    'type' => $categories->get($target->category_id)->category_type ?? '',
                    'economic_code' => $target->economic_code, 'cells' => $cells,
                    'prices' => $this->metrics->prices(array_values(array_filter($rows, fn ($r) => $r['category_id'] == $target->category_id)))];
                $targets[] = $line;
            }
            $activated = $code->activated_at?->toDateTimeString();
            $delivered = $this->metrics->mean(array_column($targets, 'fulfilment'));
            $expected = $this->metrics->expected($activated, $code->fiscal_year, $today);
            $last = collect($rows)->pluck('date')->filter()->sort()->last();
            // Association dates remain evidence of movement even if receipt metadata was lost.
            $lastMovement = $associations->where('tracking_code_id', $code->id)->max('created_at');
            $result[] = ['id' => $code->id, 'code' => $code->tracking_code, 'title' => $code->task_title,
                'status' => $code->status, 'plan' => $plan, 'fy' => $code->fiscal_year,
                'funding' => $code->fundingType?->name, 'targets' => $targets, 'receipts' => $rows,
                'received' => (float) $associations->where('tracking_code_id', $code->id)->sum('quantity'),
                'planned' => array_sum(array_column($targets, 'planned')), 'delivered' => $delivered, 'expected' => $expected,
                'health' => $this->metrics->health($code->status, in_array(true, array_column($targets, 'over'), true), $delivered, $expected, $activated, $lastMovement ? (string) $lastMovement : null, $today),
                'created' => $code->created_at?->toDateString(), 'activated' => $activated, 'approximate' => $code->activation_is_approximate, 'last' => $last,
                'forecast' => $this->metrics->forecast($delivered, $activated, $today), 'fy_end' => $this->metrics->fiscalEnd($code->fiscal_year),
                'documents' => $code->documents, 'order_pdf' => (bool) $code->order_pdf_path, 'scopes' => $code->scopes,
                'geography_label' => $geographyLabel, 'participants_label' => $participantsLabel,
            ] + $this->financials($rows);
        }
        $result = array_values(array_filter($result, function ($c) use ($filters, $locations) {
            if (($filters['status'] ?? 'all') !== 'all' && $c['status'] !== $filters['status']) {
                return false;
            }
            if (($filters['plan'] ?? 'all') !== 'all' && $c['plan'] !== $filters['plan']) {
                return false;
            }
            $q = mb_strtolower(trim($filters['q'] ?? ''));
            $officeIds = $this->codeOfficeIds($c);
            $haystack = $c['code'].' '.$c['title'].' '.implode(' ', array_column($c['targets'], 'name')).' '
                .$locations->only($officeIds)->map(fn ($o) => $o->name.' '.($o->name_bn ?? ''))->implode(' ');

            return $q === '' || str_contains(mb_strtolower($haystack), $q);
        }));
        $severity = ['over_allocation' => 0, 'stalled' => 1, 'behind' => 2, 'draft' => 3, 'no_target' => 4, 'on_pace' => 5, 'archived' => 6];
        usort($result, fn ($a, $b) => ($severity[$a['health']] <=> $severity[$b['health']]) ?: strnatcasecmp($a['code'], $b['code']));
        $active = array_values(array_filter($result, fn ($c) => $c['status'] === 'ACTIVE'));
        $allRows = array_merge([], ...array_column($active, 'receipts'));
        $lines = array_merge([], ...array_column($active, 'targets'));
        $expectedLines = [];
        foreach ($active as $c) {
            foreach ($c['targets'] as $t) {
                if ($t['fulfilment'] !== null) {
                    $expectedLines[] = $c['expected'];
                }
            }
        }
        $offices = [];
        $ids = array_unique(array_merge([], ...array_map(fn ($c) => $this->codeOfficeIds($c), $active)));
        foreach ($ids as $id) {
            if (! $locations->has($id)) {
                continue;
            }
            $slices = array_values(array_filter(array_map(fn ($c) => $this->slice($c, (int) $id), $result), fn ($s) => $s['touch']));
            $offices[] = ['id' => (int) $id, 'name' => $locations[$id]->name, 'name_bn' => $locations[$id]->name_bn ?? '',
                'company_id' => (int) $locations[$id]->company_id, 'profile' => $profiles->get($id), 'codes' => $slices] + $this->officeSummary($slices);
        }
        usort($offices, fn ($a, $b) => $b['received'] <=> $a['received']);
        $attention = $this->attention($initiative, $result, $offices);
        $owner = DB::table('companies')->where('id', $initiative->owner_company_id)->value('name');
        $contacts = DB::table('gov_tracking_operation_units')->join('users', 'users.id', '=', 'gov_tracking_operation_units.user_id')
            ->where('initiative_id', $initiative->id)->whereNull('users.deleted_at')
            ->get(['users.id', 'users.first_name', 'users.last_name', 'gov_tracking_operation_units.designation']);

        return ['initiative' => $initiative, 'owner' => $owner, 'codes' => $result, 'offices' => $offices, 'attention' => $attention,
            'contacts' => $contacts, 'today' => $today, 'filters' => $filters, 'delivered' => $this->metrics->mean(array_column($lines, 'fulfilment')),
            'expected' => in_array(null, $expectedLines, true) ? null : $this->metrics->mean($expectedLines),
            'received' => array_sum(array_column($active, 'received')), 'active_count' => count($active),
            'draft_count' => count(array_filter($result, fn ($c) => $c['status'] === 'DRAFT')),
            'reached' => count(array_filter($offices, fn ($o) => $o['received'] > 0)),
            'allocated_offices' => count(array_filter($offices, fn ($o) => $o['allocated'] > 0)),
            'waiting_offices' => count(array_filter($offices, fn ($o) => $o['allocated'] > 0 && $o['done'] == 0)),
            'location_meta' => $locations->map(fn ($o) => ['id' => (int) $o->id, 'name' => $o->name, 'name_bn' => $o->name_bn ?? '', 'company_id' => (int) $o->company_id, 'profile' => $profiles->get($o->id)])->all(),
            'receipts' => $allRows, 'items' => $this->items($result), 'activity' => $this->activity($initiative, $result)] + $this->financials($allRows);
    }

    public function codeOfficeIds(array $code): array
    {
        $ids = array_column($code['receipts'], 'location_id');
        foreach ($code['targets'] as $t) {
            if ($t['cells'] !== null) {
                $ids = array_merge($ids, array_keys($t['cells']));
            }
        }

        return array_values(array_unique($ids));
    }

    public function financials(array $rows): array
    {
        $complete = ! collect($rows)->contains(fn ($r) => $r['value'] === null);
        $known = array_sum(array_column($rows, 'value'));

        return ['value' => $complete ? $known : null, 'known_value' => $known, 'value_complete' => $complete,
            'grn_count' => collect($rows)->filter(fn ($r) => $r['grn'] !== null)
                ->map(fn ($r) => $r['document_id'] ?? ($r['location_id'].':'.$r['grn']))->unique()->count(),
            'supplier_count' => collect($rows)->pluck('supplier_id')->filter()->unique()->count(),
            'unknown_count' => collect($rows)->filter(fn ($r) => $r['value'] === null || $r['date'] === null)->count()];
    }

    public function slice(array $code, int $locationId): array
    {
        $rows = array_values(array_filter($code['receipts'], fn ($r) => $r['location_id'] === $locationId));
        $items = [];
        $allocated = $done = 0;
        $over = false;
        foreach ($code['targets'] as $t) {
            $qty = array_sum(array_column(array_filter($rows, fn ($r) => $r['category_id'] == $t['category_id']), 'quantity'));
            $alloc = $t['cells'][$locationId]['allocated'] ?? 0;
            if ($alloc <= 0 && $qty <= 0) {
                continue;
            }
            $d = min($qty, $alloc);
            $allocated += $alloc;
            $done += $d;
            $isOver = $t['cells'] !== null && $qty > $alloc;
            $over = $over || $isOver;
            $items[] = ['name' => $t['name'], 'category_id' => $t['category_id'], 'allocated' => $alloc, 'received' => $qty, 'done' => $d];
        }
        foreach (collect($rows)->groupBy('category_id') as $categoryId => $group) {
            if (collect($items)->contains('category_id', $categoryId)) {
                continue;
            }
            $items[] = ['name' => $group->first()['item'], 'category_id' => $categoryId, 'allocated' => 0, 'received' => $group->sum('quantity'), 'done' => 0];
        }
        $received = array_sum(array_column($rows, 'quantity'));

        return ['code' => $code, 'allocated' => $allocated, 'done' => $done, 'received' => $received, 'items' => $items, 'receipts' => $rows,
            'touch' => $allocated > 0 || $received > 0, 'over' => $over,
            'state' => $this->metrics->office($code['status'], $allocated, $received, $done, $over),
            'last' => collect($rows)->pluck('date')->filter()->sort()->last()] + $this->financials($rows);
    }

    public function officeSummary(array $slices): array
    {
        $active = array_values(array_filter($slices, fn ($s) => $s['code']['status'] === 'ACTIVE'));
        $rows = array_merge([], ...array_column($active, 'receipts'));
        $allocated = array_sum(array_column($active, 'allocated'));
        $done = array_sum(array_column($active, 'done'));

        return ['allocated' => $allocated, 'done' => $done, 'share' => $allocated > 0 ? $done / $allocated : null,
            'received' => array_sum(array_column($active, 'received')), 'last' => collect($rows)->pluck('date')->filter()->sort()->last(),
            'over' => in_array(true, array_column($active, 'over'), true)] + $this->financials($rows);
    }

    private function items(array $codes): array
    {
        $items = [];
        foreach ($codes as $c) {
            foreach ($c['targets'] as $t) {
                $id = $t['category_id'];
                $items[$id] ??= ['name' => $t['name'], 'type' => $t['type'], 'category_id' => $id, 'planned' => 0, 'received' => 0, 'done' => 0, 'draft_planned' => 0, 'economic_codes' => [], 'codes' => [], 'receipts' => []];
                if ($c['status'] === 'DRAFT') {
                    $items[$id]['draft_planned'] += $t['planned'];

                    continue;
                }
                if ($c['status'] !== 'ACTIVE') {
                    continue;
                }
                $items[$id]['planned'] += $t['planned'];
                $items[$id]['done'] += $t['done'];
                $items[$id]['received'] += $t['received'];
                $items[$id]['economic_codes'][] = $t['economic_code'];
                $items[$id]['codes'][] = $c['code'];
            }
        }
        foreach ($codes as $c) {
            if ($c['status'] === 'ACTIVE') {
                foreach ($c['receipts'] as $r) {
                    $id = $r['category_id'];
                    $items[$id] ??= ['name' => $r['item'], 'type' => '', 'category_id' => $id, 'planned' => 0, 'received' => 0, 'done' => 0, 'draft_planned' => 0, 'economic_codes' => [], 'codes' => [$c['code']], 'receipts' => []];
                    $items[$id]['receipts'][] = $r;
                    if (! collect($c['targets'])->contains('category_id', $id)) {
                        $items[$id]['received'] += $r['quantity'];
                    }
                }
            }
        }
        foreach ($items as &$item) {
            $item += $this->financials($item['receipts']);
        }

        return array_values($items);
    }

    private function attention(Initiative $initiative, array $codes, array $offices): array
    {
        $items = [];
        foreach ($codes as $c) {
            $href = route('gov.tracking.initiatives.tracking-codes.show', [$initiative->id, $c['id']]);
            if (in_array($c['health'], ['over_allocation', 'stalled', 'behind', 'draft'], true)) {
                $items[] = ['severity' => match ($c['health']) {
                    'over_allocation' => 'critical','stalled' => 'serious',default => 'warning'
                },
                    'title' => $c['code'].' · '.__('govtracking::general.monitor.health.'.$c['health']),
                    'detail' => __('govtracking::general.monitor.rule.'.$c['health']), 'href' => $href];
            }
            if ($c['unknown_count'] > 0 && $c['status'] === 'ACTIVE') {
                $items[] = ['severity' => 'critical', 'title' => $c['code'], 'detail' => __('govtracking::general.monitor.incomplete_receipts'), 'href' => $href];
            }
            if ($c['status'] === 'ACTIVE' && $c['plan'] !== '1_BLANKET' && collect($c['receipts'])->contains(fn ($r) => ! in_array($r['category_id'], array_column($c['targets'], 'category_id')))) {
                $items[] = ['severity' => 'critical', 'title' => $c['code'], 'detail' => __('govtracking::general.monitor.unplanned_receipt'), 'href' => $href];
            }
            if ($c['status'] === 'ACTIVE' && collect($c['targets'])->contains(fn ($t) => ! $t['economic_code'])) {
                $items[] = ['severity' => 'info', 'title' => $c['code'], 'detail' => __('govtracking::general.monitor.missing_economic'), 'href' => $href];
            }
        }
        foreach ($offices as $o) {
            $href = route('gov.tracking.initiatives.offices.show', [$initiative->id, $o['id']]);
            if ($o['allocated'] > 0 && $o['done'] == 0) {
                $items[] = ['severity' => 'warning', 'title' => $o['name'], 'detail' => __('govtracking::general.monitor.waiting_office'), 'href' => $href];
            }
            if (! ($o['profile']->geo_area_id ?? null)) {
                $items[] = ['severity' => 'info', 'title' => $o['name'], 'detail' => __('govtracking::general.monitor.no_area'), 'href' => $href];
            }
        }
        $order = ['critical' => 0, 'serious' => 1, 'warning' => 2, 'info' => 3];
        usort($items, fn ($a, $b) => $order[$a['severity']] <=> $order[$b['severity']]);

        return $items;
    }

    private function activity(Initiative $initiative, array $codes): array
    {
        $names = collect($codes)->pluck('code')->all();

        return DB::table('gov_tracking_timeline')->where('initiative_id', $initiative->id)->orderByDesc('occurred_at')->limit(100)->get()
            ->filter(function ($event) use ($names) {
                $meta = json_decode($event->metadata ?? '{}', true);

                return isset($meta['tracking_code']) && in_array($meta['tracking_code'], $names, true);
            })->take(20)->values()->all();
    }
}
