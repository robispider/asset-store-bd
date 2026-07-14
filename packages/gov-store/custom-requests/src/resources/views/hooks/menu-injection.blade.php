@auth
<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    console.log("Gov-Store: Building dynamic e-commerce menus.");

    // --- 1. SIDEBAR FOLDERS AND LINKS ---
    if ($('.sidebar-menu').length) {
        var path = window.location.pathname;
        var isStoreActive = path.includes('gov-requests');
        var isApprovalsActive = path.includes('gov-requests/admin') && !path.includes('policies') && !path.includes('locations');
        var isFulfillmentActive = path.includes('gov-requests/fulfillment');
        var isPoliciesActive = path.includes('settings/policies');
        var isLocationsActive = path.includes('settings/locations');
        var isCatalogActive = path.includes('gov-requests/catalog');
        var isMyRequestsActive = path.includes('gov-requests/my-requests');

     @php
            $user = auth()->user();
            $isSysAdmin = $user->isSuperUser() || $user->hasAccess('admin');
            $isApprover = false;
            $isStorekeeper = false;

            // NEW MATRIX LOOKUP: Check if user holds roles in ANY office they are a member of
            if (class_exists(\GovStore\OfficeMembership\Models\OfficeResponsibility::class)) {
                $isApprover = \GovStore\OfficeMembership\Models\OfficeResponsibility::where('user_id', $user->id)
                                ->whereIn('role_slug', ['primary_approver', 'final_approver'])
                                ->exists();
                                
                $isStorekeeper = \GovStore\OfficeMembership\Models\OfficeResponsibility::where('user_id', $user->id)
                                ->where('role_slug', 'storekeeper')
                                ->exists();
            }
        @endphp

        var storeMenu = '<li class="treeview ' + (isStoreActive ? 'active' : '') + '" id="gov-store-parent-menu">' +
            '<a href="#">' +
                '<i class="fas fa-shopping-cart fa-fw"></i>' +
                '<span>Government Store</span>' +
                '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>' +
            '</a>' +
            '<ul class="treeview-menu">' +
                '<li class="' + (isCatalogActive ? 'active' : '') + '"><a href="{{ route("gov.requests.catalog") }}"><i class="fas fa-store fa-fw"></i> Browse Catalog</a></li>' +
                '<li class="' + (isMyRequestsActive ? 'active' : '') + '"><a href="{{ route("gov.requests.user.index") }}"><i class="fas fa-clipboard-list fa-fw"></i> My Requests</a></li>';

        // Render Operations section if user is Admin, Approver, or Storekeeper
        @if($isSysAdmin || $isApprover || $isStorekeeper)
            storeMenu += '<li class="header" style="padding: 5px 15px; font-size: 10px; color: #72afd2; background: #1a2226; letter-spacing: 1px;">STORE OPERATIONS</li>';
            
            @if($isSysAdmin || $isApprover)
                storeMenu += '<li class="' + (isApprovalsActive ? 'active' : '') + '"><a href="{{ route("gov.requests.admin.index") }}"><i class="fas fa-clipboard-check fa-fw"></i> Gov Approvals</a></li>';
            @endif
            
            @if($isSysAdmin || $isStorekeeper)
                storeMenu += '<li class="' + (isFulfillmentActive ? 'active' : '') + '"><a href="{{ route("gov.requests.fulfillment.index") }}"><i class="fas fa-shipping-fast fa-fw"></i> Fulfillment Queue</a></li>';
            @endif
            
            @if($isSysAdmin)
                storeMenu += '<li class="' + (isLocationsActive ? 'active' : '') + '"><a href="{{ route("gov.requests.admin.locations.index") }}"><i class="fas fa-map-marked-alt fa-fw"></i> Office Assignments</a></li>';
                storeMenu += '<li class="' + (isPoliciesActive ? 'active' : '') + '"><a href="{{ route("gov.requests.admin.policies.index") }}"><i class="fas fa-tags fa-fw"></i> Category Policies</a></li>';
            @endif
        @endif

        storeMenu += '</ul></li>';

        if ($('.sidebar-menu li.firstnav').length) {
            $('.sidebar-menu li.firstnav').after(storeMenu);
        } else {
            $('.sidebar-menu').prepend(storeMenu);
        }
    }

    // --- 2. ADD TO BASKET BUTTON ON ITEM VIEWS ---
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
            }
        }
    }

    // --- 3. FLOATING BASKET WIDGET ---
    @php
        $draftCount = \GovStore\CustomRequests\Models\Request::where('requested_by', auth()->id())
            ->where('approval_status', 'draft')
            ->first()
            ?->items()->count() ?? 0;
    @endphp
    
    var basketWidget = '<a href="{{ route("gov.requests.basket.index") }}" id="floating-basket-btn" style="position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; align-items: center; justify-content: center; text-decoration: none;">' +
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
});
</script>
@endauth