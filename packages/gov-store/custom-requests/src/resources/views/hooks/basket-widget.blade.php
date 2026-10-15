@auth
{{-- Settings for gov-requests.js (published from this package, D9). Every action also works without it. --}}
<div id="gov-request-ui" hidden
     data-csrf="{{ csrf_token() }}"
     data-add-url="{{ route('gov.requests.basket.add') }}"
     data-update-url="{{ route('gov.requests.basket.update') }}"
     data-remove-url="{{ route('gov.requests.basket.remove', ['id' => '__ID__']) }}"
     data-add-label="{{ __('requestlabels::requests.catalog_add') }}"
     data-adding-label="{{ __('requestlabels::requests.catalog_adding') }}"
     data-undo-label="{{ __('requestlabels::requests.catalog_undo') }}"
     data-undone-label="{{ __('requestlabels::requests.catalog_undone') }}"
     data-saving-label="{{ __('requestlabels::requests.draft_saving') }}"
     data-saved-label="{{ __('requestlabels::requests.draft_saved') }}"
     data-error-label="{{ __('requestlabels::requests.catalog_add_error') }}"
     data-save-error-label="{{ __('requestlabels::requests.draft_save_error') }}"
     data-use-label="{{ __('requestlabels::requests.alt_use') }}"
     data-chosen-label="{{ __('requestlabels::requests.alt_chosen', ['item' => '__ITEM__']) }}"></div>
@if (is_file(public_path('js/gov-requests.js')))
    <script src="{{ asset('js/gov-requests.js') }}?v={{ filemtime(public_path('js/gov-requests.js')) }}" defer></script>
@endif
@endauth
