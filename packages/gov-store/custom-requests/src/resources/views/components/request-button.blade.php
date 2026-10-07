<form action="{{ route('gov.requests.basket.add') }}" method="POST" class="ajax-basket-form" style="margin: 0; width: 100%;">
    @csrf
    <input type="hidden" name="item_type" value="{{ strtolower($itemType) }}">
    <input type="hidden" name="item_id" value="{{ $itemId }}">
    
    <!-- Dynamic Quantity Input for ALL Items (Models, Consumables, Accessories) -->
    <div class="input-group input-group-sm" style="margin-bottom: 6px;">
        <span class="input-group-addon" style="font-size: 11px; padding: 4px 8px;">{{ __('requestlabels::requests.quantity') }}</span>
        <input aria-label="{{ __('requestlabels::requests.quantity') }} — {{ $itemName }}" type="number" name="qty" class="form-control" value="1" min="1" max="10000" required style="height: 28px; text-align: center;">
    </div>
    
    <button type="submit" class="btn btn-primary btn-sm btn-block add-to-basket-btn" style="height: 28px; font-size: 12px;">
        <i class="fas fa-cart-plus"></i> {{ __('requestlabels::requests.requestbutton_btn_add_to_basket') }}
    </button>
</form>
