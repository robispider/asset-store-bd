<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    
    // 1. INJECT SIDEBAR "GOV APPROVALS" (Only for Admins)
    @if(auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin'))
        if ($('.sidebar-menu').length) {
            var activeClass = window.location.pathname.includes('gov-requests/admin') ? 'active' : '';
            var sidebarLink = `
                <li class="${activeClass}">
                    <a href="{{ route('gov.requests.admin.index') }}">
                        <i class="fas fa-clipboard-check fa-fw"></i>
                        <span>Gov Approvals</span>
                    </a>
                </li>
            `;
            // Append right after the Dashboard link (first link)
            $('.sidebar-menu li.firstnav').after(sidebarLink);
        }
    @endif

    // 2. INJECT USER DROPDOWN LINKS
    if ($('.dropdown.user-menu .dropdown-menu').length) {
        var catalogActive = window.location.pathname.includes('gov-requests/catalog') ? 'style="font-weight: bold; background:#eee;"' : '';
        var requestsActive = window.location.pathname.includes('gov-requests/my-requests') ? 'style="font-weight: bold; background:#eee;"' : '';
        
        var dropdownLinks = `
            <li ${catalogActive}>
                <a href="{{ route('gov.requests.catalog') }}">
                    <i class="fas fa-store fa-fw"></i> Browse Item Catalog
                </a>
            </li>
            <li ${requestsActive}>
                <a href="{{ route('gov.requests.user.index') }}">
                    <i class="fas fa-clipboard-list fa-fw"></i> My Gov-Requests
                </a>
            </li>
            <li class="divider"></li>
        `;
        // Prepend to the top of the user dropdown list
        $('.dropdown.user-menu .dropdown-menu').prepend(dropdownLinks);
    }

    // 3. INJECT THE REQUEST BUTTON ON INDIVIDUAL ITEM VIEWS (Consumables, Accessories, Assets)
    if ($('.side-box .box-footer').length) {
        var path = window.location.pathname;
        var itemType = '';
        var itemId = '';
        var itemName = $('.pagetitle').text().trim();

        if (path.includes('/consumables/')) {
            itemType = 'Consumable';
            itemId = path.split('/consumables/')[1].split('/')[0];
        } else if (path.includes('/accessories/')) {
            itemType = 'Accessory';
            itemId = path.split('/accessories/')[1].split('/')[0];
        } else if (path.includes('/hardware/')) {
            itemType = 'Asset';
            itemId = path.split('/hardware/')[1].split('/')[0];
        }

        // If we identified a valid requestable page, load the request button dynamically via AJAX!
        if (itemType && itemId) {
            // Check if there is already stock left before drawing button
            var hasStock = $('.side-box').text().includes('Remaining') && !$('.side-box').text().includes('0 Remaining');
            if (itemType === 'Asset') hasStock = $('.side-box').text().includes('Ready to Deploy');

            if (hasStock) {
                // Fetch the HTML component dynamically from our custom package route
                var buttonHtml = `
                    <div style="margin-top: 10px; width: 100%;">
                        @include('govstore::components.request-button', [
                            'itemType' => '${itemType}',
                            'itemId' => '${itemId}',
                            'itemName' => '${itemName}'
                        ])
                    </div>
                `;
                // Append directly to the actions button list inside the right panel
                $('.side-box .box-footer').append(buttonHtml);
            }
        }
    }
});
</script>