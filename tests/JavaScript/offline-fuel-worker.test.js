import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const origin = 'https://example.test';
const key = '01J00000000000000000000000';
const pageUrl = `${origin}/flight-plan-brief/fuel-score/${key}`;
const assetUrls = [`${origin}/build/assets/calculator.js`, `${origin}/build/assets/app.css`, `${origin}/build/assets/shared.js`];
const source = await readFile(new URL('../../public/offline-fuel-worker.js', import.meta.url), 'utf8');

const cacheStorage = () => {
    const stores = new Map();
    const address = (request) => typeof request === 'string' ? request : request.url;

    return {
        async open(name) {
            if (!stores.has(name)) {
                stores.set(name, new Map());
            }

            const entries = stores.get(name);
            return {
                async match(request) { return entries.get(address(request))?.clone(); },
                async put(request, response) { entries.set(address(request), response.clone()); },
                async delete(request) { return entries.delete(address(request)); },
                async keys() { return [...entries.keys()].map((url) => ({ url })); },
            };
        },
        async keys() { return [...stores.keys()]; },
        async delete(name) { return stores.delete(name); },
    };
};

const page = (owner = '1', assets = assetUrls) => new Response('<main>Flight CKS241 and release data</main>', {
    headers: {
        'Content-Type': 'text/html',
        'X-Offline-Fuel-Page': '1',
        'X-Offline-Fuel-Owner': owner,
        'X-Offline-Fuel-Key': key,
        'X-Offline-Fuel-Assets': JSON.stringify(assets),
    },
});

const worker = (caches = cacheStorage()) => {
    const listeners = new Map();
    const notifications = [];
    const network = { handler: (url) => url === pageUrl ? page() : new Response('asset') };
    const context = {
        caches, URL, Response, Promise,
        fetch: (request, options) => Promise.resolve().then(() => network.handler(typeof request === 'string' ? request : request.url, options)),
        self: {
            location: { origin },
            skipWaiting: async () => {},
            clients: {
                claim: async () => {},
                matchAll: async () => [{ url: pageUrl, postMessage: (message) => notifications.push(message) }],
            },
            addEventListener: (name, listener) => listeners.set(name, listener),
        },
    };
    vm.runInNewContext(source, context);

    return {
        caches, network, notifications,
        async reconcile(ownerId, flightPlanKey) {
            let pending;
            listeners.get('message')({
                data: { type: 'RECONCILE_OFFLINE_FUEL', ownerId, flightPlanKey },
                source: { url: `${origin}/flight-plan-brief/overview` },
                waitUntil: (promise) => { pending = promise; },
            });
            await pending;
        },
        async prepare(url = pageUrl) {
            let pending;
            let result;
            listeners.get('message')({
                data: { type: 'PREPARE_OFFLINE_FUEL' }, source: { url },
                ports: [{ postMessage: (value) => { result = value; } }],
                waitUntil: (promise) => { pending = promise; },
            });
            await pending;
            return result;
        },
        async request(url = pageUrl, method = 'GET', mode = 'navigate') {
            let pending;
            listeners.get('fetch')({
                request: { url, method, mode },
                respondWith: (promise) => { pending = promise; },
            });
            return pending;
        },
    };
};

const offline = () => { throw new TypeError('Failed to fetch'); };

test('a restarted worker recovers the release page and every asset after an offline tab reload', async () => {
    const first = worker();
    assert.equal((await first.prepare()).ready, true);
    const restarted = worker(first.caches);
    restarted.network.handler = offline;
    assert.match(await (await restarted.request()).text(), /CKS241/);
    for (const asset of assetUrls) {
        assert.equal(await (await restarted.request(asset, 'GET', 'cors')).text(), 'asset');
    }
    const recovered = await restarted.prepare();
    assert.equal(recovered.ready, true);
    assert.equal(recovered.recovered, true);
});

test('an uncached offline flight gets a useful fallback, without another flight’s data', async () => {
    const instance = worker();
    await instance.prepare();
    instance.network.handler = offline;
    const response = await instance.request(pageUrl.replace(key, '01J00000000000000000000001'));
    assert.equal(response.status, 503);
    assert.match(await response.text(), /No offline copy/);
});

for (const status of [401, 403, 404, 500]) {
    test(`a server ${status} is preserved and cannot resurrect the cached calculator`, async () => {
        const instance = worker();
        await instance.prepare();
        instance.network.handler = () => new Response('denied', { status });
        assert.equal((await instance.request()).status, status);
        instance.network.handler = offline;
        assert.equal((await instance.request()).status, 503);
    });
}

