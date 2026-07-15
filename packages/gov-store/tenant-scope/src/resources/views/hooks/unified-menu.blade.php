@php
    /** @var \GovStore\TenantScope\Navigation\MenuRegistry $menuRegistry */
    $menuRegistry = app(\GovStore\TenantScope\Navigation\MenuRegistry::class);
    $menuTree = $menuRegistry->tree();
@endphp

@if(!empty($menuTree))
<script>
document.addEventListener("DOMContentLoaded", function() {
    var sidebar = document.querySelector('.sidebar-menu');
    if (!sidebar) return;

    var menuHtml = '';

    @foreach($menuTree as $root)
    menuHtml += '<li class="treeview {{ $root->isActive() ? "active" : "" }}" id="menu-{{ $root->id }}">'
        + '<a href="#">'
            + '<i class="{{ $root->icon }}"></i>'
            + '<span>{{ $root->title }}</span>'
            + '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>'
        + '</a>'
        + '<ul class="treeview-menu">';

        @foreach($root->children as $child)
            @if(empty($child->children))
    menuHtml += '<li class="{{ $child->isActive() ? "active" : "" }}">'
                + '<a href="{{ $child->route ? route($child->route) : "#" }}">'
                    + '<i class="{{ $child->icon }}"></i> {{ $child->title }}'
                + '</a>'
            + '</li>';
            @else
    menuHtml += '<li class="treeview {{ $child->isActive() ? "active" : "" }}" id="menu-{{ $child->id }}">'
                + '<a href="#">'
                    + '<i class="{{ $child->icon }}"></i> {{ $child->title }}'
                    + '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>'
                + '</a>'
                + '<ul class="treeview-menu">';
                @foreach($child->children as $grandChild)
    menuHtml += '<li class="{{ $grandChild->isActive() ? "active" : "" }}">'
                        + '<a href="{{ $grandChild->route ? route($grandChild->route) : "#" }}">'
                            + '<i class="{{ $grandChild->icon }}"></i> {{ $grandChild->title }}'
                        + '</a>'
                    + '</li>';
                @endforeach
    menuHtml += '</ul></li>';
            @endif
        @endforeach

    menuHtml += '</ul></li>';
    @endforeach

    sidebar.insertAdjacentHTML('beforeend', menuHtml);

    // Re-initialize AdminLTE tree plugin for all injected root nodes
    @foreach($menuTree as $root)
    if (typeof $.fn.tree === 'function') {
        $('#menu-{{ $root->id }}').tree();
    }
    @endforeach
});
</script>
@endif
