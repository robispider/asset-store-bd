/**
 * GovStore Live Testing - Deterministic Generators
 * Provides seeded reproducible inputs without external dependencies.
 */

export class DeterministicRandom {
  constructor(seed = 20261006) {
    this.seed = Number(seed) || 20261006;
  }

  next() {
    // Mulberry32 32-bit PRNG
    let t = (this.seed += 0x6d2b79f5);
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  }

  integer(min = 0, max = 100) {
    return Math.floor(this.next() * (max - min + 1)) + min;
  }

  decimal(min = 0, max = 100, places = 2) {
    const factor = Math.pow(10, places);
    return Math.round((this.next() * (max - min) + min) * factor) / factor;
  }

  uniqueMarkedText(prefix = 'TEST', length = 8) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let suffix = '';
    for (let i = 0; i < length; i++) {
      suffix += chars.charAt(Math.floor(this.next() * chars.length));
    }
    return `${prefix}-${suffix}`;
  }

  /** ISO date (YYYY-MM-DD), or the same day in `format` built from the tokens YYYY, YY, MM and DD (e.g. "MMYY"). */
  date(offsetDays = 0, format = null) {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    const iso = d.toISOString().split('T')[0];
    if (!format) return iso;
    const [yyyy, mm, dd] = iso.split('-');
    return format.replace(/YYYY|YY|MM|DD/g, token => ({ YYYY: yyyy, YY: yyyy.slice(2), MM: mm, DD: dd })[token]);
  }

  email(prefix = 'actor') {
    const num = this.integer(1000, 9999);
    return `${prefix}${num}@example.com`;
  }

  bilingual(en, bn) {
    return { 'en-US': en, 'bn-BD': bn };
  }
}

/**
 * Resolve generator definitions inside a journey's `data` block into concrete values.
 * `{ "generate": "markedText", "prefix": "LIVE" }` etc. Plain values pass through unchanged.
 * Returns { values, generated } where `generated` lists safe generated values for provenance/replay.
 */
export function resolveData(data = {}, rng, uniqueSuffix = '') {
  const values = {};
  const generated = {};
  for (const [key, def] of Object.entries(data)) {
    if (def && typeof def === 'object' && !Array.isArray(def) && def.generate) {
      let v;
      switch (def.generate) {
        case 'markedText': v = rng.uniqueMarkedText(def.prefix || 'LIVE', def.length || 6) + (uniqueSuffix ? `-${uniqueSuffix}` : ''); break;
        case 'integer': v = rng.integer(def.min ?? 1, def.max ?? 10); break;
        case 'decimal': v = rng.decimal(def.min ?? 0, def.max ?? 100, def.places ?? 2); break;
        case 'date': v = rng.date(def.offsetDays ?? 0, def.format ?? null); break;
        case 'email': v = rng.email(def.prefix || 'actor'); break;
        default: throw new Error(`Unknown generator '${def.generate}' for data.${key}`);
      }
      values[key] = v;
      generated[key] = v;
    } else {
      values[key] = def;
    }
  }
  return { values, generated };
}
