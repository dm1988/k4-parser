import { test } from 'node:test';
import assert from 'node:assert/strict';
import { copyBidText, createBidTripDetails, createCrewBidsHeader, shouldWarnBeforeLeaving } from '../../resources/js/crew-bids.js';

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

test('sticky action offset follows the wrapping workspace header and releases its observer', () => {
    const properties = new Map();
    let height = 70.4;
    let update;
    let observed;
    let disconnected = false;
    const header = { getBoundingClientRect: () => ({ height }) };
    const state = createCrewBidsHeader({ ResizeObserverClass: class {
        constructor(callback) { update = callback; }
        observe(element) { observed = element; }
        disconnect() { disconnected = true; }
    } });
    state.$el = { style: { setProperty: (name, value) => properties.set(name, value) } };
    state.$refs = { workspaceHeader: header };
    state.$nextTick = (callback) => callback();

    state.init();
    assert.equal(observed, header);
    assert.equal(properties.get('--crew-bids-header-height'), '71px');
    height = 160.2;
    update();
    assert.equal(properties.get('--crew-bids-header-height'), '161px');

    state.destroy();
    assert.equal(disconnected, true);
    height = 50;
    update();
    assert.equal(properties.get('--crew-bids-header-height'), '161px');
});

test('leaving before the header initializes does not attach a stale resize observer', () => {
    let initialize;
    let constructed = false;
    const state = createCrewBidsHeader({ ResizeObserverClass: class {
        constructor() { constructed = true; }
    } });
    state.$nextTick = (callback) => { initialize = callback; };

    state.init();
    state.destroy();
    initialize();

    assert.equal(constructed, false);
});

test('trip detail keyboard navigation wraps and keeps focus on the selected tab', () => {
    const state = createBidTripDetails();
    let focused;
    state.$refs = Object.fromEntries(['timeline', 'layovers', 'metadata'].map((name) => [`${name}Tab`, { focus: () => { focused = name; } }]));
    state.$nextTick = (callback) => callback();
    let prevented = 0;
    const press = (key) => state.handleTabKey({ key, preventDefault: () => { prevented++; } });

    assert.equal(state.tab, 'timeline');
    press('ArrowLeft');
    assert.equal(state.tab, 'metadata');
    assert.equal(focused, 'metadata');
    press('ArrowRight');
    assert.equal(state.tab, 'timeline');
    press('ArrowRight');
    assert.equal(state.tab, 'layovers');
    press('End');
    assert.equal(state.tab, 'metadata');
    press('Home');
    assert.equal(state.tab, 'timeline');
    assert.equal(focused, 'timeline');
    assert.equal(prevented, 5);

    press('Tab');
    state.handleTabKey({ key: 'ArrowLeft', altKey: true, preventDefault: () => { prevented++; } });
    assert.equal(prevented, 5);
    assert.equal(state.tab, 'timeline');
});

test('opening one trip detail tab does not switch another preview', () => {
    const first = createBidTripDetails();
    const second = createBidTripDetails();
    first.tab = 'layovers';

    assert.equal(first.tab, 'layovers');
    assert.equal(second.tab, 'timeline');
});
