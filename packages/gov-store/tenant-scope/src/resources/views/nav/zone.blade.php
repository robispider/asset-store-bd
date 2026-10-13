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
@elseif($zone === 'context')
    @if(config('govstore-navigation.topbar.sticky', true))
        {{-- Set while the header parses, so the floating bar applies before first paint. --}}
        <script nonce="{{ csrf_token() }}">document.documentElement.setAttribute('data-gs-sticky-header', '');</script>
    @endif
    @if($navItems)
        <ul class="nav navbar-nav gs-context-nav">
            @foreach($navItems as $navItem)
                @includeIf($navItem->view)
            @endforeach
        </ul>
    @endif
@elseif($zone === 'actions')
    @if(count($navItems) === 1)
        {{-- A one-entry menu is just a button: "＋ Request items". --}}
        @php($navItem = $navItems[0])
        <li class="gs-nav-actions" id="gov-nav-actions" data-gs-action="{{ $navItem->id }}">
            @if($navItem->form !== null)
                <form action="{{ route($navItem->route) }}" method="POST" class="gs-nav-action-form navbar-form">
                    @csrf
                    @foreach($navItem->form as $navField => $navValue)
                        <input type="hidden" name="{{ $navField }}" value="{{ $navValue }}">
                    @endforeach
                    <button type="submit" class="btn btn-link gs-nav-action-single">
                        <i class="fas fa-plus fa-fw" aria-hidden="true"></i><span class="hidden-xs"> {{ $navItem->label() }}</span>
                        <span class="sr-only visible-xs-inline">{{ $navItem->label() }}</span>
                    </button>
                </form>
            @else
                <a href="{{ route($navItem->route) }}">
                    <i class="fas fa-plus fa-fw" aria-hidden="true"></i><span class="hidden-xs"> {{ $navItem->label() }}</span>
                    <span class="sr-only visible-xs-inline">{{ $navItem->label() }}</span>
                </a>
            @endif
        </li>
    @elseif($navItems)
        <li class="dropdown gs-nav-actions" id="gov-nav-actions">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-plus fa-fw" aria-hidden="true"></i>
                <span class="hidden-xs">{{ __('tenantops::nav.new') }}</span>
                <span class="sr-only visible-xs-inline">{{ __('tenantops::nav.new') }}</span>
                <strong class="caret" aria-hidden="true"></strong>
            </a>
            <ul class="dropdown-menu">
                @foreach($navItems as $navItem)
                    <li id="menu-{{ $navItem->id }}">
                        @if($navItem->form !== null)
                            <form action="{{ route($navItem->route) }}" method="POST" class="gs-nav-action-form">
                                @csrf
                                @foreach($navItem->form as $navField => $navValue)
                                    <input type="hidden" name="{{ $navField }}" value="{{ $navValue }}">
                                @endforeach
                                <button type="submit" class="btn btn-link gs-nav-action-button">
                                    <i class="{{ $navItem->icon }}" aria-hidden="true"></i>
                                    {{ $navItem->label() }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route($navItem->route) }}">
                                <i class="{{ $navItem->icon }}" aria-hidden="true"></i>
                                {{ $navItem->label() }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </li>
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
