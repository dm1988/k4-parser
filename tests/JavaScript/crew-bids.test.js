import { test } from 'node:test';
import assert from 'node:assert/strict';
import { copyBidText, shouldWarnBeforeLeaving } from '../../resources/js/crew-bids.js';

test('copy writes the exact reviewed bid order and reports success', async () => {
    const status = {};
    let copied;
    await copyBidText({ value: '1. R2\n2. F1' }, status, { writeText: async (value) => { copied = value; } });
    assert.equal(copied, '1. R2\n2. F1');
    assert.equal(status.textContent, 'Copied');
});

test('clipboard failure selects the list for manual copying', async () => {
    const status = {};
    let focused = false;
    let selected = false;
    await copyBidText({ value: '1. F1', focus: () => { focused = true; }, select: () => { selected = true; } }, status, undefined);
    assert.equal(focused, true);
    assert.equal(selected, true);
    assert.match(status.textContent, /Select and copy/);
});

test('closing warns for unsaved edits or an in-flight request on the bids page', () => {
    assert.equal(shouldWarnBeforeLeaving({ dataset: { dirty: 'true' } }, false), true);
    assert.equal(shouldWarnBeforeLeaving({ dataset: { dirty: 'false' } }, true), true);
    assert.equal(shouldWarnBeforeLeaving({ dataset: { dirty: 'false' } }, false), false);
    assert.equal(shouldWarnBeforeLeaving(null, true), false);
});
