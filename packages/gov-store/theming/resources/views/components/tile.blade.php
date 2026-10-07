{{-- Action / summary tile: large icon, title and a short meta line. `filled` paints it in the tone colour. --}}
@props(['title', 'meta' => null, 'icon' => null, 'href' => null, 'tone' => 'primary', 'filled' => false])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} {{ $attributes->class(['gs-tile', 'gs-tone-'.$tone, 'gs-tile--filled' => $filled, 'gs-tile--link' => $href]) }} @if ($href) href="{{ $href }}" @endif>
    @if ($icon)<i class="fas {{ $icon }} gs-tile__icon" aria-hidden="true"></i>@endif
    <span class="gs-tile__text">
        <span class="gs-tile__title">{{ $title }}</span>
        @if ($meta)<span class="gs-tile__meta">{{ $meta }}</span>@endif
    </span>
</{{ $tag }}>
