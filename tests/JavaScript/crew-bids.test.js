import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { copyBidText, createBidAction, createBidGoalPreferences, createBidLineCard, createBidPayMenu, createBidSaveStatus, createBidTripDetails, createCrewBidsHeader, shouldWarnBeforeLeaving } from '../../resources/js/crew-bids.js';

test('saved local state and review actions work through Livewire’s reserved dirty alias', async () => {
    const livewire = await readFile(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js', import.meta.url), 'utf8');
    const source = livewire.slice(livewire.indexOf('var properties = {};'), livewire.indexOf('import_alpinejs6.default.magic("wire"'));
    const createWire = vm.runInNewContext(`${source};
        wireProperty('$dirty', component => () => component.localEdits);
        wireProperty('$get', component => name => component.state[name]);
        generateWireObject;
    `);
    const component = { localEdits: false, state: { dirty: false, selections: ['F1'], lastSavedAt: '2026-11-15T12:00:00Z' } };
    const status = createBidSaveStatus();
    status.$wire = createWire(component, component.state);

    assert.equal(status.saveState(), 'saved');
    assert.equal(status.hasUnsavedChanges(), false);
    component.state.dirty = true;
    assert.equal(status.saveState(), 'unsaved');
    component.state.dirty = false;
    component.localEdits = true;
    assert.equal(status.saveState(), 'unsaved');
    component.localEdits = false;

    const view = await readFile(new URL('../../resources/views/livewire/crew-bids.blade.php', import.meta.url), 'utf8');
    const actions = [...view.matchAll(/<button\b[^>]*:disabled="([^"]+)"[^>]*>(?:[\s\S]*?)<\/button>/g)]
        .filter(([markup]) => /Submit to Kalitta|AI form prompt|Copy AI prompt|Copy numbered list/.test(markup));
    assert.equal(actions.length, 4);
    for (const [, expression] of actions) {
        assert.equal(vm.runInNewContext(expression, { $wire: status.$wire, hasUnsavedChanges: () => status.hasUnsavedChanges() }), false);
        component.state.dirty = true;
        assert.equal(vm.runInNewContext(expression, { $wire: status.$wire, hasUnsavedChanges: () => status.hasUnsavedChanges() }), true);
        component.state.dirty = false;
        status.failed = true;
        assert.equal(vm.runInNewContext(expression, { $wire: status.$wire, hasUnsavedChanges: () => status.hasUnsavedChanges() }), true);
        status.failed = false;
    }
});

test('cloud confirmation requires both a local save and a matching Kalitta report', () => {
    const status = createBidSaveStatus();
    let localEdits = false;
    status.$wire = { lastSavedAt: null, $get: () => false, $dirty: () => localEdits };

    assert.equal(status.cloudState('unconfirmed'), 'unconfirmed');
    assert.equal(status.saveBadgeLabel(), 'Not saved');
    status.$wire.lastSavedAt = '2026-11-15T12:00:00Z';
    assert.equal(status.cloudState('unconfirmed'), 'unconfirmed');
    assert.equal(status.saveBadgeLabel(), 'Saved');
    assert.equal(status.cloudState('matched'), 'synced');
    assert.equal(status.cloudState('changed'), 'pending');
    localEdits = true;
    assert.equal(status.cloudState('matched'), 'pending');
    assert.equal(status.saveBadgeLabel(), 'Unsaved');
    localEdits = false;
    status.pending = 1;
    assert.equal(status.cloudState('matched'), 'pending');
    assert.equal(status.saveBadgeLabel(), 'Saving');
    status.pending = 0;
    status.failed = true;
    assert.equal(status.cloudState('matched'), 'pending');
    status.failed = false;
    assert.equal(status.cloudState('matched'), 'synced');
});

test('save indicator follows local preferences, failed saves, and confirmed saves', () => {
    const status = createBidSaveStatus({ now: () => Date.parse('2026-11-15T12:02:00Z') });
    let localEdits = false;
    status.$wire = { dirty: false, lastSavedAt: null, $get(name) { return this[name]; }, $dirty: () => localEdits };
    assert.equal(status.saveState(), 'empty');
    assert.equal(status.saveLabel(), 'Local: not saved');
    assert.equal(status.saveHelp(), 'Add a line or choose preferences to save your local workspace.');
    assert.equal(status.saveDescription(), 'No saved changes yet');

    status.$wire.lastSavedAt = '2026-11-15T12:00:00Z';
    assert.equal(status.saveState(), 'saved');
    assert.equal(status.saveLabel(), 'Local: saved');
    assert.equal(status.saveDescription(), 'Last saved 2 minutes ago');
    localEdits = true;
    assert.equal(status.saveState(), 'unsaved');
    assert.equal(status.saveLabel(), 'Local: unsaved');
    assert.equal(status.saveHelp(), 'Resolve any validation or conflict message, then retry the local save.');
    assert.equal(status.saveDescription(), 'Unsaved changes. Last saved 2 minutes ago');
    localEdits = false;
    status.$wire.dirty = true;
    assert.equal(status.saveState(), 'unsaved');
    status.$wire.dirty = false;
    assert.equal(status.saveState(), 'saved');
});

for (const [savedAt, expected] of [[null, null], ['invalid', null], ['2026-11-15T12:59:16-06:00', '2026-11-15 18:59:16 UTC']]) {
    test(`status popover formats the recorded local save ${savedAt} in UTC`, () => {
        const status = createBidSaveStatus();
        status.$wire = { lastSavedAt: savedAt };

        assert.equal(status.savedTimestamp(), expected);
    });
}

test('the status segment opens independently and list access dismisses it without moving focus', () => {
    const focus = [];
    const state = createCrewBidsHeader();
    const listTrigger = { isConnected: true };
    state.$wire = { showList: false };
    state.$refs = { statusPanel: { focus: () => focus.push('panel') }, statusTrigger: { focus: () => focus.push('trigger') } };
    state.$nextTick = (callback) => callback();
    let refreshed = 0;
    state.refreshTimestamp = () => refreshed++;

    state.toggleStatus();

    assert.equal(state.statusOpen, true);
    assert.equal(state.$wire.showList, false);
    assert.equal(refreshed, 1);
    assert.deepEqual(focus, ['panel']);
    state.toggleStatus();
    assert.equal(state.statusOpen, false);
    assert.deepEqual(focus, ['panel', 'trigger']);
    state.toggleStatus();
    state.openList({ currentTarget: listTrigger });
    assert.equal(state.statusOpen, false);
    assert.equal(state.$wire.showList, true);
    assert.equal(state.listReturnFocus, listTrigger);
    assert.deepEqual(focus, ['panel', 'trigger', 'panel']);
});

test('Escape and focus leaving the split control dismiss status with the correct focus behavior', async () => {
    const view = await readFile(new URL('../../resources/views/livewire/crew-bids.blade.php', import.meta.url), 'utf8');
    const escape = view.match(/@keydown.escape.window="([^"]+)"/)[1];
    const focusout = view.match(/data-bid-split-control[^>]*@focusout="([^"]+)"/)[1];
    const focus = [];
    const state = createCrewBidsHeader();
    state.statusOpen = true;
    state.$refs = { statusTrigger: { focus: () => focus.push('trigger') } };
    let prevented = false;

    vm.runInNewContext(escape, { statusOpen: true, $event: { preventDefault: () => { prevented = true; } }, closeStatus: state.closeStatus.bind(state) });

    assert.equal(state.statusOpen, false);
    assert.equal(prevented, true);
    assert.deepEqual(focus, ['trigger']);
    state.statusOpen = true;
    const context = { $el: { contains: (target) => target === 'inside' }, $event: { relatedTarget: 'inside' }, closeStatus: state.closeStatus.bind(state) };
    vm.runInNewContext(focusout, context);
    assert.equal(state.statusOpen, true);
    context.$event.relatedTarget = 'outside';
    vm.runInNewContext(focusout, context);
    assert.equal(state.statusOpen, false);
    assert.deepEqual(focus, ['trigger']);
});

