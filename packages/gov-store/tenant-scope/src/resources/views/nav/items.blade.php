@foreach($items as $item)
    @php($active = $item->isActive())
    @php($badge = $item->badgeCount())
    <li id="menu-{{ $item->id }}" class="{{ $item->children ? 'treeview' : '' }}{{ $active ? ' active' : '' }}{{ $active && $item->children ? ' menu-open' : '' }}">
        <a href="{{ $item->route ? route($item->route) : '#' }}" @if($active && ! $item->children) aria-current="page" @endif>
            <i class="{{ $item->icon }}" aria-hidden="true"></i>
            <span>{{ $item->label() }}</span>
            @if($item->children || $badge)
                <span class="pull-right-container">
                    @if($item->children)
                        <i class="fa fa-angle-left pull-right" aria-hidden="true"></i>
                    @endif
                    @if($badge)
                        <small class="label pull-right label-warning gs-nav-badge">{{ $badge }}</small>
                    @endif
                </span>
            @endif
        </a>
        @if($item->children)
            <ul class="treeview-menu">
                @include('govscope::nav.items', ['items' => $item->children])
            </ul>
        @endif
    </li>
@endforeach
