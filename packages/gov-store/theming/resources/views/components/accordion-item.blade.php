@aware(['name' => null])
@props(['title', 'icon' => null, 'open' => false])
<details {{ $attributes->class('gs-accordion__item') }} @if ($name) name="{{ $name }}" @endif @if ($open) open @endif>
    <summary class="gs-accordion__summary">
        @if ($icon)<i class="fas {{ $icon }} gs-muted" aria-hidden="true"></i>@endif
        <span class="gs-accordion__title">{{ $title }}</span>
        <i class="fas fa-chevron-down gs-accordion__chevron" aria-hidden="true"></i>
    </summary>
    <div class="gs-accordion__body">{{ $slot }}</div>
</details>