for (const dismiss of ['closeStatus', 'destroy']) {
    test(`${dismiss} prevents delayed focus into the status panel`, () => {
        const state = createCrewBidsHeader();
        let afterTick;
        state.refreshTimestamp = () => {};
        state.$nextTick = (callback) => { afterTick = callback; };
        state.$refs = { statusPanel: { focus: () => assert.fail('Dismissed status must not take focus') } };

        state.toggleStatus();
        state[dismiss]();
        afterTick();
    });
}

for (const [elapsed, expected] of [[-60, 'just now'], [0, 'just now'], [59, 'just now'], [60, '1 minute ago'], [3599, '59 minutes ago'], [3600, '1 hour ago'], [7200, '2 hours ago'], [86400, '1 day ago'], [172800, '2 days ago']]) {
    test(`save tooltip describes ${elapsed} seconds and refreshes on interaction`, () => {
        let time = Date.parse('2026-11-15T12:00:00Z');
        const status = createBidSaveStatus({ now: () => time });
        status.$wire = { lastSavedAt: '2026-11-15T12:00:00Z', $get(name) { return this[name]; }, $dirty: () => false };
        time += elapsed * 1000;

        status.refreshTimestamp();

        assert.equal(status.saveDescription(), `Last saved ${expected}`);
    });
}

