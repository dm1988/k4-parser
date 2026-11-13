import { test } from 'node:test';
import assert from 'node:assert/strict';
import { copyBidText, createBidAction, createBidGoalPreferences, createBidLineCard, createBidTripDetails, createCrewBidsHeader, shouldWarnBeforeLeaving } from '../../resources/js/crew-bids.js';

test('clicking a bid card background toggles its primary disclosure independently', () => {
    const state = createBidLineCard({ selectionText: () => '' });
    const other = createBidLineCard({ selectionText: () => '' });
    state.$refs = { primaryDetails: { open: false } };
    other.$refs = { primaryDetails: { open: false } };
    const event = { target: { closest: () => null } };

    state.toggleFromCard(event);
    assert.equal(state.$refs.primaryDetails.open, true);
    assert.equal(other.$refs.primaryDetails.open, false);
    state.toggleFromCard(event);
    assert.equal(state.$refs.primaryDetails.open, false);
});

test('card background clicks preserve nested controls, selected text, and prevented events', () => {
    let selection = '';
    const state = createBidLineCard({ selectionText: () => selection });
    state.$refs = { primaryDetails: { open: false } };

    for (const control of ['button', 'a', 'summary', 'details', 'input', 'select', 'textarea', '[role="button"]', '[contenteditable]']) {
        state.toggleFromCard({ target: { closest: (selector) => selector.split(', ').includes(control) ? {} : null } });
        assert.equal(state.$refs.primaryDetails.open, false, `${control} must retain its own interaction`);
    }
    selection = '90:15';
    state.toggleFromCard({ target: { closest: () => null } });
    assert.equal(state.$refs.primaryDetails.open, false);
    selection = '';
    state.toggleFromCard({ defaultPrevented: true, target: { closest: () => null } });
    assert.equal(state.$refs.primaryDetails.open, false);
});

function dateInspector(length = 30) {
    const days = Array.from({ length }, (_, index) => {
        const date = `2026-11-${String(index + 1).padStart(2, '0')}`;
        return { dataset: { date, dateDetail: `${date} UTC · ${index === 1 ? 'Unknown' : index < 15 ? 'Trip / occupied' : 'Off'}` } };
    });
    const state = createBidLineCard();
    let focused = false;
    state.$refs = {
        calendar: { querySelector: () => days[0], querySelectorAll: () => days, focus: () => { focused = true; } },
        primaryDetails: { open: false },
    };
    return { state, days, focused: () => focused };
}

test('selecting a day replaces the compact activity readout without expanding the card', () => {
    const { state, days } = dateInspector();
    const other = dateInspector().state;
    state.inspectDate({ target: { closest: () => days[1] } });
    assert.equal(state.inspected, '2026-11-02 UTC · Unknown');
    assert.equal(state.inspectedDate, '2026-11-02');
    state.inspectDate({ target: { closest: () => days[29] } });
    assert.equal(state.inspected, '2026-11-30 UTC · Off');
    assert.equal(state.$refs.primaryDetails.open, false);
    assert.equal(other.inspected, '');
});

test('keyboard activation and gap clicks select the first day or preserve the inspected date', () => {
    const { state, days } = dateInspector();
    const event = { target: { closest: () => null } };
    state.inspectDate(event);
    assert.equal(state.inspected, '2026-11-01 UTC · Trip / occupied');
    state.selectDate(days[14]);
    state.inspectDate(event);
    assert.equal(state.inspectedDate, '2026-11-15');
});

for (const length of [28, 29, 30, 31]) {
    test(`date keyboard navigation reaches and stops at the ${length}-day calendar boundaries`, () => {
        const { state, days } = dateInspector(length);
        const press = (key) => {
            let prevented = false;
            state.handleDateKey({ key, preventDefault: () => { prevented = true; } });
            assert.equal(prevented, true);
        };
        press('ArrowRight');
        assert.equal(state.inspectedDate, days[0].dataset.date);
        press('ArrowRight');
        assert.equal(state.inspected, '2026-11-02 UTC · Unknown');
        press('ArrowLeft');
        press('ArrowLeft');
        assert.equal(state.inspectedDate, days[0].dataset.date);
        press('End');
        press('ArrowRight');
        assert.equal(state.inspectedDate, days[length - 1].dataset.date);
        press('Home');
        assert.equal(state.inspectedDate, days[0].dataset.date);
        state.clearDate();
        press('ArrowLeft');
        assert.equal(state.inspectedDate, days[length - 1].dataset.date);
    });
}

