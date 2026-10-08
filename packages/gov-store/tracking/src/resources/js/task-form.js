/* Shared create/edit coordinator. Server data is JSON, never executable templates. */
(function () {
    function start() {
        const data = document.getElementById('tracking-task-data');
        if (!data) return;
        if (!window.jQuery || !window.jQuery.fn.select2) { setTimeout(start, 50); return; }
        const config = JSON.parse(data.textContent);
        const t = key => config.labels[key] || key;
        window.jQuery(function ($) {
            const $form = $('#task-creation-form, #task-modification-form');
            $form.find('.select2').select2();
            function togglePanels() {
                const level = $form.find('input[name="specificity_level"]:checked').val() || config.specificity;
                ['1_BLANKET', '2_CATEGORY', '3_MATRIX'].forEach(function (value, index) {
                    $('#panel-level' + (index + 1)).toggle(level === value);
                });
                $form.find('.target-category-select, .target-qty-input').prop('required', level === '2_CATEGORY').prop('disabled', level !== '2_CATEGORY');
            }
            function toggleGeography() {
                const restricted = $form.find('input[name="geo_override"]:checked').val() === 'GeoArea';
                $('#geo-select-group').toggle(restricted);
                $form.find('select[name="geo_area_id"]').prop('required', restricted);
            }
            function toggleTrash() { $('#targets-body .remove-row').prop('disabled', $('#targets-body tr').length <= 1); }
            $form.on('change', 'input[name="specificity_level"]', togglePanels);
            $form.on('change', 'input[name="geo_override"]', toggleGeography);
            let nextIndex = config.targetCount;
            $form.on('click', '#add-target-row', function () {
                const $row = $('<tr>');
                const $category = $('<select>', {name: 'targets[' + nextIndex + '][category_id]', class: 'form-control target-category-select', required: true});
                $category.append(new Option(t('select_category'), ''));
                config.categories.forEach(cat => $category.append(new Option(cat.text, cat.id)));
                $row.append($('<td>').append($category));
                $row.append($('<td>').append($('<input>', {type:'number', name:'targets[' + nextIndex + '][planned_qty]', class:'form-control target-qty-input', min:1, required:true})));
                $row.append($('<td>').append($('<input>', {type:'text', name:'targets[' + nextIndex + '][economic_code]', class:'form-control', maxlength:50})));
                $row.append($('<td>').append($('<button>', {type:'button', class:'btn btn-danger btn-sm remove-row', 'aria-label':t('remove')}).text(t('remove'))));
                $('#targets-body').append($row); $category.select2(); nextIndex++; toggleTrash();
            });
            $form.on('click', '#targets-body .remove-row', function () { $(this).closest('tr').remove(); toggleTrash(); });
            togglePanels(); toggleGeography(); toggleTrash();
            if (!config.editing) {
                let timer; let pending;
                $form.on('input', '#tracking_code_input', function () {
                    clearTimeout(timer); if (pending) pending.abort();
                    const code = this.value.trim();
                    if (!code) { $('#tracking-code-help').text(t('code_help')); return; }
                    timer = setTimeout(function () {
                        $('#tracking-code-help').text(t('checking_code'));
                        pending = $.getJSON(config.uniquenessUrl, {code:code, initiative_id:config.initiativeId})
                            .done(result => $('#tracking-code-help').text(t(result.is_unique ? 'code_available' : 'code_unavailable')))
                            .fail((response, status) => { if (status !== 'abort') $('#tracking-code-help').text(t('code_help')); });
                    }, 350);
                });
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
    else start();
})();
