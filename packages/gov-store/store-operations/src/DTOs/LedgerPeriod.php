<?php

namespace GovStore\StoreOperations\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The window a stock card covers. Government financial years run July to June, so "fy" is the default.
 */
final class LedgerPeriod
{
    public const KEYS = ['fy', '90d', 'month', 'custom'];

    private function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function fromRequest(Request $request, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();
        $key = in_array($request->query('period'), self::KEYS, true) ? $request->query('period') : 'fy';

        if ($key === 'custom') {
            $from = self::date($request->query('from'));
            $to = self::date($request->query('to'));
            if ($from && $to && $from->lessThanOrEqualTo($to)) {
                return new self('custom', $from->startOfDay(), $to->endOfDay());
            }
            $key = 'fy';
        }

        return match ($key) {
            '90d' => new self('90d', $now->subDays(90)->startOfDay(), $now),
            'month' => new self('month', $now->startOfMonth(), $now),
            default => new self('fy', self::financialYearStart($now), $now),
        };
    }

    public static function financialYearStart(CarbonImmutable $date): CarbonImmutable
    {
        $year = $date->month >= 7 ? $date->year : $date->year - 1;

        return CarbonImmutable::create($year, 7, 1)->startOfDay();
    }

    /** "2026–27" for the financial year the period starts in. */
    public function financialYearLabel(): string
    {
        $start = self::financialYearStart($this->start);

        return $start->year.'–'.substr((string) ($start->year + 1), -2);
    }

    /** Query string that reproduces this period, for links that keep the user's choice. */
    public function query(): array
    {
        return $this->key === 'custom'
            ? ['period' => 'custom', 'from' => $this->start->toDateString(), 'to' => $this->end->toDateString()]
            : ['period' => $this->key];
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
