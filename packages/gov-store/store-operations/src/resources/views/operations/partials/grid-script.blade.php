<script>
$(document).ready(function() {
    
    // --- GLOBAL AJAX SETUP FOR CSRF ---
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    let rowCount = 0;
    const isDraft = {{ $isDraft ? 'true' : 'false' }};
    const mathDirection = '{{ $mathDirection }}';
    
    // --- 1. THE BOOTSTRAPPING LOCK ---
    // Silences auto-save triggers until the entire page is stable
    let isBootstrapping = true; 

    const compiledSnapshot = @json($document->getCompiledProfileSnapshot() ?? ['items' => []]);
    const existingItems = @json($existingItems ?? []);

    function addRow(data = null) {
        let index = rowCount++;
        let disabled = isDraft ? '' : 'disabled';
        let currentStockVal = data ? data.current_stock : '-';

        let tr = `
            <tr data-index="${index}" class="item-row">
                <td>
                    <select name="items[${index}][id]" class="form-control item-select" required ${disabled}></select>
                </td>
                <td style="vertical-align: middle; text-align: center;">
                    <span class="current-stock badge bg-gray">${currentStockVal}</span>
                </td>
                <td>
                    <input type="number" name="items[${index}][qty]" class="form-control qty-input" min="1" value="${data ? data.quantity : ''}" required ${disabled}>
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${index}][unit_cost]" class="form-control" value="${data ? data.unit_cost ?? '' : ''}" ${disabled}>
                </td>
                <td style="vertical-align: middle; text-align: center;">
                    <strong class="balance-after">-</strong>
                </td>
                ${isDraft ? `<td><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fa fa-times"></i></button></td>` : ''}
            </tr>
            <tr class="meta-row hidden" data-parent-index="${index}">
                <td colspan="6" style="background: #fafbfe; padding: 15px 30px;">
                    <div class="meta-container"></div>
                </td>
            </tr>
        `;
        
        $('#gridBody').append(tr);
        let $row = $(`tr[data-index="${index}"]`);
        let $select = $row.find('.item-select');

        // FIXED: Initialize Select2 for both states to prevent render/styling bugs, but disable dynamically
        $select.select2({
            disabled: !isDraft, // Natively disables Select2 dropdown if document is posted/finalized
            ajax: isDraft ? {
                url: '{{ route("storeops.api.products.search") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return data; }
            } : undefined,
            placeholder: 'Search for item...',
            minimumInputLength: isDraft ? 1 : 0
        });
        
        if (isDraft) {
            // Handle Product Selection
            $select.on('select2:select', function (e) {
                let item = e.params.data;
                
                if ($select.find("option[value='" + item.id + "']").length === 0) {
                    var newOption = new Option(item.text, item.id, true, true);
                    $select.append(newOption).trigger('change');
                }

                $row.find('.current-stock').text(item.current_stock);
                renderMetadataInputs($row);
            });
        }

        // Prepopulate existing data safely on load
        if (data) {
            let itemName = data.product_name || 'Unknown Product';
            let composedId = data.product_type + '_' + data.product_id;
            let option = new Option(itemName, composedId, true, true);
            
            $select.append(option).trigger('change');
            renderMetadataInputs($row);
        }
    }

    // Server-side pre-rendering engine interface
    function renderMetadataInputs($row) {
        let index = $row.data('index');
        let $metaRow = $(`tr[data-parent-index="${index}"]`);
        let $container = $metaRow.find('.meta-container');
        let qty = parseInt($row.find('.qty-input').val()) || 1;
        
        $container.empty();

        let rawVal = $row.find('.item-select').val();
        if (!rawVal || !rawVal.includes('_')) {
            $metaRow.addClass('hidden');
            return;
        }

        let parts = rawVal.split('_');
        let productType = parts[0];
        let productId = parts[1];

        // Fetch fully compiled, highly interactive server-side layout from PHP
        $.get('{{ route("storeops.documents.render_meta") }}', {
            product_type: productType,
            product_id: productId,
            quantity: qty,
            row_index: index,
            document_id: '{{ $document->id }}'
        })
        .done(function(res) {
            if (res.has_requirements && res.html.trim() !== '') {
                $metaRow.removeClass('hidden');
                $container.append(res.html);

                // If document is posted, lock down all inputs inside the metadata sub-grid
                if (!isDraft) {
                    $container.find('input, select').attr('disabled', 'disabled');
                }
            } else {
                $metaRow.addClass('hidden');
            }
            calculateBalance($row);
        })
        .fail(function(xhr) {
            console.error('Failed to load dynamic meta layout:', xhr.responseText);
        });
    }

    function calculateBalance($row) {
        let current = parseInt($row.find('.current-stock').text()) || 0;
        let qty = parseInt($row.find('.qty-input').val()) || 0;
        
        if (qty > 0 && !isNaN(current)) {
            let balance = mathDirection === '+' ? (current + qty) : (current - qty);
            $row.find('.balance-after').text(balance);
            $row.find('.balance-after').removeClass('text-red text-green').addClass(balance < 0 ? 'text-red' : 'text-green');
        } else {
            $row.find('.balance-after').text('-');
        }
        updateTotals();
    }

    function updateTotals() {
        let lines = $('.item-row').length;
        let totalQty = 0;
        $('.qty-input').each(function() {
            totalQty += parseInt($(this).val()) || 0;
        });
        $('#sumLines').text(lines);
        $('#sumQty').text(totalQty);
    }

    function renderServerValidationChecklist(validationData) {
        let $checklist = $('#checklistRequirements');
        if ($checklist.length === 0 || !validationData) return;

        $checklist.empty();

        if (validationData.checklist) {
            validationData.checklist.forEach(function(item) {
                let iconClass = item.passed ? 'fa-check text-green' : 'fa-times text-red';
                $checklist.append(`<li><i class="fa ${iconClass}"></i> ${item.label}</li>`);
            });
        }

        $('#validationProgress').css('width', (validationData.progress || 0) + '%');

        if (validationData.is_valid) {
            $('#triggerPostBtn').removeAttr('disabled');
        } else {
            $('#triggerPostBtn').attr('disabled', 'disabled');
        }
    }

    // --- Dynamic Action Listeners ---
    if (isDraft) {
        $('#addRowBtn').click(() => addRow());

        $('#gridBody').on('click', '.remove-row', function() {
            let idx = $(this).closest('tr').data('index');
            $(this).closest('tr').remove();
            $(`tr[data-parent-index="${idx}"]`).remove();
            updateTotals();
        });

        $('#gridBody').on('input', '.qty-input', function() {
            let $row = $(this).closest('tr');
            calculateBalance($row);
            renderMetadataInputs($row);
        });
    }

    // --- ADVANCED ERROR LOGGER ---
    function logAndAlertError(xhr, context) {
        let errorMsg = 'Unknown Error';
        let debugInfo = '';

        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                // Laravel Validation Error
                errorMsg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                debugInfo = "Laravel Validation Rule Failed";
            } else if (xhr.responseJSON.error) {
                // Our Custom PHP Exception
                errorMsg = xhr.responseJSON.error;
                debugInfo = `File: ${xhr.responseJSON.file}\nLine: ${xhr.responseJSON.line}`;
            } else if (xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
        } else {
            errorMsg = xhr.responseText;
        }

        console.error(`====== ${context} FAILED ======`);
        console.error("Message:", errorMsg);
        if (debugInfo) console.error("Debug:", debugInfo);
        console.error("=================================");

        return errorMsg;
    }

    if (isDraft) {
        // Save Draft (AJAX)
        $('#saveDraftBtn').click(function() {
            let btn = $(this);
            btn.html('<i class="fa fa-spinner fa-spin"></i> Saving...');
            
            $.post('{{ route("storeops.documents.draft", ["type" => $type, "id" => $document->id]) }}', $('#workspaceForm').serialize())
                .done(function(res) {
                    btn.html('<i class="fa fa-check text-green"></i> Saved');
                    setTimeout(() => btn.html('<i class="fa fa-save"></i> Save Draft'), 2000);

                    if (res.validation) {
                        renderServerValidationChecklist(res.validation);
                    }
                })
                .fail(function(xhr) {
                    let msg = logAndAlertError(xhr, "MANUAL SAVE");
                    alert('Error saving draft:\n' + msg);
                    btn.html('<i class="fa fa-save"></i> Save Draft');
                });
        });

        // Trigger Posting Preview Modal
        $('#triggerPostBtn').click(function() {
            $.post('{{ route("storeops.documents.draft", ["type" => $type, "id" => $document->id]) }}', $('#workspaceForm').serialize())
                .done(function() {
                    $.get('{{ route("storeops.documents.preview", ["type" => $type, "id" => $document->id]) }}')
                        .done(function(data) {
                            $('#previewLines').text(data.lines);
                            $('#previewQty').text(data.total_qty);
                            $('#previewValue').text(data.total_value);
                            $('#previewRef').text(data.reference);
                            $('#postingModal').modal('show');
                        });
                })
                .fail(function(xhr) {
                    let msg = logAndAlertError(xhr, "PRE-POST SAVE");
                    alert('Cannot proceed to posting due to a save error:\n' + msg);
                });
        });

        // Live Debounced Auto-Validation on Field Edits (600ms delay)
        let liveValidationTimer = null;
        $('#workspaceForm').on('input change', 'input, select', function() {
            if (isBootstrapping) return; 
            
            clearTimeout(liveValidationTimer);
            liveValidationTimer = setTimeout(function() {
                $.post('{{ route("storeops.documents.draft", ["type" => $type, "id" => $document->id]) }}', $('#workspaceForm').serialize())
                    .done(function(res) {
                        if (res.validation) {
                            renderServerValidationChecklist(res.validation);
                        }
                    })
                    .fail(function(xhr) {
                        logAndAlertError(xhr, "AUTO-SAVE");
                    });
            }, 600);
        });
    }

    // File Upload Handler (Phase 5)
    $('#uploadFileBtn').click(function() {
        let fileInput = $('#attachmentFile')[0];
        let category = $('#attachmentCategory').val();

        if (fileInput.files.length === 0) {
            alert('Please select a file to upload first.');
            return;
        }

        let file = fileInput.files[0];
        let formData = new FormData();
        formData.append('file', file);
        formData.append('category', category);
        formData.append('_token', '{{ csrf_token() }}');

        let btn = $(this);
        btn.html('<i class="fa fa-spinner fa-spin"></i> Uploading...').attr('disabled', 'disabled');

        $.ajax({
            url: '{{ route("storeops.documents.attachments.upload", ["type" => $type, "id" => $document->id]) }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                btn.html('<i class="fa fa-upload"></i> Upload').removeAttr('disabled');
                $('#attachmentFile').val('');
                $('#noAttachmentsMsg').remove();

                let li = `
                    <li class="list-group-item attachment-item" data-id="${res.attachment.id}" style="border-bottom: 1px solid #f4f4f4; padding: 10px 0;">
                        <i class="fa fa-file-text-o text-blue"></i> 
                        <a href="${res.attachment.url}" target="_blank" style="margin-left: 5px;">
                            <strong>${res.attachment.name}</strong>
                        </a>
                        <button type="button" class="btn btn-xs btn-danger pull-right delete-attachment" data-id="${res.attachment.id}">
                            <i class="fa fa-trash"></i>
                        </button>
                    </li>
                `;
                $('#attachmentsList').append(li);
            },
            error: function(xhr) {
                alert('File upload failed: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
                btn.html('<i class="fa fa-upload"></i> Upload').removeAttr('disabled');
            }
        });
    });

    // File Delete Handler (Phase 5)
    $('#attachmentsList').on('click', '.delete-attachment', function() {
        if (!confirm('Are you sure you want to remove this supporting document?')) return;

        let btn = $(this);
        let attachmentId = btn.data('id');
        btn.html('<i class="fa fa-spinner fa-spin"></i>').attr('disabled', 'disabled');

        $.ajax({
            url: `/gov-store/operations/documents/{{ $type }}/{{ $document->id }}/attachments/${attachmentId}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                $(`li[data-id="${attachmentId}"]`).remove();
                if ($('.attachment-item').length === 0) {
                    $('#attachmentsList').append(`
                        <li class="list-group-item text-center text-muted" id="noAttachmentsMsg" style="border:none;">
                            No supporting files attached yet.
                        </li>
                    `);
                }
            },
            error: function(xhr) {
                alert('Failed to remove attachment: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
                btn.html('<i class="fa fa-trash"></i>').removeAttr('disabled');
            }
        });
    });

    // --- 3. PAGE INITIALIZATION ENGINE ---
    if (existingItems && existingItems.length > 0) {
        existingItems.forEach(item => addRow(item));
    } else if (isDraft) {
        addRow();
    }
    
    // Release the bootstrapping lock once rendering completes
    setTimeout(function() {
        isBootstrapping = false;
        
        // FIXED: Exit early and never fire silent saves on load if the document is posted (locked)
        if (!isDraft) {
            return; 
        }

        // Run an initial silent save to populate the checklist on clean load
        $.post('{{ route("storeops.documents.draft", ["type" => $type, "id" => $document->id]) }}', $('#workspaceForm').serialize())
            .done(function(res) {
                if (res.validation) {
                    renderServerValidationChecklist(res.validation);
                }
            })
            .fail(function(xhr) {
                logAndAlertError(xhr, "INITIAL SILENT SAVE");
            });
    }, 1000); 

    // =========================================================================
    // PROGRAMME TRACKING ENGINE (HANDSHAKES A1 & A2)
    // =========================================================================
    let trackingCodeTimer = null;
    const userLocationId = '{{ $document->location_id ?? auth()->user()->location_id ?? 1 }}';

    // HANDSHAKE A1: Header-Level Scope Verification
    $('#tracking_code_input').on('input change', function() {
        clearTimeout(trackingCodeTimer);
        let code = $(this).val().trim();
        let $feedback = $('#tracking_a1_feedback');

        if (code.length < 2) {
            $feedback.empty();
            $('#saveDraftBtn, #triggerPostBtn').removeAttr('disabled');
            $('.tracking-advisory-banner').remove(); // Clear item warnings
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
                        $('#saveDraftBtn, #triggerPostBtn').attr('disabled', 'disabled');
                    } else {
                        // APPROVED: Clear to proceed
                        $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #d1fae5; border: 1px solid #10b981; border-radius: 4px; color: #065f46; font-size: 12.5px;"><i class="fa fa-check-circle"></i> <strong>VERIFIED:</strong> ${res.context.initiative}</div>`);
                        $('#saveDraftBtn, #triggerPostBtn').removeAttr('disabled');
                        
                        // Trigger Line-Item Evaluations
                        evaluateGridItems(code);
                    }
                },
                error: function(xhr) {
                    // Graceful Degradation: If 404 (not installed) or 401 (auth mismatch), fail open.
                    if(xhr.status !== 404 && xhr.status !== 401) {
                        $feedback.html(`<div style="margin-top:10px; padding: 10px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px; color: #b45309; font-size: 12.5px;"><i class="fa fa-warning"></i> Tracking Engine unreachable. Proceed with caution.</div>`);
                    } else {
                        $feedback.empty();
                    }
                    $('#saveDraftBtn, #triggerPostBtn').removeAttr('disabled');
                }
            });
        }, 600);
    });

    // HANDSHAKE A2: Line-Item Level Evaluation (Advisory)
    function evaluateGridItems(code) {
        $('.item-row').each(function() {
            let $row = $(this);
            let rawVal = $row.find('.item-select').val();
            let qty = parseInt($row.find('.qty-input').val()) || 0;
            let index = $row.data('index');

            if (!rawVal || !rawVal.includes('_') || qty <= 0) return;

            let parts = rawVal.split('_');
            let productType = parts[0];
            let productId = parts[1];

            $(`#tracking_warning_${index}`).remove();

            $.ajax({
                url: '/gov-store/api/tracking/evaluate',
                type: 'GET',
                data: {
                    code: code,
                    location_id: userLocationId,
                    product_type: productType,
                    product_id: productId,
                    qty: qty
                },
                success: function(res) {
                    if (res.messages && res.messages.length > 0) {
                        let color = res.context.specificity_level === '3_MATRIX' ? '#f97316' : '#eab308';
                        let bg = res.context.specificity_level === '3_MATRIX' ? '#ffedd5' : '#fef9c3';

                        let banner = `<div id="tracking_warning_${index}" class="tracking-advisory-banner" style="background: ${bg}; border-left: 4px solid ${color}; padding: 8px 12px; margin-top: 8px; font-size: 12px; color: #78350f; border-radius: 0 4px 4px 0;"><i class="fa fa-warning"></i> ${res.messages[0]}</div>`;

                        $row.find('.item-select').parent().append(banner);
                    }
                }
            });
        });
    }

    // Bind A2 Evaluation to Grid Changes
    if (isDraft) {
        $('#gridBody').on('input', '.qty-input, .item-select', function() {
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