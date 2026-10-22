/* gs-geo store: one state object and a reducer; subscribers render on change (plan §7.2). Pure. */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};

    function reduce(state, action) {
        var s = Object.assign({}, state);
        switch (action.type) {
            case 'loading': s.loading = true; s.error = null; return s;
            case 'loaded':
                s.loading = false; s.error = null; s.payload = action.payload; s.file = action.file || null;
                s.area = action.payload.area.id;
                if (!action.keepSelection) { s.select = []; }
                s.select = s.select.filter(function (id) { return action.payload.areas[id]; });
                return s;
            case 'error': s.loading = false; s.error = action.error; return s;
            case 'layer': s.layer = action.layer; return s;
            case 'filters':
                s.filters = Object.assign({}, s.filters, action.filters);
                Object.keys(s.filters).forEach(function (k) { if (s.filters[k] === undefined || s.filters[k] === null || s.filters[k] === '') { delete s.filters[k]; } });
                return s;
            case 'select': s.select = action.select.slice(); return s;
            case 'toggle': {
                var has = s.select.indexOf(action.id) !== -1;
                if (action.additive) { s.select = has ? s.select.filter(function (x) { return x !== action.id; }) : s.select.concat([action.id]); }
                else { s.select = has && s.select.length === 1 ? [] : [action.id]; }
                return s;
            }
            case 'mode': s.mode = action.mode; return s;
            case 'help': s.help = action.open; return s;
            case 'legend': s.legendPin = action.index === s.legendPin ? null : action.index; return s;
            default: return state;
        }
    }

    G.reduce = reduce;

    G.store = function (initial) {
        var state = Object.assign({ area: null, layer: null, filters: {}, select: [], mode: 'map', loading: false, error: null, payload: null, file: null, help: false, legendPin: null }, initial || {});
        var subs = [];
        return {
            get: function () { return state; },
            dispatch: function (action) {
                var prev = state;
                state = reduce(state, action);
                if (state !== prev) { subs.slice().forEach(function (fn) { fn(state, prev, action); }); }
                return state;
            },
            subscribe: function (fn) { subs.push(fn); return function () { subs = subs.filter(function (x) { return x !== fn; }); }; }
        };
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);
