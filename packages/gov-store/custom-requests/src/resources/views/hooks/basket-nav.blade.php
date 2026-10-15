@auth
{{-- Top bar: "Draft · N" only while the draft has items (hidden at 0; gov-requests.js shows it after an add). --}}
@php $draftItems = (int) ($draftCount ?? $govBasketCount ?? 0); @endphp
<li class="cr-nav-draft" data-cr-nav-draft @if ($draftItems < 1) hidden @endif>
    <a href="{{ route('gov.requests.basket.index') }}" id="nav-basket-btn"
       aria-label="{{ trans_choice('requestlabels::requests.nav_draft_label', $draftItems, ['count' => $draftItems]) }}"
       data-label-template="{{ trans_choice('requestlabels::requests.nav_draft_label', 2, ['count' => '__N__']) }}">
        <i class="fas fa-file-pen" aria-hidden="true"></i>
        <span>{{ __('requestlabels::requests.nav_draft') }}</span>
        <span class="cr-nav-draft__count" data-basket-count>{{ $draftItems }}</span>
    </a>
</li>
@endauth
