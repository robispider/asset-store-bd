<?php

namespace GovStore\StoreOperations\Services;

use Carbon\CarbonImmutable;
use GovStore\StoreOperations\DTOs\LedgerPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Everything a stock card (or asset card) shows for one item in one office and period.
 * Brought-forward, totals and closing always cover the whole period, whatever rows are filtered,
 * so the printed arithmetic adds up.
 */
class StockCardBuilder
{
    public const KINDS = ['all', 'in', 'issue', 'adjustment', 'transfer'];
    public const PER_PAGE = 50;

    public function __construct(private StockLedgerReader $ledger) {}

    public function build(string $type, int $id, int $locationId, ?int $reorder, LedgerPeriod $period, array $filters = [], array $tagsByDocument = []): array
    {
        $movements = $this->ledger->movements($type, $id, $locationId);
        $chain = $this->ledger->verifyChain($movements);
        $context = $this->ledger->documentContext($movements);
        $entries = $movements->map(fn ($m) => $this->entry($m, $context->get($m->document_id), $reorder, $tagsByDocument));

        $before = $entries->filter(fn ($e) => $e['at']->lessThan($period->start));
        $inPeriod = $entries->filter(fn ($e) => $e['at']->betweenIncluded($period->start, $period->end))->values();
        $bf = $before->isEmpty() ? 0 : $before->last()['balance_expected'];
        $in = (int) $inPeriod->where('direction', 'IN')->sum('qty');
        $out = (int) $inPeriod->where('direction', 'OUT')->sum('qty');

        $kind = in_array($filters['kind'] ?? 'all', self::KINDS, true) ? ($filters['kind'] ?? 'all') : 'all';
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $shown = $inPeriod->filter(fn ($e) => $this->matchesKind($e, $kind)
            && ($q === '' || str_contains(mb_strtolower(implode(' ', [$e['document_number'], $e['party'], $e['reference'], implode(' ', $e['tags'])])), $q)))->values();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $paginator = new LengthAwarePaginator($shown->forPage($page, self::PER_PAGE)->values(), $shown->count(), self::PER_PAGE, $page,
            ['path' => $filters['path'] ?? request()->url(), 'query' => array_filter(array_merge($period->query(), ['kind' => $kind === 'all' ? null : $kind, 'q' => $q ?: null]))]);

        $selected = $entries->firstWhere('id', $filters['entry'] ?? null) ?? $inPeriod->last();

        return [
            'period' => $period,
            'chain' => $chain,
            'reorder' => $reorder,
            'bf' => $bf,
            'in' => $in,
            'out' => $out,
            'closing' => $bf + $in - $out,
            'closing_all' => $chain['balance'],
            'n_in' => $inPeriod->where('direction', 'IN')->count(),
            'n_out' => $inPeriod->where('direction', 'OUT')->count(),
            'period_count' => $inPeriod->count(),
            'period_entries' => $inPeriod,
            'entries' => $paginator,
            'kind' => $kind,
            'q' => $q,
            'filtered' => $kind !== 'all' || $q !== '',
            'selected' => $selected,
            'chart' => $this->chart($period, $bf, $inPeriod, $reorder, $selected),
            'outlook' => $this->outlook($entries, $chain['balance'], $reorder),
            'checked_at' => CarbonImmutable::now(),
            'fingerprint' => $this->fingerprint($entries),
        ];
    }

    private function entry($movement, ?array $document, ?int $reorder, array $tagsByDocument): array
    {
        // The combined running balance: equal to the stored balance whenever the item has one chain.
        $balance = (int) $movement->after;

        return [
            'id' => $movement->id,
            'at' => CarbonImmutable::parse($movement->created_at),
            'direction' => $movement->movement_type,
            'qty' => (int) $movement->quantity,
            'before' => (int) $movement->before,
            'balance' => $balance,
            'balance_expected' => (int) $movement->after,
            'low' => $reorder !== null && $balance <= $reorder,
            'document_id' => $movement->document_id,
            'document_number' => $document['number'] ?? null,
            'document_type' => $document['type'] ?? null,
            'kind' => $document['kind'] ?? 'other',
            'party_key' => $document['party_key'] ?? null,
            'party' => $document['party'] ?? null,
            'reference' => $document['reference'] ?? null,
            'corrects' => $document['corrects'] ?? null,
            'posted_at' => $document['posted_at'] ?? null,
            'drafter' => $document['drafter'] ?? null,
            'poster' => $document['poster'] ?? null,
            'same_person' => $document['same_person'] ?? false,
            'attachments' => $document['attachments'] ?? [],
            'history' => $document['history'] ?? [],
            'tags' => $tagsByDocument[$movement->document_id] ?? [],
            'notes' => $movement->notes,
        ];
    }

