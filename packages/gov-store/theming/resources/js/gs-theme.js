/*
 * gs-theme.js — token reader, mode sync with Snipe-IT's toggle, Chart.js v2 plugin, notices.
 * No colour literals: every colour is read from --gs-* tokens.
 */
(function (window, document) {
    'use strict';

    var html = document.documentElement;
    var configEl = document.getElementById('gs-theme-config');
    var config = {};
    try { config = configEl ? JSON.parse(configEl.textContent || '{}') : {}; } catch (e) { config = {}; }
    var i18n = config.i18n || {};
    var listeners = [];

    function token(name, el) {
        var value = window.getComputedStyle(el || html).getPropertyValue('--gs-' + name);
        return value ? value.trim() : '';
    }

    function palette(n, el) {
        var colors = [];
        for (var i = 0; i < (n || 10); i++) {
            colors.push(token('chart-' + ((i % 10) + 1), el));
        }
        return colors;
    }

    var theme = {
        get key() { return html.getAttribute('data-skin'); },
        get mode() { return html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; },
        get savedMode() { return html.getAttribute('data-gs-mode') || 'system'; },
        token: token,
        palette: palette,
        on: function (event, fn) { if (event === 'change' && typeof fn === 'function') { listeners.push(fn); } },
        config: config
    };
    window.GS = window.GS || {};
    window.GS.theme = theme;

    // ---- change events: data-skin / data-theme mutations ----
    var last = { key: theme.key, mode: theme.mode };
    new MutationObserver(function () {
        var now = { key: theme.key, mode: theme.mode };
        if (now.key === last.key && now.mode === last.mode) { return; }
        var modeChanged = now.mode !== last.mode;
        last = now;
        listeners.forEach(function (fn) { try { fn(now); } catch (e) { window.console && console.error(e); } });
        document.dispatchEvent(new CustomEvent('gs:theme-change', { detail: now }));
        if (modeChanged) { document.dispatchEvent(new CustomEvent('gs:mode-change', { detail: now })); }
        refreshCharts();
    }).observe(html, { attributes: true, attributeFilter: ['data-skin', 'data-theme'] });

    // ---- Snipe-IT light/dark toggle → save the explicit mode server-side ----
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest && event.target.closest('[data-theme-toggle]');
        if (!toggle) { return; }
        // Snipe-IT's own handler flips data-theme first; read the result on the next tick.
        window.setTimeout(function () {
            var mode = theme.mode;
            html.setAttribute('data-gs-mode', mode);
            if (!config.modeUrl) { return; }
            window.fetch(config.modeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ mode: mode })
            }).catch(function () { /* mode stays in localStorage; saved next time */ });
        }, 0);
    });

    // ---- Chart.js v2 plugin: "theme the frame, keep the meaning" ----
    var fallback = (config.fallbackPalette || []).map(function (c) { return String(c).toUpperCase(); });
    var bordered = { pie: 1, doughnut: 1, polarArea: 1, bar: 1, horizontalBar: 1 };

    function recolor(value, all, colors) {
        var map = function (c, i) {
            if (typeof c !== 'string') { return c; }
            var idx = fallback.indexOf(c.toUpperCase());
            if (all) { return colors[i % colors.length]; }
            return idx === -1 ? c : colors[idx % colors.length];
        };
        return Array.isArray(value) ? value.map(map) : map(value, 0);
    }

    var plugin = {
        id: 'gsTheme',
        beforeUpdate: function (chart) {
            var opts = chart.options.plugins && chart.options.plugins.gsTheme;
            if (opts === false) { return; }
            var el = chart.canvas || html;
            var grid = token('chart-grid', el), label = token('chart-label', el), text = token('color-text', el);
            var surface = token('color-surface', el), raised = token('color-surface-raised', el), border = token('color-border', el);
            if (!label) { return; }
            var o = chart.options;
            o.legend = o.legend || {};
            o.legend.labels = o.legend.labels || {};
            o.legend.labels.fontColor = label;
            o.title = o.title || {};
            o.title.fontColor = text;
            o.tooltips = o.tooltips || {};
            o.tooltips.backgroundColor = raised;
            o.tooltips.titleFontColor = text;
            o.tooltips.bodyFontColor = text;
            o.tooltips.borderColor = border;
            o.tooltips.borderWidth = 1;
            if (o.scales) {
                ['xAxes', 'yAxes'].forEach(function (axis) {
                    (o.scales[axis] || []).forEach(function (scale) {
                        scale.gridLines = scale.gridLines || {};
                        scale.gridLines.color = grid;
                        scale.gridLines.zeroLineColor = grid;
                        scale.ticks = scale.ticks || {};
                        scale.ticks.fontColor = label;
                        scale.scaleLabel = scale.scaleLabel || {};
                        scale.scaleLabel.fontColor = label;
                    });
                });
            }
            if (o.scale) { // radar / polar
                o.scale.gridLines = o.scale.gridLines || {};
                o.scale.gridLines.color = grid;
                o.scale.ticks = o.scale.ticks || {};
                o.scale.ticks.fontColor = label;
                o.scale.ticks.backdropColor = surface;
            }
            var colors = palette(10, el);
            var all = opts && opts.recolorData === 'all';
            (chart.data.datasets || []).forEach(function (ds) {
                if (!ds._gsOriginal) {
                    ds._gsOriginal = { backgroundColor: ds.backgroundColor, borderColor: ds.borderColor };
                }
                if (ds._gsOriginal.backgroundColor !== undefined) {
                    ds.backgroundColor = recolor(ds._gsOriginal.backgroundColor, all, colors);
                }
                if (bordered[chart.config.type]) {
                    ds.borderColor = surface;
                    ds.borderWidth = 1;
                } else if (ds._gsOriginal.borderColor !== undefined) {
                    ds.borderColor = recolor(ds._gsOriginal.borderColor, all, colors);
                }
            });
        }
    };

    function refreshCharts() {
        if (window.Chart && window.Chart.helpers && window.Chart.instances) {
            window.Chart.helpers.each(window.Chart.instances, function (c) { c.update(); });
        }
    }

    var registered = false;
    function registerChartPlugin() {
        if (registered || !window.Chart || !window.Chart.plugins) { return; }
        registered = true;
        window.Chart.plugins.register(plugin);
        refreshCharts();
    }
    registerChartPlugin();
    document.addEventListener('DOMContentLoaded', registerChartPlugin);
    window.addEventListener('load', registerChartPlugin);
    window.GS.theme.chartPlugin = plugin;

    // ---- notices ----
    function notice(id, message, linkText, href) {
        var key = 'gs-notice-dismissed:' + id;
        try { if (window.localStorage.getItem(key)) { return; } } catch (e) {}
        var box = document.createElement('div');
        box.className = 'gs-alert gs-alert--info gs-notice';
        box.setAttribute('role', 'status');
        var body = document.createElement('div');
        body.className = 'gs-alert__body';
        body.textContent = message + ' ';
        if (linkText && href) {
            var a = document.createElement('a');
            a.href = href;
            a.textContent = linkText;
            body.appendChild(a);
        }
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'gs-alert__close';
        close.setAttribute('aria-label', i18n.dismiss || 'Dismiss');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () {
            box.remove();
            try { window.localStorage.setItem(key, '1'); } catch (e) {}
        });
        box.appendChild(body);
        box.appendChild(close);
        var host = document.querySelector('.content-wrapper .content') || document.querySelector('.content-wrapper') || document.body;
        host.insertBefore(box, host.firstChild);
    }

    function onReady(fn) {
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn); } else { fn(); }
    }

    onReady(function () {
        if (window.CSS && CSS.supports && !CSS.supports('color', 'light-dark(black, white)')) {
            notice('unsupported-browser', i18n.unsupported || 'This browser is not supported.');
        }
        if (config.brandingNotice) {
            notice('branding:' + config.key, i18n.brandingNotice || '', i18n.manage, config.appearanceUrl);
        }

        // "Appearance" in the user menu, next to Snipe-IT's dark-mode toggle (no core view edit).
        var toggle = document.querySelector('.user-menu [data-theme-toggle]');
        if (toggle && config.appearanceUrl && !document.querySelector('[data-gs-appearance-link]')) {
            var li = document.createElement('li');
            var link = document.createElement('a');
            link.href = config.appearanceUrl;
            link.setAttribute('data-gs-appearance-link', '');
            link.innerHTML = '<i class="fas fa-palette fa-fw" aria-hidden="true"></i> ';
            link.appendChild(document.createTextNode(i18n.appearance || 'Appearance'));
            li.appendChild(link);
            var item = toggle.closest('li');
            item.parentNode.insertBefore(li, item.nextSibling);
        }

        // Kit behaviours: collapsible boxes, dismissible alerts, mobile filter toggle, bulk bars.
        document.addEventListener('click', function (event) {
            var collapse = event.target.closest('[data-gs-collapse]');
            if (collapse) {
                var box = collapse.closest('.gs-box');
                var collapsed = box.getAttribute('data-collapsed') !== 'true';
                box.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
                collapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            }
            var dismiss = event.target.closest('[data-gs-dismiss]');
            if (dismiss) { dismiss.closest('.gs-alert').remove(); }
            var filter = event.target.closest('[data-gs-filter-toggle]');
            if (filter) {
                var bar = filter.closest('.gs-filter-bar');
                var open = bar.getAttribute('data-open') !== 'true';
                bar.setAttribute('data-open', open ? 'true' : 'false');
                filter.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        });

        // Swatch strips: colours come from theme.json data, applied at runtime.
        document.querySelectorAll('.gs-swatch[data-color]').forEach(function (el) {
            el.style.backgroundColor = el.getAttribute('data-color');
        });
        // Theme Lab token swatches resolve in their own panel (theme × mode).
        document.querySelectorAll('[data-gs-swatch-token]').forEach(function (el) {
            el.style.background = 'var(--gs-' + el.getAttribute('data-gs-swatch-token') + ')';
        });

        // Appearance page: live preview before saving; Cancel restores.
        var picker = document.querySelector('[data-gs-theme-picker]');
        if (picker) {
            var original = {
                skin: html.getAttribute('data-skin'),
                theme: html.getAttribute('data-theme'),
                attrs: Array.prototype.filter.call(html.attributes, function (a) { return /^data-gs-/.test(a.name) && a.name !== 'data-gs-mode'; })
                    .map(function (a) { return [a.name, a.value]; })
            };
            var setVariants = function (pairs) {
                Array.prototype.slice.call(html.attributes).forEach(function (a) {
                    if (/^data-gs-/.test(a.name) && a.name !== 'data-gs-mode') { html.removeAttribute(a.name); }
                });
                pairs.forEach(function (p) { html.setAttribute(p[0], p[1]); });
            };
            var previewSkin = function (card) {
                var css = card.getAttribute('data-css');
                if (css && !document.querySelector('link[data-gs-preview-link="' + card.getAttribute('data-key') + '"]')) {
                    var link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = css;
                    link.setAttribute('data-gs-preview-link', card.getAttribute('data-key'));
                    document.head.appendChild(link);
                }
                var variants = {};
                try { variants = JSON.parse(card.getAttribute('data-variants') || '{}'); } catch (e) {}
                setVariants(Object.keys(variants).map(function (k) { return [k, variants[k]]; }));
                html.setAttribute('data-skin', card.getAttribute('data-key'));
            };
            picker.addEventListener('change', function (event) {
                if (event.target.name === 'theme') {
                    previewSkin(event.target.closest('[data-gs-theme-card]'));
                    var reset = picker.querySelector('input[name="reset"]');
                    if (reset) { reset.checked = false; }
                }
                if (event.target.name === 'mode') {
                    var m = event.target.value;
                    html.setAttribute('data-theme', m === 'system' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : m);
                }
            });
            var cancel = picker.querySelector('[data-gs-picker-cancel]');
            if (cancel) {
                cancel.addEventListener('click', function () {
                    picker.reset();
                    html.setAttribute('data-skin', original.skin);
                    html.setAttribute('data-theme', original.theme);
                    setVariants(original.attrs);
                });
            }
        }

        // Assignments page: highlight the chosen preview.
        document.querySelectorAll('[data-gs-assignment] select[name="theme"]').forEach(function (select) {
            select.addEventListener('change', function () {
                select.closest('form').querySelectorAll('[data-gs-assignment-preview]').forEach(function (img) {
                    img.classList.toggle('is-selected', img.getAttribute('data-gs-assignment-preview') === select.value);
                });
            });
        });

        document.querySelectorAll('[data-gs-bulk-for]').forEach(function (bar) {
            var id = bar.getAttribute('data-gs-bulk-for');
            var count = bar.querySelector('[data-gs-bulk-count]');
            var sync = function () {
                var n = 0;
                if (window.jQuery && window.jQuery.fn.bootstrapTable && window.jQuery('#' + id).data('bootstrap.table')) {
                    n = window.jQuery('#' + id).bootstrapTable('getSelections').length;
                } else {
                    n = document.querySelectorAll('#' + id + ' tbody input[type="checkbox"]:checked').length;
                }
                count.textContent = n;
                bar.hidden = n === 0;
            };
            if (window.jQuery) {
                window.jQuery('#' + id).on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table load-success.bs.table', sync);
            }
            document.addEventListener('change', function (event) {
                if (event.target.closest && event.target.closest('#' + id)) { sync(); }
            });
            sync();
        });
    });
})(window, document);