for (const failure of ['onFailure', 'onError']) {
    test(`save indicator stays amber after ${failure} until a save or reload succeeds`, () => {
        const status = createBidSaveStatus();
        let intercept;
        let cleaned = false;
        status.$wire = { packageId: 1, revision: 2, lastSavedAt: new Date().toISOString(), $get(name) { return this[name]; }, $dirty: () => false,
            interceptMessage(callback) { intercept = callback; return () => { cleaned = true; }; } };
        status.initSaveStatus();
        const request = (method) => {
            const hooks = {};
            intercept(Object.fromEntries(['onSend', 'onSuccess', 'onError', 'onFailure', 'onFinish'].map((name) => [name, (callback) => { hooks[name] = callback; }])));
            hooks.onSend({ payload: { calls: [{ method }] } });
            return hooks;
        };
        const succeed = (hooks, sync = () => {}) => {
            let afterSync;
            hooks.onSuccess({ onSync: (callback) => { afterSync = callback; } });
            sync();
            afterSync();
            hooks.onFinish();
        };

        const failed = request('move');
        assert.equal(status.saveState(), 'saving');
        assert.equal(status.saveLabel(), 'Local: saving');
        failed[failure]();
        failed.onFinish();
        assert.equal(status.saveState(), 'unsaved');
        succeed(request('loadPayDetails'));
        assert.equal(status.saveState(), 'unsaved');
        succeed(request('retrySave'), () => { status.$wire.revision++; });
        assert.equal(status.saveState(), 'saved');
        status.failed = true;
        succeed(request('reloadSaved'));
        assert.equal(status.saveState(), 'saved');
        const first = request('add');
        const second = request('move');
        succeed(first, () => { status.$wire.revision++; });
        assert.equal(status.saveState(), 'saving');
        succeed(second, () => { status.$wire.revision++; });
        assert.equal(status.saveState(), 'saved');
        status.destroySaveStatus();
        assert.equal(cleaned, true);
    });
}

test('pay menu closes and focuses its trigger before opening the workspace pay editor', () => {
    const actions = [];
    const menu = createBidPayMenu({ getWorkspace: () => ({ packageId: 12, openPayEditor: () => actions.push('editor') }) });
    menu.open = true;

    menu.openPayAdjustments({ button: 0, preventDefault: () => actions.push('prevent navigation') }, { focus: () => actions.push('focus trigger') });

    assert.equal(menu.open, false);
    assert.deepEqual(actions, ['prevent navigation', 'focus trigger', 'editor']);
});

for (const workspace of [undefined, { packageId: null }]) {
    test(`pay menu follows the profile link ${workspace ? 'without a published package' : 'outside the bidding workspace'}`, () => {
        const menu = createBidPayMenu({ getWorkspace: () => workspace });
        menu.open = true;

        menu.openPayAdjustments({ button: 0, preventDefault: () => assert.fail('Profile navigation must remain available') });

        assert.equal(menu.open, true);
    });
}

for (const modifier of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }, { defaultPrevented: true }]) {
    test(`pay menu preserves native link behavior for ${JSON.stringify(modifier)}`, () => {
        const menu = createBidPayMenu({ getWorkspace: () => assert.fail('Modified clicks must not open the editor') });

        menu.openPayAdjustments({ button: 0, ...modifier, preventDefault: () => assert.fail('Native navigation must be preserved') });
    });
}

test('bulk check controls update the count and button state locally without editing bid order', async () => {
    const view = await readFile(new URL('../../resources/views/livewire/crew-bids.blade.php', import.meta.url), 'utf8');
    const toolbar = view.match(/<section aria-label="Bid list bulk actions"[\s\S]*?<\/section>/)[0];
    const count = toolbar.match(/x-text="([^"]+)"/)[1];
    const selectAll = toolbar.match(/@click="([^"]+)"[^>]*>Select all/)[1];
    const deselectAll = toolbar.match(/@click="([^"]+)"[^>]*>Deselect all/)[1];
    const move = toolbar.match(/<button[^>]*wire:click="moveCheckedToTop"[^>]*>/)[0];
    const disabled = move.match(/:disabled="([^"]+)"/)[1];
    const calls = [];
    const $wire = {
        selections: ['F3', 'R2', 'F1'],
        checkedSelections: [],
        $set(name, value, live) {
            calls.push({ name, live });
            this[name] = Array.from(value);
        },
    };
    const evaluate = expression => vm.runInNewContext(expression, { $wire });

    assert.equal(evaluate(count), '0 selected');
    assert.equal(evaluate(disabled), true);
    evaluate(selectAll);
    assert.deepEqual($wire.checkedSelections, ['F3', 'R2', 'F1']);
    assert.notEqual($wire.checkedSelections, $wire.selections);
    assert.equal(evaluate(count), '3 selected');
    assert.equal(evaluate(disabled), false);
    $wire.checkedSelections = ['F1'];
    assert.equal(evaluate(count), '1 selected');
    evaluate(deselectAll);
    assert.equal(evaluate(count), '0 selected');
    assert.equal(evaluate(disabled), true);
    assert.deepEqual($wire.selections, ['F3', 'R2', 'F1']);
    assert.deepEqual(calls, [{ name: 'checkedSelections', live: false }, { name: 'checkedSelections', live: false }]);
});

