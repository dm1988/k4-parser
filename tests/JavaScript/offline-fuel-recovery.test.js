import assert from 'node:assert/strict';
import test from 'node:test';
import { setImmediate } from 'node:timers/promises';
import { MessageChannel } from 'node:worker_threads';
import { initializeOfflineFuelRecovery } from '../../resources/js/offline-fuel-recovery.js';
import initializeOfflineFuelInvalidation from '../../resources/js/offline-fuel-invalidation.js';

const workerUrl = 'https://example.test/offline-fuel-worker.js';
const config = { workerUrl, assets: ['https://example.test/build/assets/app.js'] };

const browser = (ready = true) => {
    const container = new EventTarget();
    const registrations = [];
    const messages = [];
    const controller = {
        scriptURL: workerUrl,
        postMessage(message, ports) {
            messages.push(message);
            if (ports) {
                ports[0].postMessage({ ready });
            }
        },
    };
    container.register = async (...args) => registrations.push(args);
    const window = new EventTarget();
    window.navigator = { serviceWorker: container };
    window.location = new URL('https://example.test/flight-plan-brief/fuel-score/01J00000000000000000000000');
    window.MessageChannel = MessageChannel;
    window.isSecureContext = true;
    return { window, container, controller, messages, registrations };
};

test('readiness waits for worker control and successful page and asset preparation', async () => {
    const environment = browser();
    const reports = [];
    let finish;
    const completed = new Promise((resolve) => { finish = resolve; });
    const stop = initializeOfflineFuelRecovery(config, (ready, message) => {
        reports.push([ready, message]);
        if (ready) { finish(); }
    }, environment.window);
    await setImmediate();
    assert.equal(reports.at(-1)[0], false);
    assert.equal(environment.messages.length, 0);
    environment.container.controller = environment.controller;
    environment.container.dispatchEvent(new Event('controllerchange'));
    await completed;
    assert.equal(reports.at(-1)[0], true);
    assert.equal(environment.registrations[0][1].scope, '/');
    assert.equal(environment.messages[0].type, 'PREPARE_OFFLINE_FUEL');
    stop();
});

test('failed preparation reports unavailable recovery without claiming readiness', async () => {
    const environment = browser(false);
    environment.container.controller = environment.controller;
    let finish;
    const completed = new Promise((resolve) => { finish = resolve; });
    const stop = initializeOfflineFuelRecovery(config, (ready, message) => {
        if (message.includes('unavailable')) {
            assert.equal(ready, false);
            finish();
        }
    }, environment.window);
    await completed;
    stop();
});

test('insecure contexts, unsupported browsers, and development assets remain unavailable', () => {
    for (const changes of [{ isSecureContext: false }, { navigator: {} }, {}]) {
        const environment = browser();
        Object.assign(environment.window, changes);
        const reports = [];
        initializeOfflineFuelRecovery(Object.keys(changes).length ? config : { ...config, assets: [] },
            (ready, message) => reports.push([ready, message]), environment.window)();
        assert.equal(reports.length, 1);
        assert.equal(reports[0][0], false);
        assert.match(reports[0][1], /unavailable/);
        assert.equal(environment.registrations.length, 0);
    }
});

test('cache invalidation removes the ready indication and cleanup detaches the listener', async () => {
    const environment = browser();
    environment.container.controller = environment.controller;
    const reports = [];
    let finish;
    const completed = new Promise((resolve) => { finish = resolve; });
    const stop = initializeOfflineFuelRecovery(config, (ready, message) => {
        reports.push([ready, message]);
        if (ready) { finish(); }
    }, environment.window);
    await completed;
    const invalidated = new Event('message');
    invalidated.data = { type: 'OFFLINE_FUEL_INVALIDATED' };
    environment.container.dispatchEvent(invalidated);
    assert.equal(reports.at(-1)[0], false);
    assert.match(reports.at(-1)[1], /removed/);
    const count = reports.length;
    stop();
    environment.container.dispatchEvent(invalidated);
    assert.equal(reports.length, count);
});

test('registration failure reports unavailable recovery', async () => {
    const environment = browser();
    environment.container.register = async () => { throw new Error('blocked'); };
    const reports = [];
    const stop = initializeOfflineFuelRecovery(config, (ready, message) => reports.push([ready, message]), environment.window);
    await setImmediate();
    assert.equal(reports.at(-1)[0], false);
    assert.match(reports.at(-1)[1], /unavailable/);
    stop();
});

test('Livewire release events reconcile the offline worker exactly once', () => {
    const environment = browser();
    environment.container.controller = environment.controller;
    initializeOfflineFuelInvalidation(environment.window);
    initializeOfflineFuelInvalidation(environment.window);
    const event = new Event('offline-fuel-release-changed');
    event.detail = { ownerId: '1', flightPlanKey: null };
    environment.window.dispatchEvent(event);
    assert.deepEqual(environment.messages, [{ type: 'RECONCILE_OFFLINE_FUEL', ownerId: '1', flightPlanKey: '' }]);
});
