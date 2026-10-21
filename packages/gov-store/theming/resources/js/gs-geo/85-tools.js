/*
 * gs-geo depth tools (geo-view plan §4, tag 1): find an area, flat mode, CSV / PNG export and full screen.
 * Each tool appears only when the config's toolbar (or levels.flat) enables it. Export uses only data the
 * viewer already sees, client-side (plan D8).
 */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    var V = G.View && G.View.prototype;
    if (!V) { return; }
    var el = G.el, doc = root.document;
    var ICON = {
        flat: 'M2 4h12M2 8h12M2 12h12', csv: 'M8 2v8M4.5 6.5 8 10l3.5-3.5M3 13h10', png: 'M2.5 3.5h11v9h-11zM5 10l2.5-3 2 2 1.5-1.5 2 2.5',
        full: 'M2.5 6V2.5H6M10 2.5h3.5V6M13.5 10v3.5H10M6 13.5H2.5V10'
    };
    function icon(d) {
        var s = el('svg:svg', { viewBox: '0 0 16 16', 'aria-hidden': 'true', focusable: 'false' });
        s.appendChild(el('svg:path', { d: d, fill: 'none', stroke: 'currentColor', 'stroke-width': '1.6', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));
        return s;
    }

    V.buildExtras = function () {
        var self = this, c = this.cfg, tb = c.toolbar || {}, t = this.t;
        if (tb.search && c.endpoints && c.endpoints.search) { this.tools.insertBefore(this.buildSearch(), this.extraTools); }
        if (c.levels && c.levels.flat) {
            this.flatBtn = el('button', { type: 'button', class: 'gs-geo__btn', 'aria-pressed': this.flatLevel ? 'true' : 'false', title: t('flat'), 'aria-label': t('flat'),
                onclick: function () { self.setFlat(self.flatLevel ? null : c.levels.flat); } }, [icon(ICON.flat)]);
            this.extraTools.appendChild(this.flatBtn);
        }
        if (tb.export) {
            this.extraTools.appendChild(el('button', { type: 'button', class: 'gs-geo__btn', title: t('export_csv'), 'aria-label': t('export_csv'), onclick: function () { self.exportCsv(); } }, [icon(ICON.csv)]));
            this.extraTools.appendChild(el('button', { type: 'button', class: 'gs-geo__btn', title: t('export_png'), 'aria-label': t('export_png'), onclick: function () { self.exportPng(); } }, [icon(ICON.png)]));
        }
        if (tb.fullscreen && this.rootEl.requestFullscreen) {
            this.fullBtn = el('button', { type: 'button', class: 'gs-geo__btn', 'aria-pressed': 'false', title: t('fullscreen'), 'aria-label': t('fullscreen'), onclick: function () { self.toggleFullscreen(); } }, [icon(ICON.full)]);
            this.extraTools.appendChild(this.fullBtn);
            this.onFullscreen = function () {
                var on = doc.fullscreenElement === self.rootEl;
                self.fullBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                self.rootEl.classList.toggle('is-fullscreen', on);
                self.onCamera();
            };
            doc.addEventListener('fullscreenchange', this.onFullscreen);
        }
    };

    /* ---- flat mode: one level nationally from the projected level file (plan §4.1) ---- */
    V.setFlat = function (level, thenArea) {
        this.flatLevel = level || null;
        if (this.flatBtn) { this.flatBtn.setAttribute('aria-pressed', this.flatLevel ? 'true' : 'false'); }
        this.emit('flat', { level: this.flatLevel });
        return this.go(thenArea || this.rootArea);
    };

    /* ---- find an area: typeahead over gov.geo.search, then open its parent and select it ---- */
    V.buildSearch = function () {
        var self = this, t = this.t, timer = null, token = 0;
        var box = el('div', { class: 'gs-geo__search' });
        var listId = this.id + '-search-results';
        var input = el('input', { type: 'search', class: 'gs-geo__select', placeholder: t('search'), 'aria-label': t('search'), autocomplete: 'off',
            role: 'combobox', 'aria-expanded': 'false', 'aria-controls': listId, 'aria-autocomplete': 'list' });
        var list = el('ul', { class: 'gs-geo__results', id: listId, role: 'listbox', hidden: true });
        var close = function () { list.hidden = true; input.setAttribute('aria-expanded', 'false'); };
        var render = function (rows) {
            list.textContent = '';
            if (!rows.length) { list.appendChild(el('li', { class: 'gs-geo__muted', text: t('no_results') })); }
            rows.slice(0, 8).forEach(function (r) {
                var b = el('button', { type: 'button', role: 'option', onclick: function () { close(); input.value = ''; self.reveal(r.id, r.parent_id); } },
                    [el('span', { text: r.text }), el('small', { class: 'gs-geo__muted', text: [r.geo_type_label].concat(r.trail || []).filter(Boolean).join(' · ') })]);
                b.addEventListener('keydown', function (e) {
                    var li = b.parentNode;
                    if (e.key === 'ArrowDown' && li.nextSibling) { e.preventDefault(); li.nextSibling.firstChild.focus(); }
                    if (e.key === 'ArrowUp') { e.preventDefault(); (li.previousSibling ? li.previousSibling.firstChild : input).focus(); }
                    if (e.key === 'Escape') { close(); input.focus(); }
                });
                list.appendChild(el('li', {}, [b]));
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };
        input.addEventListener('input', function () {
            var q = input.value.trim();
            root.clearTimeout(timer);
            if (q.length < 2) { close(); return; }
            timer = root.setTimeout(function () {
                var mine = ++token;
                var url = self.cfg.endpoints.search + '?' + new URLSearchParams({ q: q, with_parent: '1' }).toString();
                root.fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) { return res.ok ? res.json() : []; })
                    .then(function (rows) { if (mine === token) { render(Array.isArray(rows) ? rows.filter(function (r) { return r.level === undefined || r.level <= self.cfg.levels.max; }) : []); } })
                    .catch(function () { if (mine === token) { render([]); } });
            }, 200);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { close(); }
            if (e.key === 'ArrowDown' && !list.hidden && list.querySelector('button')) { e.preventDefault(); list.querySelector('button').focus(); }
        });
        box.appendChild(input);
        box.appendChild(list);
        this.searchInput = input;
        return box;
    };

    /** Open the area's parent (the server clamps to a drawable area) and select the area. */
    V.reveal = function (id, parentId) {
        var self = this;
        this.flatLevel = null;
        if (this.flatBtn) { this.flatBtn.setAttribute('aria-pressed', 'false'); }
        return this.go(parentId || id).then(function (p) {
            if (p && p.areas[id]) {
                self.select([Number(id)], 'search');
                var path = self.order && self.order.filter(function (x) { return Number(x.getAttribute('data-id')) === Number(id); })[0];
                if (path) { path.focus(); }
            }
            return p;
        });
    };

    /* ---- export: the table as shown, and a picture of the map with legend and attribution ---- */
    V.rows = function () {
        var p = this.store.get().payload;
        return Object.keys(p.areas).map(function (k) {
            var a = p.areas[k];
            return [a.name, (a.state === 'ok' || a.state === 'partial') && typeof a.value === 'number' ? a.value : '', typeof a.offices === 'number' ? a.offices : '', a.state];
        }).sort(function (x, y) { return String(x[0]).localeCompare(String(y[0])); });
    };

    V.download = function (name, blob) {
        var a = el('a', { href: root.URL.createObjectURL(blob), download: name });
        doc.body.appendChild(a);
        a.click();
        root.setTimeout(function () { root.URL.revokeObjectURL(a.href); a.parentNode.removeChild(a); }, 0);
    };

    V.fileName = function (ext) {
        var p = this.store.get().payload;
        return (this.id + '-' + p.area.id + '-' + p.layer.key).replace(/[^a-z0-9-]+/gi, '-') + '.' + ext;
    };

    V.exportCsv = function () {
        var p = this.store.get().payload, t = this.t;
        if (!p) { return; }
        var esc = function (v) { v = String(v === null || v === undefined ? '' : v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        var lines = [[t('area'), p.layer.label, t('offices'), t('state')].map(esc).join(',')]
            .concat(this.rows().map(function (r) { return r.map(esc).join(','); }))
            .concat(['', esc(p.area.name + ' · ' + (p.attribution || ''))]);
        this.download(this.fileName('csv'), new root.Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
        this.emit('export', { format: 'csv' });
    };

    V.exportPng = function () {
        var self = this, p = this.store.get().payload;
        if (!p || !this.cam) { return Promise.resolve(null); }
        var rect = this.svg.getBoundingClientRect();
        var w = Math.max(320, Math.round(rect.width)), h = Math.max(240, Math.round(rect.height));
        var clone = this.svg.cloneNode(true);
        var src = this.svg.querySelectorAll('*'), dst = clone.querySelectorAll('*');
        // Resolve theme tokens to the computed values the viewer sees, so the picture keeps its colours.
        for (var i = 0; i < src.length; i++) {
            var cs = root.getComputedStyle(src[i]);
            ['fill', 'stroke', 'stroke-width', 'opacity', 'font-size', 'font-family', 'paint-order'].forEach(function (prop) {
                var v = cs.getPropertyValue(prop);
                if (v) { dst[i].setAttribute(prop, v); }
            });
        }
        clone.setAttribute('width', w);
        clone.setAttribute('height', h);
        clone.setAttribute('xmlns', G.SVGNS);
        var style = root.getComputedStyle(this.rootEl);
        var ink = style.color, ground = root.getComputedStyle(this.stage).backgroundColor;
        var data = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new root.XMLSerializer().serializeToString(clone));
        return new Promise(function (resolve) {
            var img = new root.Image();
            img.onload = function () {
                var scale = 2, pad = 16, foot = 64;
                var canvas = doc.createElement('canvas');
                canvas.width = (w + pad * 2) * scale;
                canvas.height = (h + pad * 2 + foot) * scale;
                var ctx = canvas.getContext('2d');
                ctx.scale(scale, scale);
                ctx.fillStyle = ground;
                ctx.fillRect(0, 0, w + pad * 2, h + pad * 2 + foot);
                ctx.drawImage(img, pad, pad, w, h);
                ctx.fillStyle = ink;
                ctx.font = '600 14px ' + style.fontFamily;
                ctx.fillText(p.area.name + ' · ' + p.layer.label, pad, h + pad + 22);
                ctx.font = '12px ' + style.fontFamily;
                var legend = Array.prototype.map.call(self.legend.querySelectorAll('.gs-geo__bin span'), function (s) { return s.textContent; }).join('   ');
                ctx.fillText(legend, pad, h + pad + 40);
                ctx.fillText(String(p.attribution || '').slice(0, 160), pad, h + pad + 58);
                canvas.toBlob(function (blob) {
                    if (blob) { self.download(self.fileName('png'), blob); self.emit('export', { format: 'png' }); }
                    resolve(blob);
                }, 'image/png');
            };
            img.onerror = function () { resolve(null); };
            img.src = data;
        });
    };

    /* ---- full screen ---- */
    V.toggleFullscreen = function () {
        var on = doc.fullscreenElement === this.rootEl;
        var p = on ? doc.exitFullscreen() : this.rootEl.requestFullscreen();
        if (p && p.catch) { p.catch(function () { /* refused by the browser */ }); }
    };

    var destroy = V.destroy;
    V.destroy = function (keep) {
        if (this.onFullscreen) { doc.removeEventListener('fullscreenchange', this.onFullscreen); }
        return destroy.call(this, keep);
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);
