/*
 * gs-geo config: the server resolves layers 1–4 (defaults → intent → preset → host) and embeds the result.
 * The engine applies layers 5–6 (URL state, runtime calls) to keys that are not locked (plan §5.1). Pure.
 */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    var K = G.config = {};

    K.clone = function (o) { return JSON.parse(JSON.stringify(o)); };

    K.get = function (o, path) {
        return String(path).split('.').reduce(function (c, k) { return c === null || c === undefined ? undefined : c[k]; }, o);
    };

    /** The lock that covers a path ('source' locks 'source.key'), or null. */
    K.lockFor = function (cfg, path) {
        var locks = cfg.locked || [];
        for (var i = 0; i < locks.length; i++) {
            if (path === locks[i] || path.indexOf(locks[i] + '.') === 0) { return locks[i]; }
        }
        return null;
    };

    /** set(cfg, 'levels.max', 4) → new config, or null when the key is locked. */
    K.set = function (cfg, path, value) {
        if (K.lockFor(cfg, path) || path === 'locked' || path === 'contract' || path === 'id') { return null; }
        var next = K.clone(cfg);
        var keys = path.split('.');
        var c = next;
        keys.slice(0, -1).forEach(function (k) {
            if (!c[k] || typeof c[k] !== 'object' || Array.isArray(c[k])) { c[k] = {}; }
            c = c[k];
        });
        c[keys[keys.length - 1]] = value;
        return next;
    };

    /** Is a layer key allowed by the config? */
    K.allowsLayer = function (cfg, key) {
        var allow = cfg.layers && cfg.layers.allow;
        return allow === '*' || !allow || allow.indexOf(key) !== -1;
    };

    /**
     * URL state → initial view state. Locked keys and disallowed layers are ignored; the URL never widens access
     * (the server still answers no_access for areas outside scope).
     */
    K.initialState = function (cfg, urlState) {
        var u = urlState || {};
        var state = { area: null, layer: null, filters: {}, select: [], mode: 'map' };
        if (cfg.url_state !== false) {
            if (u.area && !K.lockFor(cfg, 'levels.start')) { state.area = u.area; }
            if (u.layer && K.allowsLayer(cfg, u.layer) && !K.lockFor(cfg, 'layers.default')) { state.layer = u.layer; }
            if (u.filters && !K.lockFor(cfg, 'filters')) { state.filters = u.filters; }
            if (u.select) { state.select = u.select; }
            if (u.mode && cfg.panels && cfg.panels.table !== 'none') { state.mode = u.mode; }
        }
        if (cfg.panels && cfg.panels.table === 'always') { state.mode = 'table'; }
        if (cfg.input && cfg.input.value && !state.select.length) { state.select = [cfg.input.value]; }
        return state;
    };

    /** Keys of the help panel, following intent and enabled toolbar (plan §4.1 "Exploring help"). */
    K.helpKeys = function (cfg) {
        var t = cfg.toolbar || {};
        var keys = cfg.intent === 'pick' ? ['pick', 'up', 'zoom', 'pan'] : ['open', 'up', 'zoom', 'pan', 'read'];
        if (cfg.panels && cfg.panels.legend !== false && cfg.intent !== 'pick') { keys.push('legend'); }
        if (cfg.interaction && cfg.interaction.select === 'multi') { keys.splice(1, 0, 'compare'); }
        if (t.layers || t.filters) { keys.push('layer'); }
        if (t.search) { keys.push('search'); }
        if (t.table && cfg.panels && cfg.panels.table === 'toggle') { keys.push('table'); }
        keys.push('states');
        return keys;
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);
