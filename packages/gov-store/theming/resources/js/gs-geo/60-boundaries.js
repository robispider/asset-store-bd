/*
 * gs-geo boundaries: fetches children/<id>.json and flat/level-<n>.json from the versioned, immutable boundary
 * folder, keeps an LRU of 40 files and refuses files of another version (plan §6.6, §7.2).
 */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    var MAX = 40;
    var lru = new Map();
    var inflight = new Map();

    function remember(url, doc) {
        lru.delete(url);
        lru.set(url, doc);
        while (lru.size > MAX) { lru.delete(lru.keys().next().value); }
    }

    function load(url, version, signal) {
        if (lru.has(url)) {
            var hit = lru.get(url);
            remember(url, hit);
            return Promise.resolve(hit);
        }
        if (!inflight.has(url)) {
            inflight.set(url, root.fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (res) {
                    if (res.status === 404) { return null; }
                    if (!res.ok) { throw new Error('boundary ' + res.status); }
                    return res.json();
                })
                .then(function (doc) {
                    if (doc && version && doc.version !== version) { throw new Error('boundary version ' + doc.version + ' ≠ ' + version); }
                    if (doc) { remember(url, doc); }
                    return doc;
                })
                .finally(function () { inflight.delete(url); }));
        }
        var p = inflight.get(url);
        if (!signal) { return p; }
        return new Promise(function (resolve, reject) {
            var onAbort = function () { reject(new DOMException('superseded', 'AbortError')); };
            if (signal.aborted) { onAbort(); return; }
            signal.addEventListener('abort', onAbort, { once: true });
            p.then(resolve, reject);
        });
    }

    G.boundaries = {
        children: function (boundary, areaId, signal) {
            return load(boundary.base_url.replace(/\/$/, '') + '/children/' + areaId + '.json', boundary.version, signal);
        },
        flat: function (boundary, level, signal) {
            return load(boundary.base_url.replace(/\/$/, '') + '/flat/level-' + level + '.json', boundary.version, signal);
        },
        size: function () { return lru.size; },
        clear: function () { lru.clear(); }
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);
