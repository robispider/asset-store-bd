{{--
    General-purpose button. tone: primary · secondary · success · danger · warning · info · light · dark.
    `outline` and `pill` change the shape; `loading` shows a spinner and disables the control.
    With `href` it renders a link (never disabled — omit href instead).
--}}
@props(['tone' => 'primary', 'outline' => false, 'pill' => false, 'size' => 'md', 'icon' => null, 'href' => null, 'type' => 'button', 'loading' => false, 'disabled' => false])
@php
    $classes = ['gs-btn', 'gs-btn--'.$tone, 'gs-btn--outline' => $outline, 'gs-btn--pill' => $pill, 'gs-btn--'.$size => $size !== 'md'];
@endphp
@if ($href)
    <a {{ $attributes->class($classes) }} href="{{ $href }}">
        @if ($icon)<i class="fas {{ $icon }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->class($classes)->merge(['type' => $type]) }} @disabled($disabled || $loading) @if ($loading) aria-busy="true" @endif>
        @if ($loading)
            <x-gs::spinner size="sm" tone="current" :label="false" />
        @elseif ($icon)
            <i class="fas {{ $icon }}" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </button>
@endif
