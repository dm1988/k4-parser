import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createSubmittedBidComparison } from '../../resources/js/submitted-bid-comparison.js';

test('completion updates comparison data once without navigation or changing draft state', async () => {
    let completions = 0;
    const states = ['running', 'completed'];
    const progress = createSubmittedBidComparison({ url: '/private/status', state: 'queued', message: 'Queued' }, async () => { completions++; }, async () => {
        const state = states.shift();
        return { ok: true, status: 200, json: async () => ({ state, message: state, finished_at: state === 'completed' ? '2026-11-17 16:00:00 UTC' : null }) };
    });
    try {
        progress.init();
        await progress.check();
        assert.equal(progress.state, 'running');
        assert.equal(completions, 0);
        await progress.check();
        assert.equal(progress.state, 'completed');
        assert.equal(completions, 1);
        assert.equal(progress.finishedAt, '2026-11-17 16:00:00 UTC');
    } finally {
        progress.destroy();
    }
});

test('status failures retain the last comparison and recover without a page reload', async () => {
    let calls = 0;
    let completions = 0;
    const progress = createSubmittedBidComparison({ url: '/status', state: 'queued', message: 'Queued' }, async () => { completions++; }, async () => {
        if (++calls === 1) throw new Error('offline');
        return { ok: true, status: 200, json: async () => ({ state: 'failed', message: 'Last check is unchanged' }) };
    });
    try {
        progress.init();
        await progress.check();
        assert.equal(progress.connectionError, true);
        assert.equal(progress.state, 'queued');
        await progress.check();
        assert.equal(progress.connectionError, false);
        assert.equal(progress.state, 'failed');
        assert.equal(completions, 1);
    } finally {
        progress.destroy();
    }
});

test('credentials clear after queue submission and are never retained in progress state', async () => {
    const OriginalFormData = globalThis.FormData;
    globalThis.FormData = class { constructor(form) { this.token = form.token; } };
    const inputs = [{ value: '900000' }, { value: 'synthetic-password' }];
    let sent;
    const progress = createSubmittedBidComparison({}, async () => {}, async (url, options) => {
        sent = { url, options };
        return { ok: true, status: 202, json: async () => ({ url: '/status', state: 'queued', message: 'Queued' }) };
    });
    try {
        await progress.start({ action: '/queue', token: 'csrf', querySelectorAll: () => inputs });
        assert.equal(sent.url, '/queue');
        assert.equal(sent.options.method, 'POST');
        assert.equal(sent.options.credentials, 'same-origin');
        assert.equal(sent.options.body.token, 'csrf');
        assert.deepEqual(inputs.map(input => input.value), ['', '']);
        assert.equal(progress.state, 'queued');
        assert.equal(progress.busy, false);
        assert.doesNotMatch(JSON.stringify(progress), /synthetic-password|900000/);
    } finally {
        progress.destroy();
        globalThis.FormData = OriginalFormData;
    }
});

test('closing the dialog cancels polling and ignores a late completion', async () => {
    let finish;
    let completions = 0;
    const progress = createSubmittedBidComparison({ url: '/status', state: 'queued' }, async () => { completions++; }, () => new Promise(resolve => { finish = resolve; }));
    progress.init();
    const pending = progress.check();
    progress.destroy();
    finish({ ok: true, status: 200, json: async () => ({ state: 'completed', message: 'Updated' }) });
    await pending;
    assert.equal(completions, 0);
    assert.equal(progress.state, 'queued');
});
