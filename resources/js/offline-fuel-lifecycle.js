export const bindFuelDraftLifecycle = (monitor, page = globalThis.document, browser = globalThis.window) => {
    if (!page || !browser) {
        return () => {};
    }

    const hidden = () => {
        if (page.visibilityState === 'hidden') {
            monitor.saveDraft();
        }
    };
    const leaving = () => monitor.saveDraft();

    page.addEventListener('visibilitychange', hidden);
    page.addEventListener('freeze', leaving);
    browser.addEventListener('pagehide', leaving);

    return () => {
        page.removeEventListener('visibilitychange', hidden);
        page.removeEventListener('freeze', leaving);
        browser.removeEventListener('pagehide', leaving);
    };
};
