@props(['status', 'label' => null, 'size' => 'sm'])
@php($entry = app('gs.theme')->status((string) $status))
{{-- Status is never conveyed by colour alone: icon + text in every style. --}}
<span {{ $attributes->class(['gs-badge', 'gs-badge--lg' => $size === 'lg']) }} data-tone="{{ $entry['tone'] }}" data-status="{{ $entry['key'] }}">
    <i class="fas {{ $entry['icon'] }} gs-badge__icon" aria-hidden="true"></i>
    <span class="gs-badge__label">{{ $label ?? $entry['label'] }}</span>
</span>
