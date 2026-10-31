<script nonce="{{ csrf_token() }}">
document.addEventListener('DOMContentLoaded', function () {
    var GS = window.GS || {};
    var panelOf = function (el) { return el.closest('[data-skin]') || document.documentElement; };
    var each = function (root, selector, fn) { Array.prototype.forEach.call(root.querySelectorAll(selector), fn); };
    var json = function (text, fallback) { try { return JSON.parse(text); } catch (e) { return fallback; } };
    var pressed = function (root, selector, value) {
        each(root, selector, function (btn) { btn.setAttribute('aria-pressed', btn.getAttribute('data-value') === value ? 'true' : 'false'); });
    };
    var resize = function () { setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 0); };
    var copy = function (btn, text) {
        if (navigator.clipboard) { navigator.clipboard.writeText(text).catch(function () {}); }
        var label = btn.getAttribute('data-gs-lab-copied');
        if (!label || btn.getAttribute('data-gs-lab-busy')) { return; }
        var original = btn.innerHTML;
        btn.setAttribute('data-gs-lab-busy', '1');
        btn.textContent = label;
        setTimeout(function () { btn.innerHTML = original; btn.removeAttribute('data-gs-lab-busy'); }, 1600);
    };

    // Charts: palette and frame from the panel's own tokens; redrawn by the gsTheme plugin on mode change.
    if (window.Chart) {
        document.querySelectorAll('.js-gs-lab-chart').forEach(function (canvas) {
            var data = JSON.parse(canvas.getAttribute('data-chart'));
            var type = canvas.getAttribute('data-type');
            var colors = GS.theme ? GS.theme.palette(data.labels.length, canvas) : [];
            var dataset = { label: 'Assets by division', data: data.series, backgroundColor: colors };
            if (type === 'line') {
                dataset.backgroundColor = 'transparent';
                dataset.borderColor = colors[0];
                dataset.pointBackgroundColor = colors[0];
            }
            new window.Chart(canvas, {
                type: type,
                data: { labels: data.labels, datasets: [dataset] },
                options: { responsive: true, maintainAspectRatio: false, legend: { display: type === 'pie' }, plugins: { gsTheme: { recolorData: type === 'line' ? false : 'all' } } }
            });
        });
    }

    // select2 / datepicker popups render inside the panel so they inherit its theme.
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.js-gs-lab-select2').each(function () {
            jQuery(this).select2({ dropdownParent: jQuery(panelOf(this)), width: '100%' });
        });
    }
    if (window.jQuery && jQuery.fn.datepicker) {
        jQuery('.js-gs-lab-date').each(function () {
            jQuery(this).datepicker({ container: '#' + panelOf(this).id, format: 'yyyy-mm-dd', autoclose: true });
        });
    }

    // ---- Workspace: mode, width, section, token names and variants switch in the browser ----
    var ws = document.querySelector('[data-gs-lab="workspace"]');
    if (ws) {
        var state = json(ws.getAttribute('data-gs-lab-state'), {});
        var defaults = json(ws.getAttribute('data-gs-lab-defaults'), {});
        var overrides = json(ws.getAttribute('data-gs-lab-overrides'), {});
        var stage = ws.querySelector('[data-gs-lab-stage]');
        var tokensBox = ws.querySelector('[data-gs-lab-tokens]');
        var changes = ws.querySelector('[data-gs-lab-changes]');
        var title = ws.querySelector('[data-gs-lab-changes-title]');
        var shell = ws.querySelector('[data-gs-lab-shell]');
        var DEFAULT_STATE = { mode: 'light', section: 'overview', width: 'desktop' };

        var query = function (withVariants, bn) {
            var params = new URLSearchParams();
            Object.keys(DEFAULT_STATE).forEach(function (key) {
                if (state[key] !== DEFAULT_STATE[key]) { params.set(key, state[key]); }
            });
            if (state.tokens) { params.set('tokens', '1'); }
            if (bn === '1') { params.set('bn', '1'); }
            if (withVariants) {
                Object.keys(overrides).forEach(function (name) { params.set('variant[' + name + ']', overrides[name]); });
            }
            var text = params.toString();
            return text ? '?' + text : '';
        };
        var changeLines = function () {
            var names = Object.keys(overrides);
            return names.map(function (name, i) { return '"' + name + '": "' + overrides[name] + '"' + (i < names.length - 1 ? ',' : ''); });
        };

        var apply = function () {
            ws.setAttribute('data-section', state.section);
            stage.setAttribute('data-mode', state.mode);
            stage.setAttribute('data-width', state.width);
            ws.querySelector('[data-gs-lab-width-group]').hidden = state.mode === 'split';
            ['mode', 'width', 'section'].forEach(function (key) { pressed(ws, '[data-gs-lab-set="' + key + '"]', state[key]); });
            document.body.classList.toggle('gs-lab-show-tokens', !!state.tokens);
            tokensBox.checked = !!state.tokens;

            each(ws, '[data-gs-lab-variants]', function (panel) {
                Object.keys(defaults).forEach(function (name) {
                    panel.setAttribute('data-gs-' + name.replace(/_/g, '-'), overrides[name] || defaults[name]);
                });
            });
            each(ws, '[data-gs-lab-variant]', function (row) {
                var name = row.getAttribute('data-gs-lab-variant');
                var changed = Object.prototype.hasOwnProperty.call(overrides, name);
                pressed(row, '[data-gs-lab-set-variant]', overrides[name] || defaults[name]);
                row.querySelector('[data-gs-lab-default-note]').hidden = changed;
                row.querySelector('[data-gs-lab-reset]').hidden = !changed;
            });

            var count = Object.keys(overrides).length;
            changes.hidden = count === 0;
            title.textContent = count === 1 ? title.getAttribute('data-one') : title.getAttribute('data-many').replace('__N__', count);
            ws.querySelector('[data-gs-lab-json]').textContent = changeLines().join('\n');

            var shellParams = new URLSearchParams({ gs_preview: ws.getAttribute('data-theme-key'), gs_mode: state.mode === 'dark' ? 'dark' : 'light' });
            Object.keys(overrides).forEach(function (name) { shellParams.set('gs_variant[' + name + ']', overrides[name]); });
            shell.setAttribute('href', shell.getAttribute('data-gs-lab-shell') + '?' + shellParams.toString());

            each(ws, '[data-gs-lab-keep-state]', function (link) {
                var bn = link.hasAttribute('data-bn') ? link.getAttribute('data-bn') : ws.getAttribute('data-bn');
                var url = new URL(link.href, window.location.href);
                link.href = url.origin + url.pathname + query(link.getAttribute('data-gs-lab-keep-state') === 'all', bn);
            });
            history.replaceState(null, '', window.location.pathname + query(true, ws.getAttribute('data-bn')));
        };

        ws.addEventListener('click', function (event) {
            var set = event.target.closest('[data-gs-lab-set]');
            var variant = event.target.closest('[data-gs-lab-set-variant]');
            var reset = event.target.closest('[data-gs-lab-reset]');
            if (set) {
                state[set.getAttribute('data-gs-lab-set')] = set.getAttribute('data-value');
                apply();
                resize();
            } else if (variant) {
                var name = variant.getAttribute('data-gs-lab-set-variant');
                var value = variant.getAttribute('data-value');
                if (value === defaults[name]) { delete overrides[name]; } else { overrides[name] = value; }
                apply();
            } else if (reset) {
                delete overrides[reset.getAttribute('data-gs-lab-reset')];
                apply();
            } else if (event.target.closest('[data-gs-lab-reset-all]')) {
                overrides = {};
                apply();
            } else if (event.target.closest('[data-gs-lab-copy-json]')) {
                copy(event.target.closest('[data-gs-lab-copy-json]'), changeLines().join('\n'));
            } else if (event.target.closest('[data-gs-lab-copy]')) {
                var btn = event.target.closest('[data-gs-lab-copy]');
                copy(btn, btn.getAttribute('data-gs-lab-copy'));
            }
        });
        tokensBox.addEventListener('change', function () { state.tokens = tokensBox.checked; apply(); });

        var find = ws.querySelector('[data-gs-lab-find]');
        find.addEventListener('input', function () {
            var term = find.value.trim().toLowerCase();
            var shown = 0;
            each(ws, '[data-gs-lab-theme-item]', function (item) {
                item.hidden = term !== '' && item.getAttribute('data-search').indexOf(term) === -1;
                if (!item.hidden) { shown++; }
            });
            ws.querySelector('[data-gs-lab-no-match]').hidden = shown > 0;
        });

        apply();
    }

    // ---- Compare: pick themes and mode; the glance table dims rows where every shown theme agrees ----
    var cmp = document.querySelector('[data-gs-lab="compare"]');
    if (cmp) {
        var picked = json(cmp.getAttribute('data-gs-lab-picked'), []);
        var order = Array.prototype.map.call(cmp.querySelectorAll('[data-gs-lab-pick]'), function (btn) { return btn.getAttribute('data-gs-lab-pick'); });
        var applyCompare = function () {
            var mode = cmp.getAttribute('data-mode');
            pressed(cmp, '[data-gs-lab-set="mode"]', mode);
            each(cmp, '[data-gs-lab-pick]', function (btn) {
                btn.setAttribute('aria-pressed', picked.indexOf(btn.getAttribute('data-gs-lab-pick')) >= 0 ? 'true' : 'false');
            });
            each(cmp, '[data-gs-lab-card]', function (card) { card.hidden = picked.indexOf(card.getAttribute('data-gs-lab-card')) === -1; });
            each(cmp, '[data-gs-lab-col]', function (cell) { cell.hidden = picked.indexOf(cell.getAttribute('data-gs-lab-col')) === -1; });
            each(cmp, '[data-gs-lab-glance-row]', function (row) {
                var values = Array.prototype.filter.call(row.querySelectorAll('td'), function (td) { return !td.hidden; })
                    .map(function (td) { return td.getAttribute('data-value'); });
                row.classList.toggle('is-same', values.length > 1 && values.every(function (v) { return v === values[0]; }));
            });
            var count = cmp.querySelector('[data-gs-lab-count]');
            count.textContent = count.getAttribute('data-template').replace('__S__', picked.length).replace('__T__', order.length);
            var params = new URLSearchParams();
            picked.forEach(function (key) { params.append('themes[]', key); });
            if (mode !== 'light') { params.set('mode', mode); }
            history.replaceState(null, '', window.location.pathname + '?' + params.toString());
        };
        cmp.addEventListener('click', function (event) {
            var pick = event.target.closest('[data-gs-lab-pick]');
            var set = event.target.closest('[data-gs-lab-set]');
            if (pick) {
                var key = pick.getAttribute('data-gs-lab-pick');
                if (picked.indexOf(key) >= 0) {
                    if (picked.length > 1) { picked = picked.filter(function (k) { return k !== key; }); }
                } else {
                    picked = order.filter(function (k) { return k === key || picked.indexOf(k) >= 0; });
                }
                applyCompare();
            } else if (set) {
                cmp.setAttribute('data-mode', set.getAttribute('data-value'));
                applyCompare();
            }
        });
        applyCompare();
    }

    // ---- Health report: mode, "only problems" and the side panel for the selected cell ----
    var health = document.querySelector('[data-gs-lab="health"]');
    if (health) {
        var details = json((document.getElementById('gs-lab-health-data') || {}).textContent || '{}', {});
        var selected = health.getAttribute('data-gs-lab-selected');
        var field = function (name, text) { each(health, '[data-gs-lab-d="' + name + '"]', function (el) { el.textContent = text; }); };
        var paint = function (name, fg, bg) {
            var sample = health.querySelector('[data-gs-lab-sample="' + name + '"]');
            if (sample) { sample.style.color = fg; sample.style.backgroundColor = bg; }
        };
        var show = function (id) {
            var d = details[id];
            if (!d) { return; }
            selected = id;
            each(health, '[data-gs-lab-cell]', function (cell) { cell.setAttribute('aria-pressed', cell.getAttribute('data-gs-lab-cell') === id ? 'true' : 'false'); });
            field('context', d.theme + ' · ' + d.mode);
            field('name', d.name);
            field('why', d.why);
            field('pair', d.pair);
            field('now', d.now);
            field('fg', d.fg);
            field('bg', d.bg);
            var verdict = health.querySelector('[data-gs-lab-d="verdict"]');
            verdict.textContent = d.verdict;
            verdict.classList.toggle('gs-tag--success', d.pass);
            verdict.classList.toggle('gs-tag--danger', !d.pass);
            each(health, '[data-gs-lab-d-swatch]', function (el) { el.style.backgroundColor = d[el.getAttribute('data-gs-lab-d-swatch')]; });
            paint('now', d.fg, d.bg);
            var fix = health.querySelector('[data-gs-lab-fix]');
            fix.hidden = !d.fix;
            if (d.fix) {
                field('fixTitle', d.fix.title);
                field('fixText', d.fix.text);
                field('fixFile', d.fix.file);
                field('fixJson', d.fix.json);
                paint('fix', d.fix.fg, d.fix.bg);
            }
            health.querySelector('[data-gs-lab-d-href="workspace"]').setAttribute('href', d.workspace);
        };
        var applyHealth = function () {
            var mode = health.getAttribute('data-mode');
            var only = health.getAttribute('data-show') === 'problems';
            pressed(health, '[data-gs-lab-set="mode"]', mode);
            pressed(health, '[data-gs-lab-set="show"]', health.getAttribute('data-show'));
            each(health, '.gs-lab-hmatrix', function (table) {
                var visible = 0;
                each(table, 'tbody tr', function (row) {
                    row.hidden = only && row.getAttribute('data-gs-lab-failing') === '0';
                    if (!row.hidden) { visible++; }
                });
                table.parentNode.querySelector('[data-gs-lab-all-clear]').hidden = visible > 0;
            });
            // Keep the same theme and check when the mode changes.
            if (selected && selected.indexOf(mode + '|') !== 0) {
                var moved = mode + selected.slice(selected.indexOf('|'));
                if (details[moved]) { selected = moved; }
            }
            show(selected);
            var params = new URLSearchParams();
            if (mode !== 'light') { params.set('mode', mode); }
            if (only) { params.set('show', 'problems'); }
            var text = params.toString();
            history.replaceState(null, '', window.location.pathname + (text ? '?' + text : ''));
        };
        health.addEventListener('click', function (event) {
            var cell = event.target.closest('[data-gs-lab-cell]');
            var set = event.target.closest('[data-gs-lab-set]');
            if (cell) {
                show(cell.getAttribute('data-gs-lab-cell'));
            } else if (set) {
                health.setAttribute('data-' + set.getAttribute('data-gs-lab-set'), set.getAttribute('data-value'));
                applyHealth();
            } else if (event.target.closest('[data-gs-lab-copy-fix]') && details[selected] && details[selected].fix) {
                copy(event.target.closest('[data-gs-lab-copy-fix]'), details[selected].fix.json);
            }
        });
        applyHealth();
    }
});
</script>
