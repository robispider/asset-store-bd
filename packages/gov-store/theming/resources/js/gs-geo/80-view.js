/*
 * gs-geo view: one map instance. Renders SVG paths in the shared national frame, drills with a viewBox tween,
 * and draws toolbar, breadcrumb, legend, tooltip, help, table and details panel (plan §4, §7.2–7.4).
 * Every name and value is written with textContent; fills are classes (data-bin / data-div / data-state).
 */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    var el = G.el, C = G.camera;
    var doc = root.document;
    var STATES = ['no_data', 'not_applicable', 'partial', 'suppressed', 'no_access'];
    var reducedMotion = function () { return root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches; };
    var store = function () { try { return root.localStorage; } catch (e) { return null; } };

    function icon(d) {
        var s = el('svg:svg', { viewBox: '0 0 16 16', 'aria-hidden': 'true', focusable: 'false' });
        s.appendChild(el('svg:path', { d: d, fill: 'none', stroke: 'currentColor', 'stroke-width': '1.6', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));
        return s;
    }
    var ICONS = {
        minus: 'M3 8h10', plus: 'M3 8h10M8 3v10', reset: 'M2.5 8a5.5 5.5 0 1 0 1.6-3.9M2.5 2.5v2.6h2.6',
        up: 'M8 13V3M3.5 7.5 8 3l4.5 4.5', full: 'M2.5 6V2.5H6M10 2.5h3.5V6M13.5 10v3.5H10M6 13.5H2.5V10', fit: 'M3 3h4M3 3v4M13 13H9M13 13V9M5 11l6-6'
    };

    function View(rootEl, cfg, payload) {
        this.rootEl = rootEl;
        this.cfg = cfg;
        this.id = cfg.id;
        this.t = G.strings(cfg.strings);
        this.fmt = G.formatter(cfg.locale);
        this.listeners = {};
        this.navToken = 0;
        this.detailsToken = 0;
        this.src = G.source(cfg, payload);
        this.boundary = cfg.boundary;
        this.rootArea = (cfg.levels && cfg.levels.root) || (cfg.boundary && cfg.boundary.root);
        var urlState = cfg.url_state ? G.url.read(this.id, root.location ? root.location.search : '') : {};
        var init = G.config.initialState(cfg, urlState);
        var helpOpen = false;
        var ls = store();
        if (ls && cfg.toolbar && cfg.toolbar.help !== false) { try { helpOpen = ls.getItem('gs-geo-help') === '1'; } catch (e) { helpOpen = false; } }
        this.store = G.store({ layer: init.layer, filters: init.filters, select: init.select, mode: init.mode, help: helpOpen });
        this.initialArea = init.area || (cfg.levels.start === 'auto' ? 'auto' : cfg.levels.start === 'root' ? this.rootArea : cfg.levels.start);
        this.build();
        this.bindHost();
        this.go(this.initialArea, { initial: true });
    }

    var V = View.prototype;

    /* ------------------------------------------------------------------ events */
    V.on = function (name, fn) { (this.listeners[name] = this.listeners[name] || []).push(fn); return this; };
    V.off = function (name, fn) { this.listeners[name] = (this.listeners[name] || []).filter(function (x) { return x !== fn; }); return this; };
    V.emit = function (name, detail) {
        (this.listeners[name] || []).forEach(function (fn) { try { fn(detail); } catch (e) { root.console && root.console.error(e); } });
        this.rootEl.dispatchEvent(new root.CustomEvent('gs-geo:' + name, { detail: Object.assign({ id: this.id }, detail), bubbles: true }));
    };
    V.state = function () { return this.store.get(); };

    /* ------------------------------------------------------------------ shell */
    V.build = function () {
        var self = this, c = this.cfg, t = this.t, tb = c.toolbar || {};
        var r = this.rootEl;
        r.classList.add('is-mounted');
        r.style.setProperty('--gs-geo-height', ((c.size && c.size.height) || 420) + 'px');
        r.style.setProperty('--gs-geo-hatch', 'url(#' + this.id + '-hatch)');   // CSS fill beats the SVG attribute
        this.fallback = r.querySelector('[data-gs-geo-fallback]');
        if (this.fallback) { this.fallback.hidden = true; }
        Array.prototype.forEach.call(r.querySelectorAll('[data-gs-geo-shell]'), function (n) { n.parentNode.removeChild(n); });

        this.trail = el('nav', { class: 'gs-geo__trail', 'aria-label': t('area') });
        this.tools = el('div', { class: 'gs-geo__tools' });
        this.layerSel = el('select', { class: 'gs-geo__select', 'aria-label': t('layer'), onchange: function (e) { self.setLayer(e.target.value, 'user'); } });
        this.filterBox = el('span', { class: 'gs-geo__filters' });
        if (tb.layers !== false) { this.tools.appendChild(this.layerSel); }
        if (tb.filters !== false && !c.bind_filters) { this.tools.appendChild(this.filterBox); }
        this.extraTools = el('span', { class: 'gs-geo__group gs-geo__extra' });
        this.tools.appendChild(this.extraTools);
        if (tb.zoom !== false) {
            this.tools.appendChild(el('span', { class: 'gs-geo__group' }, [
                this.button(ICONS.minus, t('zoom_out'), function () { self.zoomBy(1 / 1.6); }),
                this.button(ICONS.plus, t('zoom_in'), function () { self.zoomBy(1.6); }),
                tb.reset !== false ? this.button(ICONS.reset, t('reset'), function () { self.resetCamera(true); }) : null
            ]));
        }
        if (tb.table !== false && c.panels.table === 'toggle') {
            this.mapBtn = el('button', { type: 'button', class: 'gs-geo__btn', 'aria-pressed': 'true', onclick: function () { self.setMode('map'); } }, [t('map')]);
            this.tableBtn = el('button', { type: 'button', class: 'gs-geo__btn', 'aria-pressed': 'false', onclick: function () { self.setMode('table'); } }, [t('table')]);
            this.tools.appendChild(el('span', { class: 'gs-geo__group' }, [this.mapBtn, this.tableBtn]));
        }
        if (tb.help !== false) {
            this.helpBtn = el('button', { type: 'button', class: 'gs-geo__btn', title: t('help'), 'aria-label': t('help'), 'aria-expanded': 'false', 'aria-controls': this.id + '-help', onclick: function () { self.toggleHelp(); } }, ['?']);
            this.tools.appendChild(this.helpBtn);
        }
        var bar = el('div', { class: 'gs-geo__bar' }, [c.panels.breadcrumb !== false ? this.trail : el('span'), this.tools]);

        this.stage = el('div', { class: 'gs-geo__stage', tabindex: '0', role: 'application', 'aria-roledescription': 'map', 'aria-label': t('map_label') });
        this.svg = el('svg:svg', { class: 'gs-geo__svg', preserveAspectRatio: 'xMidYMid meet', 'aria-hidden': 'false' });
        var defs = el('svg:defs');
        this.hatch = el('svg:pattern', { id: this.id + '-hatch', patternUnits: 'userSpaceOnUse', width: '60', height: '60', patternTransform: 'rotate(45)' });
        this.hatchBg = el('svg:rect', { class: 'gs-geo__hatch-bg', width: '60', height: '60' });
        this.hatchLine = el('svg:line', { class: 'gs-geo__hatch-line', x1: '30', y1: '0', x2: '30', y2: '60' });
        this.hatch.appendChild(this.hatchBg);
        this.hatch.appendChild(this.hatchLine);
        defs.appendChild(this.hatch);
        this.gOutline = el('svg:g', { class: 'gs-geo__outline-layer' });
        this.gAreas = el('svg:g', { role: 'group', 'aria-label': t('area') });
        this.gLabels = el('svg:g', { 'aria-hidden': 'true' });
        this.gMarkers = el('svg:g', { 'aria-hidden': 'true' });
        [defs, this.gOutline, this.gAreas, this.gLabels, this.gMarkers].forEach(function (n) { self.svg.appendChild(n); });
        this.overlay = el('div', { class: 'gs-geo__overlay' });
        this.tip = el('div', { class: 'gs-geo__tip', hidden: true, role: 'presentation' });
        this.hint = el('div', { class: 'gs-geo__hint', hidden: true, text: t('wheel_hint') });
        this.busy = el('div', { class: 'gs-geo__busy', hidden: true, text: t('loading') });
        this.message = el('div', { class: 'gs-geo__message', hidden: true, role: 'status' });
        this.helpCard = el('div', { class: 'gs-geo__help', id: this.id + '-help', role: 'region', 'aria-label': t('help'), hidden: true });
        this.live = el('div', { class: 'gs-sr-only', 'aria-live': 'polite' });
        [this.svg, this.overlay, this.tip, this.hint, this.busy, this.message, this.helpCard, this.live].forEach(function (n) { self.stage.appendChild(n); });

        this.tableWrap = el('div', { class: 'gs-geo__table-wrap', hidden: true });
        this.main = el('div', { class: 'gs-geo__main' }, [this.stage, this.tableWrap]);
        this.panel = c.panels.details === 'none' ? null : el('aside', { class: 'gs-geo__panel', 'aria-live': 'polite', 'aria-label': t('details') });
        this.body = el('div', { class: 'gs-geo__body', 'data-details': c.panels.details || 'side' }, [this.main, this.panel]);
        this.legend = el('div', { class: 'gs-geo__legend' });
        this.foot = el('div', { class: 'gs-geo__foot' });
        if (c.input && c.input.name) {
            this.input = r.querySelector('input[name="' + c.input.name.replace(/"/g, '') + '"]') || el('input', { type: 'hidden', name: c.input.name });
            if (!this.input.parentNode) { r.appendChild(this.input); }
        }
        [bar, this.body, c.panels.legend !== false ? this.legend : null, this.foot].forEach(function (n) { if (n) { r.appendChild(n); } });

        this.toggleHelp(this.store.get().help, true);
        this.applyMode(this.store.get().mode);
        this.bindPointer();
        this.bindKeys();
        if (c.url_state) {
            this.onPop = function () { self.fromUrl(); };
            root.addEventListener('popstate', this.onPop);
        }
        if (root.ResizeObserver) {
            this.resizeObs = new root.ResizeObserver(function () { self.onCamera(); });
            this.resizeObs.observe(this.stage);
        }
    };

    V.button = function (path, label, onclick) {
        return el('button', { type: 'button', class: 'gs-geo__btn', title: label, 'aria-label': label, onclick: onclick }, [icon(path)]);
    };

    /* ------------------------------------------------------------------ navigation */
    V.query = function (area) {
        var s = this.store.get();
        return { area: area, layer: s.layer, fallback: s.layer ? [] : (this.cfg.layers.default || []), filters: s.filters };
    };

    V.go = function (area, opts) {
        opts = opts || {};
        var self = this, token = ++this.navToken;
        this.busy.hidden = false;
        this.store.dispatch({ type: 'loading' });
        return this.src.data(this.query(area)).then(function (res) {
            if (token !== self.navToken) { return null; }
            if (res.status !== 200 || !res.body || res.body.contract !== 'gs-geo-data/1') { self.fail(res.status); return null; }
            var p = res.body;
            var fileP = p.boundary && p.boundary.children ? G.boundaries.children(self.boundary, p.area.id) : Promise.resolve(null);
            return fileP.then(function (file) {
                if (token !== self.navToken) { return null; }
                self.show(p, file, opts);
                return p;
            });
        }).catch(function (e) {
            if (e && e.name === 'AbortError') { return null; }
            if (token === self.navToken) { root.console && root.console.error(e); self.fail(0); }
            return null;
        });
    };

    V.show = function (p, file, opts) {
        var prevArea = this.store.get().area;
        var prevCam = this.cam;
        this.busy.hidden = true;
        this.message.hidden = true;
        this.store.dispatch({ type: 'loaded', payload: p, file: file, keepSelection: !!opts.keepSelection });
        this.base = C.base(file ? file.viewBox : (this.file ? this.file.viewBox : '0 0 1000 1000'));
        this.file = file;
        if (!this.cam) { this.setCam(this.base); }
        this.render();
        if (prevCam && prevArea !== p.area.id && !reducedMotion() && !opts.initial) { this.tween(prevCam, this.base, 480); }
        else if (prevArea !== p.area.id || opts.initial) { this.setCam(this.base); }
        this.live.textContent = this.t('shown', { name: p.area.name, count: this.fmt.int(Object.keys(p.areas).length) });
        if (!opts.fromHistory) { this.writeUrl(opts.initial || prevArea === p.area.id ? 'replace' : 'push'); }
        if (opts.initial) { this.emit('ready', { area: p.area.id, layer: p.layer.key, source: p.source }); }
        else if (prevArea !== p.area.id) { this.emit('drill', { area: p.area.id, from: prevArea, level: p.level }); }
        this.renderPanel();
    };

    V.fail = function (status) {
        this.busy.hidden = true;
        this.store.dispatch({ type: 'error', error: status });
        this.message.hidden = false;
        this.message.textContent = status === 404 || status === 403 ? this.t('denied') : this.t('error');
        if (this.fallback) { this.fallback.hidden = false; }
        this.emit('error', { status: status, source: this.cfg.source.key });
    };

    V.fromUrl = function () {
        var u = G.url.read(this.id, root.location.search);
        var s = this.store.get();
        var init = G.config.initialState(this.cfg, u);
        if (init.layer !== s.layer) { this.store.dispatch({ type: 'layer', layer: init.layer }); }
        this.store.dispatch({ type: 'select', select: init.select });
        if (init.mode !== s.mode) { this.applyMode(init.mode); }
        this.go(init.area || this.rootArea, { fromHistory: true, keepSelection: true });
    };

    V.writeUrl = function (how) {
        if (!this.cfg.url_state || !root.history || !root.location) { return; }
        var s = this.store.get();
        var qs = G.url.write(this.id, { area: s.area, root: this.autoArea || this.rootArea, layer: s.layer, filters: s.filters, select: s.select, mode: s.mode }, root.location.search);
        var url = root.location.pathname + qs + root.location.hash;
        if (url === root.location.pathname + root.location.search + root.location.hash) { return; }
        try { root.history[how === 'push' ? 'pushState' : 'replaceState']({ gsGeo: this.id }, '', url); } catch (e) { /* sandboxed frame */ }
    };

    /* ------------------------------------------------------------------ render */
    V.render = function () {
        var self = this, s = this.store.get(), p = s.payload, c = this.cfg;
        if (this.store.get().area && !this.autoArea) { this.autoArea = p.area.id; }
        this.scale = G.scale(c.scale, p);
        this.gOutline.textContent = '';
        this.gAreas.textContent = '';
        this.boxes = {};
        if (this.file) { this.gOutline.appendChild(el('svg:path', { class: 'gs-geo__outline', d: this.file.outline, 'fill-rule': 'evenodd' })); }
        var ordered = [];
        (this.file ? this.file.areas : []).forEach(function (shape) {
            var a = p.areas[shape.id];
            if (!a) { return; }
            if (shape.kind === 'urban' && c.levels.urban === 'hide') { return; }
            var cls = (a.state === 'ok' || a.state === 'partial') && typeof a.value === 'number' ? self.scale.classify(a.value) : {};
            var path = el('svg:path', {
                class: 'gs-geo__area', d: shape.path, 'data-id': shape.id, 'data-kind': shape.kind, 'data-state': a.state,
                'data-bin': cls.bin !== undefined ? cls.bin : null, 'data-div': cls.div !== undefined ? cls.div : null,
                'data-pick': self.scale.type === 'none' && a.state !== 'no_access' ? '' : null,
                tabindex: '-1', role: 'button', 'aria-label': self.describe(shape.id)
            });
            self.boxes[shape.id] = shape.bbox || null;
            self.gAreas.appendChild(path);
            ordered.push(path);
        });
        ordered.forEach(function (pth) {
            var id = Number(pth.getAttribute('data-id'));
            if (!self.boxes[id] && pth.getBBox) { try { var b = pth.getBBox(); self.boxes[id] = [b.x, b.y, b.width, b.height]; } catch (e) { self.boxes[id] = [0, 0, 0, 0]; } }
        });
        ordered.sort(function (a, b) {
            var A = self.boxes[a.getAttribute('data-id')], B = self.boxes[b.getAttribute('data-id')];
            return (A[1] + A[3] / 2) - (B[1] + B[3] / 2) || A[0] - B[0];
        });
        this.order = ordered;
        if (ordered[0]) { ordered[0].setAttribute('tabindex', '0'); }
        this.labelPoints = {};
        (this.file ? this.file.areas : []).forEach(function (shape) { self.labelPoints[shape.id] = shape.label || null; });
        this.syncSelection();
        this.renderTrail();
        this.renderTools();
        this.renderLegend();
        this.renderOverlay();
        this.renderFoot();
        this.renderTable();
        this.drawLabels();
        this.emit('render', { area: p.area.id });
    };

    V.value = function (a) {
        if (!a) { return '—'; }
        if (a.state !== 'ok' && a.state !== 'partial') { return this.t(a.state); }
        if (a.display) { return a.display; }
        return this.fmt(a.value, this.store.get().payload.layer.format);
    };

    V.describe = function (id) {
        var a = this.store.get().payload.areas[id] || {};
        if (this.scale && this.scale.type === 'none') { return a.name || String(id); }
        return (a.name || String(id)) + ': ' + this.value(a);
    };

    V.renderTrail = function () {
        var self = this, trail = this.store.get().payload.area.trail || [];
        this.trail.textContent = '';
        trail.forEach(function (step, i) {
            var last = i === trail.length - 1;
            if (i) { self.trail.appendChild(el('span', { class: 'gs-geo__sep', 'aria-hidden': 'true', text: '›' })); }
            self.trail.appendChild(el('button', {
                type: 'button', class: 'gs-geo__crumb', 'aria-current': last ? 'location' : null,
                onclick: last ? null : function () { self.drillTo(step.id); }
            }, [step.short || step.name]));
        });
    };

    V.renderTools = function () {
        var self = this, p = this.store.get().payload, c = this.cfg;
        this.layerSel.textContent = '';
        (p.layers || []).filter(function (l) { return G.config.allowsLayer(c, l.key); }).forEach(function (l) {
            self.layerSel.appendChild(el('option', { value: l.key, selected: l.key === p.layer.key ? true : null }, [l.label + (l.applicable ? '' : ' · ' + self.t('not_applicable'))]));
        });
        this.layerSel.hidden = this.layerSel.options.length < 2;
        this.filterBox.textContent = '';
        (p.filters || []).forEach(function (f) {
            var current = self.store.get().filters[f.key];
            var sel = el('select', { class: 'gs-geo__select', 'aria-label': f.label, onchange: function (e) { var o = {}; o[f.key] = e.target.value || undefined; self.setFilters(o, 'user'); } },
                [el('option', { value: '' }, [f.label + ': ' + self.t('all')])].concat(f.options.map(function (o) {
                    return el('option', { value: o.value, selected: String(current === undefined ? '' : current) === String(o.value) ? true : null }, [o.label]);
                })));
            self.filterBox.appendChild(sel);
        });
    };

    V.swatch = function (attrs, hatched) {
        var s = el('svg:svg', { class: 'gs-geo__swatch', viewBox: '0 0 10 10', 'aria-hidden': 'true', focusable: 'false' });
        if (hatched) {
            s.appendChild(el('svg:rect', { class: 'gs-geo__hatch-bg', width: '10', height: '10' }));
            [-4, 0, 4, 8].forEach(function (o) { s.appendChild(el('svg:line', { class: 'gs-geo__hatch-line', x1: o, y1: 10, x2: o + 10, y2: 0 })); });
        } else {
            s.appendChild(el('svg:rect', Object.assign({ class: 'gs-geo__area', width: '10', height: '10' }, attrs)));
        }
        return s;
    };

    V.renderLegend = function () {
        var self = this, p = this.store.get().payload, s = this.scale, t = this.t, fmt = this.fmt, f = p.layer.format;
        var items = [];
        this.legend.textContent = '';
        if (s.type !== 'none') {
            items.push(el('span', { class: 'gs-geo__legend-title', text: p.layer.label + (p.layer.unit ? ' (' + p.layer.unit + ')' : '') }));
        }
        var bins = el('div', { class: 'gs-geo__bins', role: 'group', 'aria-label': p.layer.label });
        if (s.type === 'single') {
            bins.appendChild(this.binButton(this.swatch({ 'data-state': 'ok', 'data-bin': '4' }), t('all_same', { value: fmt(s.value, f) }), 0, function () { return true; }));
        } else if (s.type === 'diverging') {
            [-3, -2, -1, 0, 1, 2, 3].forEach(function (d) {
                var label = d === 0 ? fmt(s.ref, f) : (d < 0 ? '−' : '+') + fmt((Math.abs(d) * s.span) / 3, f);
                bins.appendChild(self.binButton(self.swatch(d === 0 ? { 'data-state': 'no_data' } : { 'data-div': d }), label, 'd' + d,
                    function (a) { return (a.state === 'ok' || a.state === 'partial') && s.classify(a.value).div === d; }));
            });
        } else if (s.type === 'categorical') {
            s.categories.forEach(function (cat, i) {
                bins.appendChild(self.binButton(self.swatch({ 'data-bin': [1, 4, 7][i] }), t(cat), i, function (a) { return a.state === 'ok' && s.classify(a.value).index === i; }));
            });
        } else if (s.breaks) {
            for (var i = 0; i < s.bins; i++) {
                (function (k) {
                    var label = fmt.short(s.breaks[k], f) + '–' + fmt.short(s.breaks[k + 1], f) + (f === 'percent' ? '%' : '');
                    bins.appendChild(self.binButton(self.swatch({ 'data-bin': s.tone(k) }), label, k,
                        function (a) { return (a.state === 'ok' || a.state === 'partial') && s.classify(a.value).index === k; }));
                })(i);
            }
        }
        if (bins.childNodes.length) { items.push(bins); }
        if (s.type === 'diverging') { items.push(el('span', { class: 'gs-geo__muted', text: t('national', { value: fmt(s.ref, f) }) })); }
        var present = {};
        Object.keys(p.areas).forEach(function (k) { present[p.areas[k].state] = true; });
        var states = el('div', { class: 'gs-geo__states' });
        STATES.forEach(function (st) {
            if (!present[st]) { return; }
            states.appendChild(el('span', { class: 'gs-geo__state' }, [self.swatch({ 'data-state': st }, st === 'no_access'), t(st)]));
        });
        if (Object.keys(p.areas).some(function (k) { return p.areas[k].shape === false; })) {
            states.appendChild(el('span', { class: 'gs-geo__state', text: '◌ ' + t('no_boundary') }));
        }
        if (states.childNodes.length) { items.push(states); }
        if (p.layer.fallback_from) {
            var from = (p.layers || []).filter(function (l) { return l.key === p.layer.fallback_from; })[0];
            items.push(el('p', { class: 'gs-geo__note', text: p.layer.fallback_note || t('fallback', { from: from ? from.label : p.layer.fallback_from, to: p.layer.label }) }));
        }
        if (p.scope && p.scope.partial) { items.push(el('p', { class: 'gs-geo__note', text: t('partial_note') })); }
        var c = this.cfg;
        items.push(el('span', { class: 'gs-geo__muted', text: c.intent === 'pick' ? t('hint_pick') : c.interaction.select === 'multi' ? t('hint_compare') : c.interaction.click === 'select' ? t('hint_select') : t('hint_drill') }));
        items.forEach(function (n) { self.legend.appendChild(n); });
    };

    V.binButton = function (swatch, label, key, match) {
        var self = this;
        var b = el('button', { type: 'button', class: 'gs-geo__bin', 'aria-pressed': this.store.get().legendPin === key ? 'true' : 'false' }, [swatch, el('span', { text: label })]);
        var dim = function (on) {
            var pin = self.store.get().legendPin;
            var active = on ? match : pin !== null && pin !== undefined ? self.pinMatch : null;
            (self.order || []).forEach(function (pth) {
                var a = self.store.get().payload.areas[pth.getAttribute('data-id')] || {};
                pth.classList.toggle('is-dim', !!active && !active(a));
            });
        };
        b.addEventListener('mouseenter', function () { dim(true); });
        b.addEventListener('focus', function () { dim(true); });
        b.addEventListener('mouseleave', function () { dim(false); });
        b.addEventListener('blur', function () { dim(false); });
        b.addEventListener('click', function () {
            self.store.dispatch({ type: 'legend', index: key });
            self.pinMatch = self.store.get().legendPin === null ? null : match;
            Array.prototype.forEach.call(self.legend.querySelectorAll('.gs-geo__bin'), function (x) { x.setAttribute('aria-pressed', 'false'); });
            b.setAttribute('aria-pressed', self.store.get().legendPin === key ? 'true' : 'false');
            dim(false);
        });
        return b;
    };

    V.renderOverlay = function () {
        var self = this, p = this.store.get().payload, t = this.t;
        this.overlay.textContent = '';
        var trail = p.area.trail || [];
        var top = this.autoArea || this.rootArea;
        if (trail.length > 1 && p.area.id !== this.rootArea) {
            var up = trail[trail.length - 2];
            this.overlay.appendChild(el('button', { type: 'button', class: 'gs-geo__up', 'data-gs-geo-up': '', title: t('up') + ' (Esc)', onclick: function () { self.drillTo(up.id); } },
                [icon(ICONS.up), t('up_to', { name: up.short || up.name })]));
        } else if (this.helpCard.hidden && this.helpBtn && !this.helpSeen()) {
            this.overlay.appendChild(el('button', { type: 'button', class: 'gs-geo__pill gs-geo__pill--help', onclick: function () { self.toggleHelp(true); } }, [t('help_new')]));
        }
        if (p.here && p.here.offices) {
            this.overlay.appendChild(el('button', { type: 'button', class: 'gs-geo__pill gs-geo__pill--here', onclick: function () { self.select([], 'here'); } },
                ['● ' + t('here') + ': ' + this.fmt.int(p.here.offices)]));
        }
        if (p.unplaced && p.unplaced.offices && (p.area.id === top || p.area.id === this.rootArea)) {
            this.overlay.appendChild(el('span', { class: 'gs-geo__pill', text: t('unplaced') + ': ' + this.fmt.int(p.unplaced.offices) }));
        }
    };

    V.renderFoot = function () {
        var p = this.store.get().payload;
        this.foot.textContent = '';
        this.foot.appendChild(el('span', { class: 'gs-geo__attribution', title: p.attribution, text: p.attribution }));
        if (p.synthetic) { this.foot.appendChild(el('span', { class: 'gs-geo__chip', text: 'synthetic' })); }
    };

    V.renderTable = function () {
        var self = this, p = this.store.get().payload, t = this.t;
        if (!p) { return; }
        var showOffices = Object.keys(p.areas).some(function (k) { return typeof p.areas[k].offices === 'number' && p.areas[k].offices > 0; });
        var rows = Object.keys(p.areas).map(function (k) { var a = p.areas[k]; return { id: Number(k), a: a, v: (a.state === 'ok' || a.state === 'partial') ? a.value : null }; });
        var key = this.sortKey || 'name', dir = this.sortDir || 1;
        rows.sort(function (x, y) {
            if (key === 'name') { return String(x.a.name).localeCompare(String(y.a.name)) * dir; }
            var a = x.v === null || x.v === undefined ? -Infinity : x.v, b = y.v === null || y.v === undefined ? -Infinity : y.v;
            return (a - b) * dir || String(x.a.name).localeCompare(String(y.a.name));
        });
        var head = function (k, label, cls) {
            return el('th', { scope: 'col', class: cls, 'aria-sort': key === k ? (dir > 0 ? 'ascending' : 'descending') : null }, [
                el('button', { type: 'button', class: 'gs-geo__sort', onclick: function () { self.sortDir = self.sortKey === k ? -dir : 1; self.sortKey = k; self.renderTable(); } }, [label])
            ]);
        };
        var maxLevel = this.cfg.levels.max;
        var table = el('table', { class: 'gs-geo__table' }, [
            el('caption', { class: 'gs-sr-only', text: p.layer.label + ' · ' + p.area.name }),
            el('thead', {}, [el('tr', {}, [head('name', t('area')), head('value', p.layer.label, 'gs-geo__num'), showOffices ? el('th', { scope: 'col', class: 'gs-geo__num', text: t('offices') }) : null, el('th', { scope: 'col', text: t('state') })])]),
            el('tbody', {}, rows.map(function (r) {
                var drill = r.a.drillable && p.level < maxLevel && r.a.state !== 'no_access';
                return el('tr', { class: self.store.get().select.indexOf(r.id) !== -1 ? 'is-selected' : null }, [
                    el('td', {}, [r.a.state === 'no_access' ? el('span', { text: r.a.name }) : el('button', { type: 'button', class: 'gs-geo__link', onclick: function () { if (drill) { self.drillTo(r.id); } else { self.toggle(r.id, false); } } }, [r.a.name])]),
                    el('td', { class: 'gs-geo__num', text: (r.a.state === 'ok' || r.a.state === 'partial') ? self.value(r.a) : '—' }),
                    showOffices ? el('td', { class: 'gs-geo__num', text: r.a.offices ? self.fmt.int(r.a.offices) : '—' }) : null,
                    el('td', { text: [r.a.state === 'ok' ? '' : t(r.a.state), r.a.shape === false ? t('no_boundary') : ''].filter(Boolean).join(' · ') })
                ]);
            }))
        ]);
        this.tableWrap.textContent = '';
        this.tableWrap.appendChild(table);
    };

    V.applyMode = function (m) {
        if (this.cfg.panels.table === 'none') { m = 'map'; }
        this.store.dispatch({ type: 'mode', mode: m });
        if (this.mapBtn) { this.mapBtn.setAttribute('aria-pressed', m === 'map' ? 'true' : 'false'); this.tableBtn.setAttribute('aria-pressed', m === 'table' ? 'true' : 'false'); }
        this.stage.hidden = m === 'table' && this.cfg.panels.table !== 'always';
        this.tableWrap.hidden = m !== 'table' && this.cfg.panels.table !== 'always';
        if (m === 'map') { this.drawLabels(); }
    };

    V.setMode = function (m) {
        this.applyMode(m);
        this.writeUrl('replace');
        this.emit('mode', { mode: m });
    };

    /* ------------------------------------------------------------------ camera */
    V.setCam = function (cam) {
        this.cam = cam;
        this.svg.setAttribute('viewBox', C.toString(cam));
        this.onCamera();
    };

    V.upp = function () {
        var r = this.svg.getBoundingClientRect ? this.svg.getBoundingClientRect() : { width: 0, height: 0 };
        return Math.max(this.cam.w / (r.width || 600), this.cam.h / (r.height || 420));
    };

    V.tween = function (from, to, ms) {
        var self = this, t0 = root.performance.now();
        root.cancelAnimationFrame(this.raf);
        var step = function (now) {
            var k = C.ease(Math.min(1, (now - t0) / ms));
            self.setCam(C.lerp(from, to, k));
            if (k < 1) { self.raf = root.requestAnimationFrame(step); }
        };
        this.raf = root.requestAnimationFrame(step);
    };

    V.zoomBy = function (f, at) {
        if (!this.cam) { return; }
        var next = C.zoom(this.cam, f, at, this.base);
        if (at || reducedMotion()) { this.setCam(next); } else { this.tween(this.cam, next, 200); }
        this.emit('zoom', { zoom: C.level(next, this.base) });
    };

    V.resetCamera = function (animate) {
        if (!this.base) { return; }
        if (animate && !reducedMotion()) { this.tween(this.cam, this.base, 280); } else { this.setCam(this.base); }
    };

    V.fitSelection = function () {
        var self = this;
        var boxes = this.store.get().select.map(function (id) { return self.boxes[id]; }).filter(Boolean);
        var cam = C.fit(boxes);
        if (cam) { this.tween(this.cam, C.clamp(cam, this.base), 280); }
    };

    V.onCamera = function () {
        if (!this.cam) { return; }
        var self = this, u = this.upp(), size = 6 * u;
        this.hatch.setAttribute('width', size);
        this.hatch.setAttribute('height', size);
        this.hatchBg.setAttribute('width', size);
        this.hatchBg.setAttribute('height', size);
        this.hatchLine.setAttribute('y2', size);
        this.hatchLine.setAttribute('x1', size / 2);
        this.hatchLine.setAttribute('x2', size / 2);
        this.hatchLine.style.strokeWidth = String(u);   // patterns ignore non-scaling-stroke
        root.cancelAnimationFrame(this.labelRaf);
        this.labelRaf = root.requestAnimationFrame(function () { self.drawLabels(); });
    };

    V.toFrame = function (e) {
        var pt = this.svg.createSVGPoint();
        pt.x = e.clientX;
        pt.y = e.clientY;
        return pt.matrixTransform(this.svg.getScreenCTM().inverse());
    };

    /** Labels at precomputed label points when they fit; markers with office counts (plan §4.2). */
    V.drawLabels = function () {
        var s = this.store.get(), c = this.cfg;
        this.gLabels.textContent = '';
        this.gMarkers.textContent = '';
        if (!s.payload || s.mode === 'table' || !this.order || !this.cam) { return; }
        var labels = c.panels.labels, markers = c.panels.markers;
        if (!labels && !markers) { return; }
        var self = this, u = this.upp(), placed = [];
        this.order.forEach(function (pth) {
            var id = Number(pth.getAttribute('data-id')), a = s.payload.areas[id] || {}, b = self.boxes[id], lp = self.labelPoints[id];
            if (!b || !lp) { return; }
            var cx = lp[0], cy = lp[1];
            if (markers && a.offices) {
                var g = el('svg:g', { class: 'gs-geo__marker' });
                var my = cy + (labels ? 18 * u : 0);
                g.appendChild(el('svg:circle', { cx: cx, cy: my, r: 9 * u }));
                g.appendChild(el('svg:text', { x: cx, y: my, 'font-size': 10 * u, text: self.fmt.int(a.offices) }));
                self.gMarkers.appendChild(g);
            }
            if (!labels || pth.getAttribute('data-kind') === 'urban') { return; }
            var name = a.short || a.name || '';
            var wpx = name.length * 6.4 + 8;
            if (b[2] / u < wpx || b[3] / u < 16) { return; }
            var box = { x: cx - (wpx * u) / 2, y: cy - 8 * u, w: wpx * u, h: 16 * u };
            if (placed.some(function (o) { return box.x < o.x + o.w && o.x < box.x + box.w && box.y < o.y + o.h && o.y < box.y + box.h; })) { return; }
            placed.push(box);
            self.gLabels.appendChild(el('svg:text', { class: 'gs-geo__label', x: cx, y: cy, 'font-size': 11 * u, 'stroke-width': 3 * u, text: name }));
        });
    };

    /* ------------------------------------------------------------------ input */
    V.bindPointer = function () {
        var self = this, svg = this.svg;
        var pts = new Map(), start = null, moved = false, pinch = null, downTarget = null, pointerType = 'mouse';
        svg.addEventListener('pointerdown', function (e) {
            pts.set(e.pointerId, e);
            downTarget = e.target;
            moved = false;
            pointerType = e.pointerType || 'mouse';
            if (pts.size === 1 && self.cam) { start = { x: e.clientX, y: e.clientY, cam: Object.assign({}, self.cam) }; }
            if (pts.size === 2) {
                var two = Array.from(pts.values());
                pinch = { d: Math.hypot(two[0].clientX - two[1].clientX, two[0].clientY - two[1].clientY), cam: Object.assign({}, self.cam) };
            }
        });
        svg.addEventListener('pointermove', function (e) {
            if (pts.has(e.pointerId)) { pts.set(e.pointerId, e); }
            if (pinch && pts.size === 2) {
                var two = Array.from(pts.values());
                var d = Math.hypot(two[0].clientX - two[1].clientX, two[0].clientY - two[1].clientY);
                var mid = self.toFrame({ clientX: (two[0].clientX + two[1].clientX) / 2, clientY: (two[0].clientY + two[1].clientY) / 2 });
                moved = true;
                self.setCam(C.zoom(pinch.cam, d / pinch.d, mid, self.base));
                return;
            }
            if (start && pts.size === 1) {
                var dx = e.clientX - start.x, dy = e.clientY - start.y;
                // Capture only once a drag passes 4 px, so a press still clicks the area (prototype finding).
                if (!moved && Math.hypot(dx, dy) > 4) { moved = true; try { svg.setPointerCapture(e.pointerId); } catch (x) { /* ignore */ } svg.classList.add('is-dragging'); self.hideTip(); }
                if (moved) { var u = self.upp(); self.setCam(C.pan(start.cam, -dx * u, -dy * u, self.base)); }
            } else if (!pts.size) { self.hover(e); }
        });
        var end = function (e) { pts.delete(e.pointerId); if (pts.size < 2) { pinch = null; } if (!pts.size) { start = null; svg.classList.remove('is-dragging'); } };
        svg.addEventListener('pointerup', end);
        svg.addEventListener('pointercancel', end);
        svg.addEventListener('pointerleave', function () { self.hideTip(); self.clearHover(); });
        svg.addEventListener('click', function (e) {
            if (moved) { moved = false; return; }
            var p = (e.target.closest && e.target.closest('.gs-geo__area')) || (downTarget && downTarget.closest && downTarget.closest('.gs-geo__area'));
            if (p) { self.activate(Number(p.getAttribute('data-id')), e.shiftKey, pointerType); }
        });
        svg.addEventListener('wheel', function (e) {
            var mode = self.cfg.interaction.wheel;
            if (mode === 'off') { return; }
            if (e.ctrlKey || e.metaKey || mode === 'always') { e.preventDefault(); self.zoomBy(e.deltaY < 0 ? 1.2 : 1 / 1.2, self.toFrame(e)); return; }
            self.hint.hidden = false;
            root.clearTimeout(self.hintT);
            self.hintT = root.setTimeout(function () { self.hint.hidden = true; }, 1300);
        }, { passive: false });
        svg.addEventListener('focusin', function (e) {
            var p = e.target.closest && e.target.closest('.gs-geo__area');
            if (!p) { return; }
            self.order.forEach(function (x) { x.setAttribute('tabindex', x === p ? '0' : '-1'); });
            self.showTip(Number(p.getAttribute('data-id')));
        });
        svg.addEventListener('focusout', function () { self.hideTip(); });
    };

    V.bindKeys = function () {
        var self = this;
        this.stage.addEventListener('keydown', function (e) {
            var p = e.target.closest && e.target.closest('.gs-geo__area');
            var dirs = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
            if (p && dirs[e.key]) {
                e.preventDefault();
                var i = self.order.indexOf(p);
                self.order[(i + dirs[e.key] + self.order.length) % self.order.length].focus();
                return;
            }
            if (p && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); self.activate(Number(p.getAttribute('data-id')), e.key === ' ' || e.shiftKey, 'keyboard'); return; }
            if (!self.cam) { return; }
            var u = self.upp() * 60;
            var pan = { ArrowLeft: [-u, 0], ArrowRight: [u, 0], ArrowUp: [0, -u], ArrowDown: [0, u] }[e.key];
            if (pan && e.target === self.stage) { e.preventDefault(); self.setCam(C.pan(self.cam, pan[0], pan[1], self.base)); }
            else if (e.key === '+' || e.key === '=') { e.preventDefault(); self.zoomBy(1.4); }
            else if (e.key === '-' || e.key === '_') { e.preventDefault(); self.zoomBy(1 / 1.4); }
            else if (e.key === '0') { self.resetCamera(true); }
            else if (e.key === '?' && self.helpBtn) { e.preventDefault(); self.toggleHelp(); }
            else if (e.key === 'Escape') { self.goUp(); }
        });
    };

    V.goUp = function () {
        var p = this.store.get().payload;
        if (!p) { return; }
        var trail = p.area.trail || [];
        if (trail.length > 1 && p.area.id !== this.rootArea) { this.drillTo(trail[trail.length - 2].id); }
    };

    V.hover = function (e) {
        var p = e.target.closest && e.target.closest('.gs-geo__area');
        if (!p) { this.hideTip(); this.clearHover(); return; }
        this.clearHover();
        p.classList.add('is-hover');
        this.hovered = p;
        this.showTip(Number(p.getAttribute('data-id')), e);
    };

    V.clearHover = function () { if (this.hovered) { this.hovered.classList.remove('is-hover'); } this.hovered = null; };

    V.showTip = function (id, e) {
        var s = this.store.get(), p = s.payload, t = this.t, fmt = this.fmt;
        if (!p) { return; }
        var a = p.areas[id] || { state: 'no_data', name: String(id) };
        var rows = [el('strong', { text: a.name }), a.type ? el('small', { class: 'gs-geo__muted', text: a.type }) : null];
        if (this.scale.type !== 'none') {
            rows.push(el('span', { class: 'gs-geo__tip-value', text: this.value(a) }));
            if (p.layer.aggregation === 'ratio' && typeof a.den === 'number' && a.state !== 'no_access') {
                rows.push(el('span', { class: 'gs-geo__muted', text: fmt.int(a.num || 0) + ' / ' + fmt.int(a.den) }));
            }
            if (a.offices) { rows.push(el('span', { class: 'gs-geo__muted', text: fmt.int(a.offices) + ' ' + t('offices').toLowerCase() })); }
            if (a.partial) { rows.push(el('small', { class: 'gs-geo__muted', text: t('partial_note') })); }
            if (this.scale.type === 'diverging' && typeof a.value === 'number') {
                var d = a.value - this.scale.ref;
                rows.push(el('span', { class: 'gs-geo__muted', text: (d >= 0 ? '+' : '−') + fmt(Math.abs(d), p.layer.format) + ' ' + t('vs') }));
            }
        }
        if (a.drillable && p.level < this.cfg.levels.max && a.state !== 'no_access') { rows.push(el('small', { class: 'gs-geo__muted', text: '↵ ' + t('open') })); }
        this.tip.textContent = '';
        rows.forEach(function (r) { if (r) { this.tip.appendChild(r); } }, this);
        this.tip.hidden = false;
        var sr = this.stage.getBoundingClientRect(), x, y;
        if (e) { x = e.clientX - sr.left + 14; y = e.clientY - sr.top + 14; }
        else {
            var lp = this.labelPoints[id], m = this.svg.getScreenCTM();
            if (!lp || !m) { return; }
            x = lp[0] * m.a + m.e - sr.left;
            y = lp[1] * m.d + m.f - sr.top;
        }
        this.tip.style.left = G.clamp(x, 6, Math.max(6, sr.width - this.tip.offsetWidth - 6)) + 'px';
        this.tip.style.top = G.clamp(y, 6, Math.max(6, sr.height - this.tip.offsetHeight - 6)) + 'px';
    };

    V.hideTip = function () { this.tip.hidden = true; };

    V.helpSeen = function () { var ls = store(); try { return !!(ls && ls.getItem('gs-geo-help') !== null); } catch (e) { return false; } };

    V.toggleHelp = function (force, quiet) {
        if (!this.helpBtn) { return; }
        var open = force === undefined ? this.helpCard.hidden : !!force;
        if (open) { this.renderHelp(); }
        this.helpCard.hidden = !open;
        this.helpBtn.setAttribute('aria-pressed', open ? 'true' : 'false');
        this.helpBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        this.store.dispatch({ type: 'help', open: open });
        if (!quiet) {
            var ls = store();
            try { if (ls) { ls.setItem('gs-geo-help', open ? '1' : '0'); } } catch (e) { /* storage blocked */ }
            this.emit('help', { open: open });
        }
        if (this.store.get().payload) { this.renderOverlay(); }
    };

    V.renderHelp = function () {
        var self = this, t = this.t;
        var list = el('ul', {}, G.config.helpKeys(this.cfg).map(function (k) {
            var parts = t('h_' + k).split('|');
            return el('li', {}, [el('strong', { text: parts[0] }), el('span', { text: parts[1] || '' })]);
        }));
        this.helpCard.textContent = '';
        this.helpCard.appendChild(el('div', { class: 'gs-geo__help-head' }, [
            el('strong', { text: t('help') }),
            el('button', { type: 'button', class: 'gs-geo__btn', 'aria-label': t('help_close'), title: t('help_close'), onclick: function () { self.toggleHelp(false); self.helpBtn.focus(); } }, ['×'])
        ]));
        this.helpCard.appendChild(list);
        this.helpCard.appendChild(el('p', { class: 'gs-geo__help-foot', text: t('help_foot') }));
    };

    /** Click / Enter on an area: drill, select or choose depending on intent and level (plan §5.2). */
    V.activate = function (id, additive, pointerType) {
        var s = this.store.get(), p = s.payload, c = this.cfg;
        var a = p && p.areas[id];
        if (!a || a.state === 'no_access') { return; }
        var canDrill = a.drillable && p.level < c.levels.max && c.interaction.click !== 'none';
        var multi = c.interaction.select === 'multi';
        if (c.intent === 'pick') {
            if (canDrill && s.select.indexOf(id) !== -1) { this.drillTo(id); return; }   // second click on a picked area opens it
            this.toggle(id, false, true);
            return;
        }
        if (c.interaction.click === 'select' || (additive && multi)) { this.toggle(id, additive && multi); return; }
        if (canDrill && c.interaction.click === 'drill') {
            if (pointerType === 'touch' && c.interaction.touch !== 'tap-drill' && s.select.indexOf(id) === -1) { this.toggle(id, false); this.showTip(id); return; }
            this.drillTo(id);
            return;
        }
        if (c.interaction.leaf_click !== 'none') { this.toggle(id, false); }
    };

    V.toggle = function (id, additive, keep) {
        if (keep && this.store.get().select.indexOf(id) !== -1) { return; }
        this.store.dispatch({ type: 'toggle', id: id, additive: additive });
        this.afterSelect('user');
    };

    V.afterSelect = function (via) {
        var sel = this.store.get().select;
        this.syncSelection();
        this.renderTable();
        if (this.input) {
            this.input.value = sel.length ? String(sel[0]) : '';
            this.input.dispatchEvent(new root.Event('change', { bubbles: true }));
        }
        this.writeUrl('replace');
        this.emit('select', { areas: sel.slice(), via: via });
        this.renderPanel();
    };

    V.syncSelection = function () {
        var sel = this.store.get().select;
        (this.order || []).forEach(function (pth) { pth.classList.toggle('is-selected', sel.indexOf(Number(pth.getAttribute('data-id'))) !== -1); });
    };

    /* ------------------------------------------------------------------ details panel */
    V.renderPanel = function () {
        if (!this.panel) { return; }
        var self = this, s = this.store.get(), p = s.payload, t = this.t, fmt = this.fmt;
        this.panel.textContent = '';
        if (!p) { return; }
        var sel = s.select.filter(function (id) { return p.areas[id]; });
        if (this.cfg.intent === 'pick') {
            var chosen = sel[0];
            this.panel.appendChild(el('div', { class: 'gs-geo__chosen' }, [
                el('span', { class: 'gs-geo__muted', text: t('selected') }),
                el('strong', { text: chosen ? p.areas[chosen].name : '—' }),
                chosen ? el('button', { type: 'button', class: 'gs-geo__btn', onclick: function () { self.select([]); } }, [t('clear')]) : null
            ]));
            return;
        }
        if (sel.length > 1) {
            this.panel.appendChild(el('h3', { class: 'gs-geo__panel-title', text: t('compare') }));
            this.panel.appendChild(el('dl', { class: 'gs-geo__compare' }, sel.reduce(function (acc, id) {
                var a = p.areas[id];
                acc.push(el('dt', { text: a.name }));
                acc.push(el('dd', { text: self.value(a) + (self.scale.type === 'diverging' && typeof a.value === 'number' ? ' (' + (a.value >= self.scale.ref ? '+' : '−') + fmt(Math.abs(a.value - self.scale.ref), p.layer.format) + ')' : '') }));
                return acc;
            }, [])));
            return;
        }
        var target = sel[0] || p.area.id;
        var a = sel[0] ? p.areas[sel[0]] : null;
        this.panel.appendChild(el('h3', { class: 'gs-geo__panel-title', text: a ? a.name : p.area.name }));
        var summary = a ? this.value(a) : (p.stats && typeof p.stats.total === 'number' ? fmt(p.stats.total, p.layer.format) : null);
        if (summary) { this.panel.appendChild(el('p', { class: 'gs-geo__panel-value' }, [el('strong', { text: summary }), ' ', el('span', { class: 'gs-geo__muted', text: p.layer.label })])); }
        if (!a && p.coverage && p.coverage.offices) {
            this.panel.appendChild(el('p', { class: 'gs-geo__muted', text: t('offices') + ': ' + fmt.int(p.coverage.offices) + (p.unplaced && p.unplaced.offices ? ' · ' + t('unplaced') + ': ' + fmt.int(p.unplaced.offices) : '') }));
        }
        if (a && a.state === 'no_access') { return; }
        var list = el('ul', { class: 'gs-geo__offices' }, [el('li', { class: 'gs-geo__muted', text: t('loading') })]);
        this.panel.appendChild(el('h4', { class: 'gs-geo__panel-sub', text: t('details') }));
        this.panel.appendChild(list);
        var token = ++this.detailsToken;
        this.src.details(target, this.query(target)).then(function (res) {
            if (token !== self.detailsToken) { return; }
            list.textContent = '';
            var rows = (res.status === 200 && res.body && res.body.offices) || [];
            if (!rows.length) { list.appendChild(el('li', { class: 'gs-geo__muted', text: t('none') })); return; }
            rows.forEach(function (o) {
                var name = typeof o.link === 'string' && o.link.charAt(0) === '/' ? el('a', { href: o.link, text: o.name }) : el('span', { text: o.name });
                list.appendChild(el('li', { class: o.here ? 'is-here' : null }, [
                    name,
                    el('small', { class: 'gs-geo__muted', text: [o.area_name, o.here ? t('here') : null].filter(Boolean).join(' · ') }),
                    el('span', { class: 'gs-geo__num', text: o.display || (typeof o.value === 'number' ? fmt(o.value, p.layer.format) : '—') })
                ]));
            });
            self.emit('details', { area: target, offices: rows.length });
        }).catch(function () { if (token === self.detailsToken) { list.textContent = ''; list.appendChild(el('li', { class: 'gs-geo__muted', text: t('error') })); } });
    };

    /* ------------------------------------------------------------------ host binding */
    V.bindHost = function () {
        var self = this, sel = this.cfg.bind_filters;
        if (!sel) { return; }
        var form = doc.querySelector(sel);
        if (!form) { return; }
        var keys = (this.cfg.source && this.cfg.source.param_keys) || [];
        this.onHostChange = function (e) {
            var name = e.target && e.target.name;
            if (!name || keys.indexOf(name) === -1) { return; }
            var o = {};
            o[name] = e.target.value;
            self.src.setParams(o);
            self.emit('filter', { params: o, via: 'host' });
            self.go(self.store.get().area || self.initialArea, { keepSelection: true });
        };
        form.addEventListener('change', this.onHostChange);
        this.hostForm = form;
    };

    /* ------------------------------------------------------------------ public API */
    V.drillTo = function (id) { return this.go(Number(id)); };
    V.setLayer = function (key, via) {
        if (!G.config.allowsLayer(this.cfg, key)) { return Promise.resolve(null); }
        this.store.dispatch({ type: 'layer', layer: key });
        this.emit('layer', { layer: key, via: via || 'api' });
        return this.go(this.store.get().area, { keepSelection: true });
    };
    V.setFilters = function (filters, via) {
        this.store.dispatch({ type: 'filters', filters: filters });
        this.emit('filter', { filters: this.store.get().filters, via: via || 'api' });
        return this.go(this.store.get().area, { keepSelection: true });
    };
    V.select = function (ids, via) {
        this.store.dispatch({ type: 'select', select: (ids || []).map(Number) });
        this.afterSelect(via || 'api');
    };
    V.refresh = function () { this.src.refresh(); return this.go(this.store.get().area || this.initialArea, { keepSelection: true }); };
    /** Runtime option change (layer 6); locked keys are refused with an error event. */
    V.setOption = function (path, value) {
        var next = G.config.set(this.cfg, path, value);
        if (!next) { this.emit('error', { locked: path, lock: G.config.lockFor(this.cfg, path) }); return false; }
        var area = this.store.get().area;
        this.destroy(true);
        this.cfg = next;
        View.call(this, this.rootEl, next, null);
        if (area) { this.go(area, { keepSelection: true }); }
        return true;
    };
    V.destroy = function (keepRegistration) {
        root.cancelAnimationFrame(this.raf);
        if (this.onPop) { root.removeEventListener('popstate', this.onPop); }
        if (this.resizeObs) { this.resizeObs.disconnect(); }
        if (this.hostForm) { this.hostForm.removeEventListener('change', this.onHostChange); }
        var r = this.rootEl;
        Array.prototype.slice.call(r.children).forEach(function (n) {
            if (!n.matches('script, [data-gs-geo-fallback], input[type="hidden"]')) { r.removeChild(n); }
        });
        r.classList.remove('is-mounted');
        if (this.fallback) { this.fallback.hidden = false; }
        if (!keepRegistration && G.registry) { G.registry.delete(this.id); }
    };

    G.View = View;
})(typeof globalThis !== 'undefined' ? globalThis : window);
