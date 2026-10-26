{{-- Discover area tabs. $active: collections | explorer --}}
<nav class="gs-discover-nav" aria-label="{{ __('classification::discover.nav.label') }}">
    <div class="gs-discover-nav__tabs">
        <a href="{{ route('gov.catalog.discover.collections') }}" @class(['gs-discover-nav__tab', 'is-active' => $active === 'collections']) @if ($active === 'collections') aria-current="page" @endif>{{ __('classification::discover.nav.collections') }}</a>
        <a href="{{ route('gov.catalog.discover.explorer') }}" @class(['gs-discover-nav__tab', 'is-active' => $active === 'explorer']) @if ($active === 'explorer') aria-current="page" @endif>{{ __('classification::discover.nav.explorer') }}</a>
        <a href="{{ route('gov.catalog.my_catalog.index') }}" class="gs-discover-nav__tab">{{ __('classification::discover.nav.my_catalog') }}</a>
    </div>
</nav>
