@foreach($items as $item)
    <li id="menu-{{ $item->id }}" class="{{ $item->children ? 'treeview' : '' }} {{ $item->isActive() ? 'active' : '' }}">
        <a href="{{ $item->route ? route($item->route) : '#' }}">
            <i class="{{ $item->icon }}" aria-hidden="true"></i>
            <span>{{ $item->title }}</span>
            @if($item->children)
                <span class="pull-right-container"><i class="fa fa-angle-left pull-right" aria-hidden="true"></i></span>
            @endif
        </a>
        @if($item->children)
            <ul class="treeview-menu">
                @include('govscope::hooks.menu-items', ['items' => $item->children])
            </ul>
        @endif
    </li>
@endforeach
