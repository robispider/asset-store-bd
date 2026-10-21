<div data-report-block data-report-section="quality" id="report-quality"><h2>{{ $digits(4) }}. {{ __('govtracking::report.quality') }}</h2></div>
<div data-report-block data-report-table>
    <x-gs::table>
        <thead><tr>@foreach (['indicator', 'result', 'status', 'effect'] as $key)<th scope="col">{{ __('govtracking::report.'.$key) }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($report['quality'] as $check)
                <tr><th scope="row">{{ __('govtracking::report.checks.'.$check['key']) }}</th><td class="gs-num">{{ $n($check['good']) }} / {{ $n($check['total']) }}</td><td>{{ __('govtracking::report.check_status.'.$check['status']) }}</td><td>{{ __('govtracking::report.effects.'.$check['key']) }}</td></tr>
            @endforeach
        </tbody>
    </x-gs::table>
</div>
<div data-report-block>
    @if ($report['excluded_quantity'] > 0)<p>{{ __('govtracking::report.excluded', ['quantity' => $n($report['excluded_quantity'])]) }}</p>@endif
    @if ($report['totals']['received'] == 0)<p>{{ __('govtracking::report.empty') }}</p>@endif
    <p class="gs-tracking-report__closing">{{ __('govtracking::report.closing') }}</p>
</div>
