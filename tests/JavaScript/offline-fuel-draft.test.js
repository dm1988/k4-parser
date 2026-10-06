import assert from 'node:assert/strict';
import test from 'node:test';

import { createFuelScoreDraft } from '../../resources/js/offline-fuel-draft.js';
import { offlineFuelScoreState } from '../../resources/js/offline-fuel-score-state.js';

const calculator = () => ({
    fuelUnit: 'lb',
    takeoffFuel: { amount: 20000, unit: 'lb' },
    estimatedLandingFuel: { amount: 5000, unit: 'lb' },
    waypoints: [
        { identifier: 'FIX01', coordinate: 'N01 02.3 E004 05.6', tbo: '0011', legDurationMinutes: 5, cumulativeDurationMinutes: 11, remainingFuel: { amount: 18000, unit: 'lb' } },
        { identifier: 'FIX01', coordinate: 'N02 03.4 E005 06.7', tbo: '0021', legDurationMinutes: 6, cumulativeDurationMinutes: 17, remainingFuel: { amount: 16000, unit: 'lb' } },
    ],
});

const scope = (ownerId = '1', flightPlanKey = 'release-1') => ({ ownerId, flightPlanKey });

const storage = () => {
    const values = new Map();

    return {
        values,
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: (key) => values.delete(key),
    };
};

const state = (source, draftScope, session) => {
    const monitor = offlineFuelScoreState(source, draftScope, () => session);
    const watchers = new Map();
    monitor.$watch = (name, callback) => watchers.set(name, callback);
    monitor.init();

    return { monitor, watchers };
};

test('recovers exact entered strings across a reload, including repeated waypoint identifiers', () => {
    const session = storage();
    const first = state(calculator(), scope(), session);
    first.monitor.offTime = '0000';
    first.monitor.startingFob = '0';
    first.monitor.waypoints[0].ata = '012';
    first.monitor.waypoints[1].actualFob = '0.';
    first.watchers.get('waypoints')();

    const restored = state(calculator(), scope(), session).monitor;
    assert.equal(restored.offTime, '0000');
    assert.equal(restored.startingFob, '0');
    assert.equal(restored.waypoints[0].ata, '012');
    assert.equal(restored.waypoints[0].actualFob, '');
    assert.equal(restored.waypoints[1].ata, '');
    assert.equal(restored.waypoints[1].actualFob, '0.');
    assert.equal(restored.waypoints[1].expanded, false);
    assert.equal(restored.plannedEta(restored.waypoints[0]), '0011');
});

test('restores before watchers are registered and saves every input change', () => {
    const session = storage();
    const draft = createFuelScoreDraft(calculator(), scope(), () => session);
    const first = state(calculator(), scope(), session).monitor;
    first.offTime = '23';
    assert.equal(draft.save(first), true);

    const events = [];
    const restored = offlineFuelScoreState(calculator(), scope(), () => session);
    restored.$watch = (name) => {
        events.push([name, restored.offTime]);
    };
    restored.init();
    assert.deepEqual(events, [['offTime', '23'], ['startingFob', '23'], ['waypoints', '23']]);
    assert.equal(session.getItem(draft.key) !== null, true);
});

test('restores separate phase marker inputs when coordinates are absent and identifiers repeat', () => {
    const source = calculator();
    source.waypoints = [
        { identifier: 'TOC', displayLabel: 'TOC', kind: 'toc', coordinate: null, cumulativeDurationMinutes: 15 },
        { identifier: 'TOC', displayLabel: 'TOC', kind: 'toc', coordinate: null, cumulativeDurationMinutes: 20 },
        { identifier: 'TOD', displayLabel: 'TOD', kind: 'tod', coordinate: null, cumulativeDurationMinutes: 45 },
    ];
    const session = storage();
    const first = state(source, scope(), session);
    first.monitor.offTime = '2350';
    first.monitor.startingFob = '20000';
    first.monitor.waypoints[0].ata = '0006';
    first.monitor.waypoints[0].actualFob = '19000';
    first.monitor.waypoints[1].actualFob = '18000';
    first.monitor.waypoints[2].ata = '0036';
    first.monitor.waypoints[2].actualFob = '10000';
    first.watchers.get('waypoints')();

    const restored = state(source, scope(), session).monitor;
    assert.equal(restored.offTime, '2350');
    assert.equal(restored.startingFob, '20000');
    assert.deepEqual(restored.waypoints.map(({ ata, actualFob }) => [ata, actualFob]), [
        ['0006', '19000'], ['', '18000'], ['0036', '10000'],
    ]);
    assert.deepEqual(restored.waypoints.map((waypoint) => restored.plannedEta(waypoint)), ['0005', '0010', '0035']);
});

