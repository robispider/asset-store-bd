@aware(['id', 'active' => null, 'tabs' => []])
@props(['for'])
@php($current = $active ?? ($tabs[0]['key'] ?? null))
<div {{ $attributes->class('gs-tabset__panel') }} role="tabpanel" id="{{ $id }}-panel-{{ $for }}" aria-labelledby="{{ $id }}-tab-{{ $for }}"
    tabindex="0" @if ($for !== $current) hidden @endif>
    {{ $slot }}
</div>
