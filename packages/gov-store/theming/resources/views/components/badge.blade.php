{{--
    General-purpose badge (counts, labels, tags) — rendered as .gs-tag so it never collides with
    <x-gs::status-badge> (.gs-badge), which carries workflow status semantics and follows status_style.
    `floating` pins it to the top-right corner of its parent (e.g. a count on a button).
--}}
@props(['tone' => 'primary', 'outline' => false, 'pill' => false, 'floating' => false, 'label' => null])
<span {{ $attributes->class(['gs-tag', 'gs-tag--'.$tone, 'gs-tag--outline' => $outline, 'gs-tag--pill' => $pill || $floating, 'gs-tag--floating' => $floating]) }}>{{ $slot }}@if ($label)<span class="gs-sr-only"> {{ $label }}</span>@endif</span>
