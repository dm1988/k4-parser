import assert from 'node:assert/strict';
import test from 'node:test';

import {
    calculatePlannedEta,
    calculateEtaAtaDifference,
    calculateActualBurn,
    calculateBurnVariance,
    calculatePlannedCumulativeBurn,
    calculateEstimatedDestinationFuel,
    calculateFobVariance,
    isValidOffTime,
    parseFuelInput,
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

    const monitor = waypointFuelMonitor({ fuelUnit: 'lb', takeoffFuel: null, waypoints: [{ cumulativeDurationMinutes: null }] });
    monitor.offTime = '1200';

    assert.equal(monitor.plannedEta(monitor.waypoints[0]), 'Unable to calculate');
    assert.match(monitor.etaReason(monitor.waypoints[0]), /Cumulative duration/);
});

test('ETA calculation does not require starting FOB', () => {
    const monitor = waypointFuelMonitor({ fuelUnit: 'lb', takeoffFuel: null, estimatedLandingFuel: null, waypoints: [{ cumulativeDurationMinutes: 20 }] });
    monitor.offTime = '2350';

    assert.equal(monitor.startingFob, '');
    assert.equal(monitor.plannedEta(monitor.waypoints[0]), '0010');
});

test('compares ETA and ATA with signed UTC minutes across midnight', () => {
    assert.equal(calculateEtaAtaDifference('1200', '1150'), 10);
    assert.equal(calculateEtaAtaDifference('1200', '1215'), -15);
    assert.equal(calculateEtaAtaDifference('0010', '2350'), 20);
    assert.equal(calculateEtaAtaDifference('2350', '0010'), -20);
    assert.equal(calculateEtaAtaDifference('1200', '1200'), 0);
    assert.equal(calculateEtaAtaDifference('0000', '1200'), null);
    assert.equal(calculateEtaAtaDifference('1200', '2400'), null);
});

test('shows waypoint time difference only after a valid ATA and explains unavailable plans', () => {
    const monitor = waypointFuelMonitor({
        fuelUnit: 'lb',
        takeoffFuel: null,
        estimatedLandingFuel: null,
        waypoints: [{ cumulativeDurationMinutes: 20 }],
    });
    const waypoint = monitor.waypoints[0];

    assert.equal(monitor.hasValidAta(waypoint), false);
    monitor.offTime = '2350';
    waypoint.ata = '2355';
    assert.equal(monitor.hasValidAta(waypoint), true);
    assert.equal(monitor.etaAtaDifference(waypoint), 15);
    assert.equal(monitor.etaAtaLabel(waypoint), '+15 min ahead');
    waypoint.ata = '0025';
    assert.equal(monitor.etaAtaLabel(waypoint), '−15 min behind');
    waypoint.ata = '0010';
    assert.equal(monitor.etaAtaLabel(waypoint), '0 min on time');
    monitor.offTime = '';
    assert.equal(monitor.etaAtaDifference(waypoint), null);
    assert.match(monitor.etaAtaReason(waypoint), /Enter Off time/);
});

test('calculates signed FOB and cumulative burn variances with explicit zero values', () => {
    assert.equal(calculateFobVariance('120.5', { amount: 100.25, unit: 'lb' }), 20.25);
    assert.equal(calculateFobVariance('80', { amount: 100, unit: 'lb' }), -20);
    assert.equal(calculateFobVariance('0', { amount: 0, unit: 'kg' }), 0);
    assert.equal(calculatePlannedCumulativeBurn('0050'), 5000);
    assert.equal(calculatePlannedCumulativeBurn('0000'), 0);
    assert.equal(calculateBurnVariance('20000', '17000', '0050'), 2000);
    assert.equal(calculateBurnVariance('20000', '8000', '0100'), -2000);
    assert.equal(calculateBurnVariance('0', '0', '0000'), 0);
    assert.equal(parseFuelInput('0.5'), 0.5);
    assert.equal(calculateActualBurn('150', '120'), 30);
});

