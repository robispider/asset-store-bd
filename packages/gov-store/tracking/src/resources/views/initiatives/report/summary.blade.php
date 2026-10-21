<div data-report-block data-report-section="summary" id="report-summary">
    <h1>{{ $initiative->title }}</h1>
    <h2>{{ $digits(1) }}. {{ __('govtracking::report.summary') }}</h2>
    <p class="gs-tracking-report__lead">{{ __('govtracking::report.narrative', ['quantity' => $n($report['totals']['received']), 'offices' => $n(count($report['offices'])), 'receipts' => $n($report['totals']['grns'])]) }}</p>
    @if ($report['excluded_quantity'] > 0)<x-gs::alert tone="warning">{{ __('govtracking::report.excluded', ['quantity' => $n($report['excluded_quantity'])]) }}</x-gs::alert>@endif
    <x-gs::key-value :items="[
        ['label' => __('govtracking::report.programme'), 'value' => $initiative->title],
        ['label' => __('govtracking::report.owner'), 'value' => $report['owner'] ?: '—'],
        ['label' => __('govtracking::report.funding'), 'value' => collect($report['codes'])->pluck('funding')->filter()->unique()->implode(', ') ?: $initiative->primary_funding],
        ['label' => __('govtracking::report.fy'), 'value' => collect($report['codes'])->pluck('fy')->filter()->unique()->implode(', ') ?: '—'],
    ]" />
    <p>{{ __('govtracking::report.period') }} · {{ __('govtracking::report.drafts', ['count' => $n($report['draft_count'])]) }}</p>
    <p class="gs-tracking-report__note">{{ __('govtracking::report.scope') }}</p>
    @if ($report['totals']['estimated_dates'] > 0)<p class="gs-tracking-report__note">{{ __('govtracking::report.date_note', ['count' => $n($report['totals']['estimated_dates'])]) }}</p>@endif
</div>
<div data-report-block>
    <x-gs::table>
        <thead><tr><th scope="col">{{ __('govtracking::report.metric') }}</th><th scope="col" class="gs-num">{{ __('govtracking::report.actual') }}</th></tr></thead>
        <tbody>
            @foreach (['received', 'grns', 'used', 'store', 'other', 'unknown_usage'] as $key)
                <tr><th scope="row">{{ __('govtracking::report.'.$key) }}</th><td class="gs-num">{{ $n($report['totals'][$key === 'unknown_usage' ? 'unknown' : $key]) }}</td></tr>
            @endforeach
            <tr><th scope="row">{{ __('govtracking::report.value') }}</th><td class="gs-num">{{ $money($report['totals']) }}</td></tr>
        </tbody>
    </x-gs::table>
    <p class="gs-tracking-report__note">{{ __('govtracking::report.value_note') }}</p>
</div>
@include('govtracking::initiatives.report.bars', ['chartTitle' => __('govtracking::report.monthly'), 'bars' => $report['months']])
<div data-report-block>
    <h3>{{ __('govtracking::report.contents') }}</h3>
    <ol class="gs-tracking-report__contents">
        @foreach (['summary', 'codes_items', 'distribution', 'quality'] as $key)
            <li><a href="#report-{{ $key }}" data-report-toc="{{ $key }}">{{ __('govtracking::report.'.$key) }}</a><span data-report-toc-page="{{ $key }}"></span></li>
        @endforeach
    </ol>
</div>
