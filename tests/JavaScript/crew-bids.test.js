import { test } from 'node:test';
import assert from 'node:assert/strict';
import { copyBidText, createBidGoalPreferences, createBidTripDetails, createCrewBidsHeader, shouldWarnBeforeLeaving } from '../../resources/js/crew-bids.js';

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

test('preferences waiting for the debounce keep the leave-page warning until saved', () => {
    const root = { dataset: { dirty: 'false', preferencesDirty: 'true' } };

    assert.equal(shouldWarnBeforeLeaving(root, false), true);
    root.dataset.preferencesDirty = 'false';
    assert.equal(shouldWarnBeforeLeaving(root, false), false);
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

test('goal moves apply immediately and queue the final priority order for a shared save', () => {
    const state = createBidGoalPreferences({ credit: 'Maximize credit', days_off: 'Maximize days off', blocks: 'Fewer work blocks' });
    const changes = [];
    state.$wire = { goals: ['days_off', 'credit', 'blocks'], $commit: () => assert.fail('Moving a goal must not send an immediate request') };
    state.$dispatch = (event) => changes.push([event, [...state.$wire.goals]]);

    state.moveGoal('credit', 0);
    assert.deepEqual(state.$wire.goals, ['credit', 'days_off', 'blocks']);
    state.moveGoal('blocks', 1);
    assert.deepEqual(changes, [
        ['bid-goals-changed', ['credit', 'days_off', 'blocks']],
        ['bid-goals-changed', ['credit', 'blocks', 'days_off']],
    ]);
});

test('dragging a goal restores the keyed loop order before updating its priorities', () => {
    const state = createBidGoalPreferences({});
    const credit = { dataset: { goal: 'credit' } };
    const daysOff = { dataset: { goal: 'days_off' } };
    const blocks = { dataset: { goal: 'blocks' } };
    const list = { children: [{ dataset: {} }, blocks, credit, daysOff], append(row) {
        this.children.splice(this.children.indexOf(row), 1);
        this.children.push(row);
    } };
    state.$wire = { goals: ['credit', 'days_off', 'blocks'] };
    let orderBeforeRender;
    state.$dispatch = () => { orderBeforeRender = list.children.filter((row) => row.dataset.goal).map((row) => row.dataset.goal); };

    state.sortGoal('blocks', list);

    assert.deepEqual(orderBeforeRender, ['credit', 'days_off', 'blocks']);
    assert.deepEqual(state.$wire.goals, ['blocks', 'credit', 'days_off']);
});

test('removed goals, unchanged moves, and invalid drop targets do not alter priorities or save', () => {
    const state = createBidGoalPreferences({});
    state.$wire = { goals: ['credit', 'days_off'] };
    state.$dispatch = () => assert.fail('An invalid or unchanged move must not queue a save');

    state.moveGoal('blocks', 0);
    state.moveGoal('credit', 0);
    state.moveGoal('credit', -1);
    state.moveGoal('credit', 2);
    state.moveGoal('credit', 0.5);
    state.sortGoal('credit', { children: [{ dataset: { goal: 'credit' } }] });
    state.sortGoal('blocks', { children: [{ dataset: { goal: 'credit' } }, { dataset: { goal: 'days_off' } }] });

    assert.deepEqual(state.$wire.goals, ['credit', 'days_off']);
});
