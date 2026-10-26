import test from 'node:test';
import assert from 'node:assert/strict';
import { createFlightDelayForm } from '../../resources/js/flight-delay-form.js';

const deferred = () => {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });
    return { promise, resolve };
};

function setup() {
    const documentListeners = new Map();
    const windowListeners = new Map();
    const navigated = [];
    const fields = [{ dataset: { delayInput: 'notes' }, value: 'Latest unsent note', validity: { badInput: false } }];
    const documentObject = {
        addEventListener: (name, listener) => documentListeners.set(name, listener),
        removeEventListener: (name, listener) => { if (documentListeners.get(name) === listener) documentListeners.delete(name); },
    };
    const windowObject = {
        addEventListener: (name, listener) => windowListeners.set(name, listener),
        removeEventListener: (name, listener) => { if (windowListeners.get(name) === listener) windowListeners.delete(name); },
        Livewire: { navigate: (url) => navigated.push(url) },
        location: { replace: (url) => navigated.push('replace:' + url) },
    };
    const form = createFlightDelayForm({ documentObject, windowObject });
    form.$root = { querySelectorAll: () => fields, contains: (target) => target.inside === true };
    form.$wire = {
        inputs: { notes: 'Old note' },
        savedInputs: { notes: 'Old note' },
        $set(path, value, live) {
            assert.equal(live, false);
            this.inputs[path.split('.')[1]] = value;
        },
        async savePending() {
            this.savedInputs = { ...this.inputs };
            return true;
        },
        async generateReport() { this.report = this.savedInputs.notes; },
    };
    return { form, fields, documentListeners, windowListeners, navigated };
}

function clickTarget(action = 'selectTask(\'fms\')') {
    return { getAttribute: () => action, matches: () => false, clickCount: 0, click() { this.clickCount++; } };
}

function eventFor(target = null) {
    return {
        target: target ? { closest: () => target } : null,
        prevented: false,
        stopped: false,
        preventDefault() { this.prevented = true; },
        stopImmediatePropagation() { this.stopped = true; },
    };
}

test('task navigation waits for the latest DOM input to be saved before continuing', async () => {
    const { form } = setup();
    const request = deferred();
    form.$wire.savePending = () => request.promise;
    const target = clickTarget();
    const event = eventFor(target);

    const pending = form.guardClick(event);
    assert.equal(event.prevented, true);
    assert.equal(event.stopped, true);
    assert.equal(target.clickCount, 0);
    assert.equal(form.$wire.inputs.notes, 'Latest unsent note');
    request.resolve(true);
    await pending;
    assert.equal(target.clickCount, 1);
});

test('a validation failure or save error keeps the user on the form with the unsent input', async () => {
    const { form, fields } = setup();
    const target = clickTarget('clearResults');
    form.$wire.savePending = async () => false;
    await form.guardClick(eventFor(target));
    assert.equal(target.clickCount, 0);
    assert.equal(fields[0].value, 'Latest unsent note');

    form.$wire.savePending = async () => { throw new Error('Connection lost'); };
    await form.guardClick(eventFor(target));
    assert.equal(target.clickCount, 0);
    assert.match(form.clientError, /Could not save/);
    assert.equal(form.busy, false);
});

test('partial datetime input cannot be sent as an empty value or followed by navigation', async () => {
    const { form, fields } = setup();
    fields[0] = { dataset: { delayInput: 'delay_started_at' }, value: '', validity: { badInput: true } };
    let saveCalls = 0;
    form.$wire.savePending = async () => { saveCalls++; return true; };
    const inputEvent = eventFor();
    inputEvent.target = fields[0];

    form.inspectInput(inputEvent);
    assert.equal(inputEvent.stopped, true);
    const target = clickTarget();
    await form.guardClick(eventFor(target));
    assert.equal(saveCalls, 0);
    assert.equal(target.clickCount, 0);
    assert.match(form.clientError, /Complete the UTC date and time/);
});

test('report generation waits for pending edits and uses the acknowledged values', async () => {
    const { form } = setup();
    await form.generateReport();
    assert.equal(form.$wire.report, 'Latest unsent note');
    assert.equal(form.dirty, false);

    form.$wire.report = null;
    form.$wire.savePending = async () => false;
    await form.generateReport();
    assert.equal(form.$wire.report, null);
});

test('SPA and history navigation flush edits before leaving', async () => {
    const { form, navigated, fields } = setup();
    const event = eventFor();
    event.detail = { url: new URL('https://example.test/flight-plan-brief/delays'), history: false };
    await form.guardNavigation(event);
    assert.equal(event.prevented, true);
    assert.deepEqual(navigated, ['https://example.test/flight-plan-brief/delays']);

    form.allowNavigation = false;
    fields[0].value = 'Another unsaved note';
    event.detail.history = true;
    await form.guardNavigation(event);
    assert.equal(navigated[1], 'replace:https://example.test/flight-plan-brief/delays');
});

test('listeners warn before closing with unsaved input and are removed when the component leaves', async () => {
    const { form, documentListeners, windowListeners } = setup();
    form.init();
    const dirtyEvent = eventFor();
    windowListeners.get('beforeunload')(dirtyEvent);
    assert.equal(dirtyEvent.prevented, true);

    await form.flush();
    const savedEvent = eventFor();
    windowListeners.get('beforeunload')(savedEvent);
    assert.equal(savedEvent.prevented, false);
    form.destroy();
    assert.equal(documentListeners.size, 0);
    assert.equal(windowListeners.size, 0);
});

test('timestamp actions inside the form and new-tab links are not intercepted', async () => {
    const { form } = setup();
    const timestamp = clickTarget('setNow(\'logbook_received_at\')');
    timestamp.inside = true;
    const insideEvent = eventFor(timestamp);
    await form.guardClick(insideEvent);
    assert.equal(insideEvent.prevented, false);
    const modifiedEvent = eventFor(clickTarget());
    modifiedEvent.ctrlKey = true;
    await form.guardClick(modifiedEvent);
    assert.equal(modifiedEvent.prevented, false);
});
