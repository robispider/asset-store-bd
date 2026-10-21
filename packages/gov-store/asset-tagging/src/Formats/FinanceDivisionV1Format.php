<?php

namespace GovStore\AssetTagging\Formats;

use Carbon\CarbonImmutable;
use GovStore\AssetTagging\Contracts\TagFormat;
use InvalidArgumentException;

/**
 * Govt. Tag, fd18-v1: GG SS II MMYY LL DD NNNN (18 digits).
 * GGSSII is the Finance Code, MMYY the acquisition month, LL the registration location,
 * DD the division and NNNN the sequence within the first 14 digits.
 */
class FinanceDivisionV1Format implements TagFormat
{
    public const VERSION = 'fd18-v1';

    public function version(): string
    {
        return self::VERSION;
    }

    public function prefix(array $facts): string
    {
        $code = (string) ($facts['gov_code'] ?? '');
        $location = (string) ($facts['location_code'] ?? '');
        $division = (string) ($facts['division_code'] ?? '');
        if (! preg_match('/^(?!00)\d{2}(?!00)\d{2}(?!00)\d{2}$/', $code) || ! preg_match('/^\d{2}$/', $location) || ! preg_match('/^\d{2}$/', $division)) {
            throw new InvalidArgumentException('A Govt. Tag needs a Finance Code and two-digit location and division codes.');
        }

        return $code.$this->month($facts).$location.$division;
    }

    public function encode(array $facts, int $sequence): string
    {
        if ($sequence < 1 || $sequence > 9999) {
            throw new InvalidArgumentException('A Govt. Tag sequence runs from 1 to 9999.');
        }

        return $this->prefix($facts).str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function decode(string $tag): ?array
    {
        if (! preg_match('/^(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{4})$/', $tag, $m)) {
            return null;
        }
        [, $group, $subgroup, $item, $month, $year, $location, $division, $sequence] = $m;
        if (in_array('00', [$group, $subgroup, $item], true) || (int) $month < 1 || (int) $month > 12 || (int) $sequence === 0) {
            return null;
        }

        return ['format' => self::VERSION, 'gov_code' => $group.$subgroup.$item, 'group' => $group, 'subgroup' => $subgroup,
            'item' => $item, 'month' => $month, 'year' => $year, 'location_code' => $location, 'division_code' => $division,
            'sequence' => (int) $sequence, 'prefix' => substr($tag, 0, 14)];
    }

    public function segments(array $facts, ?int $sequence): array
    {
        $code = (string) ($facts['gov_code'] ?? '······');
        $month = isset($facts['acquired_on']) && $facts['acquired_on'] ? $this->month($facts) : '····';
        $label = fn (string $key) => __('asset-tagging::tagging.segment.'.$key);

        return [
            ['value' => substr($code, 0, 2), 'label' => $label('group'), 'tone' => 'primary'],
            ['value' => substr($code, 2, 2), 'label' => $label('subgroup'), 'tone' => 'primary'],
            ['value' => substr($code, 4, 2), 'label' => $label('item'), 'tone' => 'primary'],
            ['value' => $month, 'label' => $label('month'), 'tone' => 'info'],
            ['value' => (string) ($facts['location_code'] ?? '··'), 'label' => $label('location'), 'tone' => 'success'],
            ['value' => (string) ($facts['division_code'] ?? '··'), 'label' => $label('division'), 'tone' => 'success'],
            $sequence === null
                ? ['value' => '····', 'label' => $label('sequence'), 'tone' => 'muted', 'pending' => true]
                : ['value' => str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'label' => $label('sequence'), 'tone' => 'warning'],
        ];
    }

    private function month(array $facts): string
    {
        $date = $facts['acquired_on'] ?? null;
        if (! $date) {
            throw new InvalidArgumentException('A Govt. Tag needs an acquisition date.');
        }

        return CarbonImmutable::parse($date)->format('my');
    }
}
