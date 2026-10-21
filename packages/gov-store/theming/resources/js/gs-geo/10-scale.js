/* gs-geo scale: bins values into tone classes gs-geo__area[data-bin=1..7] / [data-div=-3..3] (plan §5.2, §7.4). */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};

    function okValues(areas) {
        return Object.keys(areas || {}).map(function (k) { return areas[k]; })
            .filter(function (a) { return (a.state === 'ok' || a.state === 'partial') && typeof a.value === 'number'; })
            .map(function (a) { return a.value; })
            .sort(function (a, b) { return a - b; });
    }

    function unique(list) {
        return list.filter(function (v, i) { return i === 0 || v !== list[i - 1]; });
    }

    /**
     * build(scaleConfig, payload) → { type, breaks?, bins?, tone(i)?, classify(v) → {bin, index} | {div} | {}, ref?, span? }
     * - none: no binning (picker, reference layers)
     * - single: every area with data has the same value (prototype finding, plan §12)
     * - sequential with a fixed domain only for percent layers; otherwise quantile breaks
     */
    G.scale = function (cfg, payload) {
        cfg = cfg || {};
        var type = cfg.type || 'quantile';
        var layer = (payload && payload.layer) || {};
        var vals = okValues(payload && payload.areas);
        var none = function () { return {}; };
        if (type === 'none') { return { type: 'none', classify: none }; }
        if (!vals.length) { return { type: 'empty', classify: none }; }
        var uniq = unique(vals);

        if (type === 'diverging') {
            var ref = typeof cfg.reference === 'number' ? cfg.reference
                : (payload.stats && typeof payload.stats.national === 'number' && layer.aggregation === 'ratio' ? payload.stats.national
                    : vals.reduce(function (a, b) { return a + b; }, 0) / vals.length);
            var span = Math.max.apply(null, vals.map(function (v) { return Math.abs(v - ref); })) || 1;
            return {
                type: 'diverging', ref: ref, span: span, steps: 3,
                classify: function (v) {
                    var d = v - ref, m = Math.abs(d);
                    if (m < span * 0.02) { return { div: 0 }; }
                    var step = m <= span / 3 ? 1 : m <= (2 * span) / 3 ? 2 : 3;
                    return { div: d < 0 ? -step : step };
                }
            };
        }
        if (type === 'categorical') {
            // coverage intent: none / partial / full against the domain maximum (default 1)
            var full = Array.isArray(cfg.domain) ? cfg.domain[1] : 1;
            return {
                type: 'categorical', categories: ['none_cat', 'partial_cat', 'full'],
                classify: function (v) { var i = v <= 0 ? 0 : v >= full ? 2 : 1; return { bin: [1, 4, 7][i], index: i }; }
            };
        }
        if (uniq.length === 1) {
            return { type: 'single', value: uniq[0], classify: function () { return { bin: 4, index: 0 }; } };
        }

        var steps = G.clamp(cfg.steps || 5, 2, 7);
        var breaks;
        if (type === 'threshold' && Array.isArray(cfg.thresholds) && cfg.thresholds.length) {
            breaks = [Math.min(vals[0], cfg.thresholds[0])].concat(cfg.thresholds, [Math.max(vals[vals.length - 1], cfg.thresholds[cfg.thresholds.length - 1])]);
        } else if (type === 'sequential' && Array.isArray(cfg.domain) && layer.format === 'percent') {
            var lo = cfg.domain[0], hi = cfg.domain[1];
            breaks = [];
            for (var i = 0; i <= steps; i++) { breaks.push(lo + ((hi - lo) * i) / steps); }
        } else {
            var n = Math.min(steps, uniq.length);
            breaks = [vals[0]];
            for (var j = 1; j < n; j++) { breaks.push(vals[Math.floor((j * vals.length) / n)]); }
            breaks.push(vals[vals.length - 1]);
        }
        breaks = unique(breaks);
        var bins = breaks.length - 1;
        var tone = function (k) { return bins === 1 ? 4 : Math.round(1 + (k * 6) / (bins - 1)); };
        return {
            type: type, breaks: breaks, bins: bins, tone: tone,
            classify: function (v) {
                var k = 0;
                while (k < bins - 1 && v > breaks[k + 1]) { k++; }
                return { bin: tone(k), index: k };
            }
        };
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);
