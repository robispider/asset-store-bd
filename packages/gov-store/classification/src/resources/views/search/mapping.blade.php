<div class="detail-sheet classify-inline-514f7b08">
    
    <!-- Main Node Details Workspace -->
    <div id="details-main-content">
        <!-- Header -->
        <div class="classify-inline-cc1a0abe">
            <span class="label label-primary classify-inline-e9e0e4af">{{ __('classification::texts.mapping_detail_level_classification', ['level' => $node->level]) }}</span>
            <h2 class="classify-inline-3ebe1a72">
                {{ $node->title_en }}
            </h2>
            <p class="text-muted classify-inline-d3e2f5c3">
                {{ __('classification::texts.mapping_detail_official_code') }} <code class="classify-inline-df67104f">{{ $node->code }}</code>
            </p>
        </div>

        <!-- Split Metadata & Hierarchy -->
        <div class="row">
            <div class="col-md-7">
                <!-- Definition Block -->
                @if($node->definition && $node->definition->definition_en)
                    <div class="well bg-white classify-inline-55a2e054">
                        <h4 class="classify-inline-2b3bd00f"><i class="fas fa-info-circle"></i> {{ __('classification::texts.mapping_detail_official_definition') }}</h4>
                        <p class="classify-inline-7525f7bb">
                            {{ $node->definition->definition_en }}
                        </p>
                    </div>
                @endif

                <!-- Synonym List -->
                @if($node->synonyms->count() > 0)
                    <div class="classify-inline-39ffb623">
                        <h4 class="classify-inline-13330f66"><i class="fas fa-tags"></i> {{ __('classification::texts.mapping_detail_recognized_synonyms') }}</h4>
                        <div class="classify-inline-54d32d80">
                            @foreach($node->synonyms as $synonym)
                                <span class="badge bg-gray classify-inline-c8a482f8">
                                    {{ $synonym->synonym }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-5">
                <!-- Contextual Hierarchy Panel -->
                <div id="context-hierarchy-panel" class="classify-inline-936da7ee">
                    <h4 class="classify-inline-e8aeb6f8">
                        <i class="fas fa-sitemap"></i> {{ __('classification::texts.mapping_detail_contextual_hierarchy') }}
                    </h4>
                    <div id="context-hierarchy-tree">
                        <div class="text-center classify-inline-3c9ee7ce">
                            <i class="fas fa-spinner fa-spin text-muted"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mapping Status Segment -->
         @include('gov-classification::search.partials.adoption-card')
    </div>

    <!-- ==============================================
         INLINE SLIDING MAPPING DRAWER
         ============================================== -->
    <div id="mapping-drawer" class="classify-inline-866b743e">
        
        <h3 class="classify-inline-aff159b4">
            <i class="fas fa-link text-blue"></i> {{ __('classification::texts.mapping_drawer_title') }}
        </h3>

        <!-- Manual Autocomplete Form -->
        <form id="mapping-submission-form">
            <div class="form-group classify-inline-39ffb623">
                <label class="classify-inline-44fc5a60">{{ __('classification::texts.mapping_drawer_search_label') }}</label>
                <!-- Standard Snipe-IT Select2 container -->
                <select class="form-control input-lg classify-inline-69d66e5b" id="snipe-category-select" required>
                    <option value="">{{ __('classification::texts.mapping_drawer_select_placeholder') }}</option>
                </select>
            </div>

            <div class="classify-inline-9155d9bd text-right">
                <button class="btn btn-default btn-lg classify-inline-31f575be" type="button" id="btn-close-drawer">{{ __('classification::texts.mapping_drawer_btn_cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-lg" id="btn-save-mapping">{{ __('classification::texts.mapping_drawer_btn_save') }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Include Phase 2 & Phase 3 Reusable Modals -->
@include('gov-classification::adopt.partials.bulk-preview')
@include('gov-classification::discover.partials.collection-modal')

<script>
document.addEventListener("DOMContentLoaded", function() {
    const drawer = jQuery('#mapping-drawer');
    const categorySelect = jQuery('#snipe-category-select');

    // Drawer Slide Open
    jQuery('#btn-trigger-mapping').on('click', function() {
        drawer.css('right', '0');
        
        // Initialize Snipe-IT's global Select2 library dynamically
        categorySelect.select2({
            dropdownParent: jQuery('#mapping-drawer'),
            ajax: {
                url: '{{ route("gov.catalog.snipe-categories.ajax") }}',
                dataType: 'json',
                delay: 250,
                processResults: function (data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            },
            minimumInputLength: 1
        });
    });

    // Drawer Slide Close
    jQuery('#btn-close-drawer').on('click', function() {
        drawer.css('right', '-105%');
    });

    // Form Submission Handler
    jQuery('#mapping-submission-form').on('submit', function(e) {
        e.preventDefault();
        const selectedId = categorySelect.val();
        const selectedName = categorySelect.find('option:selected').text();
        
        if (!selectedId) return;
        saveMapping(selectedId, selectedName);
    });

    // Save Linkage via AJAX
    function saveMapping(categoryId, categoryName) {
        jQuery('#btn-save-mapping').html('{{ __('classification::texts.mapping_drawer_saving') }}').prop('disabled', true);

        jQuery.ajax({
            url: '{{ route("gov.catalog.mapping.save") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                code: '{{ $node->code }}',
                category_id: categoryId
            },
            success: function(response) {
                // Close Drawer smoothly
                drawer.css('right', '-105%');
                window.location.reload();
            },
            error: function(xhr) {
                alert('{{ __('classification::texts.mapping_drawer_error_prefix') }}' + (xhr.responseJSON?.message || '{{ __('classification::texts.mapping_drawer_error_failed_save') }}'));
            },
            complete: function() {
                jQuery('#btn-save-mapping').html('{{ __('classification::texts.mapping_drawer_btn_save') }}').prop('disabled', false);
            }
        });
    }
});
</script>