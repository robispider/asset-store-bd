<figure class="gs-tracking-chart">
    <figcaption>{{ $chart['title'] }}</figcaption>
    <svg viewBox="0 0 680 210" role="img" aria-label="{{ $chart['title'] }}" data-monitor-chart>
        @foreach([30,65,100,135,170] as $y)<line x1="36" y1="{{ $y }}" x2="636" y2="{{ $y }}" class="gs-tracking-chart__grid" />@endforeach
        <text x="4" y="30">{{ number_format($chart['max'],0) }}</text><text x="12" y="174">0</text>
        <polyline points="{{ $chart['expected_points'] }}" class="gs-tracking-chart__expected" />
        @if(($chart['ceiling'] ?? null)!==null)<line x1="36" y1="{{ 170-140*$chart['ceiling']/$chart['max'] }}" x2="636" y2="{{ 170-140*$chart['ceiling']/$chart['max'] }}" class="gs-tracking-chart__ceiling" /><text x="40" y="{{ max(15,164-140*$chart['ceiling']/$chart['max']) }}">{{ __('govtracking::general.monitor.allocated') }} {{ $chart['ceiling'] }}</text>@endif
        @if(($chart['kind'] ?? 'line')==='line' && $chart['area_path'])<path d="{{ $chart['area_path'] }}" class="gs-tracking-chart__area" />@endif
        @if(($chart['kind'] ?? 'line')==='line')<path d="{{ $chart['path'] }}" class="gs-tracking-chart__actual" />@endif
        <line x1="{{ $chart['today_x'] }}" y1="20" x2="{{ $chart['today_x'] }}" y2="175" class="gs-tracking-chart__today" />
        <text x="{{ min(575,$chart['today_x']+4) }}" y="15">{{ __('govtracking::general.monitor.today') }}</text>
        <line x1="36" y1="20" x2="36" y2="175" class="gs-tracking-chart__crosshair" data-chart-crosshair visibility="hidden" />
        @foreach($chart['rows'] as $point)@if($point['y']!==null)
            @if(($chart['kind'] ?? 'line')==='bar')<rect x="{{ $point['x']-9 }}" y="{{ $point['y'] }}" width="18" height="{{ 170-$point['y'] }}" class="gs-tracking-chart__bar" tabindex="0" data-chart-point="{{ $point['x'] }}"><title>{{ $point['label'] }} · {{ number_format($point['actual'],2) }}</title></rect>
            @else<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" tabindex="0" data-chart-point="{{ $point['x'] }}"><title>{{ $point['date'] }} · {{ $point['label'] }} · {{ number_format($point['actual'],2) }}</title></circle>@endif
        @endif @endforeach
        <text x="36" y="200">{{ $chart['start'] }}</text><text x="555" y="200">{{ $chart['end'] }}</text>
    </svg>
    <output class="gs-tracking-chart__tooltip" data-chart-tooltip></output>
    <div class="gs-tracking-chart__legend"><span>{{ $chart['actual_label'] ?? __('govtracking::general.monitor.delivered') }}</span>@if($chart['expected_points'])<span>{{ __('govtracking::general.monitor.expected') }} ┄</span>@endif</div>
    <details><summary>{{ __('govtracking::general.monitor.table_view') }}</summary><x-gs::table><thead><tr><th>{{ __('govtracking::general.monitor.date') }}</th><th>{{ __('govtracking::general.monitor.receipt') }}</th><th>{{ __('govtracking::general.monitor.received') }}</th><th>{{ __('govtracking::general.monitor.expected') }}</th></tr></thead><tbody>
        @foreach($chart['rows'] as $point)<tr><td>{{ $point['date'] }}</td><td>{{ $point['label'] }}</td><td>{{ $point['actual']===null ? '—' : number_format($point['actual'],2) }}</td><td>{{ $point['expected']===null ? '—' : number_format($point['expected'],2) }}</td></tr>@endforeach
    </tbody></x-gs::table></details>
</figure>