    private function matchesKind(array $entry, string $kind): bool
    {
        return match ($kind) {
            'in' => $entry['direction'] === 'IN',
            'issue' => $entry['kind'] === 'issue',
            'adjustment' => $entry['kind'] === 'adjustment',
            'transfer' => in_array($entry['kind'], ['transfer', 'transfer_in'], true),
            default => true,
        };
    }

    /** Step chart of the balance through the period, in an 800 × 200 drawing space. */
    private function chart(LedgerPeriod $period, int $bf, Collection $entries, ?int $reorder, ?array $selected): array
    {
        $span = max(1, $period->end->getTimestamp() - $period->start->getTimestamp());
        $peak = max(1, $bf, $reorder ?? 0, (int) $entries->max('balance'));
        $top = $this->niceCeiling($peak * 1.1);
        $x = fn (CarbonImmutable $at) => round(max(0, min(1, ($at->getTimestamp() - $period->start->getTimestamp()) / $span)) * 800, 1);
        $y = fn (int $value) => round(200 - (max(0, $value) / $top) * 200, 1);

        $line = 'M0,'.$y($bf);
        foreach ($entries as $entry) {
            $line .= ' H'.$x($entry['at']).' V'.$y($entry['balance']);
        }
        $line .= ' H800';

        $ticks = collect();
        $cursor = $period->start->startOfMonth();
        if ($cursor->lessThan($period->start)) {
            $cursor = $cursor->addMonth();
        }
        $months = $period->start->diffInDays($period->end) > 45;
        while ($months && $cursor->lessThanOrEqualTo($period->end)) {
            $ticks->push(['x' => $x($cursor), 'label' => $cursor->translatedFormat('M')]);
            $cursor = $cursor->addMonth();
        }
        if (! $months) {
            foreach ([0, 0.25, 0.5, 0.75] as $fraction) {
                $at = $period->start->addSeconds((int) ($span * $fraction));
                $ticks->push(['x' => $x($at), 'label' => $at->translatedFormat('d M')]);
            }
        }

        return [
            'line' => $line,
            'area' => $line.' V200 H0 Z',
            'reorder_y' => $reorder !== null ? $y($reorder) : null,
            'selected_x' => $selected && $selected['at']->betweenIncluded($period->start, $period->end) ? $x($selected['at']) : null,
            'y_ticks' => collect(range(0, 4))->map(fn ($i) => ['y' => $y((int) round($top * $i / 4)), 'label' => (int) round($top * $i / 4)])->all(),
            'x_ticks' => $ticks->all(),
            'low' => min($bf, (int) ($entries->min('balance') ?? $bf)),
            'high' => max($bf, (int) ($entries->max('balance') ?? $bf)),
        ];
    }

    private function niceCeiling(float $value): int
    {
        $magnitude = 10 ** max(0, floor(log10(max(1, $value))) - 1);

        return (int) max(4, ceil($value / ($magnitude * 4)) * $magnitude * 4);
    }

    /** Demand is what sections draw: issues only, over the last 90 days. */
    private function outlook(Collection $entries, int $onHand, ?int $reorder): array
    {
        $since = CarbonImmutable::now()->subDays(90);
        $issued = (int) $entries->filter(fn ($e) => $e['kind'] === 'issue' && $e['direction'] === 'OUT' && $e['at']->greaterThanOrEqualTo($since))->sum('qty');
        $perDay = $issued / 90;

        return [
            'issued_90' => $issued,
            'per_week' => round($perDay * 7, 1),
            'weeks_left' => $perDay > 0 ? (int) floor($onHand / $perDay / 7) : null,
            'state' => match (true) {
                $onHand <= 0 => 'out',
                $reorder !== null && $onHand <= $reorder => 'reorder',
                default => 'ok',
            },
        ];
    }

    /** Short code printed on the card: it changes if any entry, quantity or balance changes. */
    private function fingerprint(Collection $entries): string
    {
        $hash = strtoupper(substr(hash('sha256', $entries->map(fn ($e) => $e['id'].'|'.$e['direction'].'|'.$e['qty'].'|'.$e['balance'])->implode(';')), 0, 8));

        return 'SC-'.substr($hash, 0, 4).'-'.substr($hash, 4, 4);
    }
}
