<?php

namespace GovStore\Tracking\Services;

use Carbon\CarbonImmutable;
use GovStore\Tracking\Models\Initiative;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the programme Manage tab: setup checks, to-dos, readiness and lifecycle history.
 * Delivery figures come from the monitor read model so both tabs agree; callers authorize first.
 */
final class TrackingManageService
{
    public const TABS = ['all', 'setup', 'DRAFT', 'ACTIVE', 'ARCHIVED'];

    public const ACTIVITY = ['all', 'setup', 'receipts'];

    private const SETUP_EVENTS = ['CODE_CREATED', 'CODE_ACTIVATED', 'CODE_ARCHIVED', 'CODE_DELETED', 'ORDER_ATTACHED', 'DOCUMENT_ADDED'];

    public function __construct(private TrackingMonitorService $monitor) {}

    /** @param array{edit:bool,head:bool} $can what the viewer may change; decides which to-dos carry an action */
    public function workspace(Initiative $initiative, array $filters, array $can, ?CarbonImmutable $today = null): array
    {
        $monitor = $this->monitor->programme($initiative, [], $today);
        $events = DB::table('gov_tracking_timeline')->where('initiative_id', $initiative->id)
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit(500)->get();
        $archivedAt = [];
        foreach ($events as $event) {
            $meta = json_decode($event->metadata ?? '{}', true) ?: [];
            if ($event->event_type === 'CODE_ARCHIVED' && isset($meta['tracking_code_id'])) {
                $archivedAt[(int) $meta['tracking_code_id']] ??= $event->occurred_at;
            }
        }
        $requireOrder = (bool) $initiative->require_documents;
        $codes = array_map(function (array $c) use ($requireOrder, $archivedAt) {
            $scopes = collect($c['scopes'])->pluck('dimension')->all();
            $checks = [
                'products' => $c['plan'] === '1_BLANKET' || ($c['plan'] !== 'unknown' && count($c['targets']) > 0),
                'scope' => in_array('GEOGRAPHY', $scopes, true) && in_array('PARTICIPANTS', $scopes, true),
            ];
            if ($requireOrder) {
                $checks['order'] = $c['order_pdf'];
            }
            $missing = array_keys(array_filter($checks, fn ($ok) => ! $ok));

            return $c + ['checks' => $checks, 'missing' => $missing, 'ready' => $missing === [],
                'needs_setup' => $c['status'] !== 'ARCHIVED' && $missing !== [],
                'offices' => count($this->monitor->codeOfficeIds($c)), 'archived_at' => $archivedAt[$c['id']] ?? null];
        }, $monitor['codes']);

        $counts = ['all' => count($codes), 'setup' => count(array_filter($codes, fn ($c) => $c['needs_setup']))];
        foreach (['DRAFT', 'ACTIVE', 'ARCHIVED'] as $status) {
            $counts[$status] = count(array_filter($codes, fn ($c) => $c['status'] === $status));
        }
        $tab = $filters['tab'] ?? 'all';
        $q = mb_strtolower(trim($filters['q'] ?? ''));
        $visible = array_values(array_filter($codes, function ($c) use ($tab, $q) {
            if ($tab === 'setup' && ! $c['needs_setup']) {
                return false;
            }
            if (in_array($tab, ['DRAFT', 'ACTIVE', 'ARCHIVED'], true) && $c['status'] !== $tab) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($c['code'].' '.$c['title'].' '.$c['geography_label']), $q);
        }));
        $order = ['DRAFT' => 0, 'ACTIVE' => 1, 'ARCHIVED' => 2];
        usort($visible, fn ($a, $b) => (($order[$a['status']] ?? 3) <=> ($order[$b['status']] ?? 3))
            ?: ($b['needs_setup'] <=> $a['needs_setup']) ?: strnatcasecmp($a['code'], $b['code']));

        $team = collect($monitor['contacts'])->groupBy('designation');
        $active = array_values(array_filter($codes, fn ($c) => $c['status'] === 'ACTIVE'));
        $withOrder = count(array_filter($active, fn ($c) => $c['order_pdf']));
        $readiness = [
            ['key' => 'head', 'done' => $team->get('HEAD', collect())->count() === 1],
            ['key' => 'officer', 'done' => $team->get('OFFICER', collect())->count() >= 1],
            ['key' => 'active', 'done' => $active !== []],
        ];
        if ($requireOrder) {
            $readiness[] = ['key' => 'orders', 'done' => $active !== [] && $withOrder === count($active), 'value' => $withOrder.' / '.count($active)];
        }

        $last = collect($active)->pluck('last')->filter()->sort()->last();

