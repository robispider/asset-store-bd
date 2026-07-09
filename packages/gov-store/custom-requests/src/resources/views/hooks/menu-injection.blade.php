<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    console.log("Gov-Store: Building dynamic e-commerce menus.");

    if ($('.sidebar-menu').length) {
    
    // 1. INJECT SIDEBAR "GOV APPROVALS" & "FULFILLMENT QUEUE" (Only for Admins/Storekeepers)
    @if(auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin') || auth()->user()->hasAccess('superuser'))
        console.log("Gov-Store: User is verified as Admin/Superuser.");
        
        if ($('.sidebar-menu').length) {
            var approvalsActive = window.location.pathname.includes('gov-requests/admin') ? 'active' : '';
            var fulfillmentActive = window.location.pathname.includes('gov-requests/fulfillment') ? 'active' : '';

            var sidebarLink = `
                <li class="${approvalsActive}" id="gov-approvals-sidebar-item">
                    <a href="{{ route('gov.requests.admin.index') }}">
                        <i class="fas fa-clipboard-check fa-fw"></i>
                        <span>Gov Approvals</span>
                    </a>
                </li>
                <li class="${fulfillmentActive}" id="gov-fulfillment-sidebar-item">
                    <a href="{{ route('gov.requests.fulfillment.index') }}">
                        <i class="fas fa-shipping-fast fa-fw"></i>
                        <span>Fulfillment Queue</span>
                    </a>
                </li>
            `;
            
            if ($('.sidebar-menu li.firstnav').length) {
                $('.sidebar-menu li.firstnav').after(sidebarLink);
            } else {
                $('.sidebar-menu').prepend(sidebarLink);
            }
            console.log("Gov-Store: Left sidebar links appended successfully.");
        } else {
            console.log("Gov-Store: ERROR - '.sidebar-menu' not found.");
        }
    @endif

    // 2. INJECT USER DROPDOWN LINKS
    if ($('.dropdown.user-menu .dropdown-menu').length) {
        var catalogActive = window.location.pathname.includes('gov-requests/catalog') ? 'style="font-weight: bold; background:#eee;"' : '';
        var requestsActive = window.location.pathname.includes('gov-requests/my-requests') ? 'style="font-weight: bold; background:#eee;"' : '';
        
        var dropdownLinks = '<li ' + catalogActive + '>' +
                '<a href="{{ route('gov.requests.catalog') }}">' +
                    '<i class="fas fa-store fa-fw"></i> Browse Item Catalog' +
                '</a>' +
            '</li>' +
            '<li ' + requestsActive + '>' +
                '<a href="{{ route('gov.requests.user.index') }}">' +
                    '<i class="fas fa-clipboard-list fa-fw"></i> My Gov-Requests' +
                '</a>' +
            '</li>' +
            '<li class="divider"></li>';
            
        $('.dropdown.user-menu .dropdown-menu').prepend(dropdownLinks);
        console.log("Gov-Store: Dropdown links injected.");
    }

    // 3. INJECT THE REQUEST BUTTON ON INDIVIDUAL ITEM VIEWS (Using pure safe JS string generation)
    if ($('.side-box .box-footer').length) {
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