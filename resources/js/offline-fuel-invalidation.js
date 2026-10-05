export default function initializeOfflineFuelInvalidation(browser = window) {
    if (browser.offlineFuelInvalidationInitialized) {
        return;
    }

    browser.offlineFuelInvalidationInitialized = true;

    browser.addEventListener('offline-fuel-release-changed', (event) => {
        const controller = browser.navigator.serviceWorker?.controller;

        if (!controller || new URL(controller.scriptURL).pathname !== '/offline-fuel-worker.js') {
            return;
        }

        controller.postMessage({
            type: 'RECONCILE_OFFLINE_FUEL',
            ownerId: event.detail.ownerId,
            flightPlanKey: event.detail.flightPlanKey ?? '',
        });
    });
}
