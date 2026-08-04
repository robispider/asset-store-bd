<!-- Reusable Add to Collection Modal -->
<div class="modal fade" id="collectionMembershipModal" tabindex="-1" role="dialog" aria-labelledby="collectionModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-purple">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="collectionModalLabel">
                    <i class="fas fa-boxes"></i> Add Items to Collection
                </h4>
            </div>
            
            <div class="modal-body">
                <!-- Dropdown Selector -->
                <div id="collection-modal-content">
                    <p class="lead" style="font-size: 15px; margin-bottom: 15px;">
                        You have selected <strong id="collection-modal-count" class="text-purple">0</strong> items. Select the catalog collection you want to attach them to:
                    </p>
                    
                    <div class="form-group">
                        <label for="collection-select">Target Collection:</label>
                        <select id="collection-select" class="form-control" style="width: 100%;">
                            <option value="">-- Loading Collections... --</option>
                        </select>
                    </div>
                </div>

                <!-- API Loader -->
                <div id="collection-modal-loader" class="text-center" style="display: none; padding: 20px;">
                    <i class="fas fa-spinner fa-spin fa-2x text-purple"></i>
                    <p class="text-muted" style="margin-top: 10px;">Attaching items to collection...</p>
                </div>

                <!-- API Error Container -->
                <div id="collection-modal-error" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
            </div>

            <div class="modal-footer" style="background-color: #f9f9f9;">
                <button type="button" class="btn btn-default" data-dismiss="modal" id="btn-collection-cancel">Cancel</button>
                <button type="button" class="btn btn-purple" id="btn-collection-save" disabled>
                    <i class="fas fa-save"></i> Save to Collection
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let collectionCodesQueue = [];

    function triggerAddToCollection(codes) {
        collectionCodesQueue = Array.isArray(codes) ? codes : [codes];

        if (collectionCodesQueue.length === 0) {
            alert("No categories selected.");
            return;
        }

        // Reset UI States
        jQuery('#collection-modal-error').hide();
        jQuery('#collection-modal-loader').hide();
        jQuery('#collection-modal-content').show();
        jQuery('#btn-collection-save').prop('disabled', true);
        jQuery('#collection-modal-count').text(collectionCodesQueue.length);
        
        // Open Modal
        jQuery('#collectionMembershipModal').modal('show');

        // Dynamically load active collections
        jQuery.ajax({
            url: '{{ route("gov.catalog.discover.collections.api.list") }}',
            type: 'GET',
            success: function(res) {
                if (res.success && res.collections.length > 0) {
                    let options = '<option value="">-- Select a Collection --</option>';
                    res.collections.forEach(col => {
                        options += `<option value="${col.id}">${col.name}</option>`;
                    });
                    jQuery('#collection-select').html(options);
                    
                    if (window.jQuery && jQuery.fn.select2) {
                        jQuery('#collection-select').select2({
                            dropdownParent: jQuery('#collectionMembershipModal')
                        });
                    }
                } else {
                    jQuery('#collection-select').html('<option value="">No collections available. Contact admin.</option>');
                }
            },
            error: function() {
                jQuery('#collection-select').html('<option value="">Error loading collections.</option>');
            }
        });
    }

    // Delay interactive events until jQuery is ready
    document.addEventListener("DOMContentLoaded", function() {
        jQuery(document).on('change', '#collection-select', function() {
            if (jQuery(this).val()) {
                jQuery('#btn-collection-save').prop('disabled', false);
            } else {
                jQuery('#btn-collection-save').prop('disabled', true);
            }
        });

        jQuery('#btn-collection-save').on('click', function() {
            const targetCollectionId = jQuery('#collection-select').val();
            if (!targetCollectionId) return;

            const btn = jQuery(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            jQuery('#btn-collection-cancel').prop('disabled', true);
            jQuery('#collection-modal-content').hide();
            jQuery('#collection-modal-loader').show();

            jQuery.ajax({
                url: '{{ route("gov.catalog.discover.collections.api.add-nodes") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    collection_id: targetCollectionId,
                    codes: collectionCodesQueue
                },
                success: function(res) {
                    alert(`Successfully added ${res.added_count} items to the collection (Expanded into ${res.total_expanded} commodities).`);
                    jQuery('#collectionMembershipModal').modal('hide');
                    window.location.reload();
                },
                error: function(xhr) {
                    jQuery('#collection-modal-loader').hide();
                    jQuery('#collection-modal-content').show();
                    btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save to Collection');
                    jQuery('#btn-collection-cancel').prop('disabled', false);
                    
                    jQuery('#collection-modal-error')
                        .text('Error: ' + (xhr.responseJSON?.message || 'Failed to attach nodes.'))
                        .show();
                }
            });
        });
    });
</script>

<style>
    .bg-purple { background-color: #605ca8 !important; color: #fff !important; }
    .btn-purple { background-color: #605ca8; border-color: #555299; color: #fff; }
    .btn-purple:hover, .btn-purple:active, .btn-purple:focus { background-color: #555299; color: #fff; }
    .text-purple { color: #605ca8 !important; }
</style>