test('a login redirect is returned without an offline fallback and invalidates the release', async () => {
    const instance = worker();
    await instance.prepare();
    const redirect = new Response('login', { headers: { 'X-Offline-Fuel-Owner': '' } });
    Object.defineProperty(redirect, 'redirected', { value: true });
    instance.network.handler = () => redirect;
    assert.equal(await (await instance.request()).text(), 'login');
    instance.network.handler = offline;
    assert.equal((await instance.request()).status, 503);
});

for (const [label, headers] of [
    ['logout', { 'X-Offline-Fuel-Owner': '' }],
    ['account change', { 'X-Offline-Fuel-Owner': '2' }],
    ['release replacement', { 'X-Offline-Fuel-Owner': '1', 'X-Offline-Fuel-Key': 'another-release' }],
    ['clearing results', { 'X-Offline-Fuel-Owner': '1', 'X-Offline-Fuel-Key': '' }],
]) {
    test(`${label} removes the private offline copy and notifies the calculator`, async () => {
        const instance = worker();
        await instance.prepare();
        instance.network.handler = () => new Response('{}', { headers });
        await instance.request(`${origin}/livewire/update`, 'POST', 'cors');
        assert.equal(instance.notifications.at(-1).type, 'OFFLINE_FUEL_INVALIDATED');
        instance.network.handler = offline;
        assert.equal((await instance.request()).status, 503);
    });
}

test('failed assets never publish a partially prepared offline page', async () => {
    const instance = worker();
    instance.network.handler = (url) => url === pageUrl ? page() : new Response('missing', { status: 404 });
    assert.equal((await instance.prepare()).ready, false);
    instance.network.handler = offline;
    assert.equal((await instance.request()).status, 503);
});

test('preparation uses the asset list from the fetched page, including assets from a newer deployment', async () => {
    const instance = worker();
    const updated = [`${origin}/build/assets/new-calculator.js`, `${origin}/build/assets/new.css`];
    instance.network.handler = (url) => url === pageUrl ? page('1', updated) : new Response('new asset');
    assert.equal((await instance.prepare()).ready, true);
    instance.network.handler = offline;
    for (const asset of updated) {
        assert.equal(await (await instance.request(asset, 'GET', 'cors')).text(), 'new asset');
    }
});

test('foreign-origin assets, empty asset lists, and messages from unrelated pages cannot prepare a release', async () => {
    for (const assets of [[], ['https://other.test/build/asset.js'], [`${origin}/login`]]) {
        const instance = worker();
        instance.network.handler = () => page('1', assets);
        assert.equal((await instance.prepare()).ready, false);
    }
    assert.equal((await worker().prepare(`${origin}/dashboard`)).ready, false);
});

test('cache failures do not break successful online requests', async () => {
    const instance = worker({ open: async () => { throw new Error('Storage blocked'); } });
    assert.equal((await instance.prepare()).ready, false);
    assert.equal((await instance.request()).status, 200);
    assert.equal((await instance.request(`${origin}/dashboard`)).status, 200);
    instance.network.handler = offline;
    assert.equal((await instance.request()).status, 503);
});

test('unrelated pages and non-GET requests are never served from the calculator cache', async () => {
    const instance = worker();
    await instance.prepare();
    instance.network.handler = offline;
    await assert.rejects(instance.request(`${origin}/dashboard`), /Failed to fetch/);
    await assert.rejects(instance.request(pageUrl, 'POST'), /Failed to fetch/);
    await assert.rejects(instance.request(`${pageUrl}?other=1`), /Failed to fetch/);
});

test('clearing a release while assets are being prepared prevents publishing stale data', async () => {
    const instance = worker();
    let releaseAsset;
    let startedAsset;
    const started = new Promise((resolve) => { startedAsset = resolve; });
    instance.network.handler = (url) => {
        if (url === pageUrl) {
            return page();
        }
        if (url.endsWith('/livewire/update')) {
            return new Response('{}', { headers: { 'X-Offline-Fuel-Owner': '1', 'X-Offline-Fuel-Key': '' } });
        }
        startedAsset();
        return new Promise((resolve) => { releaseAsset = resolve; });
    };
    const preparing = instance.prepare();
    await started;
    await instance.request(`${origin}/livewire/update`, 'POST', 'cors');
    instance.network.handler = () => new Response('asset');
    releaseAsset(new Response('asset'));
    assert.equal((await preparing).ready, false);
    instance.network.handler = offline;
    assert.equal((await instance.request()).status, 503);
});

test('streamed Livewire mutations invalidate the offline copy through the client message', async () => {
    const instance = worker();
    await instance.prepare();
    await instance.reconcile('1', 'new-release');
    instance.network.handler = offline;
    assert.equal((await instance.request()).status, 503);
    assert.equal(instance.notifications.at(-1).type, 'OFFLINE_FUEL_INVALIDATED');
});
