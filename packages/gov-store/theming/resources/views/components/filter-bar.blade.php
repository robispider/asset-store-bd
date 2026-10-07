@props(['resetHref' => null, 'action' => null, 'method' => 'get', 'active' => 0])
@php($fieldsId = 'gs-filters-'.\Illuminate\Support\Str::random(6))
<form {{ $attributes->class('gs-filter-bar') }} method="{{ $method }}" @if ($action) action="{{ $action }}" @endif role="search" data-open="false">
    <button type="button" class="gs-btn gs-filter-bar__toggle" data-gs-filter-toggle aria-expanded="false" aria-controls="{{ $fieldsId }}">
        <i class="fas fa-filter" aria-hidden="true"></i> {{ __('gs-theme::appearance.kit.filters') }}
        @if ($active)<span class="gs-filter-bar__active">{{ $active }}</span>@endif
    </button>
    <div class="gs-filter-bar__fields" id="{{ $fieldsId }}">
        {{ $slot }}
        <div class="gs-filter-bar__actions">
            <button type="submit" class="gs-btn gs-btn--primary">{{ __('gs-theme::appearance.kit.apply') }}</button>
            @if ($resetHref)<a class="gs-btn gs-btn--ghost" href="{{ $resetHref }}">{{ __('gs-theme::appearance.kit.reset') }}</a>@endif
        </div>
    </div>
</form>
