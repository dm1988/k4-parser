export const isValidOffTime = (offTime) => /^(?:[01]\d|2[0-3])[0-5]\d$/.test(offTime);

export const calculatePlannedEta = (offTime, cumulativeDurationMinutes) => {
    if (!isValidOffTime(offTime) || !Number.isInteger(cumulativeDurationMinutes) || cumulativeDurationMinutes < 0) {
        return null;
    }

    const offMinutes = (Number(offTime.slice(0, 2)) * 60) + Number(offTime.slice(2));
    const plannedMinutes = (offMinutes + cumulativeDurationMinutes) % (24 * 60);
    const hours = String(Math.floor(plannedMinutes / 60)).padStart(2, '0');
    const minutes = String(plannedMinutes % 60).padStart(2, '0');

    return `${hours}${minutes}`;
};

export const calculateEtaAtaDifference = (plannedEta, ata) => {
    if (!isValidOffTime(plannedEta) || !isValidOffTime(ata)) {
        return null;
    }

    const plannedMinutes = (Number(plannedEta.slice(0, 2)) * 60) + Number(plannedEta.slice(2));
    const actualMinutes = (Number(ata.slice(0, 2)) * 60) + Number(ata.slice(2));
    let difference = plannedMinutes - actualMinutes;

    if (difference > 720) {
        difference -= 1440;
    } else if (difference < -720) {
        difference += 1440;
    }

    return Math.abs(difference) === 720 ? null : difference;
};

export const parseFuelInput = (value) => {
    if (typeof value !== 'string' || !/^\d+(?:\.\d+)?$/.test(value.trim())) {
        return null;
    }

    const amount = Number(value.trim());

    return Number.isFinite(amount) ? amount : null;
};

const isValidQuantity = (quantity) => quantity !== null
    && typeof quantity === 'object'
    && Number.isFinite(quantity.amount)
    && quantity.amount >= 0
    && ['lb', 'kg'].includes(quantity.unit);

const fuelNumberFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });
const isValidDuration = (minutes) => Number.isInteger(minutes) && minutes >= 0;

export const calculateFobVariance = (actualFob, plannedRemainingFuel) => {
    const actualAmount = parseFuelInput(actualFob);

    if (actualAmount === null || !isValidQuantity(plannedRemainingFuel)) {
        return null;
    }

    return actualAmount - plannedRemainingFuel.amount;
};

export const calculatePlannedCumulativeBurn = (tbo) => typeof tbo === 'string' && /^\d{4}$/.test(tbo)
    ? Number(tbo) * 100
    : null;

export const calculateActualBurn = (startingFob, actualFob) => {
    const startingAmount = parseFuelInput(startingFob);
    const actualAmount = parseFuelInput(actualFob);

    return startingAmount !== null && actualAmount !== null && startingAmount >= actualAmount
        ? startingAmount - actualAmount
        : null;
};

export const calculateBurnVariance = (startingFob, actualFob, tbo) => {
    const actualBurn = calculateActualBurn(startingFob, actualFob);
    const plannedBurn = calculatePlannedCumulativeBurn(tbo);

    return actualBurn !== null && plannedBurn !== null ? plannedBurn - actualBurn : null;
};

export const calculateEstimatedDestinationFuel = (actualFob, plannedRemainingFuel, estimatedLandingFuel) => {
    const actualAmount = parseFuelInput(actualFob);

    if (actualAmount === null || !isValidQuantity(plannedRemainingFuel) || !isValidQuantity(estimatedLandingFuel)
        || plannedRemainingFuel.unit !== estimatedLandingFuel.unit
        || plannedRemainingFuel.amount < estimatedLandingFuel.amount) {
        return null;
    }

    const result = actualAmount - (plannedRemainingFuel.amount - estimatedLandingFuel.amount);

    return Number.isFinite(result) && result >= 0 ? result : null;
};

