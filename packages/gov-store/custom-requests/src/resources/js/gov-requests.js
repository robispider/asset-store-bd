/*
 * Request screens: small progressive enhancements over forms that all work without JavaScript.
 * Source of truth: packages/gov-store/custom-requests/src/resources/js/gov-requests.js, published to
 * public/js/gov-requests.js (php artisan vendor:publish --tag=gov-requests-assets --force).
 * Server-side services recheck every scope, state and quantity.
 */
(() => {
    'use strict';
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const debounce = (fn, ms) => { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); }; };
    const text = (el, value) => { if (el) el.textContent = value; };

    function initialize() {
        const ui = document.getElementById('gov-request-ui');
        const config = ui ? ui.dataset : {};
        const post = (url, body) => fetch(url, {
            method: 'POST', body,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf || '' },
        }).then(async response => ({ response, data: await response.json().catch(() => ({})) }));

        $$('.cr').forEach(root => root.classList.add('cr-has-js'));

        // Missing pictures fall back to the item's icon.
        const dropBrokenImages = (root = document) => $$('[data-cr-img]', root).forEach(img => {
            const drop = () => img.remove();
            img.addEventListener('error', drop, { once: true });
            if (img.complete && !img.naturalWidth) drop();
        });
        dropBrokenImages();

        // A page that came back with errors moves focus to the summary so it is read first.
        document.querySelector('[data-cr-errors]')?.setAttribute('tabindex', '-1');
        document.querySelector('[data-cr-errors]')?.focus();

        // ---------------------------------------------------------------- draft counts
        function setDraftCount(count) {
            $$('[data-basket-count], [data-cr-draft-count]').forEach(badge => { badge.textContent = count; });
            const nav = document.querySelector('[data-cr-nav-draft]');
            if (nav) {
                nav.hidden = Number(count) < 1;
                const link = nav.querySelector('a');
                if (link?.dataset.labelTemplate) link.setAttribute('aria-label', link.dataset.labelTemplate.replace('__N__', count));
            }
        }

        // ---------------------------------------------------------------- toast with Undo
        const toastEl = document.querySelector('[data-cr-toast]');
        let toastTimer;
        function toast(message, undo) {
            if (!toastEl) return;
            const span = document.createElement('span');
            span.textContent = message;
            toastEl.replaceChildren(span);
            if (undo) {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = config.undoLabel;
                button.addEventListener('click', () => { toastEl.hidden = true; undo(); }, { once: true });
                toastEl.append(button);
            }
            toastEl.hidden = false;
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { toastEl.hidden = true; }, 6000);
        }

        // ---------------------------------------------------------------- the draft, kept current everywhere
        // Every add, change and removal returns fresh HTML for the parts of the page it touches
        // (BasketController::fragments), so the panel, cards and checkout never show a stale draft.
        const checkout = document.querySelector('[data-cr-checkout]');
        function setNeedsReason(on, reader) {
            if (!checkout) return;
            $$('[data-cr-reason-on]', checkout).forEach(el => { el.hidden = !on; });
            $$('[data-cr-reason-off]', checkout).forEach(el => { el.hidden = on; });
            const textarea = checkout.querySelector('[name=justification]');
            if (textarea) textarea.required = on;
            const chip = checkout.querySelector('[data-cr-reader]');
            if (chip) {
                chip.hidden = !on || !reader;
                if (reader && textarea?.dataset.readBy) text(chip.querySelector('[data-cr-reader-name]'), textarea.dataset.readBy.replace('__NAME__', reader));
            }
        }
        function applyDraft(data) {
            if (typeof data.count !== 'undefined') setDraftCount(data.count);
            const parts = data.fragments || {};
            if (parts.drawer) {
                const drawer = document.querySelector('[data-cr-drawer]');
                if (drawer) drawer.innerHTML = parts.drawer;
            }
            if (parts.item && parts.item_id) {
                const card = document.getElementById(parts.item_id);
                if (card) card.outerHTML = parts.item;
            }
            ['lines', 'summary', 'groups'].forEach(key => {
                if (parts[key]) $$(`[data-cr-fragment="${key}"]`).forEach(el => { el.innerHTML = parts[key]; });
            });
            if (data.reviewed) {
                const reviewed = document.querySelector('[data-cr-reviewed]');
                if (reviewed) reviewed.value = data.reviewed;
            }
            if (typeof data.needs_reason !== 'undefined') setNeedsReason(!!data.needs_reason, data.reader);
            if (data.send_label) text(document.querySelector('[data-cr-send-label]'), data.send_label);
            dropBrokenImages();
            if (data.empty && checkout) window.location.reload();
        }
        // After a part of the page is replaced, put focus back on the same control so keyboard users keep their place.
        function restoreFocus(id, selector) {
            const anchor = id && document.getElementById(id);
            const target = anchor && selector ? anchor.closest('form')?.querySelector(selector) : anchor;
            if (target) target.focus();
        }
        async function draftRequest(url, body, focus) {
            try {
                const { response, data } = await post(url, body);
                if (!response.ok || data.success === false) throw new Error(data.message || config.errorLabel);
                applyDraft(data);
                if (focus) restoreFocus(...focus);
                return data;
            } catch (error) {
                toast(error.message || config.errorLabel);
                // Never replay an uncertain POST automatically.
                return null;
            }
        }
        const addBody = (type, id, qty, context) => {
            const body = new FormData();
            body.append('item_type', type); body.append('item_id', id); body.append('qty', qty);
            if (context) body.append('context', context);
            return body;
        };

        // ---------------------------------------------------------------- add (catalog cards, quick-add, core item pages)
        document.addEventListener('submit', async event => {
            const form = event.target;
            if (!form.matches('[data-cr-add], .ajax-basket-form') || !window.fetch) return;
            event.preventDefault();
            const button = form.querySelector('button[type=submit]');
            if (button.disabled) return;
            const label = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            const added = Number(form.querySelector('[name=qty]')?.value || 1);
            const status = form.querySelector('[data-cr-add-status]');
            const context = form.querySelector('[name=context]')?.value;
            const data = await draftRequest(form.action, new FormData(form));
            if (form.isConnected) { button.innerHTML = label; button.disabled = false; button.removeAttribute('aria-busy'); }
            if (!data) return;
            if (status) status.textContent = data.in_draft || data.message;
            if (form.matches('[data-cr-quick]') && data.line) {
                const pill = document.createElement('span');
                pill.className = 'cr-pill cr-pill--ok';
                pill.textContent = '✓ ' + data.line.qty;
                form.closest('.cr-usual')?.classList.add('cr-usual--in');
                form.replaceWith(pill);
            }
            // Keyboard focus moves to the new quantity, where Add was.
            if (data.line && data.fragments?.item_id) document.getElementById(`cr-qty-catalog-${data.line.id}`)?.focus();
            toast(data.message, data.line ? () => {
                const previous = data.line.qty - added;
                if (previous > 0) {
                    const body = new FormData();
                    body.append('item_id', data.line.id); body.append('qty', previous);
                    if (context) body.append('context', context);
                    draftRequest(config.updateUrl, body);
                } else {
                    const body = new FormData();
                    if (context) body.append('context', context);
                    draftRequest(config.removeUrl.replace('__ID__', data.line.id), body);
                }
            } : null);
        });

        // ---------------------------------------------------------------- quantities and removal
        const saveTimers = new Map();
        function lineInfo(form) {
            const holder = form.closest('[data-cr-item], [data-cr-dline]');
            const key = holder?.dataset.crItem || holder?.dataset.crDline || '';
            const [type, id] = key.split(':');
            return { type, id, context: form.querySelector('[name=context]')?.value };
        }
        function saveLine(form, delay) {
            const input = form.querySelector('[data-cr-line-qty]');
            if (!input || !input.checkValidity()) return;
            clearTimeout(saveTimers.get(input.id));
            saveTimers.set(input.id, setTimeout(() => {
                form.setAttribute('aria-busy', 'true');
                const focused = form.contains(document.activeElement) ? document.activeElement : null;
                const focus = focused ? [input.id, focused === input ? null : `[data-cr-step="${focused.dataset.crStep}"]`] : null;
                draftRequest(config.updateUrl, new FormData(form), focus).finally(() => form.removeAttribute('aria-busy'));
            }, delay));
        }
        async function removeLine(lineId, context, info, qty) {
            const body = new FormData();
            if (context) body.append('context', context);
            const data = await draftRequest(config.removeUrl.replace('__ID__', lineId), body);
            if (!data) return;
            const card = info.type && document.getElementById(`cr-item-${info.type}-${info.id}`);
            card?.querySelector('[data-cr-add] button')?.focus();
            toast(data.message, info.type ? () => draftRequest(config.addUrl, addBody(info.type, info.id, qty, context)) : null);
        }
        document.addEventListener('click', event => {
            const step = event.target.closest('[data-cr-line-form] [data-cr-step]');
            if (!step) return;
            const form = step.closest('[data-cr-line-form]');
            const input = form.querySelector('[data-cr-line-qty]');
            const next = Number(input.value || 0) + Number(step.dataset.crStep);
            if (next < 1) {
                // The checkout stops at 1; its row has its own Remove button.
                if (form.querySelector('[name=context]')?.value === 'checkout') return;
                removeLine(form.dataset.crLineForm, form.querySelector('[name=context]')?.value, lineInfo(form), Number(input.value || 1));
                return;
            }
            input.value = Math.min(Number(input.max || 10000), next);
            saveLine(form, 350);
        });
        document.addEventListener('input', event => {
            const input = event.target.closest('[data-cr-line-qty]');
            if (input) saveLine(input.form, 700);
        });
        document.addEventListener('submit', event => {
            const form = event.target;
            if (form.matches('[data-cr-line-form]') && window.fetch) {
                event.preventDefault();
                saveLine(form, 0);
            } else if (form.matches('[data-cr-remove]') && window.fetch) {
                event.preventDefault();
                const info = lineInfo(form);
                const holder = form.closest('[data-cr-dline], tr');
                const qty = Number(holder?.querySelector('[data-cr-line-qty]')?.value || 1);
                // Undo is offered where the item is known (Request items); checkout rows simply go.
                removeLine(form.action.split('/').pop(), form.querySelector('[name=context]')?.value, info, qty);
            }
        });

        // ---------------------------------------------------------------- Request items: live search, kinds, Cards / List
        const live = document.querySelector('[data-cr-live]');
        if (live && window.fetch) {
            const target = document.querySelector(live.dataset.crLive);
            live.querySelector('[data-cr-live-submit]')?.setAttribute('hidden', '');
            let controller;
            const updateCounts = () => {
                try {
                    const counts = JSON.parse(target.querySelector('template[data-cr-counts]')?.innerHTML || '{}');
                    Object.entries(counts).forEach(([type, count]) => text(live.querySelector(`[data-cr-type-count="${type}"]`), count));
                } catch (_) { /* counts are a convenience */ }
            };
            const refresh = async (extra = {}) => {
                const url = new URL(live.action);
                new FormData(live).forEach((value, key) => { if (value) url.searchParams.set(key, value); });
                Object.entries(extra).forEach(([key, value]) => url.searchParams.set(key, value));
                window.history.replaceState(null, '', url);
                controller?.abort();
                controller = new AbortController();
                target.setAttribute('aria-busy', 'true');
                try {
                    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
                    if (response.ok) { target.innerHTML = await response.text(); dropBrokenImages(target); updateCounts(); }
                } catch (_) { /* a newer search replaced this one */ } finally { target.setAttribute('aria-busy', 'false'); }
                // The view is kept by the server for the session; the address bar need not carry it.
                if (extra.view) { url.searchParams.delete('view'); window.history.replaceState(null, '', url); }
            };
            const delayed = debounce(() => refresh(), 300);
            live.addEventListener('input', event => { if (event.target.name === 'q') delayed(); });
            live.addEventListener('change', event => { if (event.target.name !== 'q') refresh(); });
            live.addEventListener('submit', event => { event.preventDefault(); refresh(); });
            target.addEventListener('click', event => {
                const view = event.target.closest('[data-cr-view]');
                if (view) {
                    event.preventDefault();
                    refresh({ view: view.dataset.crView }).then(() => target.querySelector(`[data-cr-view="${view.dataset.crView}"]`)?.focus());
                    return;
                }
                const link = event.target.closest('.pagination a');
                if (!link) return;
                event.preventDefault();
                refresh({ page: new URL(link.href).searchParams.get('page') }).then(() => target.scrollIntoView({ block: 'start' }));
            });
            // "/" jumps to search, as on most catalogue sites.
            document.addEventListener('keydown', event => {
                if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
                if (/^(input|textarea|select)$/i.test(document.activeElement?.tagName || '') || document.activeElement?.isContentEditable) return;
                event.preventDefault();
                live.querySelector('[name=q]')?.focus();
            });
        }

        // ---------------------------------------------------------------- Checkout: 1 Items · 2 Details · 3 Review and send
        const send = document.querySelector('[data-cr-send]');
        if (checkout && send) {
            $$('[data-cr-js]', checkout).forEach(el => { el.hidden = false; });
            const panels = $$('[data-cr-panel]', checkout);
            const markers = $$('[data-cr-step-marker]', checkout);
            let current = 1;
            const fill = () => {
                const value = name => send.querySelector(`[name="${name}"]`)?.value.trim() || '';
                const purpose = checkout.querySelector('[data-cr-review=purpose]');
                text(purpose, value('purpose') || purpose.dataset.empty);
                const kind = send.querySelector('input[name=request_type]:checked');
                text(checkout.querySelector('[data-cr-review=kind]'), kind?.closest('label')?.querySelector('.gs-choice__label')?.textContent.trim() || '');
                const needed = checkout.querySelector('[data-cr-review=needed]');
                const when = send.querySelector('input[name=needed]:checked')?.value || 'none';
                const date = value('required_by_date');
                text(needed, when === 'date' && date
                    ? new Date(date + 'T00:00').toLocaleDateString(document.documentElement.lang || undefined, { day: 'numeric', month: 'short', year: 'numeric' })
                    : (when === 'week' ? needed.dataset.week : needed.dataset.none));
                const where = send.querySelector('input[name=delivery_location_id]:checked');
                text(checkout.querySelector('[data-cr-review=where]'), where?.closest('label')?.textContent.trim() || '');
                const cost = value('cost_center');
                text(checkout.querySelector('[data-cr-review=cost]'), cost);
                checkout.querySelector('[data-cr-review-wrap=cost]').hidden = !cost;
                const why = value('justification');
                text(checkout.querySelector('[data-cr-review=why]'), why);
                checkout.querySelector('[data-cr-review-wrap=why]').hidden = !why;
            };
            const show = (n, focus = true) => {
                current = n;
                panels.forEach(panel => { panel.hidden = Number(panel.dataset.crPanel) !== n; });
                markers.forEach(marker => {
                    const k = Number(marker.dataset.crStepMarker);
                    marker.classList.toggle('cr-steps__step--done', k < n);
                    marker.classList.toggle('cr-steps__step--current', k === n);
                    marker.classList.toggle('cr-steps__step--upcoming', k > n);
                    const link = marker.querySelector('a');
                    k === n ? link.setAttribute('aria-current', 'step') : link.removeAttribute('aria-current');
                    k > n ? link.setAttribute('aria-disabled', 'true') : link.removeAttribute('aria-disabled');
                });
                if (n === 3) fill();
                if (focus) {
                    const panel = panels.find(p => Number(p.dataset.crPanel) === n);
                    const alert = panel.querySelector('[role=alert]:not([hidden]), .gs-alert--warning');
                    (alert || panel).setAttribute('tabindex', '-1');
                    (alert || panel).focus({ preventScroll: true });
                    checkout.scrollIntoView({ block: 'start' });
                }
            };
            // Step 2 checks itself: a list of what to fix at the top, each linked to its field.
            const detailsValid = () => {
                const box = checkout.querySelector('[data-cr-step-errors="2"]');
                const list = box?.querySelector('[data-cr-error-list]');
                const fields = [send.querySelector('[name=purpose]'), send.querySelector('[name=justification]'), send.querySelector('[name=required_by_date]')];
                const problems = fields.filter(field => field && !field.disabled && field.required && !field.value.trim());
                const dateField = send.querySelector('[name=required_by_date]');
                if (send.querySelector('input[name=needed]:checked')?.value === 'date' && dateField && !dateField.value && !problems.includes(dateField)) problems.push(dateField);
                fields.forEach(field => field && (problems.includes(field) ? field.setAttribute('aria-invalid', 'true') : field.removeAttribute('aria-invalid')));
                if (!problems.length) { if (box) box.hidden = true; return true; }
                list?.replaceChildren(...problems.map(field => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = '#' + field.id; a.textContent = field.dataset.crMessage;
                    a.addEventListener('click', e => { e.preventDefault(); field.focus(); });
                    li.append(a);
                    return li;
                }));
                if (box) { box.hidden = false; box.setAttribute('tabindex', '-1'); box.focus(); }
                return false;
            };
            checkout.addEventListener('click', event => {
                const next = event.target.closest('[data-cr-next]');
                const jump = event.target.closest('[data-cr-goto]');
                if (next) {
                    event.preventDefault();
                    const n = Number(next.dataset.crNext);
                    if (n === 3 && current === 2 && !detailsValid()) return;
                    show(n);
                } else if (jump) {
                    event.preventDefault();
                    const n = Number(jump.dataset.crGoto);
                    if (n < current) show(n);
                }
            });
            send.addEventListener('submit', event => {
                if (current < 3) {
                    // Enter in a field moves on a step; it never sends from Details.
                    event.preventDefault();
                    if (current === 2 && !detailsValid()) return;
                    show(current + 1);
                    return;
                }
                const declared = send.querySelector('[name=declared]');
                if (!declared.checked) {
                    event.preventDefault();
                    declared.setAttribute('aria-invalid', 'true');
                    declared.closest('.cr-declare')?.classList.add('cr-declare--error');
                    declared.setCustomValidity(declared.dataset.crMessage);
                    declared.reportValidity();
                    declared.setCustomValidity('');
                    declared.focus();
                    return;
                }
                const button = send.querySelector('[data-cr-submit]');
                if (button.dataset.sent) { event.preventDefault(); return; }
                // One press sends once; the server's one-time token covers the rest.
                button.dataset.sent = '1';
                button.setAttribute('aria-busy', 'true');
                setTimeout(() => { button.disabled = true; text(button.querySelector('[data-cr-send-label]'), button.dataset.sending); }, 0);
            });
            send.addEventListener('change', event => {
                if (event.target.name === 'declared' && event.target.checked) {
                    event.target.removeAttribute('aria-invalid');
                    event.target.closest('.cr-declare')?.classList.remove('cr-declare--error');
                }
            });
            const start = Number(checkout.dataset.crStartStep || 1);
            show(start, start > 1);
        }

        // ---------------------------------------------------------------- sections that belong to one choice
        // data-cr-when="name=value" shows with that choice; data-cr-unless hides with it. Scoped to the form.
        function syncChoices() {
            $$('[data-cr-when], [data-cr-unless]').forEach(section => {
                const rule = (section.dataset.crWhen || section.dataset.crUnless).split('=');
                const scope = section.closest('form, [data-cr-decision]') || document;
                const checked = scope.querySelector(`input[name="${rule[0]}"]:checked`);
                let on = !!checked && checked.value === rule[1];
                if (section.dataset.crUnless) on = !on;
                section.hidden = !on;
                $$('input, select, textarea', section).forEach(field => { field.disabled = !on; });
            });
        }
        document.addEventListener('change', event => { if (event.target.type === 'radio') syncChoices(); });
        syncChoices();

        // ---------------------------------------------------------------- Decide
        const decision = document.querySelector('[data-cr-decision-form]');
        if (decision) {
            const summary = decision.querySelector('[data-cr-decision-summary]');
            const update = () => {
                const lines = $$('[data-cr-decision]', decision);
                let approved = 0;
                lines.forEach(line => {
                    const choice = line.querySelector('input[type=radio]:checked')?.value;
                    if (choice !== 'decline') approved++;
                    const reason = line.querySelector('[data-cr-reason]');
                    const prompt = line.querySelector('[data-cr-reason-prompt]');
                    if (reason && choice && choice !== 'as_asked') reason.open = true;
                    if (prompt) prompt.hidden = !choice || choice === 'as_asked';
                });
                if (summary) {
                    summary.textContent = approved === 0 ? summary.dataset.none
                        : summary.dataset.template.replace('__A__', approved).replace('__T__', lines.length);
                }
            };
            decision.addEventListener('change', update);
            update();
        }

        // ---------------------------------------------------------------- Issue items
        const issue = document.querySelector('[data-cr-issue-form]');
        if (issue) {
            const summary = issue.querySelector('[data-cr-issue-summary]');
            const list = issue.querySelector('[data-cr-issue-list]');
            const submit = issue.querySelector('[data-cr-issue-submit]');
            const update = () => {
                let count = 0;
                const rows = [];
                $$('[data-cr-issue-line]', issue).forEach(line => {
                    const name = issue.querySelector(`[data-cr-alt-chosen="${line.dataset.lineId}"]:not([hidden])`)?.textContent || line.dataset.name;
                    const bulk = line.querySelector('[data-cr-bulk]');
                    if (bulk) {
                        const qty = Math.max(0, Math.min(Number(bulk.max || Infinity), Math.floor(Number(bulk.value || 0))));
                        if (qty > 0) rows.push([name, qty]);
                        count += qty;
                    }
                    const group = line.querySelector('[data-cr-serials]');
                    if (group) {
                        const boxes = $$('input[type=checkbox]', group);
                        const picked = boxes.filter(box => box.checked);
                        // Never more serials than are still to issue.
                        boxes.forEach(box => { box.disabled = !box.checked && picked.length >= Number(group.dataset.remaining); });
                        const counter = line.querySelector('[data-cr-picked]');
                        if (counter) {
                            counter.textContent = counter.dataset.template.replace('__P__', picked.length);
                            counter.classList.toggle('cr-pill--ok', picked.length > 0);
                        }
                        if (picked.length) rows.push([`${name} (${picked.map(box => box.dataset.label).join(', ')})`, picked.length]);
                        count += picked.length;
                    }
                });
                if (summary) summary.textContent = count > 0 ? issue.dataset.summary.replace('__N__', count) : issue.dataset.summaryNone;
                if (submit && !issue.dataset.sending) submit.disabled = count === 0;
                if (list) {
                    list.replaceChildren(...(rows.length ? rows : [null]).map(row => {
                        const li = document.createElement('li');
                        if (!row) { li.className = 'cr-receipt__empty'; li.textContent = issue.dataset.summaryNone; return li; }
                        const icon = document.createElement('i');
                        icon.className = 'fas fa-check'; icon.setAttribute('aria-hidden', 'true');
                        li.append(icon, document.createTextNode(list.dataset.template.replace('__ITEM__', row[0]).replace('__QTY__', row[1])));
                        return li;
                    }));
                }
            };
            issue.addEventListener('input', update);
            issue.addEventListener('change', update);
            update();

            // − / + / "All": stay within 0 … what is still to issue.
            issue.addEventListener('click', event => {
                const button = event.target.closest('[data-cr-qty-step], [data-cr-qty-fill]');
                if (!button) return;
                const input = document.getElementById(button.getAttribute('aria-controls'));
                if (!input) return;
                const next = button.dataset.crQtyFill !== undefined
                    ? Number(button.dataset.crQtyFill)
                    : Number(input.value || 0) + Number(button.dataset.crQtyStep);
                input.value = Math.max(Number(input.min || 0), Math.min(Number(input.max || next), next));
                update();
            });

            // One press only: a second submit while the first is on its way is dropped (the server also refuses a stale page).
            issue.addEventListener('submit', event => {
                if (issue.dataset.sending) { event.preventDefault(); return; }
                issue.dataset.sending = '1';
                if (submit) {
                    submit.setAttribute('aria-busy', 'true');
                    submit.disabled = true;
                    submit.textContent = issue.dataset.submitting;
                }
            });
            window.addEventListener('pageshow', event => {
                // Back from the next page (bfcache): the form is usable again.
                if (event.persisted && issue.dataset.sending) { delete issue.dataset.sending; window.location.reload(); }
            });

            $$('[data-cr-scan]', issue).forEach(scan => {
                const group = issue.querySelector(`[data-cr-serials="${scan.dataset.crScan}"]`);
                const filter = () => {
                    const needle = scan.value.trim().toLowerCase();
                    $$('li[data-tag]', group).forEach(row => { row.hidden = needle !== '' && !row.dataset.tag.includes(needle); });
                };
                scan.addEventListener('input', filter);
                scan.addEventListener('keydown', event => {
                    if (event.key !== 'Enter') return;
                    event.preventDefault();
                    const needle = scan.value.trim().toLowerCase();
                    // An exact tag or serial, else a partial match only when it is the one row: never guess between items.
                    const rows = $$('li[data-tag]', group);
                    const partial = needle ? rows.filter(li => li.dataset.tag.includes(needle)) : [];
                    const row = rows.find(li => needle && li.dataset.tag.split(' ').includes(needle)) || (partial.length === 1 ? partial[0] : null);
                    const box = row?.querySelector('input[type=checkbox]');
                    if (box && !box.disabled) {
                        box.checked = true; scan.value = ''; filter(); update();
                    } else {
                        scan.setCustomValidity(scan.dataset.noMatch); scan.reportValidity(); scan.setCustomValidity('');
                    }
                });
            });

            // Alternatives: search this store; dearer, other-category or short items are shown but cannot be chosen.
            $$('[data-cr-alt-search]', issue).forEach(search => {
                const list = issue.querySelector(`[data-cr-alt-results="${search.dataset.line}"]`);
                const chosen = issue.querySelector(`[data-cr-alt-chosen="${search.dataset.line}"]`);
                const run = debounce(async () => {
                    const term = search.value.trim();
                    if (term.length < 2) { list.replaceChildren(); return; }
                    const url = new URL(search.dataset.crAltSearch, window.location.href);
                    url.searchParams.set('q', term);
                    const items = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).catch(() => []);
                    list.replaceChildren();
                    if (!items.length) { const li = document.createElement('li'); li.className = 'cr-meta'; li.textContent = search.dataset.none; list.append(li); return; }
                    items.forEach(item => {
                        const li = document.createElement('li');
                        const label = document.createElement('label');
                        label.className = 'cr-radio' + (item.allowed ? '' : ' cr-radio--off');
                        const input = document.createElement('input');
                        input.type = 'radio'; input.name = `substitutions[${search.dataset.line}]`; input.value = item.id; input.disabled = !item.allowed;
                        input.addEventListener('change', () => { if (chosen) { chosen.hidden = false; chosen.textContent = config.chosenLabel.replace('__ITEM__', item.name); } });
                        const span = document.createElement('span');
                        span.textContent = [item.name, item.free_label, item.price_label, item.note].filter(Boolean).join(' · ');
                        label.append(input, span);
                        li.append(label);
                        list.append(li);
                    });
                }, 300);
                search.addEventListener('input', run);
            });
            issue.addEventListener('change', event => {
                if (event.target.name?.startsWith('substitutions[') && event.target.value === '') {
                    const chosen = issue.querySelector(`[data-cr-alt-chosen="${event.target.name.match(/\d+/)[0]}"]`);
                    if (chosen) chosen.hidden = true;
                }
            });
        }
        document.addEventListener('click', event => {
            const keep = event.target.closest('[data-cr-close-details]');
            if (keep) { const details = keep.closest('details'); if (details) { details.open = false; details.querySelector('summary')?.focus(); } }
            if (event.target.closest('[data-cr-print]')) window.print();
        });

        // ---------------------------------------------------------------- Request rules: live example and item search
        $$('[data-cr-rule-form]').forEach(form => {
            const examples = JSON.parse(form.dataset.examples || '{}');
            const list = form.querySelector('[data-cr-example]');
            const update = () => {
                const policy = form.querySelector('input[name=policy_name]:checked')?.value;
                const qty = form.querySelector('[name=threshold_qty]')?.value;
                const value = form.querySelector('[name=threshold_value]')?.value;
                const lines = [examples[{ AUTO_APPROVE: 'auto', PRIMARY_AND_FINAL: 'two' }[policy] || 'one']];
                if (policy !== 'PRIMARY_AND_FINAL') {
                    if (qty) lines.push(examples.qty.replace('__Q__', qty));
                    if (value) lines.push(examples.value.replace('__V__', Number(value).toLocaleString()));
                }
                list?.replaceChildren(...lines.map(line => { const li = document.createElement('li'); li.textContent = line; return li; }));
            };
            form.addEventListener('input', update);
            form.addEventListener('change', update);
            update();
        });
        const itemSearch = document.querySelector('[data-cr-item-search]');
        if (itemSearch) {
            const form = itemSearch.closest('form');
            const list = form.querySelector('[data-cr-item-results]');
            const run = debounce(async () => {
                const term = itemSearch.value.trim();
                if (term.length < 2) return;
                const url = new URL(itemSearch.dataset.crItemSearch, window.location.href);
                url.searchParams.set('q', term);
                url.searchParams.set('type', form.querySelector('[data-cr-item-type]').value);
                const items = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).catch(() => []);
                list.replaceChildren(...(items.length ? items.map(item => {
                    const li = document.createElement('li');
                    const label = document.createElement('label');
                    label.className = 'cr-radio';
                    const input = document.createElement('input');
                    input.type = 'radio'; input.name = 'target_id'; input.value = item.id; input.required = true;
                    const span = document.createElement('span');
                    span.textContent = [item.name, item.category].filter(Boolean).join(' · ');
                    label.append(input, span);
                    li.append(label);
                    return li;
                }) : [Object.assign(document.createElement('li'), { className: 'cr-meta', textContent: itemSearch.dataset.none })]));
            }, 300);
            itemSearch.addEventListener('input', run);
            form.querySelector('[data-cr-item-type]').addEventListener('change', run);
        }

        // ---------------------------------------------------------------- core item pages: "Add to draft"
        const match = window.location.pathname.match(/^\/(consumables|accessories|hardware)\/(\d+)$/);
        const footer = document.querySelector('.side-box .box-footer');
        if (ui && match && footer && !document.getElementById('add-to-basket-btn-container')) {
            const form = document.createElement('form');
            form.className = 'ajax-basket-form'; form.id = 'add-to-basket-btn-container'; form.action = config.addUrl; form.method = 'POST';
            const type = { consumables: 'consumable', accessories: 'accessory', hardware: 'asset' }[match[1]];
            for (const [name, value] of [['_token', config.csrf], ['item_type', type], ['item_id', match[2]]]) {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
            }
            const button = document.createElement('button');
            button.type = 'submit'; button.className = 'btn btn-primary btn-sm btn-block'; button.textContent = config.addLabel;
            const status = document.createElement('p'); status.setAttribute('role', 'status'); status.dataset.crAddStatus = '';
            form.append(button, status);
            footer.append(form);
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
