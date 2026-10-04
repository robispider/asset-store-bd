/* Shared request UI. Server-side services recheck all scopes, states and quantities. */
(() => {
    'use strict';
    function initialize() {
        const ui = document.getElementById('gov-request-ui');
        if (!ui) return;
        const config = ui.dataset;
        document.querySelectorAll('.catalog-image').forEach(image => {
            const fallback = () => {
                image.hidden = true;
                image.nextElementSibling.style.display = 'block';
            };
            image.addEventListener('error', fallback, { once: true });
            if (image.complete && !image.naturalWidth) fallback();
        });
        document.addEventListener('submit', async event => {
            const form = event.target;
            if (!form.matches('.ajax-basket-form')) return;
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            if (button.disabled) return;
            const label = button.innerHTML;
            button.textContent = config.addingLabel;
            button.disabled = true;
            try {
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || data.error || config.errorLabel);
                const badge = document.getElementById('floating-basket-count');
                if (badge) badge.textContent = data.count;
                button.textContent = config.addedLabel;
            } catch (error) {
                window.alert(error.message || config.errorLabel);
                // Never replay an uncertain POST as an automatic fallback.
            } finally {
                window.setTimeout(() => { button.innerHTML = label; button.disabled = false; }, 1200);
            }
        });
        const timers = new WeakMap();
        document.querySelectorAll('.basket-qty-input').forEach(input => {
            input.addEventListener('input', () => {
                if (!input.checkValidity()) return;
                clearTimeout(timers.get(input));
                timers.set(input, setTimeout(async () => {
                    const indicator = document.querySelector(`.save-status-indicator[data-item-id="${input.dataset.itemId}"]`);
                    if (indicator) { indicator.textContent = '…'; indicator.setAttribute('aria-live', 'polite'); }
                    try {
                        const response = await fetch(config.updateUrl, { method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json' },
                            body: JSON.stringify({ item_id: input.dataset.itemId, qty: input.value }) });
                        if (!response.ok) throw new Error();
                        if (indicator) indicator.textContent = config.savedLabel;
                    } catch (_) { if (indicator) indicator.textContent = config.errorLabel; }
                }, 600));
            });
        });
        document.querySelectorAll('.line-status-radio').forEach(input => input.addEventListener('change', () => {
            const qty = document.getElementById(`qty_${input.dataset.id}`);
            if (qty) { qty.value = input.value === 'rejected' ? 0 : qty.max; qty.readOnly = input.value === 'rejected'; }
        }));
        document.querySelectorAll('[data-request-confirm]').forEach(button => button.addEventListener('click', event => {
            if (!window.confirm(button.dataset.requestConfirm)) event.preventDefault();
        }));
        const catalog = document.getElementById('catalogContainer');
        function changeView(grid) {
            catalog.className = grid ? 'row view-grid' : 'row view-list';
            for (const [id, active] of [['btnGrid', grid], ['btnList', !grid]]) {
                const button = document.getElementById(id);
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', String(active));
            }
        }
        document.getElementById('btnGrid')?.addEventListener('click', () => changeView(true));
        document.getElementById('btnList')?.addEventListener('click', () => changeView(false));
        const search = document.getElementById('catalogSearch');
        function filterCatalog() {
            const url = new URL(window.location.href);
            url.searchParams.delete('page');
            for (const [name, id] of [['q', 'catalogSearch'], ['category_id', 'catFilter'], ['type', 'typeFilter']]) {
                const value = document.getElementById(id)?.value || '';
                value ? url.searchParams.set(name, value) : url.searchParams.delete(name);
            }
            window.location.assign(url.toString());
        }
        search?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); filterCatalog(); } });
        document.getElementById('catalogFilterButton')?.addEventListener('click', filterCatalog);
        for (const id of ['catFilter', 'typeFilter']) document.getElementById(id)?.addEventListener('change', filterCatalog);
        document.querySelectorAll('.quick-request-btn').forEach(button => button.addEventListener('click', () => { search.value = button.dataset.search; filterCatalog(); }));
        function checklist() {
            const list = document.getElementById('fulfillmentChecklist');
            if (!list) return;
            list.replaceChildren();
            let picked = 0;
            document.querySelectorAll('.picking-card').forEach(card => {
                const quantity = card.dataset.type === 'asset'
                    ? [...card.querySelectorAll('.asset-scanner-select')].filter(select => select.value).length
                    : Number(card.querySelector('.bulk-issue-qty')?.value || 0);
                picked += quantity;
                const row = document.createElement('li'); row.className = 'list-group-item';
                row.textContent = `${card.querySelector('.item-title').textContent}: ${quantity} / ${card.dataset.remaining}`;
                list.append(row);
            });
            const button = document.getElementById('completeIssueBtn');
            if (button) button.disabled = picked === 0;
        }
        if (window.jQuery && window.jQuery.fn.select2) {
            const $ = window.jQuery;
            $('.asset-scanner-select').select2().on('change', function () {
                const values = [...document.querySelectorAll('.asset-scanner-select')].filter(select => select !== this).map(select => select.value);
                if (this.value && values.includes(this.value)) {
                    window.alert(config.duplicateLabel); $(this).val('').trigger('change');
                }
                checklist();
            });
            $('#substituteSelector').select2({ dropdownParent: $('#substitutionModal'), minimumInputLength: 2,
                ajax: { url: config.searchUrl, dataType: 'json', delay: 300,
                    data: params => ({ q: params.term, type: document.getElementById('modalItemType').value }),
                    processResults: data => ({ results: data }) } });
            document.querySelectorAll('[data-substitute-line]').forEach(button => button.addEventListener('click', () => {
                document.getElementById('modalLineItemId').value = button.dataset.substituteLine;
                document.getElementById('modalItemType').value = button.dataset.substituteType;
                document.getElementById('modalOriginalItemName').textContent = button.dataset.substituteName;
                $('#substituteSelector').val(null).trigger('change'); $('#substitutionModal').modal('show');
            }));
            document.getElementById('applySubstitution')?.addEventListener('click', () => {
                const selected = $('#substituteSelector').select2('data')[0]; if (!selected) return;
                const line = document.getElementById('modalLineItemId').value;
                document.getElementById(`sub_input_${line}`).value = selected.id;
                document.getElementById(`sub_badge_${line}`).textContent = selected.text;
                $('#substitutionModal').modal('hide');
            });
        }
        document.querySelectorAll('.bulk-issue-qty').forEach(input => input.addEventListener('input', checklist));
        checklist();
        const match = window.location.pathname.match(/^\/(consumables|accessories|hardware)\/(\d+)$/);
        const footer = document.querySelector('.side-box .box-footer');
        if (match && footer && !document.getElementById('add-to-basket-btn-container')) {
            const form = document.createElement('form'); form.className = 'ajax-basket-form'; form.id = 'add-to-basket-btn-container';
            form.action = config.addUrl; form.method = 'POST';
            const type = { consumables: 'consumable', accessories: 'accessory', hardware: 'asset' }[match[1]];
            for (const [name, value] of [['_token', config.csrf], ['item_type', type], ['item_id', match[2]]]) {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
            }
            const button = document.createElement('button'); button.type = 'submit'; button.className = 'btn btn-primary btn-sm btn-block';
            button.textContent = config.addLabel; form.append(button); footer.append(form);
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
