<div class="catalog-search-dropdown">
    <div class="form-group">
        <label>{{ __('classification::texts.livewire_label_classification_code') }}</label>
        <div class="input-group">
            <input type="text" 
                   wire:model.live.debounce.300ms="query"
                   class="form-control" 
                   placeholder="{{ __('classification::texts.livewire_placeholder_search_catalog') }}">
        </div>

        <!-- Suggestions Dropdown -->
        @if(count($suggestions) > 0)
        <div class="catalog-suggestions list-group" style="margin-top: 5px;">
            @foreach($suggestions as $index => $item)
                <button type="button" 
                        class="list-group-item list-group-item-action"
                        wire:click="selectItem('{{ $item->code }}')">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">[{{ $item->code }}] {{ $item->title_en }}</h6>
                        @if($item->snipeMapping)
                            <span class="badge badge-success">{{ __('classification::texts.livewire_badge_mapped') }}</span>
                        @else
                            <span class="badge badge-warning">{{ __('classification::texts.livewire_badge_not_mapped') }}</span>
                        @endif
                    </div>
                    <small class="text-muted">Level {{ $item->level }} · {{ $item->scheme }}</small>
                </button>
            @endforeach
        </div>
        @endif

        <!-- Selected Indicator -->
        @if($selectedSnipeCategoryId)
        <div class="alert alert-success mt-2">
            <i class="fas fa-check"></i> {{ __('classification::texts.livewire_alert_category_selected') }}
        </div>
        @endif
    </div>
</div>

@push('js')
<script>
document.addEventListener('livewire:init', function() {
    // Listen for auto-selected category event
    Livewire.on('category-auto-selected', ({ id }) => {
        // Update Snipe-IT select2 field
        if (typeof $('#category_id').select2 !== 'undefined') {
            $('#category_id').val(id).trigger('change');
        }
        alert('{{ __('classification::texts.livewire_js_category_auto_selected') }}');
    });

    // Listen for mapping prompt event
    Livewire.on('prompt-category-mapping', ({ code, title }) => {
        if (confirm(`[{{ __('classification::texts.livewire_js_prompt_mapping_title') }}]\n\n${title}\n\n{{ __('classification::texts.livewire_js_prompt_mapping_message') }}`)) {
            window.location.href = `{{ route('gov.catalog.mapping.show') }}?code=${code}`;
        }
    });
});
</script>
@endpush
