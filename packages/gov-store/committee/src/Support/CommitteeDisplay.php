<?php

namespace GovStore\Committee\Support;

use Carbon\CarbonImmutable;

final class CommitteeDisplay
{
    public static function text(?string $bn, ?string $en): string
    {
        return app()->getLocale() === 'bn-BD' ? ($bn ?: $en ?: '') : ($en ?: $bn ?: '');
    }

    public static function digits(string|int $value): string
    {
        return app()->getLocale() === 'bn-BD' ? strtr((string)$value, array_combine(range(0,9), ['০','১','২','৩','৪','৫','৬','৭','৮','৯'])) : (string)$value;
    }

    public static function date(?string $value, bool $time = false): string
    {
        if (! $value) { return __('committee::committee.options.UNTIL_FURTHER_ORDER'); }
        $date = CarbonImmutable::parse($value, $time ? 'UTC' : 'Asia/Dhaka')->setTimezone('Asia/Dhaka');
        $months = __('committee::committee.ux.months');
        return self::digits($date->day).' '.$months[$date->month-1].' '.self::digits($date->year).($time ? ' · '.self::digits($date->format('H:i')) : '');
    }
}
