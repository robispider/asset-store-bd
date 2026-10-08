<!-- Bulk Adoption Modal -->
<div class="modal fade" id="bulkAdoptionModal" tabindex="-1" role="dialog" aria-labelledby="bulkAdoptionLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <button class="close classify-inline-0d75823b" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="bulkAdoptionLabel">
                    <i class="fas fa-layer-group"></i> Confirm Bulk Adoption
                </h4>
            </div>
            
            <div class="modal-body">
                <!-- Loader -->
                <div class="text-center classify-inline-73fc9c37" id="bulk-loader">
                    <i class="fas fa-spinner fa-spin fa-3x text-blue"></i>
                    <p class="text-muted classify-inline-b62ee557">Analyzing catalog impact...</p>
                </div>

                <!-- Error Container -->
                <div class="alert alert-danger classify-inline-f2892a2e" id="bulk-error"></div>

                <!-- Preview Content -->
                <div id="bulk-content" class="classify-inline-f2892a2e">
                    <p class="lead">You are adopting categories for your <strong><span id="bulk-target-scope">Office</span></strong>.</p>
                    
                    <!-- Global Actions Header -->
                    <div class="well well-sm classify-inline-cfba63d0">
                        <div class="row">
                            <div class="col-sm-8 classify-inline-66e4501a">
                                <strong class="text-blue"><i class="fas fa-sliders-h"></i> Bulk Category Configurator</strong>
                            </div>
                            <div class="col-sm-4 text-right">
                                <div class="input-group">
                                    <select class="form-control input-sm classify-inline-0038473a" id="bulk-global-type-select">
                                        <option value="asset">Asset</option>
                                        <option value="consumable" selected>Consumable</option>
                                        <option value="accessory">Accessory</option>
                                        <option value="component">Component</option>
                                        <option value="license">License</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button class="btn btn-primary btn-sm" type="button" onclick="applyGlobalCategoryType()">Apply to Checked</button>
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
                                    <th class="classify-inline-ccddc627"><input type="checkbox" id="bulk-modal-select-all" checked></th>
                                    <th class="classify-inline-807e5119">Action</th>
                                    <th>Code</th>
                                    <th>Title</th>
                                    <th class="classify-inline-85066813">Category Type Allocation</th>
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
                <button type="button" class="btn btn-default" data-dismiss="modal" id="btn-bulk-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-bulk-execute" disabled>
                    <i class="fas fa-rocket"></i> Execute Bulk Adoption
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let bulkCodesQueue = [];

    function triggerBulkAdoption(codes) {
        if (!codes || codes.length === 0) {
            alert("No categories selected.");
            return;
        }

        bulkCodesQueue = codes;
        
        jQuery('#bulk-content, #bulk-error').hide();
        jQuery('#bulk-loader').show();
        jQuery('#btn-bulk-execute').prop('disabled', true).html('<i class="fas fa-rocket"></i> Execute Bulk Adoption');
        jQuery('#bulkAdoptionModal').modal('show');

        // Fetch Preview Analytics
        jQuery.ajax({
            url: '{{ route("gov.catalog.bulk.preview") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', codes: bulkCodesQueue },
            success: function(res) {
                jQuery('#bulk-loader').hide();
                jQuery('#bulk-target-scope').text(res.target_scope);
                
                let listHtml = '';
                
                // 1. Process "New" items (Dropdown is enabled)
                res.summary.new.forEach(i => {
                    listHtml += `
                    <tr class="bulk-preview-row" data-code="${i.code}">
                        <td class="text-center classify-inline-da57c5e8"><input type="checkbox" class="bulk-item-checkbox" data-code="${i.code}" checked></td>
                        <td class="classify-inline-da57c5e8"><span class="label label-success"><i class="fas fa-plus"></i> Create</span></td>
                        <td class="classify-inline-da57c5e8"><code>${i.code}</code></td>
                        <td class="classify-inline-da57c5e8">${i.title}</td>
                        <td class="classify-inline-da57c5e8">
                            <select class="form-control input-sm row-category-type" data-code="${i.code}">
                                <option value="asset">Asset</option>
                                <option value="consumable" selected>Consumable</option>
                                <option value="accessory">Accessory</option>
                                <option value="component">Component</option>
                                <option value="license">License</option>
                            </select>
                        </td>
                    </tr>`;
                });

                // 2. Process "Link" items (Pre-existing type is preserved and dropdown is disabled)
                res.summary.link.forEach(i => {
                    listHtml += `
                    <tr class="bulk-preview-row" data-code="${i.code}">
                        <td class="text-center classify-inline-da57c5e8"><input type="checkbox" class="bulk-item-checkbox" data-code="${i.code}" checked></td>
                        <td class="classify-inline-da57c5e8"><span class="label label-info"><i class="fas fa-link"></i> Link</span></td>
                        <td class="classify-inline-da57c5e8"><code>${i.code}</code></td>
                        <td class="classify-inline-da57c5e8">${i.title}</td>
                        <td class="classify-inline-da57c5e8">
                            <select class="form-control input-sm row-category-type" data-code="${i.code}" disabled>
                                <option value="asset" ${i.category_type === 'asset' ? 'selected' : ''}>Asset</option>
                                <option value="consumable" ${i.category_type === 'consumable' ? 'selected' : ''}>Consumable</option>
                                <option value="accessory" ${i.category_type === 'accessory' ? 'selected' : ''}>Accessory</option>
                                <option value="component" ${i.category_type === 'component' ? 'selected' : ''}>Component</option>
                                <option value="license" ${i.category_type === 'license' ? 'selected' : ''}>License</option>
                            </select>
                        </td>
                    </tr>`;
                });

                // 3. Process "Skipped" items (Deselected and disabled)
                res.summary.skipped.forEach(i => {
                    listHtml += `
                    <tr class="bulk-preview-row text-muted" data-code="${i.code}">
                        <td class="text-center classify-inline-da57c5e8"><input type="checkbox" class="bulk-item-checkbox" data-code="${i.code}" disabled></td>
                        <td class="classify-inline-da57c5e8"><span class="label label-default"><i class="fas fa-forward"></i> Skip</span></td>
                        <td class="classify-inline-da57c5e8"><code>${i.code}</code></td>
                        <td class="classify-inline-da57c5e8 text-muted">${i.title}</td>
                        <td class="classify-inline-da57c5e8">
                            <select class="form-control input-sm row-category-type" data-code="${i.code}" disabled>
                                <option value="consumable" selected>Consumable</option>
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
                jQuery('#bulk-error').text('Error: ' + (xhr.responseJSON?.message || 'Failed to load preview.')).show();
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
                    code: code,
                    category_type: selectedType
                });
            });

            if (itemsToExecute.length === 0) {
                alert("Please select at least one item to adopt.");
                return;
            }

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            jQuery('#btn-bulk-cancel').prop('disabled', true);

            jQuery.ajax({
                url: '{{ route("gov.catalog.bulk.execute") }}',
                type: 'POST',
                data: { 
                    _token: '{{ csrf_token() }}', 
                    items: itemsToExecute // Passes array of objects (code + category_type)
                },
                success: function(res) {
                    alert(`Success! Bulk operation processed ${res.processed_count} categories.`);
                    jQuery('#bulkAdoptionModal').modal('hide');
                    window.location.reload(); 
                },
                error: function(xhr) {
                    alert('Execution Error: ' + (xhr.responseJSON?.message || 'Transaction failed.'));
                    btn.prop('disabled', false).html('<i class="fas fa-rocket"></i> Execute Bulk Adoption');
                    jQuery('#btn-bulk-cancel').prop('disabled', false);
                }
            });
        });
    });
</script>