test('uses starting FOB for cumulative burn at every waypoint even when earlier AFOB is missing', () => {
    const monitor = waypointFuelMonitor({
        fuelUnit: 'lb',
        takeoffFuel: { amount: 20000, unit: 'lb' },
        estimatedLandingFuel: { amount: 5000, unit: 'lb' },
        waypoints: [
            { tbo: '0050', remainingFuel: { amount: 15000, unit: 'lb' } },
            { tbo: '0100', remainingFuel: { amount: 10000, unit: 'lb' } },
        ],
    });
    monitor.startingFob = '20000';
    monitor.waypoints[0].actualFob = '17000';
    monitor.waypoints[1].actualFob = '11000';
    monitor.waypoints[0].ata = '1200';

    assert.equal(monitor.plannedBurnLabel(monitor.waypoints[0]), '0050 · 5,000 LB');
    assert.equal(monitor.burnVariance(monitor.waypoints[0]), 2000);
    assert.equal(monitor.actualBurn(monitor.waypoints[0]), 3000);
    assert.equal(monitor.actualBurn(monitor.waypoints[1]), 9000);
    assert.equal(monitor.burnVariance(monitor.waypoints[1]), 1000);
    assert.equal(monitor.estimatedDestinationFuel(monitor.waypoints[1]), 6000);
    assert.equal(monitor.varianceLabel(10), '+10 LB');
    assert.equal(monitor.varianceLabel(-10), '−10 LB');
    assert.equal(monitor.varianceClasses(10), 'text-emerald-700 dark:text-emerald-400');
    assert.equal(monitor.varianceClasses(-10), 'text-red-700 dark:text-red-400');

    monitor.waypoints[0].actualFob = '';
    assert.equal(monitor.actualBurn(monitor.waypoints[1]), 9000);
    assert.equal(monitor.burnVariance(monitor.waypoints[1]), 1000);

    monitor.reset();
    assert.equal(monitor.startingFob, '');
    assert.equal(monitor.waypoints[0].ata, '');
    assert.equal(monitor.waypoints[0].actualFob, '');
    assert.equal(monitor.waypoints[1].actualFob, '');
});

test('withholds fuel comparisons when source data or actual readings are unusable', () => {
    const remaining = { amount: 50, unit: 'lb' };

    for (const invalid of ['', '-1', 'abc', '1e3', 'Infinity']) {
        assert.equal(calculateFobVariance(invalid, remaining), null);
        assert.equal(calculateEstimatedDestinationFuel(invalid, remaining, { amount: 20, unit: 'lb' }), null);
    }

    assert.equal(calculateFobVariance('80', null), null);
    assert.equal(calculatePlannedCumulativeBurn(null), null);
    assert.equal(calculatePlannedCumulativeBurn('----'), null);
    assert.equal(calculateBurnVariance('100', '80', null), null);
    assert.equal(calculateBurnVariance('50', '60', '0001'), null);
    assert.equal(calculateActualBurn('50', '60'), null);
    assert.equal(calculateEstimatedDestinationFuel('80', remaining, null), null);
    assert.equal(calculateEstimatedDestinationFuel('80', remaining, { amount: 20, unit: 'kg' }), null);
    assert.equal(calculateEstimatedDestinationFuel('80', remaining, { amount: 60, unit: 'lb' }), null);
    assert.equal(calculateEstimatedDestinationFuel('10', remaining, { amount: 20, unit: 'lb' }), null);
});

test('explains why cumulative burn cannot be compared without starting FOB or TBO', () => {
    const monitor = waypointFuelMonitor({
        fuelUnit: 'lb',
        takeoffFuel: { amount: 10000, unit: 'lb' },
        estimatedLandingFuel: null,
        waypoints: [{ tbo: null, remainingFuel: { amount: 9000, unit: 'lb' } }],
    });
    const waypoint = monitor.waypoints[0];
    waypoint.actualFob = '9000';

    assert.equal(monitor.actualBurn(waypoint), null);
    assert.equal(monitor.burnReason(waypoint), 'Enter starting FOB.');
    monitor.startingFob = '8000';
    assert.equal(monitor.burnReason(waypoint), 'AFOB exceeds starting FOB.');
    monitor.startingFob = '10000';
    assert.equal(monitor.actualBurn(waypoint), 1000);
    assert.equal(monitor.burnVariance(waypoint), null);
    assert.equal(monitor.burnReason(waypoint), 'TBO is not present in this release.');
    waypoint.tbo = '0010';
    assert.equal(monitor.burnVariance(waypoint), 0);

    monitor.fuelUnit = null;
    assert.equal(monitor.plannedBurnLabel(waypoint), '0010 · release fuel unit unavailable');
    assert.equal(monitor.actualBurn(waypoint), null);
    assert.equal(monitor.burnReason(waypoint), 'Release fuel unit is not present.');
});

