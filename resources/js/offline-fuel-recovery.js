const unavailable = 'Offline recovery unavailable. Keep this tab open and reconnect before refreshing.';

export const initializeOfflineFuelRecovery = (config, report, browser = globalThis.window) => {
    let disposed = false;
    const container = browser?.navigator.serviceWorker;
    const update = (ready, message) => {
        if (!disposed) {
            report(ready, message);
        }
    };
    const invalidated = (event) => {
        if (event.data?.type === 'OFFLINE_FUEL_INVALIDATED') {
            update(false, 'Offline copy removed. Reconnect and open the current flight’s calculator.');
        }
    };

    if (!browser?.isSecureContext || !container || !config?.assets?.length) {
        update(false, unavailable);
        return () => { disposed = true; };
    }

    container.addEventListener('message', invalidated);
    update(false, 'Preparing offline recovery…');

    const start = async () => {
        const workerUrl = new URL(config.workerUrl, browser.location.href);

        if (workerUrl.origin !== browser.location.origin) {
            throw new Error('Unsupported worker origin');
        }

        await container.register(workerUrl.href, { scope: '/', updateViaCache: 'none' });

        if (!container.controller || container.controller.scriptURL !== workerUrl.href) {
            await new Promise((resolve, reject) => {
                const changed = () => {
                    if (container.controller?.scriptURL === workerUrl.href) {
                        clearTimeout(timeout);
                        container.removeEventListener('controllerchange', changed);
                        resolve();
                    }
                };
                const timeout = setTimeout(() => {
                    container.removeEventListener('controllerchange', changed);
                    reject(new Error('Worker unavailable'));
                }, 10000);
                container.addEventListener('controllerchange', changed);
                changed();
            });
        }

        if (disposed) {
            return;
        }

        const result = await new Promise((resolve, reject) => {
            const channel = new browser.MessageChannel();
            const close = () => {
                channel.port1.close();
                channel.port2.close();
            };
            const timeout = setTimeout(() => {
                close();
                reject(new Error('Preparation timed out'));
            }, 20000);
            channel.port1.onmessage = (event) => {
                clearTimeout(timeout);
                close();
                resolve(event.data);
            };
            container.controller.postMessage({ type: 'PREPARE_OFFLINE_FUEL' }, [channel.port2]);
        });

        update(result.ready === true, result.ready
            ? (result.recovered ? 'Offline copy loaded. This tab can reload without a connection.' : 'Offline recovery ready. This tab can reload without a connection.')
            : unavailable);
    };

    start().catch(() => update(false, unavailable));

    return () => {
        disposed = true;
        container.removeEventListener('message', invalidated);
    };
};
