import assert from 'node:assert/strict';
import test from 'node:test';

import {
    calculatePlannedEta,
    isValidOffTime,
    waypointFuelMonitor,
} from '../../resources/js/waypoint-fuel-monitor.js';

test('validates optional off time as an exact 24-hour HHMM value', () => {
    for (const valid of ['0000', '0715', '2359']) {
        assert.equal(isValidOffTime(valid), true);
    }

    for (const invalid of ['', '2400', '1260', '930', '09:30', 'abcd']) {
        assert.equal(isValidOffTime(invalid), false);
    }
});

test('calculates planned UTC ETA with midnight rollover', () => {
    assert.equal(calculatePlannedEta('2350', 20), '0010');
    assert.equal(calculatePlannedEta('0000', 0), '0000');
});

test('does not calculate ETA without a confirmed cumulative duration', () => {
    assert.equal(calculatePlannedEta('1200', null), null);

    const monitor = waypointFuelMonitor([{ cumulativeDurationMinutes: null }]);
    monitor.offTime = '1200';

    assert.equal(monitor.hasCalculatedEtas, false);
    assert.equal(monitor.plannedEta(monitor.waypoints[0]), 'Not present');
});
