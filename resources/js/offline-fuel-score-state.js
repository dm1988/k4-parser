import { createFuelScoreDraft } from './offline-fuel-draft.js';
import { waypointFuelMonitor } from './waypoint-fuel-monitor.js';

export const offlineFuelScoreState = (calculator, scope, storageProvider) => {
    const monitor = waypointFuelMonitor(calculator);
    const draft = createFuelScoreDraft(calculator, scope, storageProvider);

    return {
        ...monitor,
        draftUnavailable: false,

        init() {
            const restored = draft.load();
            this.draftUnavailable = !restored.available;

            if (restored.inputs !== null) {
                this.offTime = restored.inputs.offTime;
                this.startingFob = restored.inputs.startingFob;
                this.waypoints.forEach((waypoint, index) => {
                    waypoint.ata = restored.inputs.waypoints[index].ata;
                    waypoint.actualFob = restored.inputs.waypoints[index].actualFob;
                });
            }

            this.$watch('offTime', () => this.saveDraft());
            this.$watch('startingFob', () => this.saveDraft());
            this.$watch('waypoints', () => this.saveDraft());
        },

        saveDraft() {
            this.draftUnavailable = !draft.save(this);
        },

        reset() {
            monitor.reset.call(this);
            this.draftUnavailable = !draft.clear();
        },
    };
};
