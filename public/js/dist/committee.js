/* Committee workspaces use existing Bootstrap/jQuery and ordinary CSRF protected routes. */
(function ($) {
    'use strict';
    let pending = null;
    let people = [];
    function base() { return $('.committee-workspace').data('base'); }
    function feedback(message, success) {
        $('.cm-feedback').removeClass('hidden alert-success alert-danger').addClass(success ? 'alert-success' : 'alert-danger').text(message);
    }
    function options(select, rows) {
        const selected = select.val();
        select.empty().append($('<option>').val('').text('—'));
        rows.forEach(row => select.append($('<option>').val(row.id).text(row.text)));
        if (selected) select.val(selected);
    }
    function init() {
        if ($('.cm-people,.cm-external').length) {
            $.getJSON(base() + '/api/people').done(data => {
                people = data.people;
                $('.cm-people').each(function () { options($(this), data.people); });
                $('.cm-external').each(function () { options($(this), data.external); });
            }).fail(xhr => feedback(errorMessage(xhr), false));
        }
        $('.cm-scope-type').trigger('change');
    }
    function errorMessage(xhr) {
        const body = xhr.responseJSON || {};
        return Object.values(body.errors || {}).flat().join(' ') || body.error || body.reason || body.message || String(xhr.status);
    }
    function refresh() {
        $.get(window.location.href).done(html => {
            const workspace = $(html).find('.committee-workspace');
            if (workspace.length) { $('.committee-workspace').replaceWith(workspace); init(); }
        }).fail(xhr => feedback(errorMessage(xhr), false));
    }
    function send(form, extra) {
        const data = new FormData(form);
        if ($(form).data('method')) data.set('_method', $(form).data('method'));
        Object.entries(extra || {}).forEach(([key, value]) => data.set(key, value));
        const buttons = $(form).find(':submit'); buttons.prop('disabled', true);
        $.ajax({url: form.action, method: 'POST', data, processData: false, contentType: false, headers: {'Accept':'application/json'}})
            .done(result => {
                if (result.review_required) {
                    pending = {form, review: result};
                    const summary = $('#cm-confirm .cm-confirm-summary').empty();
                    summary.append($('<pre>').text(JSON.stringify(result.proposal, null, 2)));
                    summary.append($('<label>').text('CHANGE').append($('<input class="form-control cm-review-confirmation" required>')));
                    $('#cm-confirm').modal('show'); return;
                }
                if ($(form).hasClass('cm-code')) {
                    feedback(result.name_bn + ' / ' + result.name_en + ' · ' + result.designation_en + ' · ' + result.office_name, true);
                    $('.cm-people').append($('<option>').val(result.user_id).text(result.name_bn + ' / ' + result.name_en)); return;
                }
                if (result.url && (form.action.endsWith('/drafts') || form.action.endsWith('/reconstitute') || form.action.endsWith('/activate') || form.action.endsWith('/dissolve'))) { window.location.assign(result.url); return; }
                refresh();
            }).fail(xhr => feedback(errorMessage(xhr), false)).always(() => buttons.prop('disabled', false));
    }
    $(document).on('submit', '.cm-command', function (event) {
        event.preventDefault();
        if ($(this).data('confirm')) {
            pending = {form: this};
            const summary = $('#cm-confirm .cm-confirm-summary').empty();
            $('.committee-workspace h2').first().clone().appendTo(summary);
            $(this).find('input:not([type=hidden]):not([type=file]),select').each(function () {
                const value = $(this).is('select') ? $(this).find(':selected').text() : this.value;
                if (value) summary.append($('<p>').text($(this).closest('.form-group').find('label').text() + ': ' + value));
            });
            const committee = $('.committee-workspace').data('committee');
            const dateField = $(this).find('[name="from_date"],[name="effective_from"],[name="date"],[name="to_date"],[name="effective_to"]').first();
            if (committee && dateField.val() && dateField.val() < $('.committee-workspace').data('today')) {
                $.getJSON(base() + '/api/' + committee + '/impact', {as_of:dateField.val()}).done(data => {
                    (data.snapshot_usage || []).forEach(usage => summary.append($('<p>').text(String(usage.count || 0) + ' · ' + usage.label)));
                }).fail(xhr => feedback(errorMessage(xhr),false));
            }
            $('#cm-confirm').modal('show');
        } else send(this);
    });
    $(document).on('click', '.cm-confirm-send', function () {
        if (!pending) return;
        const extra = pending.review ? {review_token:pending.review.review_token, confirmation:$('.cm-review-confirmation').val()} : {};
        if (pending.review && extra.confirmation !== 'CHANGE') return;
        const form = pending.form; pending = null; $('#cm-confirm').modal('hide'); send(form, extra);
    });
    $(document).on('change', '.cm-scope-type', function () {
        const select = $(this).closest('form').find('.cm-scope-id');
        $.getJSON(base() + '/api/scopes/' + this.value).done(data => options(select,data.results));
    });
    $(document).on('submit', '.cm-search', function (event) {
        event.preventDefault();
        $.getJSON(this.action, $(this).serialize()).done(data => {
            const body = $('.cm-registry-rows').empty();
            data.rows.forEach(row => body.append($('<tr>').append($('<td>').append($('<a>').attr('href',row.url).text(row.number)), $('<td>').text(row.name_bn + ' / ' + row.name_en), $('<td>').text(row.status), $('<td>').text(row.effective_to || ''))));
        }).fail(xhr => feedback(errorMessage(xhr),false));
    });
    $(document).on('change', '.cm-transfer-person', function () {
        const user = Number(this.value), container = $('.cm-transfer-seats').empty();
        $.getJSON(base() + '/api/transfers', {user_id:user}).done(data => data.seats.forEach((row, index) => {
            const section = $('<fieldset>').append($('<legend>').text(row.committee_number + ' · ' + row.name));
            ['committee_id','seat_id','order_id','from_date'].forEach(key => {
                if (key === 'order_id') {
                    const select = $('<select class="form-control" required>').attr('name', 'changes['+index+']['+key+']'); options(select,row.orders); section.append(select);
                } else section.append($('<input>').attr({type:key === 'from_date' ? 'date' : 'hidden', name:'changes['+index+']['+key+']', value:row[key]}));
            });
            const replacement = $('<select class="form-control" required>').attr('name','changes['+index+'][user_id]'); options(replacement,people.filter(p => Number(p.id) !== user));
            container.append(section.append(replacement));
        })).fail(xhr => feedback(errorMessage(xhr),false));
    });
    $(init);
})(window.jQuery);
