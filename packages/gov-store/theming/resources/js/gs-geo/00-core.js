/*
 * gs-geo — neutral geographic view engine (geo-view plan §7). Plain ES2019 IIFE modules concatenated in file
 * order into the lazy "geo" bundle. Pure modules export to globalThis.GsGeo so `node --test` can load them in a
 * vm context. The engine knows boundary files, a gs-geo-config/1 object and gs-geo-data/1 payloads, nothing else.
 */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    G.SVGNS = 'http://www.w3.org/2000/svg';

    G.clamp = function (v, lo, hi) { return Math.max(lo, Math.min(hi, v)); };

    /** el('div', {class:'x', text:'…', onclick:fn}, [children]) — text only via textContent. */
    G.el = function (tag, attrs, kids) {
        var doc = root.document;
        var svg = tag.indexOf('svg:') === 0;
        var n = svg ? doc.createElementNS(G.SVGNS, tag.slice(4)) : doc.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k];
            if (v === null || v === undefined || v === false) { return; }
            if (k === 'text') { n.textContent = v; } else if (k.indexOf('on') === 0) { n.addEventListener(k.slice(2), v); } else { n.setAttribute(k, v === true ? '' : v); }
        });
        [].concat(kids || []).forEach(function (k) {
            if (k === null || k === undefined || k === false) { return; }
            n.appendChild(k.nodeType ? k : doc.createTextNode(String(k)));
        });
        return n;
    };

    /** Strings come from the server (config.strings, kit translations); a missing key shows the key. */
    G.STRINGS = {};

    G.strings = function (overrides) {
        var s = {};
        Object.keys(G.STRINGS).forEach(function (k) { s[k] = G.STRINGS[k]; });
        Object.keys(overrides || {}).forEach(function (k) { s[k] = overrides[k]; });
        return function (key, params) {
            var v = s[key] === undefined ? key : s[key];
            Object.keys(params || {}).forEach(function (p) { v = v.split(':' + p).join(params[p]); });
            return v;
        };
    };

    /** Number formatting in the viewer's locale (bn-BD gives Bengali digits). */
    G.formatter = function (locale) {
        var tag = /^bn/i.test(locale || '') ? 'bn-BD' : 'en-IN';
        var cache = {};
        var nf = function (d) {
            if (!cache[d]) { cache[d] = new Intl.NumberFormat(tag, { maximumFractionDigits: d, minimumFractionDigits: d }); }
            return cache[d];
        };
        var bn = tag === 'bn-BD';
        var fmt = function (v, format) {
            if (v === null || v === undefined || typeof v !== 'number' || isNaN(v)) { return '—'; }
            if (format === 'percent') { return nf(1).format(v * 100) + '%'; }
            if (format === 'decimal') { return nf(2).format(v); }
            if (format === 'money') {
                var unit = v >= 1e7 ? [v / 1e7, bn ? 'কোটি' : 'crore'] : v >= 1e5 ? [v / 1e5, bn ? 'লাখ' : 'lakh'] : [v, ''];
                return '৳' + nf(unit[1] ? 2 : 0).format(unit[0]) + (unit[1] ? ' ' + unit[1] : '');
            }
            return nf(0).format(Math.round(v));
        };
        fmt.int = function (v) { return nf(0).format(v); };
        fmt.short = function (v, format) { return format === 'percent' ? nf(0).format(v * 100) : fmt(v, format); };
        fmt.locale = tag;
        return fmt;
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);
