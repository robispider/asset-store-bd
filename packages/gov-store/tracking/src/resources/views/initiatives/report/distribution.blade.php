<div data-report-block data-report-section="distribution" data-report-break id="report-distribution">
    <h2>{{ $digits(3) }}. {{ __('govtracking::report.distribution') }}</h2>
    <p class="gs-tracking-report__note">{{ __('govtracking::report.usage_note') }}</p>
    <p class="gs-tracking-report__note">{{ __('govtracking::report.use_pct_note') }}</p>
    <h3>{{ __('govtracking::report.level_totals') }}</h3>
</div>
<div data-report-block data-report-table>
    <x-gs::table>
        <thead><tr><th scope="col">{{ __('govtracking::report.level') }}</th><th scope="col">{{ __('govtracking::report.offices') }}</th>@include('govtracking::initiatives.report.metric-head')</tr></thead>
        <tbody>
            @foreach ($report['levels'] as $level => $totals)
                <tr><th scope="row">{{ __('govtracking::report.levels.'.$level) }}</th><td class="gs-num">{{ $n($totals['offices']) }}</td>@include('govtracking::initiatives.report.metric-cells', ['row' => $totals])</tr>
            @endforeach
            <tr class="gs-tracking-report__total"><th scope="row">{{ __('govtracking::report.total') }}</th><td class="gs-num">{{ $n(count($report['offices'])) }}</td>@include('govtracking::initiatives.report.metric-cells', ['row' => $report['totals']])</tr>
        </tbody>
    </x-gs::table>
</div>
@include('govtracking::initiatives.report.bars', ['chartTitle' => __('govtracking::report.level_chart'), 'bars' => array_map(fn ($key, $v) => ['label' => __('govtracking::report.levels.'.$key)] + $v, array_keys($report['levels']), array_values($report['levels'])), 'stacked' => true])
<div data-report-block>
    <p class="gs-tracking-report__note">{{ __('govtracking::report.level_note', ['inferred' => $n($report['inferred_levels']), 'unknown' => $n($report['unknown_levels'])]) }}</p>
    <h3>{{ __('govtracking::report.matrix') }}</h3>
</div>
<div data-report-block data-report-table>
    <x-gs::table>
        <thead><tr><th scope="col">{{ __('govtracking::report.item') }}</th>@foreach ($report['levels'] as $key => $level)<th scope="col">{{ __('govtracking::report.levels.'.$key) }}</th>@endforeach<th scope="col">{{ __('govtracking::report.total') }}</th></tr></thead>
        <tbody>
            @foreach ($report['items'] as $item)<tr><th scope="row">{{ $item['name'] }}</th>@foreach ($item['matrix'] as $qty)<td class="gs-num">{{ $n($qty) }}</td>@endforeach<td class="gs-num">{{ $n($item['received']) }}</td></tr>@endforeach
            <tr class="gs-tracking-report__total"><th scope="row">{{ __('govtracking::report.total') }}</th>@foreach ($report['levels'] as $level)<td class="gs-num">{{ $n($level['received']) }}</td>@endforeach<td class="gs-num">{{ $n($report['totals']['received']) }}</td></tr>
        </tbody>
    </x-gs::table>
</div>
<div data-report-block><h3>{{ __('govtracking::report.office_list') }}</h3></div>
@include('govtracking::initiatives.report.bars', ['chartTitle' => __('govtracking::report.division_chart'), 'bars' => array_map(fn ($v) => ['label' => $v['name']] + $v, array_values(array_filter($report['hierarchy'], fn ($v) => $v['band'] && $v['depth'] === 1))), 'stacked' => true])
<div data-report-block data-report-table>
    <x-gs::table>
        <thead><tr><th scope="col">{{ __('govtracking::report.office_area') }}</th><th scope="col">{{ __('govtracking::report.level') }}</th>@include('govtracking::initiatives.report.metric-head')</tr></thead>
        <tbody>
            @foreach ($report['hierarchy'] as $row)
                <tr @class(['gs-tracking-report__band' => $row['band'], 'gs-tracking-report__depth-'.$row['depth']]) data-report-path="{{ $row['path'] }}" @if($row['band']) data-report-band @endif>
                    <th scope="row"><span class="gs-tracking-report__office-name">{{ $row['name'] }} @if($row['band']) <small>({{ $n($row['offices']) }})</small>@endif</span></th>
                    <td>{{ $row['band'] ? '—' : __('govtracking::report.levels.'.$row['level']) }}</td>
                    @include('govtracking::initiatives.report.metric-cells')
                </tr>
            @endforeach
            <tr class="gs-tracking-report__total"><th scope="row">{{ __('govtracking::report.total') }}</th><td>—</td>@include('govtracking::initiatives.report.metric-cells', ['row' => $report['totals']])</tr>
        </tbody>
    </x-gs::table>
</div>
