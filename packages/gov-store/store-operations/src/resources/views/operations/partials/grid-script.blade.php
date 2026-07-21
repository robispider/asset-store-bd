<script>
$(document).ready(function() {
    let rowCount = 0;
    const isDraft = {{ $isDraft ? 'true' : 'false' }};
    const mathDirection = '{{ $mathDirection }}';
    
    const compiledSnapshot = @json($document->getCompiledProfileSnapshot() ?? ['items' => []]);
    const existingItems = @json($existingItems ?? []);

    let documentRequirements = {};

    function addRow(data = null) {
        let index = rowCount++;
        let disabled = isDraft ? '' : 'disabled';
        
        // 1. Read the flat appended current_stock attribute directly
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
         // Handle Product Selection
            $select.on('select2:select', function (e) {
                let item = e.params.data;
                
                // FORCE APPEND to native select so jQuery serialize() can read it
                if ($select.find("option[value='" + item.id + "']").length === 0) {
                    var newOption = new Option(item.text, item.id, true, true);
                    $select.append(newOption).trigger('change');
                }

                $row.find('.current-stock').text(item.current_stock);
                
                // Parse composed key in JS (e.g. "App\Models\Consumable_3")
                let parts = item.id.split('_');
                let productType = parts[0];
                let productId = parts[1];

                // Fetch dynamic metadata requirements
                fetchProductProfile(productId, productType, $row);
            });
        }

        // 2. Read the flat appended product_name attribute directly
        if (data) {
            let itemName = data.product_name || 'Unknown Product';
            let composedId = data.product_type + '_' + data.product_id;
            let option = new Option(itemName, composedId, true, true);
            
            $select.append(option).trigger('change');
            
            // Query local frozen snapshot to render requirements immediately
            let items = (compiledSnapshot && compiledSnapshot.items) ? compiledSnapshot.items : [];
            let localItem = items.find(i => i.product_id == data.product_id && i.product_type == data.product_type);
            if (localItem) {
                renderMetadataInputs($row, localItem, data.metadata || []);
            }
        }
    }

    function fetchProductProfile(productId, productType, $row) {
        let normalizedType = productType.split('\\').pop().toLowerCase();

        $.get(`/gov-store/operations/products/${normalizedType}/${productId}/profile`)
            .done(function(res) {
                renderMetadataInputs($row, res, []);
            })
            .fail(function(xhr) {
                console.error('Failed to load product profile:', xhr.responseText);
            });
    }

    function renderMetadataInputs($row, profile, existingMeta = []) {
        let index = $row.data('index');
        let $metaRow = $(`tr[data-parent-index="${index}"]`);
        let $container = $metaRow.find('.meta-container');
        let qty = parseInt($row.find('.qty-input').val()) || 1;
        
        $container.empty();
        existingMeta = existingMeta || [];

        if (!profile || !profile.requirements || profile.requirements.length === 0) {
            $metaRow.addClass('hidden');
            return;
        }

        $metaRow.removeClass('hidden');
        documentRequirements[index] = profile.requirements;

        let table = `<table class="table table-condensed table-bordered"><thead><tr>`;
        profile.requirements.forEach(req => {
            table += `<th>${req.key.replace('_', ' ').toUpperCase()}</th>`;
        });
        table += `</tr></thead><tbody>`;

        for (let r = 0; r < qty; r++) {
            table += `<tr>`;
            profile.requirements.forEach(req => {
                let value = '';
                if (existingMeta && existingMeta.length > 0) {
                    let matchingMeta = existingMeta.find(m => m.field_key === req.key && m.row_index === r);
                    if (matchingMeta) value = matchingMeta.value;
                }

                let inputType = req.type === 'date' ? 'date' : 'text';
                table += `
                    <td>
                        <input type="${inputType}" 
                               name="items[${index}][meta][${r}][${req.key}]" 
                               class="form-control meta-input" 
                               data-key="${req.key}" 
                               data-required="${req.rules && req.rules.includes('required') ? '1' : '0'}"
                               value="${value}" 
                               required style="border: 1px solid #ccc;">
                    </td>`;
            });
            table += `</tr>`;
        }
        table += `</tbody></table>`;
        $container.append(table);
        
        calculateBalance($row);
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

        validateDocumentChecklist();
    }

    function validateDocumentChecklist() {
        let $checklist = $('#checklistRequirements');
        if ($checklist.length === 0) return;
        
        $checklist.empty();

        let totalRequirements = 0;
        let satisfiedRequirements = 0;

        let refNo = $('input[name="reference_no"]').val();
        let refDate = $('input[name="reference_date"]').val();

        totalRequirements += 2;
        if (refNo) satisfiedRequirements++;
        if (refDate) satisfiedRequirements++;

        $checklist.append(`<li><i class="fa ${refNo ? 'fa-check text-green' : 'fa-times text-red'}"></i> Reference Number</li>`);
        $checklist.append(`<li><i class="fa ${refDate ? 'fa-check text-green' : 'fa-times text-red'}"></i> Reference Date</li>`);

        $('.meta-input').each(function() {
            let val = $(this).val();
            let required = $(this).data('required') === 1;

            if (required) {
                totalRequirements++;
                if (val) satisfiedRequirements++;
            }
        });

        let percent = totalRequirements > 0 ? Math.round((satisfiedRequirements / totalRequirements) * 100) : 0;
        $('#validationProgress').css('width', percent + '%');

        if (percent === 100 && totalRequirements > 0) {
            $('#triggerPostBtn').removeAttr('disabled');
        } else {
            $('#triggerPostBtn').attr('disabled', 'disabled');
        }
    }

    // --- Dynamic Listeners ---

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
        
        let idx = $row.data('index');
        if (documentRequirements[idx]) {
            renderMetadataInputs($row, { requirements: documentRequirements[idx] }, []);
        }
    });

    $('#gridBody').on('input', '.meta-input', function() {
        validateDocumentChecklist();
    });

    $('input[name="reference_no"], input[name="reference_date"]').on('input', function() {
        validateDocumentChecklist();
    });

    // Save Draft (AJAX)
    $('#saveDraftBtn').click(function() {
        let btn = $(this);
        console.log('Initiating draft save via AJAX...');
        btn.html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        
        $.post('{{ route("storeops.documents.draft", ["type" => $type, "id" => $document->id]) }}', $('#workspaceForm').serialize())
            .done(function(res) {
                console.log('Draft saved successfully:', res);
                btn.html('<i class="fa fa-check text-green"></i> Saved');
                setTimeout(() => btn.html('<i class="fa fa-save"></i> Save Draft'), 2000);
            })
            .fail(function(xhr) {
                console.error('Failed to save draft:', xhr.responseText);
                alert('Error saving draft: ' + xhr.responseText);
                btn.html('<i class="fa fa-save"></i> Save Draft');
            });
    });

    // Trigger Posting Preview Modal
    $('#triggerPostBtn').click(function() {
        console.log('Initiating pre-posting save & preview compile...');
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

    // Bootstrapping: Populate existing document items on load safely
    if (existingItems && existingItems.length > 0) {
        existingItems.forEach(item => addRow(item));
    } else if (isDraft) {
        addRow();
    }
});
</script>