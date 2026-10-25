import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

test('the adoption dialog sends the complete large selection as JSON with string codes', () => {
    const base = new URL('../../../packages/gov-store/classification/src/', import.meta.url);
    const csv = readFileSync(new URL('database/data/office-defaults/v2/items.csv', base), 'utf8');
    const items = csv.trim().split('\n').slice(1).map(line => ({
        code: line.split(',')[1],
        category_type: line.match(/,(asset|consumable|accessory|component),/)[1],
    }));
    assert.ok(items.length * 2 > 1000, 'exercise more than the default PHP form-field limit');
    const blade = readFileSync(new URL('resources/views/adopt/partials/bulk-preview.blade.php', base), 'utf8');
    const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1]
        .replace(/@json\(__\('classification::collections\.(?:bulk|types)'\)\)/g, '{}');
    const handlers = new Map();
    const document = { addEventListener: (event, callback) => callback() };
    const button = { prop() { return this; }, html() { return this; }, on(event, callback) { handlers.set(event, callback); } };
    let request;
    const jQuery = value => {
        if (value === document) return { on() {} };
        // Match jQuery's numeric coercion of data-code attributes in real rendered rows.
        if (typeof value === 'object' && value.code) return { data: () => Number(value.code) };
        if (value === '.bulk-item-checkbox:checked') return { each: callback => items.forEach(item => callback.call(item)) };
        if (typeof value === 'string' && value.startsWith('.row-category-type[')) {
            const code = value.match(/data-code="(\d+)"/)[1];
            return { val: () => items.find(item => item.code === code).category_type };
        }
        return button;
    };
    jQuery.ajax = options => { request = options; };
    vm.runInNewContext(script, { jQuery, document, alert: message => assert.fail(message) });
    handlers.get('click').call(button);
    assert.match(request.contentType, /^application\/json/);
    const sent = JSON.parse(request.data);
    assert.equal(sent.items.length, items.length);
    assert.deepEqual(sent.items, items);
    assert.equal(typeof sent._token, 'string');
});