test('reset clears stored values even when queued watchers run afterward', () => {
    const session = storage();
    const first = state(calculator(), scope(), session);
    first.monitor.offTime = '2359';
    first.monitor.startingFob = '100';
    first.monitor.waypoints[0].ata = '0001';
    first.monitor.waypoints[1].actualFob = '0';
    first.watchers.get('waypoints')();
    first.monitor.reset();
    for (const callback of first.watchers.values()) {
        callback();
    }

    const draft = createFuelScoreDraft(calculator(), scope(), () => session);
    assert.equal(session.getItem(draft.key), null);
    const restored = state(calculator(), scope(), session).monitor;
    assert.equal(restored.offTime, '');
    assert.equal(restored.startingFob, '');
    assert.deepEqual(restored.waypoints.map(({ ata, actualFob }) => [ata, actualFob]), [['', ''], ['', '']]);
});

test('drafts are isolated by owner, release, and changed source facts', () => {
    const session = storage();
    const original = state(calculator(), scope(), session);
    original.monitor.offTime = '0915';
    original.watchers.get('offTime')();

    assert.equal(state(calculator(), scope('2'), session).monitor.offTime, '');
    assert.equal(state(calculator(), scope('1', 'release-2'), session).monitor.offTime, '');
    const changed = calculator();
    changed.waypoints[0].coordinate = 'N09 02.3 E004 05.6';
    assert.equal(state(changed, scope(), session).monitor.offTime, '');
    assert.equal(state(calculator(), scope(), session).monitor.offTime, '');
});

test('rejects malformed and incompatible drafts without breaking the calculator', () => {
    const session = storage();
    const draft = createFuelScoreDraft(calculator(), scope(), () => session);
    session.setItem(draft.key, '{broken');
    assert.equal(state(calculator(), scope(), session).monitor.offTime, '');
    assert.equal(session.getItem(draft.key), null);

    const input = state(calculator(), scope(), session);
    input.monitor.offTime = '1200';
    input.watchers.get('offTime')();
    const envelope = JSON.parse(session.getItem(draft.key));
    envelope.waypoints[0].actualFob = 0;
    session.setItem(draft.key, JSON.stringify(envelope));
    assert.equal(state(calculator(), scope(), session).monitor.offTime, '');
    assert.equal(session.getItem(draft.key), null);
});

test('storage failures leave calculations usable and show recovery as unavailable', () => {
    const source = calculator();
    const unavailable = () => {
        throw new Error('storage disabled');
    };
    const monitor = offlineFuelScoreState(source, scope(), unavailable);
    monitor.$watch = () => {};
    monitor.init();
    assert.equal(monitor.draftUnavailable, true);
    monitor.offTime = '2350';
    monitor.saveDraft();
    assert.equal(monitor.plannedEta(monitor.waypoints[0]), '0001');
    monitor.reset();
    assert.equal(monitor.offTime, '');
    assert.equal(monitor.draftUnavailable, true);
});

test('read, write, and reset failures report unavailable recovery without losing live inputs', () => {
    const session = storage();
    let failRead = true;
    let failWrite = false;
    let failRemove = false;
    const failingStorage = {
        getItem(key) {
            if (failRead) {
                throw new Error('read blocked');
            }
            return session.getItem(key);
        },
        setItem(key, value) {
            if (failWrite) {
                throw new Error('quota exceeded');
            }
            session.setItem(key, value);
        },
        removeItem(key) {
            if (failRemove) {
                throw new Error('remove blocked');
            }
            session.removeItem(key);
        },
    };
    const current = state(calculator(), scope(), failingStorage);
    assert.equal(current.monitor.draftUnavailable, true);
    failRead = false;
    current.monitor.offTime = '1200';
    failWrite = true;
    current.watchers.get('offTime')();
    assert.equal(current.monitor.draftUnavailable, true);
    failWrite = false;
    current.watchers.get('offTime')();
    assert.equal(current.monitor.draftUnavailable, false);
    failRemove = true;
    current.monitor.reset();
    assert.equal(current.monitor.draftUnavailable, false);
    assert.equal(current.monitor.offTime, '');
    assert.equal(state(calculator(), scope(), session).monitor.offTime, '');

    current.monitor.offTime = '0900';
    current.watchers.get('offTime')();
    failWrite = true;
    current.monitor.reset();
    assert.equal(current.monitor.draftUnavailable, true);
    assert.equal(current.monitor.offTime, '');
});
