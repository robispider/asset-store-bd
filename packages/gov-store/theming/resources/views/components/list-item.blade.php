{{-- One row of <x-gs::list>: leading image / avatar / icon, title + subtitle, trailing `meta` and `actions` slots. --}}
@props(['title', 'subtitle' => null, 'href' => null, 'image' => null, 'imageAlt' => '', 'avatar' => null, 'icon' => null])
<li {{ $attributes->class('gs-list__item') }}>
    @if ($image)
        <img class="gs-list__media" src="{{ $image }}" alt="{{ $imageAlt }}" loading="lazy">
    @elseif ($avatar)
        <x-gs::avatar :name="$avatar" />
    @elseif ($icon)
        <span class="gs-list__icon" aria-hidden="true"><i class="fas {{ $icon }}"></i></span>
    @endif
    <div class="gs-list__text">
        @if ($href)<a class="gs-list__title" href="{{ $href }}">{{ $title }}</a>@else<span class="gs-list__title">{{ $title }}</span>@endif
        @if ($subtitle)<span class="gs-list__subtitle">{{ $subtitle }}</span>@endif
        {{ $slot }}
    </div>
    @isset($meta)<div class="gs-list__meta">{{ $meta }}</div>@endisset
    @isset($actions)<div class="gs-list__actions">{{ $actions }}</div>@endisset
</li>
