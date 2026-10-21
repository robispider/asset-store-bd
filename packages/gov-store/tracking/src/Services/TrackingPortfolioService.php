<?php

namespace GovStore\Tracking\Services;

use Carbon\CarbonImmutable;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** All related queries are bounded by the caller's freshly scoped programme IDs. */
final class TrackingPortfolioService
{
    public const SORTS = ['priority', 'title', 'health', 'delivered', 'value', 'offices', 'codes', 'team', 'last'];
    private const RANK = ['attention' => 0, 'behind' => 1, 'no_target' => 2, 'on_pace' => 3, 'setup' => 4, 'closed' => 5, 'archived' => 6];

    public function __construct(private TrackingOfficeAccess $access, private TrackingMonitorMetrics $metrics,
        private TrackingReceiptResolver $resolver, private TrackingMonitorService $monitor) {}

    public function portfolio(array $filters = [], ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today(config('app.timezone'));
        $initiatives = $this->access->programmes()->orderBy('id')->get();
        $ids = $initiatives->pluck('id');
        $owners = DB::table('companies')->whereIn('id', $initiatives->pluck('owner_company_id'))->pluck('name', 'id');
        $teams = DB::table('gov_tracking_operation_units')->join('users', 'users.id', '=', 'gov_tracking_operation_units.user_id')
            ->whereIn('initiative_id', $ids)->whereNull('users.deleted_at')->orderBy('users.id')
            ->get(['initiative_id', 'user_id', 'designation', 'first_name', 'last_name'])->groupBy('initiative_id');
        $adminCompanies = DB::table('gov_company_admins')->where('user_id', auth()->id())->pluck('company_id')->all();
        $super = auth()->user()?->isSuperUser() ?? false;
        $codes = TrackingCode::with('targets.allocations')->whereIn('initiative_id', $ids)->orderBy('id')->get();
        $associations = TrackingAssociation::whereIn('tracking_code_id', $codes->pluck('id'))->where('status', 'ACTIVE')->get()->groupBy('tracking_code_id');
        $receipts = collect($this->resolver->resolve($associations->flatten(1)))->groupBy('tracking_code_id');
        $locationIds = $codes->flatMap(fn ($c) => $c->targets->flatMap(fn ($t) => $t->allocations->pluck('location_id')))
            ->merge($associations->flatten(1)->pluck('location_id'))->unique();
        $locations = DB::table('locations')->whereIn('id', $locationIds)->whereNull('deleted_at')->pluck('name', 'id');
        $profiles = DB::table('gov_location_profiles')->whereIn('location_id', $locationIds)->get(['location_id', 'geo_area_id'])->keyBy('location_id');
        $byProgramme = $codes->groupBy('initiative_id');
        $summaries = [];
        foreach ($initiatives as $initiative) {
            $team = $teams->get($initiative->id, collect())->map(fn ($person) => [
                'id' => (int) $person->user_id, 'name' => trim($person->first_name.' '.$person->last_name), 'role' => $person->designation])->all();
            $mine = array_values(array_filter($team, fn ($person) => $person['id'] === (int) auth()->id()));
            $manager = $super || in_array($initiative->owner_company_id, $adminCompanies);
            $canEdit = $manager || collect($mine)->contains('role', 'HEAD');
            $programmeCodes = [];
            foreach ($byProgramme->get($initiative->id, collect()) as $code) {
                $qty = $associations->get($code->id, collect());
                $rows = $receipts->get($code->id, collect())->all();
                $plan = $this->metrics->plan($code->specificity_level);
                $targets = [];
                foreach ($code->targets as $target) {
                    $targets[] = $this->metrics->targetLine((float) $target->planned_qty, $target->allocations,
                        $qty->where('category_id', $target->category_id), $plan === '3_MATRIX')
                        + ['category_id' => $target->category_id, 'economic_code' => $target->economic_code];
                }
                $delivered = $this->metrics->mean(array_column($targets, 'fulfilment'));
                $expected = $this->metrics->expected($code->activated_at?->toDateTimeString(), $code->fiscal_year, $today);
                $movement = $qty->max('created_at');
                $programmeCodes[] = ['id' => $code->id, 'code' => $code->tracking_code, 'status' => $code->status,
                    'plan' => $plan, 'targets' => $targets, 'receipts' => $rows, 'expected' => $expected,
                    'health' => $this->metrics->health($code->status, in_array(true, array_column($targets, 'over'), true),
                        $delivered, $expected, $code->activated_at?->toDateTimeString(), $movement ? (string) $movement : null, $today)]
                    + $this->monitor->financials($rows);
            }
            $active = array_values(array_filter($programmeCodes, fn ($c) => $c['status'] === 'ACTIVE'));
            $rows = array_merge([], ...array_column($active, 'receipts'));
            $offices = [];
            foreach ($active as $code) {
                foreach ($code['receipts'] as $row) {
                    $id = $row['location_id'];
                    if (! $locations->has($id)) continue;
                    $offices[$id] ??= ['id' => $id, 'name' => $locations[$id], 'allocated' => 0, 'done' => 0, 'received' => 0, 'profile' => $profiles->get($id)];
                    $offices[$id]['received'] += $row['quantity'];
                }
                foreach ($code['targets'] as $target) {
                    foreach ($target['cells'] ?? [] as $id => $cell) {
                        if (! $locations->has($id)) continue;
                        $offices[$id] ??= ['id' => $id, 'name' => $locations[$id], 'allocated' => 0, 'done' => 0, 'received' => 0, 'profile' => $profiles->get($id)];
                        $offices[$id]['allocated'] += $cell['allocated'];
                        // Monitor office slices use resolved receipt evidence rather than associations.
                        $received = collect($code['receipts'])->where('location_id', $id)->where('category_id', $target['category_id'])->sum('quantity');
                        $offices[$id]['done'] += min($received, $cell['allocated']);
                    }
                }
            }
            $attention = $this->monitor->attention($initiative, $active, array_values($offices));
            $health = match ($initiative->status) {
                'Planning' => 'setup', 'Closed' => 'closed', 'Archived' => 'archived',
                default => collect($attention)->contains(fn ($a) => in_array($a['severity'], ['critical', 'serious'])) ? 'attention'
                    : (collect($attention)->contains('severity', 'warning') ? 'behind'
                        : (! $active || collect($active)->contains('health', 'no_target') ? 'no_target' : 'on_pace')),
            };
            $lines = array_merge([], ...array_column($active, 'targets'));
            $expectedLines = [];
            foreach ($active as $code) foreach ($code['targets'] as $target) if ($target['fulfilment'] !== null) $expectedLines[] = $code['expected'];
            $head = collect($team)->firstWhere('role', 'HEAD');
            $readiness = [
                ['label' => __('govtracking::portfolio.ready_team'), 'done' => collect($team)->where('role', 'HEAD')->count() === 1 && collect($team)->contains('role', 'OFFICER')],
                ['label' => __('govtracking::portfolio.ready_code'), 'done' => count($programmeCodes) > 0],
                ['label' => __('govtracking::portfolio.ready_active'), 'done' => count($active) > 0],
            ];
            $next = $initiative->status === 'Planning' ? (collect($readiness)->firstWhere('done', false)['label'] ?? __('govtracking::portfolio.review_setup'))
                : ($attention[0]['detail'] ?? __('govtracking::portfolio.review_progress'));
            $summaries[] = ['id' => $initiative->id, 'title' => $initiative->title, 'purpose' => $initiative->purpose,
                'status' => $initiative->status, 'funding' => $initiative->primary_funding, 'owner' => $owners->get($initiative->owner_company_id, ''),
                'years' => $byProgramme->get($initiative->id, collect())->pluck('fiscal_year')->unique()->sort()->values()->all(),
                'updated' => $initiative->updated_at?->toDateTimeString(), 'team' => $team, 'head' => $head, 'mine' => (bool) $mine,
                'can_edit' => $canEdit, 'can_manage' => $manager || (bool) $mine, 'readiness' => $readiness, 'next' => $next,
                'delivered' => $this->metrics->mean(array_column($lines, 'fulfilment')),
                'expected' => in_array(null, $expectedLines, true) ? null : $this->metrics->mean($expectedLines),
                'health' => $health, 'health_mix' => collect($active)->countBy('health')->all(), 'attention' => $attention,
                'active_count' => count($active), 'draft_count' => collect($programmeCodes)->where('status', 'DRAFT')->count(),
                'reached_ids' => array_keys(array_filter($offices, fn ($o) => $o['received'] > 0)),
                'allocated_ids' => array_keys(array_filter($offices, fn ($o) => $o['allocated'] > 0)),
                'done_ids' => array_keys(array_filter($offices, fn ($o) => $o['done'] > 0)),
                'last' => collect($rows)->pluck('date')->filter()->sort()->last(), 'rows' => $rows]
                + $this->monitor->financials($rows);
        }
        $base = array_values(array_filter($summaries, function ($p) use ($filters) {
            $q = mb_strtolower(trim($filters['q'] ?? ''));
            return (($filters['funding'] ?? 'all') === 'all' || $p['funding'] === $filters['funding'])
                && (empty($filters['mine']) || $p['mine'])
                && ($q === '' || str_contains(mb_strtolower($p['title'].' '.$p['purpose'].' '.$p['owner'].' '.implode(' ', array_column($p['team'], 'name'))), $q));
        }));
        $operational = array_values(array_filter($base, fn ($p) => $p['status'] === 'Active'));
        $summaryRows = array_merge([], ...array_column($operational, 'rows'));
        $union = fn ($key) => array_values(array_unique(array_merge([], ...array_column($operational, $key))));
        $allocated = $union('allocated_ids');
        $done = $union('done_ids');
        $counts = array_merge(array_fill_keys(['Active', 'Planning', 'Closed', 'Archived'], 0), collect($base)->countBy('status')->all());
        $list = array_values(array_filter($base, fn ($p) => ($filters['status'] ?? 'all') === 'all' || $p['status'] === $filters['status']));
        $sort = $filters['sort'] ?? 'priority';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? -1 : 1;
        usort($list, function ($a, $b) use ($sort, $direction) {
            if ($sort === 'priority') return $direction * $this->priorityOrder($a, $b);
            $x = $this->sortValue($a, $sort); $y = $this->sortValue($b, $sort);
            if ($x === null && $y === null) return $a['id'] <=> $b['id'];
            if ($x === null) return 1;
            if ($y === null) return -1;
            return $direction * (is_string($x) ? strnatcasecmp($x, $y) : ($x <=> $y)) ?: ($a['id'] <=> $b['id']);
        });
        $page = min(max(1, (int) ceil(count($list) / 20)), max(1, (int) ($filters['page'] ?? 1)));
        if (isset($filters['page'])) $filters['page'] = $page;
        $paginator = new LengthAwarePaginator(array_slice($list, ($page - 1) * 20, 20), count($list), 20, $page,
            ['path' => route('gov.tracking.initiatives.index'), 'query' => $filters]);
        $priority = collect($operational)->sort(fn ($a, $b) => $this->priorityOrder($a, $b))->first(fn ($p) => in_array($p['health'], ['attention', 'behind', 'no_target']));

        return ['programmes' => $paginator, 'filters' => $filters, 'counts' => $counts, 'base_count' => count($base),
            'visible_count' => count($summaries), 'priority' => $priority, 'can_create' => $super || count($adminCompanies) > 0,
            'summary' => ['operational' => count($operational), 'health' => collect($operational)->countBy('health')->all(),
                'reached' => count($union('reached_ids')), 'allocated' => count($allocated), 'waiting' => count(array_diff($allocated, $done)),
                'active_count' => collect($base)->whereIn('status', ['Active', 'Planning'])->sum('active_count'),
                'draft_count' => collect($base)->whereIn('status', ['Active', 'Planning'])->sum('draft_count')]
                + $this->monitor->financials($summaryRows)];
    }

    private function priorityOrder(array $a, array $b): int
    {
        $severity = ['critical' => 0, 'serious' => 1, 'warning' => 2, 'info' => 3];
        return (self::RANK[$a['health']] <=> self::RANK[$b['health']])
            ?: (($severity[$a['attention'][0]['severity'] ?? ''] ?? 4) <=> ($severity[$b['attention'][0]['severity'] ?? ''] ?? 4))
            ?: strcmp($b['updated'] ?? '', $a['updated'] ?? '') ?: ($a['id'] <=> $b['id']);
    }

    private function sortValue(array $p, string $key): mixed
    {
        return match ($key) {
            'title' => $p['title'], 'health' => self::RANK[$p['health']],
            'delivered' => $p['delivered'] !== null && $p['expected'] !== null ? $p['delivered'] - $p['expected'] : null,
            'value' => $p['value'], 'offices' => count($p['reached_ids']), 'codes' => $p['active_count'] + $p['draft_count'],
            'team' => count($p['team']), 'last' => $p['last'], default => null,
        };
    }
}
