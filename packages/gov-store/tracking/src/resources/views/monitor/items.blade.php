<div class="gs-tracking-item-grid">
@forelse($items as $item)
    @php
        $share=$item['planned']>0 ? 100*($item['done'] ?? min($item['received'],$item['planned']))/$item['planned'] : null;
    @endphp
    <article class="gs-tracking-item"><h3>{{ $item['name'] }}</h3><small>{{ $item['type'] ?? '' }}</small>
        <p><strong>{{ number_format($item['received']) }}</strong> / {{ $item['planned']>0 ? number_format($item['planned']) : __('govtracking::general.monitor.no_target') }}</p>
        <x-gs::pace-bar :value="$share" :label="__('govtracking::general.monitor.delivered')" />
        <p>{{ __('govtracking::general.monitor.economic_code') }}: {{ collect($item['economic_codes'] ?? [])->filter()->unique()->implode(', ') ?: __('govtracking::general.monitor.missing_economic') }}</p>
        <p>{{ \GovStore\Tracking\Services\TrackingMonitorMetrics::money($item['value'] ?? null) }} · {{ $item['supplier_count'] ?? 0 }} {{ __('govtracking::general.monitor.suppliers') }}</p>
        @if(($item['draft_planned'] ?? 0)>0)<p class="gs-tracking-muted">{{ __('govtracking::general.monitor.draft_planned',['quantity'=>number_format($item['draft_planned'])]) }}</p>@endif
    </article>
@empty<p>{{ __('govtracking::general.monitor.no_items') }}</p>@endforelse
</div>
