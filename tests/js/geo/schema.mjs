// Minimal JSON Schema (2020-12 subset) validator for the gs-geo contracts, so node tests need no dependency.
// Supports: type, const, enum, required, properties, additionalProperties, propertyNames, items, minItems,
// maxItems, minimum, maximum, pattern, oneOf, anyOf, $ref (#/$defs/...). Mirrors GovStore\GeoView\Support\JsonSchema.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
export const repo = path.resolve(here, '../../..');
export const contracts = path.join(repo, 'packages/gov-store/geo-view/contracts');

export function loadSchema(name) {
  return JSON.parse(readFileSync(path.join(contracts, name), 'utf8'));
}

const typeOf = v => (v === null ? 'null' : Array.isArray(v) ? 'array' : typeof v);
const isType = (v, t) => t === 'integer' ? Number.isInteger(v) : t === 'number' ? typeof v === 'number' : typeOf(v) === t;

export function validate(schema, value, root = schema, at = '$') {
  const errors = [];
  const fail = msg => errors.push(`${at}: ${msg}`);
  if (schema === true) return errors;
  if (schema === false) { fail('not allowed'); return errors; }
  if (schema.$ref) {
    const target = schema.$ref.replace(/^#\//, '').split('/').reduce((o, k) => o?.[k], root);
    if (!target) { fail(`unresolved ${schema.$ref}`); return errors; }
    return validate(target, value, root, at);
  }
  if (schema.type) {
    const types = [].concat(schema.type);
    if (!types.some(t => isType(value, t))) { fail(`expected ${types.join('|')}, got ${typeOf(value)}`); return errors; }
  }
  if ('const' in schema && JSON.stringify(schema.const) !== JSON.stringify(value)) fail(`expected ${JSON.stringify(schema.const)}`);
  if (schema.enum && !schema.enum.some(e => JSON.stringify(e) === JSON.stringify(value))) fail(`${JSON.stringify(value)} not in enum`);
  if (schema.oneOf) {
    const ok = schema.oneOf.filter(s => !validate(s, value, root, at).length).length;
    if (ok !== 1) fail(`matches ${ok} of oneOf`);
  }
  if (schema.anyOf && !schema.anyOf.some(s => !validate(s, value, root, at).length)) fail('matches none of anyOf');
  if (typeof value === 'number') {
    if ('minimum' in schema && value < schema.minimum) fail(`below minimum ${schema.minimum}`);
    if ('maximum' in schema && value > schema.maximum) fail(`above maximum ${schema.maximum}`);
  }
  if (typeof value === 'string' && schema.pattern && !new RegExp(schema.pattern, 'u').test(value)) fail(`does not match ${schema.pattern}`);
  if (Array.isArray(value)) {
    if ('minItems' in schema && value.length < schema.minItems) fail(`fewer than ${schema.minItems} items`);
    if ('maxItems' in schema && value.length > schema.maxItems) fail(`more than ${schema.maxItems} items`);
    if (schema.items) value.forEach((v, i) => errors.push(...validate(schema.items, v, root, `${at}[${i}]`)));
  }
  if (typeOf(value) === 'object') {
    for (const k of schema.required || []) if (!(k in value)) fail(`missing ${k}`);
    for (const [k, v] of Object.entries(value)) {
      if (schema.propertyNames) errors.push(...validate(schema.propertyNames, k, root, `${at}.${k}(name)`));
      if (schema.properties && k in schema.properties) errors.push(...validate(schema.properties[k], v, root, `${at}.${k}`));
      else if ('additionalProperties' in schema) errors.push(...validate(schema.additionalProperties, v, root, `${at}.${k}`));
    }
  }
  return errors;
}
