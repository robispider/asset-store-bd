@props(['title', 'bn' => null, 'subtitle' => null, 'level' => 1])
<header {{ $attributes->class('gs-page-header') }}>
    <div class="gs-page-header__text">
        <h{{ $level }} class="gs-page-header__title">
            {{ $title }}
            @if ($bn)<span class="gs-page-header__bn" lang="bn">{{ $bn }}</span>@endif
        </h{{ $level }}>
        @if ($subtitle)<p class="gs-page-header__subtitle">{{ $subtitle }}</p>@endif
        @isset($meta)<div class="gs-page-header__meta">{{ $meta }}</div>@endisset
    </div>
    @isset($actions)<div class="gs-page-header__actions">{{ $actions }}</div>@endisset
</header>
