@props(['value' => null, 'expected' => null, 'label' => null])
@php
    $actual = max(0, min(100, (float) $value));
    $pace = $expected === null ? null : max(0, min(100, (float) $expected));
@endphp
<div {{ $attributes->class('gs-pace') }}>
    <div class="gs-pace__numbers"><strong>{{ $value === null ? __('gs-theme::kit.unavailable') : number_format($actual, 1).'%' }}</strong><span>{{ $label }}</span></div>
    <div class="gs-pace__track" @if($value !== null) role="progressbar" aria-label="{{ $label ?? __('gs-theme::kit.delivered') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $actual }}" @endif>
        <span class="gs-pace__fill" style="width: {{ $actual }}%"></span>
        @if($pace !== null)<span class="gs-pace__expected" style="left: {{ $pace }}%" title="{{ __('gs-theme::kit.expected') }} {{ number_format($pace,1) }}%"></span>@endif
    </div>
    @if($pace !== null)<small>{{ __('gs-theme::kit.expected') }} {{ number_format($pace,1) }}% · {{ __('gs-theme::kit.points', ['points' => number_format($actual-$pace,1)]) }}</small>@endif
</div>
