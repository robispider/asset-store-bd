/**
 * GovStore Live Testing - Typed Variable Resolver
 * Manages immutable namespaces: run, actors, fixtures, data, captured, observed, baseline.
 */

import { registerSecret } from '../reporters/redaction.mjs';

export class VariableContext {
  constructor(initialData = {}) {
    for (const actor of Object.values(initialData.actors || {})) registerSecret(actor?.password);
    this.run = { ...(initialData.run || {}) };
    this.actors = { ...(initialData.actors || {}) };
    this.fixtures = { ...(initialData.fixtures || {}) };
    this.data = { ...(initialData.data || {}) };
    this.baseline = { ...(initialData.baseline || {}) };
    this.captured = { ...(initialData.captured || {}) };
    this.observed = { ...(initialData.observed || {}) };
  }

  /**
   * Clone the current variable context.
   */
  clone() {
    return new VariableContext({
      run: JSON.parse(JSON.stringify(this.run)),
      actors: JSON.parse(JSON.stringify(this.actors)),
      fixtures: JSON.parse(JSON.stringify(this.fixtures)),
      data: JSON.parse(JSON.stringify(this.data)),
      baseline: JSON.parse(JSON.stringify(this.baseline)),
      captured: JSON.parse(JSON.stringify(this.captured)),
      observed: JSON.parse(JSON.stringify(this.observed))
    });
  }

  /**
   * Set a captured value. Prohibits overwriting fixtures directly.
   */
  setCaptured(key, value) {
    if (!key || typeof key !== 'string') {
      throw new Error(`Invalid captured key: ${key}`);
    }
    this.captured[key] = value;
  }

  /**
   * Set an observed snapshot result.
   */
  setObserved(alias, data) {
    if (!alias || typeof alias !== 'string') {
      throw new Error(`Invalid observed alias: ${alias}`);
    }
    this.observed[alias] = data;
  }

  /**
   * Resolve a path like "fixtures.item.id" or "observed.before.name"
   */
  resolvePath(path) {
    const parts = path.trim().split('.');
    const namespace = parts[0];

    if (!['run', 'actors', 'fixtures', 'data', 'baseline', 'captured', 'observed'].includes(namespace)) {
      throw new Error(`Unknown variable namespace: '${namespace}' in path '${path}'`);
    }

    let current = this[namespace];
    for (let i = 1; i < parts.length; i++) {
      if (current === undefined || current === null) {
        throw new Error(`Cannot read property '${parts[i]}' of undefined in path '${path}'`);
      }
      current = current[parts[i]];
    }

    if (current === undefined) {
      throw new Error(`Variable not found: '${path}'`);
    }

    return current;
  }

  /**
   * Interpolate a string containing ${namespace.path}
   */
  interpolate(value) {
    if (typeof value !== 'string') {
      return value;
    }

    // Exact variable replacement (preserves types like integer, object)
    const exactMatch = value.match(/^\$\{([a-zA-Z0-9_.-]+)\}$/);
    if (exactMatch) {
      return this.resolvePath(exactMatch[1]);
    }

    // Substring replacements
    return value.replace(/\$\{([a-zA-Z0-9_.-]+)\}/g, (match, path) => {
      const val = this.resolvePath(path);
      return val !== undefined && val !== null ? String(val) : match;
    });
  }

  /**
   * Recursively interpolate all strings in an object or array.
   */
  interpolateDeep(obj) {
    if (obj === null || obj === undefined) return obj;
    if (typeof obj === 'string') return this.interpolate(obj);
    if (typeof obj === 'number' || typeof obj === 'boolean') return obj;

    if (Array.isArray(obj)) {
      return obj.map(item => this.interpolateDeep(item));
    }

    if (typeof obj === 'object') {
      const result = {};
      for (const [key, val] of Object.entries(obj)) {
        result[key] = this.interpolateDeep(val);
      }
      return result;
    }

    return obj;
  }
}
