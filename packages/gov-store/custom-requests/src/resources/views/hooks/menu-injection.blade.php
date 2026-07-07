<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    
    console.log("Gov-Store: Injection script loaded.");

    
    // 1. INJECT SIDEBAR "GOV APPROVALS" & "FULFILLMENT QUEUE" (Only for Admins/Storekeepers)
  // 1. INJECT SIDEBAR ADMIN SETTINGS (Only for Admins)
    @if(auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin') || auth()->user()->hasAccess('superuser'))
        console.log("Gov-Store: User is verified as Admin/Superuser.");
        
        if ($('.sidebar-menu').length) {
            var approvalsActive = window.location.pathname.includes('gov-requests/admin') && !window.location.pathname.includes('settings') ? 'active' : '';
            var fulfillmentActive = window.location.pathname.includes('gov-requests/fulfillment') ? 'active' : '';
            var locationsActive = window.location.pathname.includes('settings/locations') ? 'active' : '';
            var policiesActive = window.location.pathname.includes('settings/policies') ? 'active' : '';
            
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
                <li class="${locationsActive}" id="gov-locations-sidebar-item">
                    <a href="{{ route('gov.requests.admin.locations.index') }}">
                        <i class="fas fa-map-marked-alt fa-fw"></i>
                        <span>Office Assignments</span>
                    </a>
                </li>
                <li class="${policiesActive}" id="gov-policies-sidebar-item">
                    <a href="{{ route('gov.requests.admin.policies.index') }}">
                        <i class="fas fa-tags fa-fw"></i>
                        <span>Category Policies</span>
                    </a>
                </li>
            `;
            
            if ($('.sidebar-menu li.firstnav').length) {
                $('.sidebar-menu li.firstnav').after(sidebarLink);
            } else {
                $('.sidebar-menu').prepend(sidebarLink);
            }
            console.log("Gov-Store: Sidebar links appended successfully.");
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
        var itemType = '';
        var itemId = '';

        if (path.includes('/consumables/')) {
            itemType = 'consumable';
            itemId = path.split('/consumables/')[1].split('/')[0];
        } else if (path.includes('/accessories/')) {
            itemType = 'accessory';
            itemId = path.split('/accessories/')[1].split('/')[0];
        } else if (path.includes('/hardware/')) {
            itemType = 'asset';
            itemId = path.split('/hardware/')[1].split('/')[0];
        }

        if (itemType && itemId) {
            var hasStock = $('.side-box').text().includes('Remaining') && !$('.side-box').text().includes('0 Remaining');
            if (itemType === 'asset') hasStock = $('.side-box').text().includes('Ready to Deploy');

            if (hasStock) {
                // Generate the exact checkout AJAX form purely in Javascript to prevent compiling errors
                var buttonHtml = '<div style="margin-top: 10px; width: 100%;">' +
                    '<form action="{{ route("gov.requests.basket.add") }}" method="POST" class="ajax-basket-form" style="margin: 0; width: 100%;">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        '<input type="hidden" name="item_type" value="' + itemType + '">' +
                        '<input type="hidden" name="item_id" value="' + itemId + '">' +
                        '<button type="submit" class="btn btn-primary btn-sm btn-block add-to-basket-btn">' +
                            '<i class="fas fa-cart-plus"></i> Add to Request Basket' +
                        '</button>' +
                    '</form>' +
                '</div>';
                
                $('.side-box .box-footer').append(buttonHtml);
                console.log("Gov-Store: Request button injected.");
            }
        }
    }

    // 4. INJECT FLOATING REQUEST BASKET BUTTON
    @auth
        @php
            $draftCount = \GovStore\CustomRequests\Models\Request::where('requested_by', auth()->id())
                ->where('approval_status', 'draft')
                ->first()
                ?->items()->count() ?? 0;
        @endphp
        
        var basketWidget = '<a href="{{ route('gov.requests.basket.index') }}" id="floating-basket-btn" style="position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; align-items: center; justify-content: center; text-decoration: none;">' +
            '<div style="background: #3c8dbc; color: white; padding: 12px 20px; border-radius: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: bold; display: flex; align-items: center; gap: 10px;">' +
                '<i class="fas fa-shopping-basket fa-lg"></i>' +
                '<span>Basket (<span id="floating-basket-count">{{ $draftCount }}</span>)</span>' +
            '</div>' +
        '</a>';
        
        $('body').append(basketWidget);
        $('#floating-basket-btn').hover(
            function() { $(this).css('transform', 'scale(1.05)'); },
            function() { $(this).css('transform', 'scale(1)'); }
        );
        console.log("Gov-Store: Floating basket injected.");
    @endauth
});
</script>