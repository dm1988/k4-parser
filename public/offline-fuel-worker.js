const CACHE_PREFIX = 'k4-offline-fuel-';
const PAGE_CACHE = `${CACHE_PREFIX}pages-v1`;
const ASSET_CACHE = `${CACHE_PREFIX}assets-v1`;
const calculatorPath = /^\/flight-plan-brief\/fuel-score\/[0-9A-HJKMNP-TV-Z]{26}$/i;
let revision = 0;
let queue = Promise.resolve();
let currentOwner = null;
let currentKey = null;

const serialize = (task) => {
    queue = queue.then(task, task);
    return queue;
};

const isCalculator = (url) => url.origin === self.location.origin
    && calculatorPath.test(url.pathname) && url.search === '';

const notifyInvalidation = async (urls) => {
    for (const client of await self.clients.matchAll({ type: 'window' })) {
        if (urls.includes(client.url)) {
            client.postMessage({ type: 'OFFLINE_FUEL_INVALIDATED' });
        }
    }
};

const synchronize = async (response) => {
    if (!response.headers.has('X-Offline-Fuel-Owner')) {
        return;
    }

    const pages = await caches.open(PAGE_CACHE);
    const owner = response.headers.get('X-Offline-Fuel-Owner');
    const hasKey = response.headers.has('X-Offline-Fuel-Key');
    const key = response.headers.get('X-Offline-Fuel-Key');
    const requests = await pages.keys();
    const removed = [];

    if (currentOwner === null && requests.length > 0) {
        const known = await pages.match(requests[0]);
        currentOwner = known.headers.get('X-Offline-Fuel-Owner');
        currentKey = known.headers.get('X-Offline-Fuel-Key');
    }

    const changedOwner = currentOwner !== owner;

    if (changedOwner || (hasKey && currentKey !== key)) {
        revision++;
    }

    currentOwner = owner;

    if (hasKey || changedOwner) {
        currentKey = key;
    }

    for (const request of requests) {
        const page = await pages.match(request);

        if (!owner || page.headers.get('X-Offline-Fuel-Owner') !== owner
            || (hasKey && page.headers.get('X-Offline-Fuel-Key') !== key)) {
            await pages.delete(request);
            removed.push(request.url);
        }
    }

    if (removed.length > 0 || !owner) {
        await caches.delete(ASSET_CACHE);
        await notifyInvalidation(removed);
    }
};

const forgetPage = async (url) => {
    const pages = await caches.open(PAGE_CACHE);

    if (await pages.delete(url)) {
        revision++;
        await notifyInvalidation([url]);
    }
};

const missingPage = () => new Response(
    '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    + '<title>Offline calculator unavailable</title><main><h1>No offline copy is available</h1>'
    + '<p>Reconnect and open your current flight’s calculator. Wait until offline recovery is ready before going offline.</p>'
    + '<a href="/flight-plan-brief">Return to Flight Plan Brief</a></main></html>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } },
);

const calculatorResponse = async (request) => {
    try {
        const response = await fetch(request);

        await serialize(async () => {
            await synchronize(response);

            if (!response.ok || response.redirected || response.headers.get('X-Offline-Fuel-Page') !== '1') {
                await forgetPage(request.url);
            }
        }).catch(() => {});

        return response;
    } catch {
        try {
            const cached = await (await caches.open(PAGE_CACHE)).match(request);

            if (cached) {
                return cached;
            }
        } catch {
            // Navigation remains usable even when the browser blocks Cache Storage.
        }

        return missingPage();
    }
};

const prepare = async (url) => {
    const fetchRevision = revision;
    let page;
    let recovered = false;

    try {
        page = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
    } catch {
        page = await (await caches.open(PAGE_CACHE)).match(url);
        recovered = true;
    }

    if (!page) {
        throw new Error('Page unavailable');
    }

    await serialize(async () => {
        if (fetchRevision !== revision) {
            throw new Error('Session changed during preparation');
        }

        await synchronize(page);

        if (!page.ok || page.redirected || page.headers.get('X-Offline-Fuel-Page') !== '1'
            || !page.headers.get('X-Offline-Fuel-Owner')
            || page.headers.get('X-Offline-Fuel-Key') !== new URL(url).pathname.split('/').at(-1)) {
            await forgetPage(url);
            throw new Error('Release unavailable');
        }
    });

    const assetUrls = JSON.parse(page.headers.get('X-Offline-Fuel-Assets'));

    if (!Array.isArray(assetUrls) || assetUrls.length === 0) {
        throw new Error('Assets unavailable');
    }

    const assets = [...new Set(assetUrls.map((asset) => {
        const parsed = new URL(asset, self.location.origin);

        if (parsed.origin !== self.location.origin || !parsed.pathname.startsWith('/build/') || parsed.search !== '') {
            throw new Error('Unsupported asset');
        }

        return parsed.href;
    }))];
    const preparedRevision = revision;
    const assetCache = await caches.open(ASSET_CACHE);

    for (const asset of assets) {
        if (await assetCache.match(asset)) {
            continue;
        }

        const response = await fetch(asset, { cache: 'no-store' });

        if (!response.ok || response.redirected) {
            throw new Error('Asset unavailable');
        }

        await assetCache.put(asset, response);
    }

    await serialize(async () => {
        if (preparedRevision !== revision) {
            throw new Error('Release changed during preparation');
        }

        await (await caches.open(PAGE_CACHE)).put(url, page);
    });

    return { ready: true, recovered };
};

self.addEventListener('install', (event) => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const name of await caches.keys()) {
            if (name.startsWith(CACHE_PREFIX) && name !== PAGE_CACHE && name !== ASSET_CACHE) {
                await caches.delete(name);
            }
        }

        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'RECONCILE_OFFLINE_FUEL' && event.source
        && new URL(event.source.url).origin === self.location.origin
        && typeof event.data.ownerId === 'string' && typeof event.data.flightPlanKey === 'string') {
        event.waitUntil(serialize(() => synchronize(new Response(null, {
            headers: { 'X-Offline-Fuel-Owner': event.data.ownerId, 'X-Offline-Fuel-Key': event.data.flightPlanKey },
        }))));
        return;
    }

    if (event.data?.type !== 'PREPARE_OFFLINE_FUEL' || !event.ports[0] || !event.source) {
        return;
    }

    const url = new URL(event.source.url);

    if (!isCalculator(url)) {
        event.ports[0].postMessage({ ready: false });
        return;
    }

    event.waitUntil(prepare(url.href)
        .then((result) => event.ports[0].postMessage(result))
        .catch(() => event.ports[0].postMessage({ ready: false })));
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (event.request.method === 'GET' && event.request.mode === 'navigate' && isCalculator(url)) {
        event.respondWith(calculatorResponse(event.request));
        return;
    }

    if (event.request.method === 'GET' && url.pathname.startsWith('/build/')) {
        event.respondWith((async () => {
            try {
                const cached = await (await caches.open(ASSET_CACHE)).match(event.request);

                if (cached) {
                    return cached;
                }
            } catch {
                // Use the network when browser storage is unavailable.
            }

            return fetch(event.request);
        })());
        return;
    }

    event.respondWith(fetch(event.request).then(async (response) => {
        await serialize(() => synchronize(response)).catch(() => {});
        return response;
    }));
});
