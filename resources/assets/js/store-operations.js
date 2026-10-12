/* Store document workspace. All server/form text is inserted as text, never HTML. */
$(function () {
    const configNode = document.getElementById('storeops-config');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent);
    const text = config.labels;
    const form = $('#workspaceForm');
    const csrf = $('meta[name="csrf-token"]').attr('content');
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
    let nextIndex = 0;
    let revision = 0;
    let savedRevision = -1;
    let valid = false;
    let trackingValid = !$('#tracking_code_input').val();
    let saveQueue = Promise.resolve();
    let saveTimer;
    let trackingRequest;

    function error(xhr) {
        const response = xhr.responseJSON || {};
        const message = response.errors ? Object.values(response.errors).flat().join('\n')
            : response.error || response.message || text.operation_failed;
        $('#gov-access-inline').text(message).prop('hidden', false);
        return message;
    }
    function updatePostButton() {
        $('#triggerPostBtn').prop('disabled', !config.ledgerOpen || (config.isDraft ? !(valid && trackingValid && savedRevision === revision) : config.status !== 'READY'));
    }
    function checklist(result) {
        valid = Boolean(result && result.is_valid);
        const list = $('#checklistRequirements').empty();
        (result?.checklist || []).forEach(item => {
            $('<li>').append($('<i>').addClass('fa ' + (item.passed ? 'fa-check text-green' : 'fa-times text-red')))
                .append(document.createTextNode(' ' + item.label)).appendTo(list);
        });
        $('#validationProgress').css('width', (result?.progress || 0) + '%').attr('aria-valuenow', result?.progress || 0);
        updatePostButton();
    }
    function totals() {
        $('#sumLines').text($('.item-row').length);
        let total = 0;
        $('.qty-input').each(function () { total += Number(this.value) || 0; });
        $('#sumQty').text(total);
    }
    function balance(row) {
        const stock = Number(row.find('.current-stock').text());
        const quantity = Number(row.find('.qty-input').val());
        const adjustment = $(`tr[data-parent-index="${row.data('index')}"] select[name*="[adjustment_direction]"]`).val();
        const direction = adjustment || (config.type === 'receipt' ? 'IN' : 'OUT');
        const value = direction === 'IN' ? stock + quantity : stock - quantity;
        row.find('.balance-after').text(Number.isFinite(value) ? value : '—').toggleClass('text-danger', value < 0);
        totals();
    }
    function metadata(row) {
        const index = row.data('index');
        const container = $(`tr[data-parent-index="${index}"] .meta-container`);
        const value = row.find('.item-select').val();
        if (!value) return;
        const separator = value.lastIndexOf('_');
        const previous = {};
        container.find('input, select').each(function () { previous[this.name] = this.value; });
        row.data('metadataRequest')?.abort();
        const request = $.get(config.urls.metadata, {
            product_type: value.substring(0, separator), product_id: value.substring(separator + 1),
            quantity: row.find('.qty-input').val() || 1, row_index: index, document_id: config.id
        }).done(result => {
            // Authorized server-rendered Blade is the only HTML inserted in this workspace.
            container.html(result.html).closest('tr').toggleClass('hidden', !result.has_requirements);
            container.find('input, select').each(function () {
                if (Object.hasOwn(previous, this.name)) this.value = previous[this.name];
                this.disabled = !config.isDraft;
            });
            balance(row);
        }).fail(xhr => { if (xhr.statusText !== 'abort') error(xhr); });
        row.data('metadataRequest', request);
    }
    function addRow(item) {
        const index = nextIndex++;
        const row = $('<tr>').addClass('item-row').attr('data-index', index).data('index', index);
        const select = $('<select>').attr({ name: `items[${index}][id]`, required: true, 'aria-label': text.item_name }).addClass('form-control item-select');
        row.append($('<td>').append(select));
        row.append($('<td>').append($('<span>').addClass('current-stock badge bg-gray').text(item?.current_stock ?? '—')));
        row.append($('<td>').append($('<input>').attr({ type: 'number', min: 1, max: 99999, step: 1, required: true,
            name: `items[${index}][qty]`, 'aria-label': text.quantity }).addClass('form-control qty-input').val(item?.quantity || '')));
        row.append($('<td>').append($('<input>').attr({ type: 'number', min: 0, step: '0.01',
            name: `items[${index}][unit_cost]`, 'aria-label': text.unit_cost }).addClass('form-control').val(item?.unit_cost ?? '')));
        row.append($('<td>').append($('<strong>').addClass('balance-after')));
        if (config.isDraft) row.append($('<td>').append($('<button>').attr({ type: 'button', 'aria-label': text.remove_row })
            .addClass('btn btn-danger remove-row').append($('<i>').addClass('fa fa-times'))));
        row.find('input, select').prop('disabled', !config.isDraft);
        const metaRow = $('<tr>').addClass('meta-row hidden').attr('data-parent-index', index)
            .append($('<td>').attr('colspan', config.isDraft ? 6 : 5).append($('<div>').addClass('meta-container')));
        $('#gridBody').append(row, metaRow);
        select.select2({ width: '100%', placeholder: text.search_item, minimumInputLength: config.isDraft ? 1 : 0,
            ajax: config.isDraft ? { url: config.urls.search, dataType: 'json', delay: 250,
                data: params => ({ q: params.term, document_id: config.id }), processResults: result => result } : undefined });
        if (item) {
            select.append(new Option(item.product_name, item.product_type + '_' + item.product_id, true, true)).trigger('change.select2');
            metadata(row);
        }
        select.on('select2:select', event => {
            row.find('.current-stock').text(event.params.data.current_stock);
            // Values from another product must never survive a selection change.
            metaRow.find('.meta-container').empty();
            metadata(row);
            changed();
        });
        balance(row);
    }
    function save() {
        clearTimeout(saveTimer);
        const payload = form.serialize();
        const savingRevision = revision;
        saveQueue = saveQueue.catch(() => {}).then(() => new Promise((resolve, reject) => {
            $.post(config.urls.draft, payload).done(result => {
                if (savingRevision === revision) {
                    savedRevision = savingRevision;
                    checklist(result.validation);
                    $('#gov-access-inline').prop('hidden', true);
                    if (config.type === 'transfer') $('.item-row').each(function () {
                        if (!$(`tr[data-parent-index="${$(this).data('index')}"] select[name*="destination_stockable_id"]`).length) metadata($(this));
                    });
                }
                resolve(result);
            }).fail(xhr => { valid = false; updatePostButton(); error(xhr); reject(xhr); });
        }));
        return saveQueue;
    }
    function changed() {
        revision++;
        updatePostButton();
        clearTimeout(saveTimer);
        if (form[0].checkValidity()) saveTimer = setTimeout(() => save().catch(() => {}), 700);
    }
    function preview() {
        $.get(config.urls.preview).done(result => {
            $('#previewLines').text(result.lines); $('#previewQty').text(result.total_qty);
            $('#previewValue').text(result.total_value); $('#previewRef').text(result.reference);
            $('#previewItems').empty();
            (result.items || []).forEach(item => $('<li>').text(item.name + ': ' + item.quantity).appendTo('#previewItems'));
            $('#postingModal').modal('show');
        }).fail(error);
    }
    function verifyTracking() {
        trackingRequest?.abort();
        const code = ($('#tracking_code_input').val() || '').trim();
        trackingValid = !code;
        updatePostButton();
        const feedback = $('#tracking_a1_feedback').empty();
        if (!code) return;
        feedback.text(text.verifying_tracking);
        trackingRequest = $.get(config.urls.tracking, { code, location_id: config.office }).done(result => {
            trackingValid = result.can_proceed === true;
            feedback.text(trackingValid ? text.tracking_valid : (result.messages || [text.tracking_failed]).join(' '));
            updatePostButton();
        }).fail(xhr => {
            if (xhr.statusText === 'abort') return;
            trackingValid = false; feedback.text(text.tracking_failed); updatePostButton();
        });
    }
    (config.items || []).forEach(addRow);
    if (!config.items.length && config.isDraft) addRow();
    if (config.isDraft) {
        $('#addRowBtn').on('click', () => { addRow(); changed(); });
        $('#gridBody').on('click', '.remove-row', function () {
            const row = $(this).closest('tr'); row.data('metadataRequest')?.abort();
            $(`tr[data-parent-index="${row.data('index')}"]`).remove(); row.remove(); totals(); changed();
        });
        let metadataTimer;
        $('#gridBody').on('input', '.qty-input', function () {
            const row = $(this).closest('tr'); balance(row);
            clearTimeout(metadataTimer); metadataTimer = setTimeout(() => metadata(row), 250);
        }).on('change', 'select[name*="adjustment_direction"]', function () { balance($(this).closest('tr').prev()); });
        form.on('input change', 'input, select, textarea', changed);
        $('#saveDraftBtn').on('click', () => {
            if (form[0].reportValidity()) save().catch(() => {});
        });
        $('#triggerPostBtn').on('click', () => {
            if (!form[0].reportValidity()) return;
            save().then(() => { if (valid && trackingValid && revision === savedRevision) preview(); }).catch(() => {});
        });
        $('#destination_location_id').on('change', () => {
            // Save the selected office before fetching its authorized matching item choices.
            $('#gridBody .meta-container').empty();
            if (form[0].checkValidity()) save().then(() => $('.item-row').each(function () { metadata($(this)); })).catch(() => {});
        });
    } else if (config.status === 'READY') $('#triggerPostBtn').on('click', preview);
    $('#confirmPostBtn').on('click', () => {
        if (config.isDraft && (revision !== savedRevision || !valid || !trackingValid)) {
            $('#postingModal').modal('hide'); return;
        }
        form[0].submit();
    });
    let trackingTimer;
    $('#tracking_code_input').on('input change', () => { clearTimeout(trackingTimer); trackingValid = false; updatePostButton(); trackingTimer = setTimeout(verifyTracking, 400); });
    if ($('#tracking_code_input').val()) verifyTracking();
    $('#uploadFileBtn').on('click', function () {
        const file = $('#attachmentFile')[0]?.files[0];
        if (!file) { $('#gov-access-inline').text(text.select_file).prop('hidden', false); return; }
        const payload = new FormData(); payload.append('file', file); payload.append('category', $('#attachmentCategory').val());
        const button = $(this).prop('disabled', true);
        $.ajax({ url: config.urls.upload, method: 'POST', data: payload, processData: false, contentType: false }).done(result => {
            const attachment = result.attachment;
            const row = $('<li>').addClass('list-group-item attachment-item').attr('data-id', attachment.id);
            row.append($('<a>').attr({ href: attachment.url, target: '_blank', rel: 'noopener' }).text(attachment.name));
            row.append($('<button>').attr({ type: 'button', 'data-id': attachment.id, 'aria-label': text.remove_attachment })
                .addClass('btn btn-danger pull-right delete-attachment').append($('<i>').addClass('fa fa-trash')));
            $('#noAttachmentsMsg').remove(); $('#attachmentsList').append(row); $('#attachmentFile').val('');
        }).fail(error).always(() => button.prop('disabled', false));
    });
    $('#attachmentsList').on('click', '.delete-attachment', function () {
        if (!window.confirm(text.remove_attachment_confirm)) return;
        const button = $(this).prop('disabled', true);
        $.ajax({ url: config.urls.upload + '/' + encodeURIComponent(button.data('id')), method: 'DELETE' })
            .done(() => button.closest('li').remove()).fail(error).always(() => button.prop('disabled', false));
    });
    updatePostButton();
});