test('footer actions menu closes on Escape before the drawer and resets when the drawer closes', async () => {
    const view = await readFile(new URL('../../resources/views/livewire/crew-bids.blade.php', import.meta.url), 'utf8');
    const wrapper = view.match(/<div[^>]*x-data="\{ open: false \}"[^>]*@keydown.escape="[^"]+"[^>]*>/)[0];
    const escape = wrapper.match(/@keydown.escape="([^"]+)"/)[1];
    const reset = wrapper.match(/x-effect="([^"]+)"/)[1];
    const state = { open: true, $wire: { showList: true }, $event: { stopPropagation: () => calls.push('stop') }, $refs: { moreActionsTrigger: { focus: () => calls.push('focus') } } };
    const calls = [];

    vm.runInNewContext(escape, state);

    assert.equal(state.open, false);
    assert.equal(state.$wire.showList, true);
    assert.deepEqual(calls, ['stop', 'focus']);
    calls.length = 0;
    vm.runInNewContext(escape, state);
    assert.deepEqual(calls, []);
    state.open = true;
    state.$wire.showList = false;
    vm.runInNewContext(reset, state);
    assert.equal(state.open, false);
});

test('bid position dropdown sends the selected index to move without calling other component methods', async () => {
    const view = await readFile(new URL('../../resources/views/livewire/crew-bids.blade.php', import.meta.url), 'utf8');
    const livewire = await readFile(new URL('../../vendor/livewire/livewire/dist/livewire.esm.js', import.meta.url), 'utf8');
    const start = livewire.indexOf('function contextualizeExpression(');
    const evaluator = livewire.slice(start, livewire.indexOf('// js/$wire.js', start));
    const contextualize = vm.runInNewContext(`${evaluator}; contextualizeExpression`);
    const directive = view.match(/<select\s+wire:change="(move\([^"]+)"/);
    assert.ok(directive, 'The bid position dropdown must have a move action');
    const expression = directive[1].replace("{{ $line['id'] }}", 'F1');

    for (const position of ['0', '1', '10']) {
        const calls = [];
        const $wire = new Proxy({}, {
            get(target, method) {
                assert.equal(method, 'move', 'The dropdown must only call the move component method');
                return (...args) => calls.push(args);
            },
        });

        vm.runInNewContext(contextualize(expression), { $wire, $event: { target: { value: position } } });

        assert.deepEqual(calls, [['F1', position]]);
    }
});

test('ranking goals dim and strike through immediately for manual sorts and resume for Best match', async () => {
    const view = await readFile(new URL('../../resources/views/livewire/crew-bids.blade.php', import.meta.url), 'utf8');
    const button = view.match(/<button\b[^>]*x-ref="preferencesTrigger"[^>]*>([\s\S]*?)<\/button>/);
    assert.ok(button, 'The preferences control must be available');
    const dimmed = button[1].match(/:class="([^"]+)"/)[1];
    const struckThrough = button[1].match(/<span :class="([^"]+)"/)[1];
    const $wire = { tab: 'flying', sort: 'best' };
    const evaluate = (expression) => vm.runInNewContext(`(${expression})`, { $wire });

    for (const [sort, expected] of [['best', false], ['credit', true], ['block', true], ['days_off', true], ['pay', true], ['id', true], ['submitted', true], ['best', false]]) {
        $wire.sort = sort;

        assert.equal(evaluate(dimmed)['opacity-50'], expected, `${sort}: goal summary dimming`);
        assert.equal(evaluate(struckThrough)['line-through'], expected, `${sort}: ranking goals struck through`);
    }

    $wire.tab = 'trips';
    assert.equal(evaluate(dimmed)['opacity-50'], true);
    assert.equal(evaluate(struckThrough)['line-through'], true);
});

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
    state.$wire = { interceptMessage: () => () => {} };
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
    state.$wire = { interceptMessage: () => () => {} };
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
