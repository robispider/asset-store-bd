<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    console.log("Gov-Store: Building dynamic e-commerce menus.");

    if ($('.sidebar-menu').length) {
        var path = window.location.pathname;
        var isStoreActive = path.includes('gov-requests');
        var isApprovalsActive = path.includes('gov-requests/admin') && !path.includes('policies') && !path.includes('settings');
        var isFulfillmentActive = path.includes('gov-requests/fulfillment');
        var isPoliciesActive = path.includes('settings/policies');
        var isCatalogActive = path.includes('gov-requests/catalog');
        var isMyRequestsActive = path.includes('gov-requests/my-requests');

        // 1. Create the main expandable "Government Store" directory tree
        var storeMenu = '<li class="treeview ' + (isStoreActive ? 'active' : '') + '" id="gov-store-parent-menu">' +
            '<a href="#">' +
                '<i class="fas fa-shopping-cart fa-fw"></i>' +
                '<span>Government Store</span>' +
                '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>' +
            '</a>' +
            '<ul class="treeview-menu">' +
                // Standard Employee Links
                '<li class="' + (isCatalogActive ? 'active' : '') + '"><a href="{{ route("gov.requests.catalog") }}"><i class="fas fa-store fa-fw"></i> Browse Catalog</a></li>' +
                '<li class="' + (isMyRequestsActive ? 'active' : '') + '"><a href="{{ route("gov.requests.user.index") }}"><i class="fas fa-clipboard-list fa-fw"></i> My Requests</a></li>';

        // 2. Inject Restricted "Store Operations" section strictly to authorized staff
        @if(auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin') || auth()->user()->hasAccess('superuser'))
            storeMenu += '<li class="header" style="padding: 5px 15px; font-size: 10px; color: #72afd2; background: #1a2226; letter-spacing: 1px;">STORE OPERATIONS</li>' +
                '<li class="' + (isApprovalsActive ? 'active' : '') + '"><a href="{{ route("gov.requests.admin.index") }}"><i class="fas fa-clipboard-check fa-fw"></i> Gov Approvals</a></li>' +
                '<li class="' + (isFulfillmentActive ? 'active' : '') + '"><a href="{{ route("gov.requests.fulfillment.index") }}"><i class="fas fa-shipping-fast fa-fw"></i> Fulfillment Queue</a></li>' +
                '<li class="' + (isPoliciesActive ? 'active' : '') + '"><a href="{{ route("gov.requests.admin.policies.index") }}"><i class="fas fa-tags fa-fw"></i> Category Policies</a></li>';
        @endif

        storeMenu += '</ul></li>';

        // Attach securely right below Snipe-IT's core dashboard
        if ($('.sidebar-menu li.firstnav').length) {
            $('.sidebar-menu li.firstnav').after(storeMenu);
        } else {
            $('.sidebar-menu').prepend(storeMenu);
        }
        console.log("Gov-Store: Folders successfully generated.");
    }
});
</script>