test('clearing a date restores focus to the calendar and leaves disclosures unchanged', () => {
    const { state, days, focused } = dateInspector();
    state.selectDate(days[0]);
    state.$refs.primaryDetails.open = true;
    state.handleDateKey({ key: 'Escape', preventDefault() {} });
    assert.equal(state.inspected, '');
    assert.equal(state.inspectedDate, '');
    assert.equal(focused(), true);
    assert.equal(state.$refs.primaryDetails.open, true);
});

test('date inspection leaves unrelated and modified keys alone and tolerates an empty calendar', () => {
    const { state } = dateInspector();
    const unexpected = () => assert.fail('unhandled keys must keep their default behavior');
    for (const event of [{ key: 'Tab' }, { key: 'Enter' }, { key: 'Escape' }, { key: 'ArrowRight', ctrlKey: true }, { key: 'Home', altKey: true }, { key: 'End', metaKey: true }]) {
        state.handleDateKey({ ...event, preventDefault: unexpected });
    }
    assert.equal(state.inspected, '');
    const empty = dateInspector(0).state;
    empty.handleDateKey({ key: 'ArrowRight', preventDefault: unexpected });
    empty.inspectDate({ target: { closest: () => null } });
    assert.equal(empty.inspected, '');
});

test('closing the bid list restores its opener after the drawer releases focus', () => {
    const state = createCrewBidsHeader();
    const ticks = [];
    const focused = [];
    const opener = { isConnected: true, focus: (options) => focused.push(['opener', options]) };
    state.$wire = { showList: false };
    state.$refs = { listTrigger: { focus: (options) => focused.push(['header', options]) } };
    state.$nextTick = (callback) => ticks.push(callback);

    state.openList({ currentTarget: opener });
    assert.equal(state.$wire.showList, true);
    state.closeList();
    assert.equal(state.$wire.showList, false);
    assert.deepEqual(focused, []);
    ticks.shift()();
    assert.deepEqual(focused, [['opener', { preventScroll: true }]]);
    assert.equal(state.listReturnFocus, null);

    state.closeList();
    assert.equal(ticks.length, 0);
});

test('closing the bid list falls back to the header trigger when its opener was removed', () => {
    const state = createCrewBidsHeader();
    let focused = '';
    state.$wire = { showList: false };
    state.$refs = { listTrigger: { focus: () => { focused = 'header'; } } };
    state.$nextTick = (callback) => callback();

    state.openList({ currentTarget: { isConnected: false, focus: () => assert.fail('Detached opener received focus') } });
    state.closeList();
    assert.equal(focused, 'header');
});

test('bid actions show progress immediately and ignore duplicate clicks until the request finishes', async () => {
    const state = createBidAction();
    let finish;
    let requests = 0;
    const action = () => { requests++; return new Promise((resolve) => { finish = resolve; }); };

    const pending = state.run(action);
    assert.equal(state.pending, true);
    await state.run(action);
    assert.equal(requests, 1);

    finish('saved');
    assert.equal(await pending, 'saved');
    assert.equal(state.pending, false);
});

test('failed bid actions announce the error and allow a retry without claiming success', async () => {
    const state = createBidAction();
    const announcements = [];
    state.$dispatch = (event) => announcements.push(event);

    assert.equal(await state.run(async () => { throw new Error('Connection failed'); }), undefined);
    assert.equal(state.pending, false);
    assert.deepEqual(announcements, ['bid-action-failed']);
    assert.equal(await state.run(async () => 'saved'), 'saved');
    assert.equal(state.pending, false);
});

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
