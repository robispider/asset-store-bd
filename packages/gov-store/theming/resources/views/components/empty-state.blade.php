@props(['icon' => 'fa-inbox', 'title'])
<div {{ $attributes->class('gs-empty') }}>
    <i class="fas {{ $icon }} gs-empty__icon" aria-hidden="true"></i>
    <h3 class="gs-empty__title">{{ $title }}</h3>
    @if (trim($slot))<div>{{ $slot }}</div>@endif
    @isset($actions)<div class="gs-empty__actions">{{ $actions }}</div>@endisset
</div>
