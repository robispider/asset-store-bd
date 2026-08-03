<script>
$(document).ready(function() {
    
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

            console.log("====== [A1 REQUEST: VERIFY CODE] ======");
            console.log("Code:", code);
            console.log("Location ID:", userLocationId);
            console.log("=======================================");

            $.ajax({
                url: '/gov-store/api/tracking/verify-code',
                type: 'GET',
                data: {
                    code: code,
                    location_id: userLocationId
                },
                success: function(res) {
                    console.log("====== [A1 RESPONSE: SUCCESS] ======");
                    console.log("Payload:", res);
                    console.log("=====================================");

                    if (res.can_proceed === false) {
                        let errorMsg = (res.messages && res.messages.length > 0) ? res.messages[0] : 'Scope validation failed.';
                        $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #fee2e2; border: 1px solid #ef4444; border-radius: 4px; color: #991b1b; font-size: 12.5px;"><i class="fa fa-ban"></i> <strong>BLOCKED:</strong> ${errorMsg}</div>`);
                        if (isDraft) {
                            $('#saveDraftBtn, #triggerPostBtn').attr('disabled', 'disabled');
                        }
                    } else {
                        let initiativeName = (res.context && res.context.initiative) ? res.context.initiative : 'Project Validated';
                        
                        $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #d1fae5; border: 1px solid #10b981; border-radius: 4px; color: #065f46; font-size: 12.5px;"><i class="fa fa-check-circle"></i> <strong>VERIFIED:</strong> ${initiativeName}</div>`);
                        if (isDraft) {
                            $(':button[id="saveDraftBtn"], :button[id="triggerPostBtn"]').removeAttr('disabled');
                        }
                        
                        evaluateGridItems(code);
                    }
                },
                error: function(xhr) {
                    console.error("====== [A1 RESPONSE: FAILED] ======");
                    console.error("HTTP Status:", xhr.status);
                    console.error("Payload:", xhr.responseJSON || xhr.responseText);
                    console.error("====================================");

                    if (xhr.status === 403 && xhr.responseJSON) {
                        let blockMsg = (xhr.responseJSON.messages && xhr.responseJSON.messages.length > 0) ? xhr.responseJSON.messages[0] : 'Scope authorization failed.';
                        $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #fee2e2; border: 1px solid #ef4444; border-radius: 4px; color: #991b1b; font-size: 12.5px;"><i class="fa fa-ban"></i> <strong>BLOCKED:</strong> ${blockMsg}</div>`);
                        if (isDraft) {
                            $('#saveDraftBtn, #triggerPostBtn').attr('disabled', 'disabled');
                        }
                    } else {
                        // FIXED: Silent fail on 404/401, friendly messaging on network timeouts
                        if(xhr.status !== 404 && xhr.status !== 401) {
                            $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px; color: #b45309; font-size: 12.5px;"><i class="fa fa-info-circle"></i> Could not verify project details at this moment. Proceeding with regular receipt.</div>`);
                        } else {
                            $feedback.empty();
                        }
                        if (isDraft) {
                            $('#saveDraftBtn, #triggerPostBtn').removeAttr('disabled');
                        }
                    }
                }
            });
        }, 600);
    });

    // --- HANDSHAKE A2: Line-Item Level Evaluation (Advisory) ---
    window.evaluateGridItems = function(code) {
        $('.item-row').each(function() {
            let $row = $(this);
            let categoryId = $row.attr('data-category-id'); 
            let qty = parseInt($row.find('.qty-input').val()) || 0;
            let index = $row.data('index');

            if (!categoryId || qty <= 0) return;

            console.log(`====== [A2 REQUEST: EVALUATE LINE ${index}] ======`);
            console.log("Code:", code);
            console.log("Location ID:", userLocationId);
            console.log("Category ID:", categoryId);
            console.log("Quantity:", qty);
            console.log("==================================================");

            $.ajax({
                url: '/gov-store/api/tracking/evaluate',
                type: 'GET',
                data: {
                    code: code,
                    location_id: userLocationId,
                    category_id: categoryId,
                    qty: qty
                },
                success: function(res) {
                    console.log(`====== [A2 RESPONSE: SUCCESS (LINE ${index})] ======`);
                    console.log("Payload:", res);
                    console.log("=====================================================");

                    // FIXED: Always clear old warning banners immediately when a response arrives to prevent duplication
                    $(`#tracking_warning_${index}`).remove();

                    if (res.messages && res.messages.length > 0) {
                        let isMatrix = (res.context && res.context.specificity_level === '3_MATRIX');
                        let color = isMatrix ? '#f97316' : '#eab308';
                        let bg = isMatrix ? '#ffedd5' : '#fef9c3';

                        let banner = `<div id="tracking_warning_${index}" class="tracking-advisory-banner" style="background: ${bg}; border-left: 4px solid ${color}; padding: 8px 12px; margin-top: 8px; font-size: 12px; color: #78350f; border-radius: 0 4px 4px 0;"><i class="fa fa-warning"></i> ${res.messages[0]}</div>`;

                        $row.find('.item-select').parent().append(banner);
                    }
                },
                error: function(xhr) {
                    console.error(`====== [A2 RESPONSE: FAILED (LINE ${index})] ======`);
                    console.error("HTTP Status:", xhr.status);
                    console.error("Payload:", xhr.responseJSON || xhr.responseText);
                    console.error("====================================================");

                    // Always clear old warnings on error too
                    $(`#tracking_warning_${index}`).remove();

                    if (xhr.status === 403 && xhr.responseJSON) {
                        let warnMsg = (xhr.responseJSON.messages && xhr.responseJSON.messages.length > 0) ? xhr.responseJSON.messages[0] : 'Evaluation failed.';
                        let banner = `<div id="tracking_warning_${index}" class="tracking-advisory-banner" style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 8px 12px; margin-top: 8px; font-size: 12px; color: #991b1b; border-radius: 0 4px 4px 0;"><i class="fa fa-ban"></i> <strong>BLOCKED:</strong> ${warnMsg}</div>`;
                        $row.find('.item-select').parent().append(banner);
                    }
                }
            });
        });
    }

    // Bind A2 Evaluation to Grid Changes
    if (isDraft) {
        $('#gridBody').on('input change', '.qty-input, .item-select', function() {
            let code = $('#tracking_code_input').val().trim();
            if (code.length >= 2 && !$('#saveDraftBtn').is(':disabled')) {
                evaluateGridItems(code);
            }
        });
    }

    if ($('#tracking_code_input').val().trim().length >= 2) {
        $('#tracking_code_input').trigger('change');
    }
});
</script>