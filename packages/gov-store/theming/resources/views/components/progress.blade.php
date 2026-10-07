@props(['value' => 0, 'max' => 100, 'tone' => 'primary', 'label' => null, 'showValue' => true, 'caption' => null, 'size' => 'md'])
@php($percent = $max > 0 ? max(0, min(100, round($value / $max * 100))) : 0)
<div {{ $attributes->class(['gs-progress', 'gs-progress--'.$size, 'gs-tone-'.$tone]) }}>
    @if ($label || $showValue)
        <div class="gs-progress__head">
            @if ($label)<span class="gs-progress__label">{{ $label }}</span>@endif
            @if ($showValue)<span class="gs-progress__value">{{ $percent }}%</span>@endif
        </div>
    @endif
    <div class="gs-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $value }}"
        aria-label="{{ $label ?? __('gs-theme::appearance.kit.progress') }}">
        <span class="gs-progress__bar" style="width: {{ $percent }}%"></span>
    </div>
    @if ($caption)<p class="gs-progress__caption">{{ $caption }}</p>@endif
</div>
