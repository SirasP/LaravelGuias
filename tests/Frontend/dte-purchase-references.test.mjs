import test from 'node:test';
import assert from 'node:assert/strict';
import { webcrypto } from 'node:crypto';
import component from '../../resources/js/dte-purchase-references.js';

function scenario({ invoice = '4.000000', requested = '10.000000', packaged = false, description = 'SPRAY BLANCO', unit = 'UN' } = {}) {
    const state = component({ candidates: '/candidates' });
    state.report = { lines: [{ id: 1, remaining: invoice, description, unit }] };
    state.choose({ item_id: 8, line_id: 1, unit: 'Unidades', remaining: requested, supplier_known: true, packaged });
    return state;
}

test('reviewing a suggestion never saves a relationship or checks a confirmation', () => {
    const state = scenario();
    assert.equal(state.form.acknowledge, false);
    assert.equal(state.form.requestQuantity, '4.000000');
    assert.equal(state.selected.item_id, 8);
});

test('does not infer conversion when the requested product is a box of 12', () => {
    const state = scenario({ invoice: '120.000000', requested: '10.000000', packaged: true });
    assert.equal(state.form.invoiceQuantity, '120.000000');
    assert.equal(state.form.requestQuantity, '');
    assert.equal(state.needsReview, true);
});

test('does not infer conversion when the supplier describes a pack but uses UN', () => {
    const state = scenario({ description: 'CAJA DE 12 SPRAY', requested: '48.000000' });
    assert.equal(state.form.requestQuantity, '');
    assert.equal(state.needsReview, true);
});

test('preserves quantities beyond JavaScript floating point precision', () => {
    const state = scenario({ invoice: '999999999999.000002', requested: '999999999999.000001' });
    assert.equal(state.form.invoiceQuantity, '999999999999.000001');
    state.form.requestQuantity = '999999999999.000002';
    assert.equal(state.needsReview, true);
    assert.equal(state.number('999999999999.000001'), '999.999.999.999,000001');
    assert.equal(state.number('-0.100000'), '-0,1');
});

test('can generate an idempotency key on the HTTP-by-IP environment without randomUUID', () => {
    const original = Object.getOwnPropertyDescriptor(globalThis, 'crypto');
    Object.defineProperty(globalThis, 'crypto', { configurable: true, value: { getRandomValues: webcrypto.getRandomValues.bind(webcrypto) } });
    try {
        const state = component({});
        const first = state.newOperationKey();
        assert.match(first, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
        assert.notEqual(state.newOperationKey(), first);
    } finally { Object.defineProperty(globalThis, 'crypto', original); }
});

test('ignores an older search response after a newer request has finished', async () => {
    const state = component({ candidates: '/candidates' });
    const waiting = new Map();
    state.request = url => new Promise(resolve => waiting.set(url, resolve));
    state.query = 'SC-1';
    const first = state.search();
    state.query = 'SC-2';
    const second = state.search();
    waiting.get('/candidates?q=SC-2')({ candidates: [{ folio: 'SC-2' }] });
    await second;
    waiting.get('/candidates?q=SC-1')({ candidates: [{ folio: 'SC-1' }] });
    await first;
    assert.equal(state.candidates[0].folio, 'SC-2');
});
