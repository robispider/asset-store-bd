@props(['steps' => []])
<ol {{ $attributes->class('gs-monitor-lifecycle') }}>
    @foreach($steps as $step)<li><i class="fas {{ ($step['date'] ?? null) ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i><strong>{{ $step['label'] }}</strong><span>{{ $step['date'] ?? __('gs-theme::kit.unavailable') }}</span>@if($step['detail'] ?? null)<small>{{ $step['detail'] }}</small>@endif</li>@endforeach
</ol>
