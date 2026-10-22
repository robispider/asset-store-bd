/*
 * gs-geo public API (plan §7.3): GsGeoView.get(id), GsGeoView.mount(el, config, payload), GsGeoView.loader(name, fn).
 * Auto-mounts every [data-gs-geo] element whose markup carries its config (and optional first payload) as JSON.
 */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    var registry = G.registry = G.registry || new Map();
    var pending = {};

    function readJson(el, attr) {
        var node = el.querySelector('script[type="application/json"][' + attr + ']');
        if (!node) { return null; }
        try { return JSON.parse(node.textContent || 'null'); } catch (e) { root.console && root.console.error('gs-geo: bad ' + attr, e); return null; }
    }

    function mount(el, cfg, payload) {
        cfg = cfg || readJson(el, 'data-gs-geo-config');
        if (!cfg || cfg.contract !== 'gs-geo-config/1') { root.console && root.console.error('gs-geo: missing or unknown config', el); return null; }
        if (registry.has(cfg.id)) { return registry.get(cfg.id); }
        var view = new G.View(el, cfg, payload === undefined ? readJson(el, 'data-gs-geo-payload') : payload);
        registry.set(cfg.id, view);
        (pending[cfg.id] || []).forEach(function (fn) { fn(view); });
        delete pending[cfg.id];
        return view;
    }

    function scan(scope) {
        Array.prototype.forEach.call((scope || root.document).querySelectorAll('[data-gs-geo]:not(.is-mounted)'), function (el) { mount(el); });
    }

    root.GsGeoView = {
        get: function (id) { return registry.get(id) || null; },
        /** Calls fn(view) now, or once the view mounts (the bundle loads lazily). */
        ready: function (id, fn) { if (registry.has(id)) { fn(registry.get(id)); } else { (pending[id] = pending[id] || []).push(fn); } },
        mount: mount,
        scan: scan,
        all: function () { return Array.from(registry.keys()); },
        /** Register a function source (§7.3): only for data the page already rendered for this viewer. */
        loader: function (name, fn) { G.registerLoader(name, fn); }
    };

    if (root.document) {
        if (root.document.readyState === 'loading') { root.document.addEventListener('DOMContentLoaded', function () { scan(); }); } else { scan(); }
        root.document.dispatchEvent(new root.CustomEvent('gs-geo:loaded'));
    }
})(typeof globalThis !== 'undefined' ? globalThis : window);
