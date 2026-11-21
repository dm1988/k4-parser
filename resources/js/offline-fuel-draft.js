const DRAFT_VERSION = 1;

const isRecord = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

const waypointIdentity = (waypoint, index) => JSON.stringify([index, waypoint.identifier, waypoint.coordinate]);

const sourceSignature = (calculator) => JSON.stringify([
    calculator.fuelUnit,
    calculator.takeoffFuel,
    calculator.estimatedLandingFuel,
    calculator.waypoints.map((waypoint) => [
        waypoint.identifier,
        waypoint.coordinate,
        waypoint.tbo,
        waypoint.legDurationMinutes,
        waypoint.cumulativeDurationMinutes,
        waypoint.remainingFuel,
    ]),
]);

export const createFuelScoreDraft = (
    calculator,
    scope,
    storageProvider = () => window.localStorage,
    legacyStorageProvider = () => globalThis.window?.sessionStorage,
) => {
    const key = `offline-fuel-score:v${DRAFT_VERSION}:${encodeURIComponent(scope.ownerId)}:${encodeURIComponent(scope.flightPlanKey)}`;
    const signature = sourceSignature(calculator);
    const waypointIds = calculator.waypoints.map(waypointIdentity);
    let savedInputs = null;

    const emptyDraft = () => ({
        version: DRAFT_VERSION,
        sourceSignature: signature,
        offTime: '',
        startingFob: '',
        waypoints: waypointIds.map((identity) => ({ identity, ata: '', actualFob: '' })),
    });

    const clearStorage = (provider) => {
        try {
            provider()?.removeItem(key);
            return true;
        } catch {
            try {
                provider()?.setItem(key, JSON.stringify(emptyDraft()));
                return true;
            } catch {
                return false;
            }
        }
    };

    const clear = () => {
        const legacyCleared = clearStorage(legacyStorageProvider);
        const currentCleared = clearStorage(storageProvider);

        if (legacyCleared && currentCleared) {
            savedInputs = JSON.stringify(emptyDraft());
        }

        return legacyCleared && currentCleared;
    };

    return {
        key,
        clear,
        load() {
            try {
                const storage = storageProvider();
                let raw = storage.getItem(key);
                const needsMigration = raw === null;

                if (needsMigration) {
                    raw = legacyStorageProvider()?.getItem(key) ?? null;
                }

                if (raw === null) {
                    savedInputs = JSON.stringify(emptyDraft());
                    return { available: true, inputs: null };
                }

                let draft;

                try {
                    draft = JSON.parse(raw);
                } catch {
                    return { available: clear(), inputs: null };
                }

                const valid = isRecord(draft)
                    && draft.version === DRAFT_VERSION
                    && draft.sourceSignature === signature
                    && typeof draft.offTime === 'string'
                    && typeof draft.startingFob === 'string'
                    && Array.isArray(draft.waypoints)
                    && draft.waypoints.length === waypointIds.length
                    && draft.waypoints.every((waypoint, index) => isRecord(waypoint)
                        && waypoint.identity === waypointIds[index]
                        && typeof waypoint.ata === 'string'
                        && typeof waypoint.actualFob === 'string');

                if (!valid) {
                    return { available: clear(), inputs: null };
                }

                if (needsMigration) {
                    try {
                        storage.setItem(key, raw);
                        clearStorage(legacyStorageProvider);
                    } catch {
                        return { available: false, inputs: draft };
                    }
                }

                savedInputs = JSON.stringify(draft);
                return { available: true, inputs: draft };
            } catch {
                return { available: false, inputs: null };
            }
        },
        save(monitor) {
            const waypoints = monitor.waypoints.map((waypoint, index) => ({
                identity: waypointIds[index],
                ata: waypoint.ata,
                actualFob: waypoint.actualFob,
            }));
            const serialized = JSON.stringify({
                version: DRAFT_VERSION,
                sourceSignature: signature,
                offTime: monitor.offTime,
                startingFob: monitor.startingFob,
                waypoints,
            });

            if (serialized === savedInputs) {
                return true;
            }

            if (monitor.offTime === '' && monitor.startingFob === ''
                && waypoints.every((waypoint) => waypoint.ata === '' && waypoint.actualFob === '')) {
                return clear();
            }

            try {
                storageProvider().setItem(key, serialized);
                savedInputs = serialized;
                return true;
            } catch {
                return false;
            }
        },
    };
};
