@php
    $isStacked = $stacked ?? false;
    $max = max([1, ...array_map(fn ($b) => max($b['value'] ?? $b['received'] ?? 0, $b['planned'] ?? 0), $bars)]);
@endphp
<div data-report-block><h3>{{ $chartTitle }}</h3>
    @if ($isStacked)<p class="gs-tracking-report__legend">@foreach (['used', 'store', 'other', 'unknown_usage'] as $key)<span class="gs-tracking-report__key gs-tracking-report__key--{{ $key }}">{{ __('govtracking::report.'.$key) }}</span>@endforeach</p>@endif
    @if (! $isStacked && collect($bars)->contains(fn ($b) => isset($b['planned'])))<p class="gs-tracking-report__legend"><span class="gs-tracking-report__key gs-tracking-report__key--used">{{ __('govtracking::report.received') }}</span><span class="gs-tracking-report__key gs-tracking-report__key--plan">{{ __('govtracking::report.planned') }}</span></p>@endif
</div>
@foreach ($bars as $bar)
    <div class="gs-tracking-report__chart-row" data-report-block role="figure" aria-label="{{ $bar['label'] }}">
        <span>{{ $bar['label'] }}</span>
        @php
            $description = $isStacked ? implode('; ', array_map(fn ($key) => __('govtracking::report.'.($key === 'unknown' ? 'unknown_usage' : $key)).': '.$n($bar[$key]), ['used', 'store', 'other', 'unknown'])) : $n($bar['value']);
        @endphp
        <svg viewBox="0 0 400 20" role="img" aria-label="{{ $bar['label'] }}: {{ $description }}">
            @if ($isStacked)
                @php($offset = 0)
                @foreach (['used', 'store', 'other', 'unknown'] as $state)
                    <rect class="gs-tracking-report__fill--{{ $state }}" x="{{ $offset }}" y="2" width="{{ 400 * $bar[$state] / $max }}" height="16" />
                    @php($offset += 400 * $bar[$state] / $max)
                @endforeach
            @else
                @if (isset($bar['planned']))<rect class="gs-tracking-report__fill--plan" x="0" y="2" width="{{ 400 * $bar['planned'] / $max }}" height="16" />@endif
                <rect class="gs-tracking-report__fill--used" x="0" y="6" width="{{ 400 * $bar['value'] / $max }}" height="8" />
            @endif
        </svg>
        <span class="gs-num">{{ $n($bar['value'] ?? $bar['received']) }}{{ !empty($bar['partial']) ? '*' : '' }}@if(isset($bar['planned'])) / {{ $n($bar['planned']) }}@endif</span>
    </div>
@endforeach
