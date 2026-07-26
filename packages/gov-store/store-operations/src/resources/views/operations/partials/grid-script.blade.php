<script>
$(document).ready(function() {
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

        if (isDraft) {
            $select.select2({
                ajax: {
                    url: '{{ route("storeops.api.products.search") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { q: params.term }; },
                    processResults: function(data) { return data; }
                },
                placeholder: 'Search for item...',
                minimumInputLength: 1
            });
            
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

        // Prepopulate existing data on load safely
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
                let errorMsg = 'An error occurred while saving.';
                
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    } else if (xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                    }
                } else if (xhr.responseText) {
                    errorMsg = xhr.responseText;
                }

                console.error('Save Draft Failed:', xhr);
                alert('Error saving draft:\n' + errorMsg);
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
            .fail(function() {
                alert('Please fill all required fields before posting.');
            });
    });

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

    // --- 2. THE DYNAMIC AUTO-SAVE LISTENER ---
    // Listens for input changes, but ignores them if the page is currently bootstrapping
    let liveValidationTimer = null;
    $('#workspaceForm').on('input change', 'input, select', function() {
        if (isBootstrapping) {
            return; // Exit immediately if loading existing items!
        }
        
        clearTimeout(liveValidationTimer);
        
        liveValidationTimer = setTimeout(function() {
            $.post('{{ route("storeops.documents.draft", ["type" => $type, "id" => $document->id]) }}', $('#workspaceForm').serialize())
                .done(function(res) {
                    if (res.validation) {
                        renderServerValidationChecklist(res.validation);
                    }
                });
        }, 600);
    });

    // --- 3. PAGE INITIALIZATION ENGINE ---
    // Safe bootstrapping sequence with explicit lock releasing
    if (existingItems && existingItems.length > 0) {
        existingItems.forEach(item => addRow(item));
    } else if (isDraft) {
        addRow();
    }
    
    // Release the bootstrapping lock once rendering completes
    setTimeout(function() {
        isBootstrapping = false;
        
        // Run an initial silent save to populate the checklist on clean load
        $.post('{{ route("storeops.documents.draft", ["type" => $type, "id" => $document->id]) }}', $('#workspaceForm').serialize())
            .done(function(res) {
                if (res.validation) {
                    renderServerValidationChecklist(res.validation);
                }
            });
    }, 1000); 
});
</script>