<div class="box box-solid">
    <div class="box-header with-border"><h3 class="box-title text-aqua">1. Task Identity</h3></div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-4 form-group has-feedback" id="tracking-code-group">
                <label>Tracking Code (Used in GRN)</label>
                @if(isset($trackingCode))
                    <input type="text" class="form-control input-lg" value="{{ $trackingCode->tracking_code }}" disabled>
                    <input type="hidden" name="tracking_code" value="{{ $trackingCode->tracking_code }}">
                @else
                    <input type="text" name="tracking_code" id="tracking_code_input" class="form-control input-lg" value="{{ old('tracking_code') }}" placeholder="e.g. ICT-2027-01" required autocomplete="off">
                    <span class="fa form-control-feedback" id="tracking-code-icon" style="top: 25px; font-size: 20px; line-height: 45px;"></span>
                @endif
                <p class="help-block text-sm" id="tracking-code-help">Storekeepers type this exact code to authorize receipts.</p>
            </div>
            
            <div class="col-md-8 form-group">
                <label>Task / Component Title</label>
                <input type="text" name="task_title" class="form-control input-lg" value="{{ old('task_title', $trackingCode?->task_title) }}" placeholder="e.g. Supply of Equipment for Sylhet Zone" required>
            </div>
        </div>
        
        @if(!isset($trackingCode))
            <div class="form-group">
                <label>Official Government Order / Memo (PDF)</label>
                <input type="file" name="order_pdf" class="form-control" accept="application/pdf" {{ $initiative->require_documents ? 'required' : '' }}>
                @if($initiative->require_documents)
                    <p class="help-block text-red"><i class="fa fa-exclamation-circle"></i> Official document upload is strictly required for this Initiative.</p>
                @endif
            </div>
        @endif
    </div>
</div>

@if(!isset($trackingCode))
<!-- Safe Polling Script Engine: Guarantees jQuery is loaded before execution -->
<script>
    (function() {
        function initUniquenessChecker() {
            // If jQuery is not loaded yet, wait 50ms and check again
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initUniquenessChecker, 50);
                return;
            }

            // jQuery is loaded, execute safely mapping $
            window.jQuery(function($) {
                var $input = $('#tracking_code_input');
                var $group = $('#tracking-code-group');
                var $icon = $('#tracking-code-icon');
                var $help = $('#tracking-code-help');
                var xhr = null;
                var debounceTimer = null;

                $input.on('input', function() {
                    var codeVal = $(this).val().trim();

                    clearTimeout(debounceTimer);
                    if (xhr) xhr.abort();

                    if (codeVal === '') {
                        resetFeedback();
                        return;
                    }

                    debounceTimer = setTimeout(function() {
                        $group.removeClass('has-success has-error');
                        $icon.removeClass('fa-check fa-remove text-green text-red').addClass('fa-spinner fa-spin text-muted');
                        $help.text('Evaluating database records for code availability...');

                        xhr = $.ajax({
                            url: "{{ route('gov.tracking.tracking-codes.check-uniqueness') }}",
                            method: 'GET',
                            data: { code: codeVal },
                            success: function(response) {
                                $icon.removeClass('fa-spinner fa-spin text-muted');
                                
                                if (response.is_unique) {
                                    $group.addClass('has-success').removeClass('has-error');
                                    $icon.addClass('fa-check text-green');
                                    $help.html('<span class="text-green"><strong>✔ Acceptable:</strong> This tracking code is available for use.</span>');
                                } else {
                                    $group.addClass('has-error').removeClass('has-success');
                                    $icon.addClass('fa-remove text-red');
                                    $help.html('<span class="text-red"><strong>✖ Unavailable:</strong> This tracking code is already in use by another task.</span>');
                                }
                            },
                            error: function(err) {
                                if (err.statusText !== 'abort') {
                                    resetFeedback();
                                }
                            }
                        });
                    }, 350);
                });

                function resetFeedback() {
                    $group.removeClass('has-success has-error');
                    $icon.removeClass('fa-check fa-remove fa-spinner fa-spin text-green text-red text-muted');
                    $help.text('Storekeepers type this exact code to authorize receipts.');
                }
            });
        }

        // Initialize polling
        initUniquenessChecker();
    })();
</script>
@endif