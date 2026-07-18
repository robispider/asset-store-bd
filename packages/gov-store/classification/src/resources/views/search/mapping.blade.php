<div class="detail-sheet" style="position: relative; overflow: hidden; min-height: 600px;">
    
    <!-- Main Node Details Workspace -->
    <div id="details-main-content">
        <!-- Header -->
        <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
            <span class="label label-primary" style="font-size: 11px;">{{ __('classification::texts.mapping_detail_level_classification', ['level' => $node->level]) }}</span>
            <h2 style="margin-top: 10px; margin-bottom: 5px; font-weight: bold; color: #333;">
                {{ $node->title_en }}
            </h2>
            <p class="text-muted" style="font-size: 14px; margin-bottom: 0;">
                {{ __('classification::texts.mapping_detail_official_code') }} <code style="font-size: 14px;">{{ $node->code }}</code>
            </p>
        </div>

        <!-- Split Metadata & Hierarchy -->
        <div class="row">
            <div class="col-md-7">
                <!-- Definition Block -->
                @if($node->definition && $node->definition->definition_en)
                    <div class="well bg-white" style="background-color: #fff; border-left: 3px solid #00c0ef; margin-bottom: 25px;">
                        <h4 style="margin-top: 0; font-weight: bold; color: #00c0ef;"><i class="fas fa-info-circle"></i> {{ __('classification::texts.mapping_detail_official_definition') }}</h4>
                        <p style="font-size: 13.5px; line-height: 1.6; color: #555; margin-bottom: 0;">
                            {{ $node->definition->definition_en }}
                        </p>
                    </div>
                @endif

                <!-- Synonym List -->
                @if($node->synonyms->count() > 0)
                    <div style="margin-bottom: 25px;">
                        <h4 style="font-weight: bold; color: #333;"><i class="fas fa-tags"></i> {{ __('classification::texts.mapping_detail_recognized_synonyms') }}</h4>
                        <div style="margin-top: 10px;">
                            @foreach($node->synonyms as $synonym)
                                <span class="badge bg-gray" style="font-size: 12px; font-weight: normal; margin-right: 5px; margin-bottom: 5px; padding: 5px 10px; color: #444;">
                                    {{ $synonym->synonym }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-5">
                <!-- Contextual Hierarchy Panel -->
                <div id="context-hierarchy-panel" style="background: #f9fafb; padding: 15px; border-radius: 4px; border: 1px solid #eee; min-height: 200px;">
                    <h4 style="margin-top: 0; font-weight: bold; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 10px;">
                        <i class="fas fa-sitemap"></i> {{ __('classification::texts.mapping_detail_contextual_hierarchy') }}
                    </h4>
                    <div id="context-hierarchy-tree">
                        <div class="text-center" style="padding: 20px 0;">
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
    <div id="mapping-drawer" style="position: absolute; right: -105%; top: 0; width: 100%; height: 100%; background: #ffffff; padding: 25px 20px; box-shadow: -10px 0 25px rgba(0,0,0,0.08); transition: right 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); z-index: 100; border-radius: 4px; border-left: 1px solid #eee;">
        
        <h3 style="margin-top: 0; font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 25px;">
            <i class="fas fa-link text-blue"></i> {{ __('classification::texts.mapping_drawer_title') }}
        </h3>

       

        <!-- Manual Autocomplete Form -->
        <form id="mapping-submission-form">
            <div class="form-group" style="margin-bottom: 25px;">
                <label style="font-size: 14px; margin-bottom: 8px;">{{ __('classification::texts.mapping_drawer_search_label') }}</label>
                <!-- Standard Snipe-IT Select2 container -->
                <select id="snipe-category-select" class="form-control input-lg" style="width: 100%;" required>
                    <option value="">{{ __('classification::texts.mapping_drawer_select_placeholder') }}</option>
                </select>
            </div>

            <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 40px;" class="text-right">
                <button type="button" class="btn btn-default btn-lg" id="btn-close-drawer" style="margin-right: 10px;">{{ __('classification::texts.mapping_drawer_btn_cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-lg" id="btn-save-mapping">{{ __('classification::texts.mapping_drawer_btn_save') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    const drawer = $('#mapping-drawer');
    const categorySelect = $('#snipe-category-select');

    // Drawer Slide Open
    $('#btn-trigger-mapping').on('click', function() {
        drawer.css('right', '0');
        
        // Initialize Snipe-IT's global Select2 library dynamically
        categorySelect.select2({
            dropdownParent: $('#mapping-drawer'),
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
    $('#btn-close-drawer').on('click', function() {
        drawer.css('right', '-105%');
    });

  

    // Form Submission Handler
    $('#mapping-submission-form').on('submit', function(e) {
        e.preventDefault();
        const selectedId = categorySelect.val();
        const selectedName = categorySelect.find('option:selected').text();
        
        if (!selectedId) return;
        saveMapping(selectedId, selectedName);
    });

    // Save Linkage via AJAX
    function saveMapping(categoryId, categoryName) {
        $('#btn-save-mapping').html('{{ __('classification::texts.mapping_drawer_saving') }}').prop('disabled', true);

        $.ajax({
            url: '{{ route("gov.catalog.mapping.save") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                code: '{{ $node->code }}',
                category_id: categoryId
            },
            success: function(response) {
                // Instantly update the parent Details Column status box
                $('#status-card-box').removeClass('box-warning').addClass('box-success');
                $('#status-title-text').html('<i class="fas fa-check-circle text-success"></i> {{ __('classification::texts.mapping_drawer_success_title') }}');
                $('#status-desc-text').html('This node is mapped to Snipe-IT Category: <strong class="text-green">' + categoryName + '</strong>');
                $('#btn-trigger-mapping').removeClass('btn-warning').addClass('btn-default').html('<i class="fas fa-link"></i> {{ __('classification::texts.mapping_drawer_change_link') }}');

                // Close Drawer smoothly
                drawer.css('right', '-105%');
                
                // Update the corresponding Left Results Card mapping label inline
                $(`.catalog-result-item[data-code="{{ $node->code }}"] .far.fa-circle`)
                    .removeClass('far fa-circle')
                    .addClass('fas fa-check-circle text-success')
                    .parent().html('<span class="text-success"><i class="fas fa-check-circle"></i> Mapped</span>');
            },
            error: function(xhr) {
                alert('{{ __('classification::texts.mapping_drawer_error_prefix') }}' + (xhr.responseJSON?.message || '{{ __('classification::texts.mapping_drawer_error_failed_save') }}'));
            },
            complete: function() {
                $('#btn-save-mapping').html('{{ __('classification::texts.mapping_drawer_btn_save') }}').prop('disabled', false);
            }
        });
    }
});
</script>