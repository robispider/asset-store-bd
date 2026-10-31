<!-- Bulk Adoption Modal -->
<div class="modal fade" id="bulkAdoptionModal" tabindex="-1" role="dialog" aria-labelledby="bulkAdoptionLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <button class="close classify-inline-0d75823b" type="button" data-dismiss="modal" aria-label="{{ __('classification::collections.bulk.close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="bulkAdoptionLabel">
                    <i class="fas fa-layer-group"></i> {{ __('classification::collections.bulk.title') }}
                </h4>
            </div>
            
            <div class="modal-body">
                <!-- Loader -->
                <div class="text-center classify-inline-73fc9c37" id="bulk-loader">
                    <i class="fas fa-spinner fa-spin fa-3x text-blue"></i>
                    <p class="text-muted classify-inline-b62ee557">{{ __('classification::collections.bulk.loading') }}</p>
                </div>

                <!-- Error Container -->
                <div class="alert alert-danger classify-inline-f2892a2e" id="bulk-error"></div>

                <!-- Preview Content -->
                <div id="bulk-content" class="classify-inline-f2892a2e">
                    <p class="lead">{{ __('classification::collections.bulk.intro') }} <strong><span id="bulk-target-scope">{{ __('classification::collections.bulk.office') }}</span></strong></p>
                    
                    <!-- Global Actions Header -->
                    <div class="well well-sm classify-inline-cfba63d0">
                        <div class="row">
                            <div class="col-sm-8 classify-inline-66e4501a">
                                <strong class="text-blue"><i class="fas fa-sliders-h"></i> {{ __('classification::collections.bulk.configure') }}</strong>
                            </div>
                            <div class="col-sm-4 text-right">
                                <div class="input-group">
                                    <select class="form-control input-sm classify-inline-0038473a" id="bulk-global-type-select">
                                        <option value="asset">{{ __('classification::collections.types.asset') }}</option>
                                        <option value="consumable" selected>{{ __('classification::collections.types.consumable') }}</option>
                                        <option value="accessory">{{ __('classification::collections.types.accessory') }}</option>
                                        <option value="component">{{ __('classification::collections.types.component') }}</option>
                                        <option value="license">{{ __('classification::collections.types.license') }}</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button class="btn btn-primary btn-sm" type="button" onclick="applyGlobalCategoryType()">{{ __('classification::collections.bulk.apply') }}</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Layout List Table -->
                    <div class="classify-inline-244c3b92">
                        <x-gs::table class="table table-striped table-hover table-bordered classify-inline-648149ce">
                            <thead class="classify-inline-8b2a777e">
                                <tr>
                                    <th class="classify-inline-ccddc627"><input type="checkbox" id="bulk-modal-select-all" aria-label="{{ __('classification::collections.bulk.select_all') }}" checked></th>
                                    <th class="classify-inline-807e5119">{{ __('classification::collections.bulk.action') }}</th>
                                    <th>{{ __('classification::collections.code') }}</th>
                                    <th>{{ __('classification::collections.item') }}</th>
                                    <th class="classify-inline-85066813">{{ __('classification::collections.bulk.configure') }}</th>
                                </tr>
                            </thead>
                            <tbody id="bulk-details-list">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </x-gs::table>
                    </div>
                </div>
            </div>

            <div class="modal-footer classify-inline-0e5be659">
                <button type="button" class="btn btn-default" data-dismiss="modal" id="btn-bulk-cancel">{{ __('classification::collections.bulk.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="btn-bulk-execute" disabled>
                    <i class="fas fa-rocket"></i> {{ __('classification::collections.bulk.execute') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const bulkLabels = @json(__('classification::collections.bulk'));
    const bulkTypeLabels = @json(__('classification::collections.types'));
    let bulkCodesQueue = [];

    function escapeBulkHtml(value) {
        return String(value).replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
    }

    function triggerBulkAdoption(codes, collectionId = null) {
        if (!codes || codes.length === 0) {
            alert(bulkLabels.empty);
            return;
        }

        bulkCodesQueue = codes;
        
        jQuery('#bulk-content, #bulk-error').hide();
        jQuery('#bulk-loader').show();
        jQuery('#btn-bulk-execute').prop('disabled', true).html('<i class="fas fa-rocket"></i> ' + escapeBulkHtml(bulkLabels.execute));
        jQuery('#bulkAdoptionModal').modal('show');

        // Fetch Preview Analytics
        jQuery.ajax({
            url: '{{ route("gov.catalog.bulk.preview") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', codes: bulkCodesQueue, collection_id: collectionId },
            success: function(res) {
                jQuery('#bulk-loader').hide();
                jQuery('#bulk-target-scope').text(res.target_scope);
                
                let listHtml = '';
                
                // 1. Process "New" items (Dropdown is enabled)
                res.summary.new.forEach(i => {
                    listHtml += `
                    <tr class="bulk-preview-row" data-code="${escapeBulkHtml(i.code)}">
                        <td class="text-center classify-inline-da57c5e8"><input type="checkbox" class="bulk-item-checkbox" data-code="${escapeBulkHtml(i.code)}" checked></td>
                        <td class="classify-inline-da57c5e8"><span class="label label-success"><i class="fas fa-plus"></i> ${escapeBulkHtml(bulkLabels.create)}</span></td>
                        <td class="classify-inline-da57c5e8"><code>${escapeBulkHtml(i.code)}</code></td>
                        <td class="classify-inline-da57c5e8">${escapeBulkHtml(i.title)}</td>
                        <td class="classify-inline-da57c5e8">
                            <select class="form-control input-sm row-category-type" data-code="${escapeBulkHtml(i.code)}" aria-label="${escapeBulkHtml(i.title)}">
                                <option value="asset" ${i.category_type === 'asset' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.asset)}</option>
                                <option value="consumable" ${i.category_type === 'consumable' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.consumable)}</option>
                                <option value="accessory" ${i.category_type === 'accessory' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.accessory)}</option>
                                <option value="component" ${i.category_type === 'component' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.component)}</option>
                                <option value="license" ${i.category_type === 'license' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.license)}</option>
                            </select>
                        </td>
                    </tr>`;
                });

                // 2. Process "Link" items (Pre-existing type is preserved and dropdown is disabled)
                res.summary.link.forEach(i => {
                    listHtml += `
                    <tr class="bulk-preview-row" data-code="${escapeBulkHtml(i.code)}">
                        <td class="text-center classify-inline-da57c5e8"><input type="checkbox" class="bulk-item-checkbox" data-code="${escapeBulkHtml(i.code)}" checked></td>
                        <td class="classify-inline-da57c5e8"><span class="label label-info"><i class="fas fa-link"></i> ${escapeBulkHtml(bulkLabels.link)}</span></td>
                        <td class="classify-inline-da57c5e8"><code>${escapeBulkHtml(i.code)}</code></td>
                        <td class="classify-inline-da57c5e8">${escapeBulkHtml(i.title)}</td>
                        <td class="classify-inline-da57c5e8">
                            <select class="form-control input-sm row-category-type" data-code="${escapeBulkHtml(i.code)}" aria-label="${escapeBulkHtml(i.title)}" disabled>
                                <option value="asset" ${i.category_type === 'asset' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.asset)}</option>
                                <option value="consumable" ${i.category_type === 'consumable' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.consumable)}</option>
                                <option value="accessory" ${i.category_type === 'accessory' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.accessory)}</option>
                                <option value="component" ${i.category_type === 'component' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.component)}</option>
                                <option value="license" ${i.category_type === 'license' ? 'selected' : ''}>${escapeBulkHtml(bulkTypeLabels.license)}</option>
                            </select>
                        </td>
                    </tr>`;
                });

                // 3. Process "Skipped" items (Deselected and disabled)
                res.summary.skipped.forEach(i => {
                    listHtml += `
                    <tr class="bulk-preview-row text-muted" data-code="${escapeBulkHtml(i.code)}">
                        <td class="text-center classify-inline-da57c5e8"><input type="checkbox" class="bulk-item-checkbox" data-code="${escapeBulkHtml(i.code)}" disabled></td>
                        <td class="classify-inline-da57c5e8"><span class="label label-default"><i class="fas fa-forward"></i> ${escapeBulkHtml(bulkLabels.skip)}</span></td>
                        <td class="classify-inline-da57c5e8"><code>${escapeBulkHtml(i.code)}</code></td>
                        <td class="classify-inline-da57c5e8 text-muted">${escapeBulkHtml(i.title)}</td>
                        <td class="classify-inline-da57c5e8">
                            <select class="form-control input-sm row-category-type" data-code="${escapeBulkHtml(i.code)}" aria-label="${escapeBulkHtml(i.title)}" disabled>
                                <option value="${escapeBulkHtml(i.category_type)}" selected>${escapeBulkHtml(bulkTypeLabels[i.category_type])}</option>
                            </select>
                        </td>
                    </tr>`;
                });

                jQuery('#bulk-details-list').html(listHtml);
                jQuery('#bulk-content').show();

                updateExecutionButtonState();
            },
            error: function(xhr) {
                jQuery('#bulk-loader').hide();
                jQuery('#bulk-error').text(xhr.responseJSON?.message || bulkLabels.failed_preview).show();
            }
        });
    }

    /**
     * Bulk Action: Changes category type dropdown for all CHECKED rows
     */
    function applyGlobalCategoryType() {
        const selectedGlobalType = jQuery('#bulk-global-type-select').val();
        
        // Loop through all currently selected rows and update their dropdown values
        jQuery('.bulk-item-checkbox:checked').each(function() {
            const code = jQuery(this).data('code');
            const typeDropdown = jQuery(`.row-category-type[data-code="${code}"]`);
            
            // Only modify dropdowns that are not disabled (e.g. modify new creations only)
            if (!typeDropdown.prop('disabled')) {
                typeDropdown.val(selectedGlobalType);
            }
        });
    }

    /**
     * Determines whether the execution button should be clickable based on checkbox states
     */
    function updateExecutionButtonState() {
        const checkedCount = jQuery('.bulk-item-checkbox:checked').length;
        if (checkedCount > 0) {
            jQuery('#btn-bulk-execute').prop('disabled', false);
        } else {
            jQuery('#btn-bulk-execute').prop('disabled', true);
        }
    }

    // Delay interactive events until jQuery is ready
    document.addEventListener("DOMContentLoaded", function() {
        // Modal Select All Listener
        jQuery(document).on('change', '#bulk-modal-select-all', function() {
            // Check only the checkboxes that are NOT disabled
            jQuery('.bulk-item-checkbox:not(:disabled)').prop('checked', jQuery(this).prop('checked'));
            updateExecutionButtonState();
        });

        // Individual modal checkbox change listener
        jQuery(document).on('change', '.bulk-item-checkbox', function() {
            updateExecutionButtonState();
        });

        // Handle transaction execution
        jQuery('#btn-bulk-execute').on('click', function() {
            const btn = jQuery(this);
            
            // Gather all items that have been CHECKED by the user, and read their respective allocated types
            let itemsToExecute = [];
            jQuery('.bulk-item-checkbox:checked').each(function() {
                const code = jQuery(this).data('code');
                const selectedType = jQuery(`.row-category-type[data-code="${code}"]`).val();
                
                itemsToExecute.push({
                    code: String(code),
                    category_type: selectedType
                });
            });

            if (itemsToExecute.length === 0) {
                alert(bulkLabels.choose);
                return;
            }

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + escapeBulkHtml(bulkLabels.processing));
            jQuery('#btn-bulk-cancel').prop('disabled', true);

            jQuery.ajax({
                url: '{{ route("gov.catalog.bulk.execute") }}',
                type: 'POST',
                // Large collections exceed PHP's form-field limit; JSON preserves the complete selection.
                contentType: 'application/json; charset=UTF-8',
                data: JSON.stringify({
                    _token: '{{ csrf_token() }}',
                    items: itemsToExecute
                }),
                success: function(res) {
                    alert(bulkLabels.success.replace(':count', res.processed_count));
                    jQuery('#bulkAdoptionModal').modal('hide');
                    window.location.reload(); 
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.message || bulkLabels.failed_execute);
                    btn.prop('disabled', false).html('<i class="fas fa-rocket"></i> ' + escapeBulkHtml(bulkLabels.execute));
                    jQuery('#btn-bulk-cancel').prop('disabled', false);
                }
            });
        });
    });
</script>