        return ['initiative' => $initiative, 'owner' => $monitor['owner'], 'codes' => $visible, 'all_codes' => $codes,
            'counts' => $counts, 'filters' => ['tab' => $tab, 'q' => $filters['q'] ?? '', 'activity' => $filters['activity'] ?? 'all'],
            'team' => $team, 'contacts' => $monitor['contacts'], 'readiness' => $readiness,
            'todos' => $this->todos($initiative, $codes, $team, $can),
            'rules' => ['documents' => $requireOrder, 'overshoot' => (bool) $initiative->allow_overshoot, 'metadata' => (bool) $initiative->require_metadata],
            'snapshot' => ['delivered' => $monitor['delivered'], 'received' => $monitor['received'],
                'planned' => array_sum(array_column($active, 'planned')), 'receipts' => $monitor['grn_count'], 'last' => $last],
            'activity' => $this->activity($initiative, $events, $codes, $filters['activity'] ?? 'all'),
            'fiscal_years' => collect($codes)->pluck('fy')->filter()->unique()->values()->all()];
    }

    /** Ordered work for the team; a to-do carries an action only when the viewer may take it. */
    private function todos(Initiative $initiative, array $codes, $team, array $can): array
    {
        $items = [];
        foreach (['HEAD', 'OFFICER'] as $designation) {
            if ($team->get($designation, collect())->isEmpty()) {
                $items[] = ['tone' => 'danger', 'icon' => 'fa-user-plus', 'key' => 'team', 'role' => $designation,
                    'who' => 'head', 'action' => $can['head'] ? 'team' : null];
            }
        }
        $noOrder = array_values(array_filter($codes, fn ($c) => $c['status'] === 'ACTIVE' && in_array('order', $c['missing'], true)));
        if (count($noOrder) > 2) {
            $items[] = ['tone' => 'warning', 'icon' => 'fa-file-pdf', 'key' => 'orders', 'count' => count($noOrder),
                'who' => 'editors', 'action' => $can['edit'] ? 'setup' : null];
        } else {
            foreach ($noOrder as $c) {
                $items[] = ['tone' => 'warning', 'icon' => 'fa-file-pdf', 'key' => 'order', 'code' => $c,
                    'who' => 'editors', 'action' => $can['edit'] ? 'attach' : null];
            }
        }
        foreach ($codes as $c) {
            if ($c['status'] !== 'DRAFT') {
                continue;
            }
            $items[] = $c['ready']
                ? ['tone' => 'info', 'icon' => 'fa-circle-play', 'key' => 'ready', 'code' => $c, 'who' => 'head', 'action' => $can['head'] ? 'activate' : null]
                : ['tone' => 'info', 'icon' => 'fa-pen', 'key' => 'finish', 'code' => $c, 'who' => 'editors', 'action' => $can['edit'] ? 'edit' : null];
        }

        return $items;
    }

    /**
     * Receipts are logged once per product line; group them back into one entry per goods receipt.
     * Lifecycle steps that predate timeline logging are shown from the code record and marked as such.
     */
    private function activity(Initiative $initiative, $events, array $codes, string $filter): array
    {
        $names = collect($codes)->pluck('code', 'id');
        $actors = DB::table('users')->whereIn('id', $events->pluck('actor_id')->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $name = fn ($id) => ($u = $actors->get($id)) ? trim($u->first_name.' '.$u->last_name) : null;
        $items = [];
        $receipts = [];
        $activated = [];
        foreach ($events as $event) {
            $meta = json_decode($event->metadata ?? '{}', true) ?: [];
            $code = $meta['tracking_code'] ?? null;
            if ($event->event_type === 'GRN_RECEIVED') {
                $grn = $meta['grn_reference'] ?? (preg_match('/GRN \(([^)]+)\)/', (string) $event->description, $m) ? $m[1] : null);
                $quantity = $meta['quantity'] ?? (preg_match('/Received (\d+(?:\.\d+)?)/', (string) $event->description, $m) ? $m[1] : 0);
                $key = ($grn ?? 'event-'.$event->id).'|'.$code;
                if (! isset($receipts[$key])) {
                    $receipts[$key] = count($items);
                    $items[] = ['type' => 'receipt', 'event' => 'GRN_RECEIVED', 'at' => $event->occurred_at, 'by' => $name($event->actor_id),
                        'code' => $code, 'grn' => $grn, 'units' => 0.0, 'lines' => 0, 'derived' => false];
                }
                $items[$receipts[$key]]['units'] += (float) $quantity;
                $items[$receipts[$key]]['lines']++;

                continue;
            }
            if ($event->event_type === 'CODE_ACTIVATED' && $code) {
                $activated[$code] = true;
            }
            $items[] = ['type' => in_array($event->event_type, self::SETUP_EVENTS, true) ? 'setup' : 'receipt', 'event' => $event->event_type,
                'at' => $event->occurred_at, 'by' => $name($event->actor_id), 'code' => $code,
                'detail' => in_array($event->event_type, ['DOCUMENT_ADDED', 'ORDER_ATTACHED'], true) ? $event->description : null, 'derived' => false];
        }
        foreach ($codes as $c) {
            if ($c['activated'] && ! isset($activated[$c['code']])) {
                $items[] = ['type' => 'setup', 'event' => 'CODE_ACTIVATED', 'at' => $c['activated'], 'by' => null, 'code' => $c['code'], 'derived' => true];
            }
        }
        if ($initiative->created_at) {
            $items[] = ['type' => 'setup', 'event' => 'PROGRAMME_CREATED', 'at' => $initiative->created_at->toDateTimeString(), 'by' => null, 'code' => null, 'derived' => true];
        }
        usort($items, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        $counts = ['all' => count($items), 'setup' => count(array_filter($items, fn ($i) => $i['type'] === 'setup'))];
        $counts['receipts'] = $counts['all'] - $counts['setup'];
        $items = array_values(array_filter($items, fn ($i) => $filter === 'all' || ($filter === 'setup') === ($i['type'] === 'setup')));

        return ['items' => $items, 'counts' => $counts, 'codes' => $names->all()];
    }
}
