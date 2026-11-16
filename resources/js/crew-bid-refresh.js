export function createCrewBidRefresh({ url, state, message }, request = window.fetch.bind(window)) {
    let timer;
    let stopped = false;
    let controller;

    return {
        state,
        message,
        connectionError: false,

        init() {
            if (['queued', 'running'].includes(this.state)) {
                timer = setTimeout(() => this.check(), 3000);
            }
        },

        async check() {
            if (stopped || !['queued', 'running'].includes(this.state)) return;
            clearTimeout(timer);
            try {
                controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 10000);
                let response;
                try {
                    response = await request(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    });
                } finally {
                    clearTimeout(timeout);
                }
                if ([401, 403, 404, 410].includes(response.status)) {
                    if (stopped) return;
                    this.connectionError = false;
                    this.state = 'failed';
                    this.message = 'Refresh status is no longer available. Open this page again to check or retry.';
                    return;
                }
                if (!response.ok) throw new Error('Status unavailable');
                const result = await response.json();
                if (stopped) return;
                this.state = result.state;
                this.message = result.message;
                this.connectionError = false;
            } catch {
                if (!stopped) this.connectionError = true;
            } finally {
                if (!stopped && ['queued', 'running'].includes(this.state)) {
                    timer = setTimeout(() => this.check(), 5000);
                }
            }
        },

        destroy() {
            stopped = true;
            clearTimeout(timer);
            controller?.abort();
        },
    };
}

if (typeof window !== 'undefined') window.createCrewBidRefresh = createCrewBidRefresh;
