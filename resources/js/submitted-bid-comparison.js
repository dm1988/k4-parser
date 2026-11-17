import { createCrewBidRefresh } from './crew-bid-refresh.js';

export function createSubmittedBidComparison(initial, completed, request = window.fetch.bind(window)) {
    let poller;
    let submitting;
    let destroyed = false;
    let notified = false;
    return {
        state: initial.state ?? '',
        message: initial.message ?? '',
        finishedAt: initial.finished_at ?? '',
        connectionError: false,
        busy: false,
        init() {
            if (initial.url && ['queued', 'running'].includes(this.state)) this.watch(initial);
        },
        watch(result) {
            poller?.destroy();
            notified = false;
            const polling = createCrewBidRefresh(result, request);
            const check = polling.check;
            polling.check = async () => {
                await check.call(polling);
                if (destroyed) return;
                this.state = polling.state;
                this.message = polling.message;
                this.finishedAt = polling.finishedAt;
                this.connectionError = polling.connectionError;
                if (['completed', 'failed'].includes(this.state) && !notified) {
                    notified = true;
                    try {
                        await completed();
                    } catch {
                        this.connectionError = true;
                    }
                }
            };
            poller = polling;
            poller.init();
        },
        async check() {
            if (poller && ['queued', 'running'].includes(poller.state)) return poller.check();
            try {
                await completed();
                this.connectionError = false;
            } catch {
                this.connectionError = true;
            }
        },
        async start(form) {
            if (this.busy || ['queued', 'running'].includes(this.state)) return;
            this.busy = true;
            this.message = 'Queuing K4 check…';
            this.finishedAt = '';
            submitting = new AbortController();
            const timeout = setTimeout(() => submitting.abort(), 15000);
            try {
                const response = await request(form.action, {
                    method: 'POST', body: new FormData(form), credentials: 'same-origin',
                    headers: { Accept: 'application/json' }, cache: 'no-store', signal: submitting.signal,
                });
                const result = await response.json();
                if (destroyed) return;
                this.message = result.message;
                if (!response.ok) {
                    this.state = 'failed';
                    return;
                }
                this.state = result.state;
                this.connectionError = false;
                this.watch(result);
            } catch {
                if (!destroyed) {
                    this.state = 'failed';
                    this.message = 'K4 check status is unavailable. Reopen Compare with K4 before retrying; My bid list is unchanged.';
                }
            } finally {
                clearTimeout(timeout);
                for (const input of form.querySelectorAll('[name="employee"], [name="portal_password"]')) input.value = '';
                this.busy = false;
            }
        },
        destroy() {
            destroyed = true;
            poller?.destroy();
            submitting?.abort();
        },
    };
}

if (typeof window !== 'undefined') window.createSubmittedBidComparison = createSubmittedBidComparison;
