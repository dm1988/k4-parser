import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createCrewBidRefresh } from '../../resources/js/crew-bid-refresh.js';

test('progress updates queued and running states then stops at completion without navigating', async () => {
    const calls = [];
    const states = ['running', 'completed'];
    const progress = createCrewBidRefresh({ url: '/private/status', state: 'queued', message: 'Queued' }, async (url, options) => {
        calls.push({ url, options });
        const state = states.shift();
        return { ok: true, status: 200, json: async () => ({ state, message: state === 'running' ? 'Reading' : 'Updated' }) };
    });
    try {
        await progress.check();
        assert.equal(progress.state, 'running');
        assert.equal(progress.message, 'Reading');
        await progress.check();
        assert.equal(progress.state, 'completed');
        assert.equal(progress.message, 'Updated');
        await progress.check();
        assert.equal(calls.length, 2);
        assert.equal(calls[0].url, '/private/status');
        assert.equal(calls[0].options.cache, 'no-store');
        assert.equal(calls[0].options.credentials, 'same-origin');
        assert.deepEqual(calls[0].options.headers, { Accept: 'application/json' });
    } finally {
        progress.destroy();
    }
});

test('temporary status errors preserve progress and recover on the next check', async () => {
    let calls = 0;
    const progress = createCrewBidRefresh({ url: '/status', state: 'running', message: 'Reading' }, async () => {
        if (++calls === 1) throw new Error('Offline');
        return { ok: true, status: 200, json: async () => ({ state: 'failed', message: 'The global report is missing choice ranks.' }) };
    });
    try {
        await progress.check();
        assert.equal(progress.connectionError, true);
        assert.equal(progress.state, 'running');
        assert.equal(progress.message, 'Reading');
        await progress.check();
        assert.equal(progress.connectionError, false);
        assert.equal(progress.state, 'failed');
        assert.equal(progress.message, 'The global report is missing choice ranks.');
    } finally {
        progress.destroy();
    }
});

test('expired or unauthorized status stops polling and offers a recoverable message', async () => {
    let calls = 0;
    const progress = createCrewBidRefresh({ url: '/status', state: 'queued', message: 'Queued' }, async () => {
        calls++;
        return { ok: false, status: 404 };
    });
    await progress.check();
    await progress.check();
    assert.equal(calls, 1);
    assert.equal(progress.state, 'failed');
    assert.match(progress.message, /Open this page again/);
    progress.destroy();
});

test('destroy aborts an outstanding request and prevents later DOM updates or polls', async () => {
    let finish;
    let signal;
    let calls = 0;
    const progress = createCrewBidRefresh({ url: '/status', state: 'queued', message: 'Queued' }, async (_url, options) => {
        calls++;
        signal = options.signal;
        return new Promise(resolve => { finish = resolve; });
    });
    const pending = progress.check();
    progress.destroy();
    assert.equal(signal.aborted, true);
    finish({ ok: true, status: 200, json: async () => ({ state: 'completed', message: 'Done' }) });
    await pending;
    await progress.check();
    assert.equal(calls, 1);
    assert.equal(progress.state, 'queued');
});
