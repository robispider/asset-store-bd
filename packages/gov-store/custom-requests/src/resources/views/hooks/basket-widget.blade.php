@auth
<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    console.log("Gov-Store: Initializing basket widgets.");

    // --- 1. ADD TO BASKET BUTTON ON PRODUCT SPECIFIC VIEWS ---
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

        if (itemType && itemId && !$('#add-to-basket-btn-container').length) {
            var hasStock = $('.side-box').text().includes('Remaining') && !$('.side-box').text().includes('0 Remaining');
            if (itemType === 'asset') hasStock = $('.side-box').text().includes('Ready to Deploy');

            if (hasStock) {
                var buttonHtml = '<div id="add-to-basket-btn-container" style="margin-top: 10px; width: 100%;">' +
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

    // --- 2. FLOATING BASKET WIDGET ---
    @php
        $draftCount = \GovStore\CustomRequests\Models\Request::where('requested_by', auth()->id())
            ->where('approval_status', 'draft')
            ->first()
            ?->items()->count() ?? 0;
    @endphp
    
    if (!$('#floating-basket-btn').length) {
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
    }
});
</script>
@endauth