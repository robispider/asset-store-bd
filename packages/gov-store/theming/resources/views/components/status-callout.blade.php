@props(['status', 'verdict', 'next' => null, 'forecast' => null])
<div {{ $attributes->class('gs-status-callout') }} data-status="{{ $status }}">
    <x-gs::status-badge :status="$status" />
    <div><strong>{{ $verdict }}</strong>@if($next)<p><b>{{ __('gs-theme::kit.next') }}:</b> {{ $next }}</p>@endif
    @if($forecast)<p><b>{{ __('gs-theme::kit.forecast') }}:</b> {{ $forecast }}</p>@endif</div>
</div>
