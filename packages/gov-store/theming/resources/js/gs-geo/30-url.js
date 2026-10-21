/* gs-geo URL state: ?gv-<id>[area]=…&gv-<id>[layer]=…&gv-<id>[f][code]=… (plan §4.1, §5.1 layer 5). Pure. */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    var U = G.url = {};
    var KEY = /^gv-([a-z][a-z0-9-]*)\[([a-z_]+)\](?:\[([a-z0-9_]+)\])?$/;

    /** read('programme-map', '?gv-programme-map[area]=3142') → { area: 3142, layer: null, filters: {}, select: [] } */
    U.read = function (id, search) {
        var out = { area: null, layer: null, filters: {}, select: [], mode: null };
        new URLSearchParams(search || '').forEach(function (value, key) {
            var m = KEY.exec(key);
            if (!m || m[1] !== id) { return; }
            if (m[2] === 'area' && /^\d+$/.test(value)) { out.area = Number(value); }
            else if (m[2] === 'layer' && /^[a-z][a-z0-9_]*$/.test(value)) { out.layer = value; }
            else if (m[2] === 'mode' && (value === 'map' || value === 'table')) { out.mode = value; }
            else if (m[2] === 'select') { out.select = value.split(',').filter(function (v) { return /^\d+$/.test(v); }).map(Number); }
            else if (m[2] === 'f' && m[3]) { out.filters[m[3]] = value; }
        });
        return out;
    };

    /** write(id, state, search) → new query string ('' or '?…'), keeping every parameter that isn't ours. */
    U.write = function (id, state, search) {
        var params = new URLSearchParams(search || '');
        var drop = [];
        params.forEach(function (v, key) { var m = KEY.exec(key); if (m && m[1] === id) { drop.push(key); } });
        drop.forEach(function (k) { params.delete(k); });
        var p = 'gv-' + id;
        if (state.area !== null && state.area !== undefined && state.area !== state.root) { params.set(p + '[area]', String(state.area)); }
        if (state.layer) { params.set(p + '[layer]', state.layer); }
        if (state.mode === 'table') { params.set(p + '[mode]', 'table'); }
        if (state.select && state.select.length) { params.set(p + '[select]', state.select.join(',')); }
        Object.keys(state.filters || {}).sort().forEach(function (k) {
            var v = state.filters[k];
            if (v !== null && v !== undefined && v !== '') { params.set(p + '[f][' + k + ']', String(v)); }
        });
        var s = params.toString().replace(/%5B/g, '[').replace(/%5D/g, ']');
        return s ? '?' + s : '';
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);
