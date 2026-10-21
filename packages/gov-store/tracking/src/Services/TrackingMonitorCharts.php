<?php

namespace GovStore\Tracking\Services;

use Carbon\CarbonImmutable;

final class TrackingMonitorCharts
{
    public function __construct(private TrackingMonitorMetrics $metrics) {}

    public function programme(array $monitor): array
    {
        $charts = [];
        foreach (collect($monitor['codes'])->where('status', 'ACTIVE')->groupBy('fy') as $fy => $codes) {
            $end = $this->metrics->fiscalEnd($fy);
            if (! $end) {
                continue;
            }
            $start = $end->subYear()->addDay();
            $rows = [];
            for ($i = 0; $i < 12; $i++) {
                $date = $start->addMonths($i)->endOfMonth()->startOfDay();
                $actual = [];
                $expected = [];
                foreach ($codes as $code) {
                    foreach ($code['targets'] as $target) {
                        $quantity = collect($code['receipts'])->where('category_id', $target['category_id'])
                            ->filter(fn ($r) => $r['date'] && CarbonImmutable::parse($r['date'])->startOfDay()->lte(min($date, $monitor['today'])));
                        $cells = $target['cells'];
                        if ($cells !== null) {
                            foreach ($cells as $id => &$cell) {
                                $cell['received'] = $quantity->where('location_id', $id)->sum('quantity');
                            }
                        }
                        unset($cell);
                        $share = $this->metrics->line($target['planned'], $quantity->sum('quantity'), $cells)['fulfilment'];
                        if ($share !== null) {
                            $actual[] = $share;
                            $expected[] = $this->metrics->expected($code['activated'], $fy, $date);
                        }
                    }
                }
                $rows[] = ['label' => $date->translatedFormat('M Y'), 'date' => $date->toDateString(),
                    'actual' => $date->startOfMonth()->gt($monitor['today']) || $monitor['unknown_count'] > 0 ? null : (($this->metrics->mean($actual) ?? 0) * 100),
                    'expected' => in_array(null, $expected, true) ? null : ($this->metrics->mean($expected) ?? 0) * 100];
            }
            $charts[] = $this->chart($rows, 100, $start, $end, $monitor['today'], $fy);
        }

        return $charts;
    }

    public function target(array $code, array $target, CarbonImmutable $today): array
    {
        $end = $code['fy_end'] ?? $today->addDay();
        $start = $code['activated'] ? CarbonImmutable::parse($code['activated'])->startOfDay() : $end->subYear()->addDay();
        if ($start->gte($end)) {
            $start = $end->subDay();
        }
        $receipts = collect($code['receipts'])->where('category_id', $target['category_id'])->filter(fn ($r) => $r['date'])->sortBy('date');
        $rows = [['label' => $start->toDateString(), 'date' => $start->toDateString(), 'actual' => 0, 'expected' => 0]];
        $total = 0;
        foreach ($receipts as $receipt) {
            $date = CarbonImmutable::parse($receipt['date'])->startOfDay();
            $total += $receipt['quantity'];
            $rows[] = ['label' => $receipt['grn'].' · '.$receipt['office'].' · '.TrackingMonitorMetrics::money($receipt['value']),
                'date' => $date->toDateString(), 'actual' => $total, 'expected' => $target['planned'] * ($this->metrics->expected($code['activated'], $code['fy'], $date) ?? 0)];
        }
        $rows[] = ['label' => $today->toDateString(), 'date' => $today->toDateString(), 'actual' => $total,
            'expected' => $target['planned'] * ($this->metrics->expected($code['activated'], $code['fy'], $today) ?? 0)];
        $rows[] = ['label' => $end->toDateString(), 'date' => $end->toDateString(), 'actual' => null, 'expected' => $target['planned']];
        if ($this->metrics->expected($code['activated'], $code['fy'], $today) === null) {
            foreach ($rows as &$row) {
                $row['expected'] = null;
            }
            unset($row);
        }
        if (collect($code['receipts'])->where('category_id', $target['category_id'])->contains(fn ($r) => ! $r['date'])) {
            // An undated receipt cannot be placed at zero or at a fabricated position on a time axis.
            foreach ($rows as &$row) {
                $row['actual'] = null;
            }
            unset($row);
        }

        return $this->chart($rows, max(1, $target['planned'], $total), $start, $end, $today, $target['name'], true)
            + ['actual_label' => __('govtracking::general.monitor.received'), 'ceiling' => $target['planned'] > 0 ? $target['planned'] : null];
    }

    public function monthlyValue(array $receipts, CarbonImmutable $today): array
    {
        $start = $today->month >= 7 ? $today->startOfYear()->addMonths(6) : $today->subYear()->startOfYear()->addMonths(6);
        $rows = [];
        for ($i = 0; $i < 12; $i++) {
            $date = $start->addMonths($i);
            $group = collect($receipts)->filter(fn ($r) => $r['date'] && CarbonImmutable::parse($r['date'])->format('Y-m') === $date->format('Y-m'));
            $rows[] = ['label' => $date->translatedFormat('M Y').' · '.__('govtracking::general.monitor.receipt_count', ['count' => $group->pluck('grn')->filter()->unique()->count()]),
                'date' => $date->toDateString(), 'actual' => $group->sum('value'), 'expected' => null];
        }
        if (collect($receipts)->contains(fn ($r) => $r['value'] === null || ! $r['date'])) {
            foreach ($rows as &$row) {
                $row['actual'] = null;
            }
            unset($row);
        }

        return $this->chart($rows, max(1, ...array_column($rows, 'actual')), $start, $start->addYear()->subDay(), $today, __('govtracking::general.monitor.receipts_by_month'))
            + ['kind' => 'bar', 'actual_label' => __('govtracking::general.monitor.value')];
    }

    private function chart(array $rows, float $max, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today, string $title, bool $step = false): array
    {
        $days = max(1, $start->diffInDays($end, false));
        $actual = $expected = [];
        foreach ($rows as &$row) {
            $row['x'] = round(36 + 600 * max(0, min(1, $start->diffInDays(CarbonImmutable::parse($row['date']), false) / $days)), 2);
            $row['y'] = $row['actual'] === null ? null : round(170 - 140 * $row['actual'] / $max, 2);
            if ($row['y'] !== null) {
                $actual[] = [$row['x'], $row['y']];
            }
            if ($row['expected'] !== null) {
                $expected[] = [$row['x'], round(170 - 140 * $row['expected'] / $max, 2)];
            }
        }
        unset($row);
        $path = '';
        foreach ($actual as $i => $point) {
            $path .= ($i === 0 ? 'M' : ($step ? 'H'.$point[0].' V' : 'L')).($i > 0 && $step ? $point[1] : implode(' ', $point)).' ';
        }

        return compact('rows', 'max', 'title', 'path') + ['expected_points' => implode(' ', array_map(fn ($p) => implode(',', $p), $expected)),
            'area_path' => $actual ? $path.'L'.end($actual)[0].' 170 L'.$actual[0][0].' 170 Z' : '',
            'today_x' => 36 + 600 * max(0, min(1, $start->diffInDays($today, false) / $days)), 'start' => $start->toDateString(), 'end' => $end->toDateString()];
    }
}
