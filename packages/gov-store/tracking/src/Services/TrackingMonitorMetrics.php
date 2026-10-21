<?php

namespace GovStore\Tracking\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Unit-aware calculations. Null means the evidence does not define a denominator/date. */
final class TrackingMonitorMetrics
{
    /** Shared allocation arithmetic for the monitor and the portfolio. */
    public function targetLine(float $planned, Collection $allocations, Collection $associations, bool $matrix): array
    {
        $cells = null;
        if ($matrix) {
            $cells = [];
            foreach ($allocations->pluck('location_id')->merge($associations->pluck('location_id'))->unique() as $id) {
                $cells[$id] = ['allocated' => (float) $allocations->where('location_id', $id)->sum('allocated_qty'),
                    'received' => (float) $associations->where('location_id', $id)->sum('quantity')];
            }
        }

        return $this->line($planned, (float) $associations->sum('quantity'), $cells) + ['cells' => $cells];
    }

    public function plan(string $value): string
    {
        return match (strtoupper($value)) {
            'MATRIX','3_MATRIX' => '3_MATRIX', 'CATEGORY','2_CATEGORY' => '2_CATEGORY',
            'BLANKET','OPEN','1_BLANKET' => '1_BLANKET', default => 'unknown',
        };
    }

    public function line(float $planned, float $received, ?array $cells = null): array
    {
        $done = min($received, max(0, $planned));
        $over = $planned > 0 && $received > $planned;
        if ($cells !== null) {
            $planned = array_sum(array_column($cells, 'allocated'));
            $done = 0;
            foreach ($cells as $cell) {
                $done += min($cell['received'], $cell['allocated']);
                $over = $over || $cell['received'] > $cell['allocated'];
            }
        }

        return ['planned' => $planned, 'received' => $received, 'done' => $done,
            'fulfilment' => $planned > 0 ? $done / $planned : null, 'over' => $over];
    }

    public function fiscalEnd(string $year): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-(\d{2}|\d{4})$/', $year, $m)) {
            return null;
        }
        $end = strlen($m[2]) === 2 ? intdiv((int) $m[1], 100) * 100 + (int) $m[2] : (int) $m[2];
        if ($end !== (int) $m[1] + 1) {
            return null;
        }

        return CarbonImmutable::create($end, 6, 30, 0, 0, 0, config('app.timezone'))->startOfDay();
    }

    public function expected(?string $activated, string $year, CarbonImmutable $today): ?float
    {
        $end = $this->fiscalEnd($year);
        if (! $activated || ! $end) {
            return null;
        }
        $start = CarbonImmutable::parse($activated)->startOfDay();
        $days = $start->diffInDays($end, false);

        return $days > 0 ? max(0, min(1, $start->diffInDays($today->startOfDay(), false) / $days)) : null;
    }

    public function mean(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($v) => $v !== null));

        return $values ? array_sum($values) / count($values) : null;
    }

    public function health(string $status, bool $over, ?float $delivered, ?float $expected,
        ?string $activated, ?string $last, CarbonImmutable $today): string
    {
        if ($status === 'DRAFT') {
            return 'draft';
        }
        if ($status !== 'ACTIVE') {
            return 'archived';
        }
        if ($over) {
            return 'over_allocation';
        }
        $movement = $last ?: $activated;
        if ($movement && CarbonImmutable::parse($movement)->startOfDay()->diffInDays($today->startOfDay(), false) > 30) {
            return 'stalled';
        }
        if ($delivered !== null && $expected !== null && $delivered < $expected - .05 - 1e-10) {
            return 'behind';
        }

        return $expected === null || $delivered === null ? 'no_target' : 'on_pace';
    }

    public function forecast(?float $delivered, ?string $activated, CarbonImmutable $today): ?CarbonImmutable
    {
        if (! $activated || $delivered === null || $delivered <= 0) {
            return null;
        }
        if ($delivered >= 1) {
            return $today;
        }
        $days = CarbonImmutable::parse($activated)->startOfDay()->diffInDays($today->startOfDay(), false);

        return $days > 0 ? $today->addDays((int) ceil((1 - $delivered) / ($delivered / $days))) : null;
    }

    public function office(string $status, float $allocated, float $received, float $done, bool $over): string
    {
        return $status === 'DRAFT' ? 'draft' : ($over ? 'over_allocation' : ($allocated <= 0 ? 'no_target'
            : ($done >= $allocated ? 'complete' : ($done > 0 ? 'in_progress' : 'waiting'))));
    }

    public function prices(array $receipts): array
    {
        $known = array_values(array_filter($receipts, fn ($r) => $r['value'] !== null && $r['quantity'] > 0));
        $qty = array_sum(array_column($known, 'quantity'));
        $average = $qty > 0 ? array_sum(array_column($known, 'value')) / $qty : null;
        $prices = array_map(fn ($r) => $r['value'] / $r['quantity'], $known);

        return ['average' => $average, 'min' => $prices ? min($prices) : null, 'max' => $prices ? max($prices) : null,
            'spread' => $average > 0 ? (max($prices) - min($prices)) / $average : null, 'points' => $prices];
    }

    public static function money(?float $value): string
    {
        if ($value === null) {
            return __('govtracking::general.monitor.unavailable');
        }
        if (abs($value) >= 10000000) {
            return '৳'.number_format($value / 10000000, 2).' '.__('govtracking::general.monitor.crore');
        }
        if (abs($value) >= 100000) {
            return '৳'.number_format($value / 100000, 2).' '.__('govtracking::general.monitor.lakh');
        }
        $parts = explode('.', number_format(abs($value), 2, '.', ''));
        $tail = substr($parts[0], -3);
        $head = substr($parts[0], 0, -3);
        $head = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head);

        return '৳'.($value < 0 ? '-' : '').($head ? $head.',' : '').$tail.($parts[1] === '00' ? '' : '.'.$parts[1]);
    }
}
