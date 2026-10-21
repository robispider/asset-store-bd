@extends('layouts/default')
@section('title', __('govtracking::report.title'))
@section('content')
@php
    $digits = fn ($value) => \GovStore\Tracking\Services\TrackingMonitorMetrics::digits($value);
    $n = fn ($value, $places = 4) => $value === null ? '—' : $digits($places ? rtrim(rtrim(number_format($value, $places), '0'), '.') : number_format($value));
    $money = fn ($row) => $n($row['known_value'], 2).($row['value_complete'] ? '' : '*');
    $percent = fn ($actual, $plan) => $plan !== null && $plan > 0 ? $n(100 * $actual / $plan, 1).'%' : '—';
    $usePercent = fn ($row) => $row['unknown'] == 0 ? $percent($row['used'], $row['received']) : '—';
@endphp
<div class="gs-tracking-report" data-programme-report data-page-label="{{ __('govtracking::report.page', ['page' => '{page}', 'total' => '{total}']) }}" data-continued="{{ __('govtracking::report.continued') }}" data-pagination-failed="{{ __('govtracking::report.pagination_failed') }}" data-print-failed="{{ __('govtracking::report.print_failed') }}" data-preparing="{{ __('govtracking::report.print_preparing') }}" lang="{{ app()->getLocale() }}">
    <div class="gs-tracking-report__toolbar hidden-print">
        @include('govtracking::monitor.tabs')
        <x-gs::button data-report-print>{{ __('govtracking::report.print') }}</x-gs::button>
        <p data-report-message role="status"></p>
    </div>
    <div data-report-source>
        <x-gs::document :title="__('govtracking::report.title')" :date="$digits($report['generated']->format('Y-m-d'))">
            <x-slot:office><strong>{{ $report['owner'] ?: __('govtracking::report.unknown') }}</strong></x-slot:office>
            <x-slot:meta>
                <span>{{ __('govtracking::report.reference') }}: {{ $report['reference'] }}</span>
                <span>{{ __('govtracking::report.as_of') }}: {{ $digits($report['generated']->format('Y-m-d H:i:s T')) }}</span>
            </x-slot:meta>
            @include('govtracking::initiatives.report.summary')
            @include('govtracking::initiatives.report.codes-items')
            @include('govtracking::initiatives.report.distribution')
            @include('govtracking::initiatives.report.quality')
        </x-gs::document>
    </div>
    <div data-report-pages tabindex="0" role="region" aria-label="{{ __('govtracking::report.title') }}"></div>
    <template data-report-running><div class="gs-tracking-report__running"><strong>{{ $initiative->title }}</strong><span>{{ $report['reference'] }}</span></div></template>
</div>
@endsection