test('calculator reset clears ephemeral inputs', () => {
    const monitor = waypointFuelMonitor({ fuelUnit: 'lb', takeoffFuel: { amount: 100, unit: 'lb' }, estimatedLandingFuel: null, waypoints: [] });
    monitor.offTime = '2359';
    monitor.startingFob = '100';
    monitor.reset();

    assert.equal(monitor.offTime, '');
    assert.equal(monitor.startingFob, '');
});

test('separates summary values from muted units without inventing missing values', () => {
    const monitor = waypointFuelMonitor({
        fuelUnit: 'lb',
        takeoffFuel: null,
        estimatedLandingFuel: { amount: 20, unit: 'lb' },
        waypoints: [{ cumulativeDurationMinutes: 0, remainingFuel: { amount: 100.25, unit: 'lb' } }],
    });
    const waypoint = monitor.waypoints[0];

    assert.equal(monitor.durationValue(0), '0');
    assert.equal(monitor.durationUnit(0), 'min');
    assert.equal(monitor.durationValue(null), 'Not present in this release');
    assert.equal(monitor.durationUnit(null), '');
    assert.equal(monitor.sourceFuelValue(waypoint.remainingFuel), '100.25');
    assert.equal(monitor.sourceFuelUnit(waypoint.remainingFuel), 'LB');
    assert.equal(monitor.sourceFuelValue(null), 'Not present in this release');
    assert.equal(monitor.sourceFuelUnit(null), '');
    assert.equal(monitor.destinationValue(waypoint), '—');
    assert.equal(monitor.destinationUnit(waypoint), '');

    waypoint.actualFob = '110.5';
    assert.equal(monitor.destinationValue(waypoint), '30.25');
    assert.equal(monitor.destinationUnit(waypoint), 'LB');
    waypoint.actualFob = 'invalid';
    assert.equal(monitor.destinationValue(waypoint), 'Unable to calculate');
    assert.equal(monitor.destinationUnit(waypoint), '');
});

test('empty inputs keep each waypoint compact without calculation prompts', () => {
    const monitor = waypointFuelMonitor({
        fuelUnit: 'lb',
        takeoffFuel: { amount: 150, unit: 'lb' },
        estimatedLandingFuel: { amount: 30, unit: 'lb' },
        waypoints: [
            { identifier: 'FIX01', cumulativeDurationMinutes: 10, remainingFuel: { amount: 120, unit: 'lb' } },
            { identifier: 'FIX02', cumulativeDurationMinutes: 20, remainingFuel: { amount: 100, unit: 'lb' } },
        ],
    });
    const waypoint = monitor.waypoints[0];

    assert.equal(monitor.waypoints.every((item) => item.expanded === false), true);
    assert.equal(monitor.plannedEta(waypoint), '—');
    assert.equal(monitor.etaReason(waypoint), '');
    assert.equal(monitor.hasActualFob(waypoint), false);
    assert.equal(monitor.fobReason(waypoint), '');
    assert.equal(monitor.burnReason(waypoint), '');
    assert.equal(monitor.destinationLabel(waypoint), '—');
    assert.equal(monitor.destinationReason(waypoint), '');

    monitor.toggleWaypoint(waypoint);
    assert.equal(waypoint.expanded, true);
    assert.equal(monitor.waypoints[1].expanded, false);
    waypoint.actualFob = '130';
    assert.equal(monitor.hasActualFob(waypoint), true);
    assert.equal(monitor.destinationLabel(waypoint), '40 LB');
    monitor.toggleWaypoint(waypoint);
    assert.equal(waypoint.expanded, false);
    assert.equal(waypoint.actualFob, '130');

    monitor.reset();
    assert.equal(waypoint.expanded, false);
    assert.equal(waypoint.actualFob, '');
});
