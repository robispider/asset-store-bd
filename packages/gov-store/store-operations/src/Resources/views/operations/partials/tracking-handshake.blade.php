<script>
$(document).ready(function() {
    
    // Global AJAX setup to ensure CSRF is passed automatically on standard Web requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    const isDraft = {{ $isDraft ? 'true' : 'false' }};
    const userLocationId = '{{ $document->location_id ?? auth()->user()->location_id ?? 1 }}';
    let trackingCodeTimer = null;

    // --- HANDSHAKE A1: Header-Level Scope Verification ---
    $('#tracking_code_input').on('input change', function() {
        clearTimeout(trackingCodeTimer);
        let code = $(this).val().trim();
        let $feedback = $('#tracking_a1_feedback');

        if (code.length < 2) {
            $feedback.empty();
            if (isDraft) {
                $('#saveDraftBtn, #triggerPostBtn').removeAttr('disabled');
            }
            $('.tracking-advisory-banner').remove(); // Clear line item warnings
            return;
        }

        trackingCodeTimer = setTimeout(function() {
            $feedback.html('<div style="margin-top:10px; font-size: 12px; color: #854d0e;"><i class="fa fa-spinner fa-spin"></i> Verifying tracking code...</div>');

            $.ajax({
                url: '/gov-store/api/tracking/verify-code',
                type: 'GET',
                data: {
                    code: code,
                    location_id: userLocationId
                },
                success: function(res) {
                    if (res.can_proceed === false) {
                        // HARD BLOCK: Location out of scope
                        $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #fee2e2; border: 1px solid #ef4444; border-radius: 4px; color: #991b1b; font-size: 12.5px;"><i class="fa fa-ban"></i> <strong>BLOCKED:</strong> ${res.messages[0]}</div>`);
                        if (isDraft) {
                            $('#saveDraftBtn, #triggerPostBtn').attr('disabled', 'disabled');
                        }
                    } else {
                        // APPROVED: Clear to proceed
                        $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #d1fae5; border: 1px solid #10b981; border-radius: 4px; color: #065f46; font-size: 12.5px;"><i class="fa fa-check-circle"></i> <strong>VERIFIED:</strong> ${res.context.initiative}</div>`);
                        if (isDraft) {
                            $('#saveDraftBtn, #triggerPostBtn').removeAttr('disabled');
                        }
                        
                        // Trigger Line-Item Evaluations
                        evaluateGridItems(code);
                    }
                },
                error: function(xhr) {
                    if(xhr.status !== 404 && xhr.status !== 401) {
                        $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px; color: #b45309; font-size: 12.5px;"><i class="fa fa-warning"></i> Tracking Engine unreachable. Proceed with caution.</div>`);
                    } else {
                        $feedback.empty();
                    }
                    if (isDraft) {
                        $('#saveDraftBtn, #triggerPostBtn').removeAttr('disabled');
                    }
                }
            });
        }, 600);
    });

    // --- HANDSHAKE A2: Line-Item Level Evaluation (Advisory) ---
    function evaluateGridItems(code) {
        $('.item-row').each(function() {
            let $row = $(this);
            let categoryId = $row.attr('data-category-id'); // Read solved Category ID from data attribute
            let qty = parseInt($row.find('.qty-input').val()) || 0;
            let index = $row.data('index');

            if (!categoryId || qty <= 0) return;

            // Remove existing warnings for this row
            $(`#tracking_warning_${index}`).remove();

            $.ajax({
                url: '/gov-store/api/tracking/evaluate',
                type: 'GET',
                data: {
                    code: code,
                    location_id: userLocationId,
                    category_id: categoryId, // Strict category_id passing as defined in contract!
                    qty: qty
                },
                success: function(res) {
                    if (res.messages && res.messages.length > 0) {
                        let color = res.context.specificity_level === '3_MATRIX' ? '#f97316' : '#eab308';
                        let bg = res.context.specificity_level === '3_MATRIX' ? '#ffedd5' : '#fef9c3';

                        let banner = `<div id="tracking_warning_${index}" class="tracking-advisory-banner" style="background: ${bg}; border-left: 4px solid ${color}; padding: 8px 12px; margin-top: 8px; font-size: 12px; color: #78350f; border-radius: 0 4px 4px 0;"><i class="fa fa-warning"></i> ${res.messages[0]}</div>`;

                        // Append warning banner directly below the Select2 element
                        $row.find('.item-select').parent().append(banner);
                    }
                }
            });
        });
    }

    // FIXED: Bind evaluation to both text inputs, selects, and Select2 dynamic choices
    if (isDraft) {
        $('#gridBody').on('input change select2:select', '.qty-input, .item-select', function() {
            let code = $('#tracking_code_input').val().trim();
            if (code.length >= 2 && !$('#saveDraftBtn').is(':disabled')) {
                evaluateGridItems(code);
            }
        });
    }

    // Run verification on initial load if code exists
    if ($('#tracking_code_input').val().trim().length >= 2) {
        $('#tracking_code_input').trigger('change');
    }
});
</script>