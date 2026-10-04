@php
    /** @var \GovStore\TenantScope\Navigation\MenuRegistry $menuRegistry */
    $menuRegistry = app(\GovStore\TenantScope\Navigation\MenuRegistry::class);
    $menuTree = $menuRegistry->tree();
    $rolloutArgs = ['date' => config('govstore-access.enforcement_date'), 'contact' => config('govstore-access.help_contact')];
    $rolloutBn = trans('tenantops::access.rollout', $rolloutArgs, 'bn-BD');
    $rolloutEn = trans('tenantops::access.rollout', $rolloutArgs, 'en-US');
@endphp

@if(!empty($menuTree))
<script>
document.addEventListener("DOMContentLoaded", function() {
    var sidebar = document.querySelector('.sidebar-menu');
    if (!sidebar) return;
    var userMenu = document.querySelector('.dropdown.user.user-menu .dropdown-menu');
    if (userMenu) {
        var accessItem = document.createElement('li');
        var accessLink = document.createElement('a');
        accessLink.href = @json(route('gov.access.index'));
        accessLink.textContent = @json(__('tenantops::access.my_access'));
        accessItem.appendChild(accessLink); userMenu.appendChild(accessItem);
    }
    @if(config('govstore-access.enforcement_date'))
    var banner = document.createElement('div'); banner.className = 'alert alert-info'; banner.setAttribute('role', 'status');
    banner.textContent = @json($rolloutBn) + ' ' + @json($rolloutEn);
    var contentArea = document.querySelector('.content'); if (contentArea) contentArea.prepend(banner);
    @endif
    $(document).ajaxError(function(event, xhr) {
        var data = xhr.responseJSON;
        if (!data || !data.reason_key) return;
        var panel = document.getElementById('gov-access-inline');
        if (!panel) { panel = document.createElement('div'); panel.id = 'gov-access-inline'; panel.className = 'alert alert-warning'; panel.setAttribute('role', 'alert'); document.querySelector('.content')?.prepend(panel); }
        panel.hidden = false; panel.textContent = (data.action || '') + ' ' + data.reason + ' ' + (data.next_step || '') + ' ' + (data.reference_id || '');
        (data.helpers || []).forEach(function(helper) { var person = document.createElement('p'); person.textContent = helper.name; panel.appendChild(person); });
        var link = document.createElement('a'); link.className = 'btn btn-default'; link.href = data.review_url || data.access_url;
        link.textContent = data.review_url ? @json(__('tenantops::access.national_review')) : @json(__('tenantops::access.request'));
        panel.appendChild(link);
    });

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
