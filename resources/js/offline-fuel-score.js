import Alpine from 'alpinejs';
import { offlineFuelScoreState } from './offline-fuel-score-state';
import { initializeOfflineFuelRecovery } from './offline-fuel-recovery';

window.offlineFuelScore = (calculator, scope, offlineRecovery) => {
    const state = offlineFuelScoreState(calculator, scope);
    let stopRecovery = () => {};

    return {
        ...state,
        offlineReady: false,
        offlineMessage: 'Preparing offline recovery…',
        init() {
            state.init.call(this);
            stopRecovery = initializeOfflineFuelRecovery(offlineRecovery, (ready, message) => {
                this.offlineReady = ready;
                this.offlineMessage = message;
            });
        },
        destroy() {
            state.destroy.call(this);
            stopRecovery();
        },
    };
};
window.Alpine = Alpine;

Alpine.start();
