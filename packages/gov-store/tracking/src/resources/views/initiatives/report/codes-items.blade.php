<div data-report-block data-report-section="codes_items" data-report-break id="report-codes_items">
    <h2>{{ $digits(2) }}. {{ __('govtracking::report.codes_items') }}</h2>
    <h3>{{ $digits('2.1') }} {{ __('govtracking::report.code') }}</h3>
</div>
<div data-report-block data-report-table>
    <x-gs::table>
        <thead><tr>@foreach (['code', 'status', 'area', 'planned', 'received', 'percent', 'value', 'grns', 'last'] as $key)<th scope="col" @class(['gs-tracking-report__date' => $key === 'last'])>{{ __('govtracking::report.'.$key) }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($report['codes'] as $code)
                <tr><th scope="row">{{ $code['code'] }}</th><td>{{ __('govtracking::report.states.'.$code['status']) }}</td><td>{{ $code['area'] }}</td><td class="gs-num">{{ $n($code['planned']) }}</td><td class="gs-num">{{ $n($code['received']) }}</td><td class="gs-num">{{ $percent($code['received'], $code['planned']) }}</td><td class="gs-num">{{ $money($code) }}</td><td class="gs-num">{{ $n($code['grns']) }}</td><td class="gs-tracking-report__date">@if($code['last_estimated'])<abbr title="{{ __('govtracking::report.estimated_date') }}">≈</abbr> @endif{{ $digits(substr($code['last'] ?? '', 0, 10)) ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="9">{{ __('govtracking::report.empty') }}</td></tr>
            @endforelse
            <tr class="gs-tracking-report__total"><th scope="row" colspan="4">{{ __('govtracking::report.total') }}</th><td class="gs-num">{{ $n($report['totals']['received']) }}</td><td>—</td><td class="gs-num">{{ $money($report['totals']) }}</td><td class="gs-num">{{ $n($report['totals']['grns']) }}</td><td>—</td></tr>
        </tbody>
    </x-gs::table>
</div>
<div data-report-block><h3>{{ $digits('2.2') }} {{ __('govtracking::report.item') }}</h3></div>
<div data-report-block data-report-table>
    <x-gs::table>
        <thead><tr>@foreach (['item', 'planned', 'received', 'percent', 'price', 'value', 'economic'] as $key)<th scope="col">{{ __('govtracking::report.'.$key) }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($report['items'] as $item)
                <tr><th scope="row">{{ $item['name'] }}</th><td class="gs-num">{{ $n($item['planned']) }}</td><td class="gs-num">{{ $n($item['received']) }}</td><td class="gs-num">{{ $percent($item['received'], $item['planned']) }}</td><td class="gs-num">{{ $n($item['price_min'], 2) }}@if($item['price_min'] !== $item['price_max']) – {{ $n($item['price_max'], 2) }}@endif</td><td class="gs-num">{{ $money($item) }}</td><td>{{ $item['economic'] ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="7">{{ __('govtracking::report.empty') }}</td></tr>
            @endforelse
            <tr class="gs-tracking-report__total"><th scope="row" colspan="2">{{ __('govtracking::report.total') }}</th><td class="gs-num">{{ $n($report['totals']['received']) }}</td><td>—</td><td>—</td><td class="gs-num">{{ $money($report['totals']) }}</td><td>—</td></tr>
        </tbody>
    </x-gs::table>
</div>
@include('govtracking::initiatives.report.bars', ['chartTitle' => __('govtracking::report.item_chart'), 'bars' => array_map(fn ($i) => ['label' => $i['name'], 'value' => $i['received'], 'planned' => $i['planned']], $report['items'])])
@include('govtracking::initiatives.report.bars', ['chartTitle' => __('govtracking::report.value_chart'), 'bars' => array_map(fn ($i) => ['label' => $i['name'], 'value' => $i['known_value'], 'partial' => ! $i['value_complete']], $report['items'])])
