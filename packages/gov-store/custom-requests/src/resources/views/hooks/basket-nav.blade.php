@auth
{{-- Header basket (Amazon/AliExpress style): always visible, never overlaps page content. --}}
<li class="notifications-menu cr-nav-basket">
    <a href="{{ route('gov.requests.basket.index') }}" id="nav-basket-btn"
       data-tooltip="true" data-placement="bottom"
       data-title="{{ __('requestlabels::requests.basket_widget_basket_label') }}"
       aria-label="{{ __('requestlabels::requests.basket_widget_basket_label') }}">
        <i class="fas fa-shopping-basket" aria-hidden="true"></i>
        <span class="label label-warning" data-basket-count aria-live="polite">{{ $draftCount ?? 0 }}</span>
        <span class="hidden-xs hidden-sm">{{ __('requestlabels::requests.basket_widget_basket_label') }}</span>
    </a>
</li>
@endauth
