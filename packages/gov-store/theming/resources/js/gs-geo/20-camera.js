/* gs-geo camera: viewBox math in the shared national frame. Pure; the view applies the result to the <svg>. */
(function (root) {
    'use strict';
    var G = root.GsGeo = root.GsGeo || {};
    var C = G.camera = {};

    C.parse = function (viewBox) {
        var p = String(viewBox || '0 0 1 1').split(/[\s,]+/).map(Number);
        return { x: p[0], y: p[1], w: p[2] || 1, h: p[3] || 1 };
    };

    /** The resting camera for a children file: its viewBox with 4% padding. */
    C.base = function (viewBox) {
        var v = C.parse(viewBox);
        var pad = Math.max(v.w, v.h) * 0.04;
        return { x: v.x - pad, y: v.y - pad, w: v.w + 2 * pad, h: v.h + 2 * pad };
    };

    /** Keep zoom between 1/14 and 1.25 of the base width and the centre inside the base box. */
    C.clamp = function (cam, base) {
        var w = G.clamp(cam.w, base.w / 14, base.w * 1.25);
        var h = w * (cam.h / cam.w);
        var cx = G.clamp(cam.x + cam.w / 2, base.x, base.x + base.w);
        var cy = G.clamp(cam.y + cam.h / 2, base.y, base.y + base.h);
        return { x: cx - w / 2, y: cy - h / 2, w: w, h: h };
    };

    /** Zoom by factor f (>1 = in) keeping point p (frame units) fixed. */
    C.zoom = function (cam, f, p, base) {
        p = p || { x: cam.x + cam.w / 2, y: cam.y + cam.h / 2 };
        var next = { x: p.x - (p.x - cam.x) / f, y: p.y - (p.y - cam.y) / f, w: cam.w / f, h: cam.h / f };
        return base ? C.clamp(next, base) : next;
    };

    C.pan = function (cam, dx, dy, base) {
        var next = { x: cam.x + dx, y: cam.y + dy, w: cam.w, h: cam.h };
        return base ? C.clamp(next, base) : next;
    };

    /** Camera that fits a set of [x, y, w, h] boxes with 8% padding. */
    C.fit = function (boxes) {
        if (!boxes.length) { return null; }
        var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
        boxes.forEach(function (b) { x0 = Math.min(x0, b[0]); y0 = Math.min(y0, b[1]); x1 = Math.max(x1, b[0] + b[2]); y1 = Math.max(y1, b[1] + b[3]); });
        var w = x1 - x0, h = y1 - y0, pad = Math.max(w, h) * 0.08 || 1;
        return { x: x0 - pad, y: y0 - pad, w: w + 2 * pad, h: h + 2 * pad };
    };

    C.ease = function (k) { return k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2; };

    C.lerp = function (a, b, k) {
        return { x: a.x + (b.x - a.x) * k, y: a.y + (b.y - a.y) * k, w: a.w + (b.w - a.w) * k, h: a.h + (b.h - a.h) * k };
    };

    /** Zoom level relative to the base camera, e.g. 2 = twice as close. */
    C.level = function (cam, base) { return Math.round((base.w / cam.w) * 100) / 100; };

    C.toString = function (cam) { return [cam.x, cam.y, cam.w, cam.h].map(function (v) { return Math.round(v * 100) / 100; }).join(' '); };
})(typeof globalThis !== 'undefined' ? globalThis : window);
