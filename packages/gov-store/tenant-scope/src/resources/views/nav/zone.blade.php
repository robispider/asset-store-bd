{{-- One GovStore navigation zone, attached to layouts/default by NavigationHook. --}}
@auth
@php($navItems = app(\GovStore\TenantScope\Navigation\MenuRegistry::class)->zone($zone))
@if(in_array($zone, \GovStore\TenantScope\Navigation\MenuRegistry::SIDEBAR_ZONES, true))
    @if($navItems)
        <li class="header gs-nav-header" id="gov-nav-{{ $zone }}-header">{{ __('tenantops::nav.zone_'.$zone) }}</li>
        @include('govscope::nav.items', ['items' => $navItems])
        @if($zone === 'work' && \GovStore\TenantScope\Navigation\CoreSidebar::visibleTo(auth()->user()))
            <li class="header gs-nav-header" id="gov-nav-assets-header">{{ __('tenantops::nav.zone_assets') }}</li>
        @endif
    @endif
@else
    @foreach($navItems as $navItem)
        @if($navItem->view)
            @includeIf($navItem->view)
        @else
            <li id="menu-{{ $navItem->id }}" @if($navItem->isActive()) class="active" @endif>
                <a href="{{ route($navItem->route) }}" @if($navItem->isActive()) aria-current="page" @endif>
                    <i class="{{ $navItem->icon }}" aria-hidden="true"></i>
                    {{ $navItem->label() }}
                </a>
            </li>
        @endif
    @endforeach
@endif
@endauth
