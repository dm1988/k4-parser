import assert from 'node:assert/strict';
import test from 'node:test';
import { bindFuelDraftLifecycle } from '../../resources/js/offline-fuel-lifecycle.js';
import { offlineFuelScoreState } from '../../resources/js/offline-fuel-score-state.js';

test('hiding the tab synchronously persists pending inputs for recovery after discard', () => {
    const values = new Map();
    const storage = {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: (key) => values.delete(key),
    };
    const calculator = { fuelUnit: 'lb', takeoffFuel: null, estimatedLandingFuel: null, waypoints: [] };
    const scope = { ownerId: '1', flightPlanKey: 'release-1' };
    const monitor = offlineFuelScoreState(calculator, scope, () => storage);
    monitor.$watch = () => {};
    monitor.init();
    const page = new EventTarget();
    const browser = new EventTarget();
    const unbind = bindFuelDraftLifecycle(monitor, page, browser);
    monitor.offTime = '0000';
    monitor.startingFob = '150000.0';
    page.visibilityState = 'visible';
    page.dispatchEvent(new Event('visibilitychange'));
    assert.equal(values.size, 0);
    page.visibilityState = 'hidden';
    page.dispatchEvent(new Event('visibilitychange'));

    const restored = offlineFuelScoreState(calculator, scope, () => storage);
    restored.$watch = () => {};
    restored.init();
    assert.equal(restored.offTime, '0000');
    assert.equal(restored.startingFob, '150000.0');
    unbind();
});

test('pagehide flushes the draft and cleanup removes lifecycle listeners', () => {
    let saves = 0;
    const page = new EventTarget();
    const browser = new EventTarget();
    const unbind = bindFuelDraftLifecycle({ saveDraft: () => saves++ }, page, browser);
    browser.dispatchEvent(new Event('pagehide'));
    assert.equal(saves, 1);
    unbind();
    page.visibilityState = 'hidden';
    page.dispatchEvent(new Event('visibilitychange'));
    browser.dispatchEvent(new Event('pagehide'));
    assert.equal(saves, 1);
});
