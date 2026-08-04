<!-- Bulk Adoption Modal -->
<div class="modal fade" id="bulkAdoptionModal" tabindex="-1" role="dialog" aria-labelledby="bulkAdoptionLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="bulkAdoptionLabel">
                    <i class="fas fa-layer-group"></i> Confirm Bulk Adoption
                </h4>
            </div>
            
            <div class="modal-body">
                <!-- Loader -->
                <div id="bulk-loader" class="text-center" style="padding: 40px;">
                    <i class="fas fa-spinner fa-spin fa-3x text-blue"></i>
                    <p class="text-muted" style="margin-top: 15px;">Analyzing catalog impact...</p>
                </div>

                <!-- Error Container -->
                <div id="bulk-error" class="alert alert-danger" style="display: none;"></div>

                <!-- Preview Content -->
                <div id="bulk-content" style="display: none;">
                    <p class="lead">You are adopting categories for your <strong><span id="bulk-target-scope">Office</span></strong>.</p>
                    
                    <div class="row text-center" style="margin-bottom: 20px;">
                        <div class="col-sm-4">
                            <div class="info-box bg-green">
                                <span class="info-box-icon"><i class="fas fa-plus-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">New (Create & Adopt)</span>
                                    <span class="info-box-number" id="count-new">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="info-box bg-aqua">
                                <span class="info-box-icon"><i class="fas fa-link"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Existing (Link Only)</span>
                                    <span class="info-box-number" id="count-link">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="info-box bg-gray">
                                <span class="info-box-icon"><i class="fas fa-forward"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Skipped (Already Adopted)</span>
                                    <span class="info-box-number" id="count-skipped">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #eee;">
                        <table class="table table-striped table-condensed" style="margin-bottom: 0;">
                            <tbody id="bulk-details-list">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background-color: #f9f9f9;">
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

    /**
     * Call this function from anywhere in the app to trigger a bulk adoption preview.
     * @param {Array} codes - Array of UNSPSC codes (e.g. ['41112201', '41112204'])
     */
    function triggerBulkAdoption(codes) {
        if (!codes || codes.length === 0) {
            alert("No categories selected.");
            return;
        }

        bulkCodesQueue = codes;
        
        // Reset UI
        $('#bulk-content, #bulk-error').hide();
        $('#bulk-loader').show();
        $('#btn-bulk-execute').prop('disabled', true).html('<i class="fas fa-rocket"></i> Execute Bulk Adoption');
        $('#bulkAdoptionModal').modal('show');

        // Fetch Preview
        $.ajax({
            url: '{{ route("gov.catalog.bulk.preview") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', codes: bulkCodesQueue },
            success: function(res) {
                $('#bulk-loader').hide();
                $('#bulk-target-scope').text(res.target_scope);
                
                $('#count-new').text(res.summary.new.length);
                $('#count-link').text(res.summary.link.length);
                $('#count-skipped').text(res.summary.skipped.length);

                let listHtml = '';
                
                res.summary.new.forEach(i => {
                    listHtml += `<tr><td><span class="text-success"><i class="fas fa-plus"></i> Create</span></td><td><code>${i.code}</code></td><td>${i.title}</td></tr>`;
                });
                res.summary.link.forEach(i => {
                    listHtml += `<tr><td><span class="text-info"><i class="fas fa-link"></i> Link</span></td><td><code>${i.code}</code></td><td>${i.title}</td></tr>`;
                });
                res.summary.skipped.forEach(i => {
                    listHtml += `<tr><td><span class="text-muted"><i class="fas fa-forward"></i> Skip</span></td><td><code>${i.code}</code></td><td class="text-muted">${i.title}</td></tr>`;
                });

                $('#bulk-details-list').html(listHtml);
                $('#bulk-content').show();

                if (res.summary.new.length > 0 || res.summary.link.length > 0) {
                    $('#btn-bulk-execute').prop('disabled', false);
                }
            },
            error: function(xhr) {
                $('#bulk-loader').hide();
                $('#bulk-error').text('Error: ' + (xhr.responseJSON?.message || 'Failed to load preview.')).show();
            }
        });
    }

    // Handle Execution
    $('#btn-bulk-execute').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        $('#btn-bulk-cancel').prop('disabled', true);

        $.ajax({
            url: '{{ route("gov.catalog.bulk.execute") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', codes: bulkCodesQueue },
            success: function(res) {
                alert(`Success! Adopted ${res.processed_count} categories.`);
                $('#bulkAdoptionModal').modal('hide');
                // Reload the page or fire a JS event so the parent page updates
                window.location.reload(); 
            },
            error: function(xhr) {
                alert('Execution Error: ' + (xhr.responseJSON?.message || 'Transaction failed.'));
                btn.prop('disabled', false).html('<i class="fas fa-rocket"></i> Execute Bulk Adoption');
                $('#btn-bulk-cancel').prop('disabled', false);
            }
        });
    });
</script>