export const waypointFuelMonitor = (calculator) => ({
    offTime: '',
    startingFob: '',
    fuelUnit: calculator.fuelUnit,
    takeoffFuel: calculator.takeoffFuel,
    estimatedLandingFuel: calculator.estimatedLandingFuel,
    waypoints: calculator.waypoints.map((waypoint) => ({ ...waypoint, ata: '', actualFob: '', expanded: false })),

    reset() {
        this.offTime = '';
        this.startingFob = '';
        this.waypoints.forEach((waypoint) => {
            waypoint.ata = '';
            waypoint.actualFob = '';
            waypoint.expanded = false;
        });
    },

    toggleWaypoint(waypoint) {
        waypoint.expanded = !waypoint.expanded;
    },

    hasActualFob(waypoint) {
        return waypoint.actualFob.trim() !== '';
    },

    durationLabel(minutes) {
        return isValidDuration(minutes) ? `${minutes} min` : 'Not present in this release';
    },

    durationValue(minutes) {
        return isValidDuration(minutes) ? String(minutes) : 'Not present in this release';
    },

    durationUnit(minutes) {
        return isValidDuration(minutes) ? 'min' : '';
    },

    plannedEta(waypoint) {
        if (this.offTime.trim() === '') {
            return '—';
        }

        return calculatePlannedEta(this.offTime, waypoint.cumulativeDurationMinutes) ?? 'Unable to calculate';
    },

    etaReason(waypoint) {
        if (this.offTime.trim() === '') {
            return '';
        }

        if (!isValidOffTime(this.offTime)) {
            return 'Enter a valid UTC Off time (0000–2359).';
        }

        return calculatePlannedEta(this.offTime, waypoint.cumulativeDurationMinutes) === null
            ? 'Cumulative duration is not present in this release.'
            : '';
    },

    sourceFuelLabel(quantity) {
        return isValidQuantity(quantity)
            ? `${this.sourceFuelValue(quantity)} ${this.sourceFuelUnit(quantity)}`
            : 'Not present in this release';
    },

    sourceFuelValue(quantity) {
        return isValidQuantity(quantity) ? fuelNumberFormatter.format(quantity.amount) : 'Not present in this release';
    },

    sourceFuelUnit(quantity) {
        return isValidQuantity(quantity) ? quantity.unit.toUpperCase() : '';
    },

    ataReason(waypoint) {
        return waypoint.ata !== '' && !isValidOffTime(waypoint.ata)
            ? 'Enter ATA as four-digit UTC (0000–2359).'
            : '';
    },

    hasValidAta(waypoint) {
        return isValidOffTime(waypoint.ata);
    },

    etaAtaDifference(waypoint) {
        return calculateEtaAtaDifference(
            calculatePlannedEta(this.offTime, waypoint.cumulativeDurationMinutes),
            waypoint.ata,
        );
    },

    etaAtaLabel(waypoint) {
        const difference = this.etaAtaDifference(waypoint);

        if (difference === null) {
            return 'Unable to calculate';
        }

        return difference > 0 ? `+${difference} min ahead`
            : difference < 0 ? `−${Math.abs(difference)} min behind`
                : '0 min on time';
    },

    etaAtaReason(waypoint) {
        if (!this.hasValidAta(waypoint)) {
            return '';
        }

        const plannedEta = calculatePlannedEta(this.offTime, waypoint.cumulativeDurationMinutes);

        if (plannedEta === null) {
            return this.offTime.trim() === ''
                ? 'Enter Off time to compare ETA and ATA.'
                : this.etaReason(waypoint);
        }

        return 'ETA and ATA are 12 hours apart; a waypoint date is needed to determine ahead or behind.';
    },

    fobVariance(waypoint) {
        if (this.fuelUnit !== waypoint.remainingFuel?.unit) {
            return null;
        }

        return calculateFobVariance(waypoint.actualFob, waypoint.remainingFuel);
    },

    plannedBurnLabel(waypoint) {
        const plannedBurn = calculatePlannedCumulativeBurn(waypoint.tbo);

        if (plannedBurn === null) {
            return 'Not present in this release';
        }

        return this.fuelUnit === null
            ? `${waypoint.tbo} · release fuel unit unavailable`
            : `${waypoint.tbo} · ${this.fuelLabel(plannedBurn)}`;
    },

    actualBurn(waypoint) {
        return this.fuelUnit !== null ? calculateActualBurn(this.startingFob, waypoint.actualFob) : null;
    },

    burnVariance(waypoint) {
        return this.fuelUnit !== null
            ? calculateBurnVariance(this.startingFob, waypoint.actualFob, waypoint.tbo)
            : null;
    },

    estimatedDestinationFuel(waypoint) {
        if (this.fuelUnit !== waypoint.remainingFuel?.unit || this.fuelUnit !== this.estimatedLandingFuel?.unit) {
            return null;
        }

        return calculateEstimatedDestinationFuel(waypoint.actualFob, waypoint.remainingFuel, this.estimatedLandingFuel);
    },

    fuelLabel(amount) {
        return amount === null
            ? 'Unable to calculate'
            : `${fuelNumberFormatter.format(amount)} ${this.fuelUnit.toUpperCase()}`;
    },

    varianceLabel(amount) {
        return amount === null
            ? 'Unable to calculate'
            : `${amount > 0 ? '+' : amount < 0 ? '−' : ''}${fuelNumberFormatter.format(Math.abs(amount))} ${this.fuelUnit.toUpperCase()}`;
    },

    varianceClasses(amount) {
        return amount > 0 ? 'text-emerald-700 dark:text-emerald-400'
            : amount < 0 ? 'text-red-700 dark:text-red-400'
                : 'text-[#4A5568] dark:text-slate-400';
    },

    fobReason(waypoint) {
        if (!this.hasActualFob(waypoint)) {
            return '';
        }

        if (parseFuelInput(waypoint.actualFob) === null) {
            return 'Enter a non-negative AFOB.';
        }

        if (!isValidQuantity(waypoint.remainingFuel)) {
            return 'Planned remaining fuel is not present in this release.';
        }

        return 'Fuel units do not match.';
    },

    burnReason(waypoint) {
        if (!this.hasActualFob(waypoint)) {
            return '';
        }

        if (this.fuelUnit === null) {
            return 'Release fuel unit is not present.';
        }

        if (parseFuelInput(waypoint.actualFob) === null) {
            return 'Enter AFOB.';
        }

        if (parseFuelInput(this.startingFob) === null) {
            return 'Enter starting FOB.';
        }

        if (this.actualBurn(waypoint) === null) {
            return 'AFOB exceeds starting FOB.';
        }

        return 'TBO is not present in this release.';
    },

    destinationReason(waypoint) {
        if (!this.hasActualFob(waypoint)) {
            return '';
        }

        if (parseFuelInput(waypoint.actualFob) === null) {
            return 'Enter AFOB.';
        }

        if (!isValidQuantity(this.estimatedLandingFuel)) {
            return 'Estimated landing fuel is not present in this release.';
        }

        return 'Confirmed fuel values or projected fuel are inconsistent.';
    },

    destinationLabel(waypoint) {
        return this.hasActualFob(waypoint)
            ? this.fuelLabel(this.estimatedDestinationFuel(waypoint))
            : '—';
    },

    destinationValue(waypoint) {
        if (!this.hasActualFob(waypoint)) {
            return '—';
        }

        const amount = this.estimatedDestinationFuel(waypoint);

        return amount === null ? 'Unable to calculate' : fuelNumberFormatter.format(amount);
    },

    destinationUnit(waypoint) {
        return this.hasActualFob(waypoint) && this.estimatedDestinationFuel(waypoint) !== null
            ? this.fuelUnit.toUpperCase()
            : '';
    },
});
