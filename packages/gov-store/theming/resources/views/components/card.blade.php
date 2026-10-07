{{--
    Content card: optional media on top, a header strip, title/subtitle, body and footer/actions.
    For working panels with tools and collapse use <x-gs::box>; a card is for presenting content.
--}}
@props(['title' => null, 'subtitle' => null, 'header' => null, 'image' => null, 'imageAlt' => '', 'align' => 'start', 'href' => null])
<article {{ $attributes->class(['gs-card', 'gs-card--center' => $align === 'center']) }}>
    @if ($header)<div class="gs-card__header">{{ $header }}</div>@endif
    @if ($image)<img class="gs-card__media" src="{{ $image }}" alt="{{ $imageAlt }}" loading="lazy">@endif
    @isset($media)<div class="gs-card__media">{{ $media }}</div>@endisset
    <div class="gs-card__body">
        @if ($title)
            <h3 class="gs-card__title">@if ($href)<a href="{{ $href }}">{{ $title }}</a>@else{{ $title }}@endif</h3>
        @endif
        @if ($subtitle)<p class="gs-card__subtitle">{{ $subtitle }}</p>@endif
        {{ $slot }}
    </div>
    @isset($actions)<div class="gs-card__actions">{{ $actions }}</div>@endisset
    @isset($footer)<footer class="gs-card__footer">{{ $footer }}</footer>@endisset
</article>
