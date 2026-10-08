@auth
<div id="gov-request-ui" hidden
     data-csrf="{{ csrf_token() }}"
     data-add-url="{{ route('gov.requests.basket.add') }}"
     data-update-url="{{ route('gov.requests.basket.update') }}"
     data-search-url="{{ route('gov.requests.catalog.search') }}"
     data-add-label="{{ __('requestlabels::requests.requestbutton_btn_add_to_basket') }}"
     data-adding-label="{{ __('requestlabels::requests.requestbutton_btn_adding') }}"
     data-added-label="{{ __('requestlabels::requests.requestbutton_btn_added') }}"
     data-saved-label="{{ __('requestlabels::requests.saved') }}"
     data-error-label="{{ __('requestlabels::requests.requestbutton_ajax_error') }}"
     data-duplicate-label="{{ __('requestlabels::requests.duplicate_asset') }}"></div>
<a href="{{ route('gov.requests.basket.index') }}" id="floating-basket-btn" class="btn btn-primary cr-floating-basket">
    <i class="fas fa-shopping-basket" aria-hidden="true"></i>
    {{ __('requestlabels::requests.basket_widget_basket_label') }}
    (<span id="floating-basket-count" aria-live="polite">{{ $draftCount }}</span>)
</a>
<script src="{{ asset('js/gov-requests.js') }}?v={{ filemtime(public_path('js/gov-requests.js')) }}" defer></script>
@endauth
