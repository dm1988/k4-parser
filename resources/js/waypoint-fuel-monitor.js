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

export const waypointFuelMonitor = (waypoints) => ({
    offTime: '',
    waypoints,

    get hasCalculatedEtas() {
        return isValidOffTime(this.offTime)
            && this.waypoints.some((waypoint) => Number.isInteger(waypoint.cumulativeDurationMinutes));
    },

    get offTimeMessage() {
        if (this.offTime === '') {
            return 'Enter 0000–2359 to show planned ETA.';
        }

        return isValidOffTime(this.offTime)
            ? 'Planned ETA uses confirmed cumulative duration.'
            : 'Enter a valid 24-hour time from 0000 to 2359.';
    },

    durationLabel(durationMinutes) {
        return Number.isInteger(durationMinutes) ? `${durationMinutes} min` : 'Not present';
    },

    plannedEta(waypoint) {
        return calculatePlannedEta(this.offTime, waypoint.cumulativeDurationMinutes) ?? 'Not present';
    },
});

if (typeof window !== 'undefined') {
    window.waypointFuelMonitor = waypointFuelMonitor;
}
