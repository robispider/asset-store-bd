/* Shared tracking workspace controls. Server data stays in HTML attributes. */
(function () {
    function start() {
        document.querySelectorAll('[data-tracking-confirm]').forEach(button => {
            button.addEventListener('click', event => {
                if (!window.confirm(button.dataset.trackingConfirm)) event.preventDefault();
            });
        });
        document.querySelectorAll('[data-tracking-print]').forEach(button => button.addEventListener('click', () => window.print()));
        document.querySelectorAll('[data-tracking-delete]').forEach(button => button.addEventListener('click', () => {
            if (window.confirm(button.dataset.trackingDelete)) document.getElementById('delete-initiative-form').requestSubmit();
        }));
        // Manage tab: "Attach PDF" opens that code's detail row and moves focus to its upload field.
        document.querySelectorAll('[data-manage-open]').forEach(link => link.addEventListener('click', event => {
            const id = link.dataset.manageOpen;
            const toggle = document.querySelector('[aria-controls="manage-detail-' + id + '"]');
            const input = document.getElementById('order-file-' + id);
            if (!toggle || toggle.hidden) return;
            event.preventDefault();
            link.closest('details')?.removeAttribute('open');
            if (toggle.getAttribute('aria-expanded') !== 'true') toggle.click();
            const target = input || document.getElementById('order-' + id);
            target?.scrollIntoView({block: 'center'});
            target?.focus({preventScroll: true});
        }));
        document.addEventListener('click', event => {
            document.querySelectorAll('.gs-tracking-more[open]').forEach(menu => { if (!menu.contains(event.target)) menu.removeAttribute('open'); });
        });
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            const menu = document.querySelector('.gs-tracking-more[open]');
            if (menu) { menu.removeAttribute('open'); menu.querySelector('summary').focus(); }
        });
        const selectAll = document.getElementById('select-all-trigger');
        const submit = document.getElementById('bulk-submit');
        const boxes = Array.from(document.querySelectorAll('.asset-checkbox'));
        const update = () => { if (submit) submit.disabled = !boxes.some(box => box.checked && !box.disabled); };
        boxes.forEach(box => box.addEventListener('change', update));
        if (selectAll) selectAll.addEventListener('change', () => { boxes.forEach(box => { if (!box.disabled) box.checked = selectAll.checked; }); update(); });
        update();
        const staff = document.querySelector('[data-tracking-staff-url]');
        if (staff) {
            let attempts = 0;
            const init = () => {
                if (!window.jQuery || !window.jQuery.fn.select2) { if (++attempts < 100) setTimeout(init, 50); return; }
                window.jQuery('.user-search-select').select2({
                    placeholder: staff.dataset.trackingStaffPlaceholder, minimumInputLength: 2,
                    ajax: { url: staff.dataset.trackingStaffUrl, dataType: 'json', delay: 250,
                        data: params => ({q: params.term, initiative_id: staff.dataset.trackingInitiative}),
                        processResults: data => ({results: data.results}) }
                });
            };
            init();
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
