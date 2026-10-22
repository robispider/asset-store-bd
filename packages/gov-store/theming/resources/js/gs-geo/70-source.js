/*
 * gs-geo data sources (plan §7.2): remote (the data API), inline (the payload embedded in the page for first
 * paint), function (a host loader registered with GsGeoView.loader(name, fn) — only for data the page already
 * rendered for this viewer, because it bypasses server scope; §7.3). Requests are deduplicated and the previous
 * request of a view is aborted when a newer one starts.
 */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    var loaders = {};

    function query(cfg, q) {
        var params = new URLSearchParams();
        var src = cfg.source || {};
        Object.keys(src.params || {}).forEach(function (k) {
            var v = src.params[k];
            if (v !== null && v !== undefined && v !== '') { params.set(k, String(v)); }
        });
        params.set('area', String(q.area));
        if (q.layer) { params.set('layer', q.layer); }
        if (q.fallback && q.fallback.length) { params.set('fallback', q.fallback.join(',')); }
        Object.keys(q.filters || {}).sort().forEach(function (k) { params.set('filters[' + k + ']', String(q.filters[k])); });
        if (q.level) { params.set('level', String(q.level)); }
        if (cfg.levels && cfg.levels.urban && cfg.levels.urban !== 'colour') { params.set('urban', cfg.levels.urban); }
        return params;
    }

    function request(url, signal) {
        return root.fetch(url, { credentials: 'same-origin', signal: signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (res.status === 304) { return { status: 304 }; }
                return res.json().catch(function () { return null; }).then(function (body) { return { status: res.status, body: body }; });
            });
    }

    G.source = function (cfg, inline) {
        var first = inline || null;
        var controller = null;
        var cache = new Map();
        var mode = (cfg.source && cfg.source.mode) || (cfg.endpoints && cfg.endpoints.data ? 'remote' : 'inline');

        function abortPrevious() {
            if (controller) { controller.abort(); }
            controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            return controller ? controller.signal : undefined;
        }

        return {
            mode: mode,
            /** data({area, layer, fallback, filters}) → Promise<{status, body}> */
            data: function (q) {
                if (first && (q.area === 'auto' || q.area === first.area.id) && !q.layer && !q.level && !Object.keys(q.filters || {}).length) {
                    var p = first;
                    first = null;
                    return Promise.resolve({ status: 200, body: p });
                }
                first = null;
                if (mode === 'function') {
                    var fn = loaders[cfg.source.key];
                    if (!fn) { return Promise.resolve({ status: 404, body: null }); }
                    return Promise.resolve(fn(q, cfg)).then(function (body) { return { status: body ? 200 : 404, body: body }; });
                }
                if (!cfg.endpoints || !cfg.endpoints.data) { return Promise.resolve({ status: 404, body: null }); }
                var url = cfg.endpoints.data + (cfg.endpoints.data.indexOf('?') === -1 ? '?' : '&') + query(cfg, q).toString();
                if (cache.has(url)) { return Promise.resolve(cache.get(url)); }
                return request(url, abortPrevious()).then(function (res) {
                    if (res.status === 200) {
                        cache.set(url, res);
                        if (cache.size > 30) { cache.delete(cache.keys().next().value); }
                    }
                    return res;
                });
            },
            /** details(areaId, extra) → Promise<{status, body}> — side panel rows, same scope rules on the server. */
            details: function (areaId, q) {
                if (mode === 'function') {
                    var fn = loaders[cfg.source.key + ':details'];
                    return Promise.resolve(fn ? fn(areaId, q, cfg) : null).then(function (body) { return { status: body ? 200 : 404, body: body }; });
                }
                if (!cfg.endpoints || !cfg.endpoints.details) { return Promise.resolve({ status: 404, body: null }); }
                var params = query(cfg, Object.assign({}, q, { area: areaId }));
                return request(cfg.endpoints.details + (cfg.endpoints.details.indexOf('?') === -1 ? '?' : '&') + params.toString());
            },
            refresh: function () { cache.clear(); first = null; },
            setParams: function (params) {
                cfg.source.params = Object.assign({}, cfg.source.params || {}, params);
                Object.keys(cfg.source.params).forEach(function (k) { if (cfg.source.params[k] === '' || cfg.source.params[k] === null) { delete cfg.source.params[k]; } });
                cache.clear();
                first = null;
            }
        };
    };

    G.registerLoader = function (name, fn) { loaders[name] = fn; };
    G.query = query;
})(typeof globalThis !== 'undefined' ? globalThis : window);
