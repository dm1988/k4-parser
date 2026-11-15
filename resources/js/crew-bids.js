export function shouldWarnBeforeLeaving(root, pending) {
    return Boolean(root && (pending || root.dataset.dirty === 'true' || root.dataset.preferencesDirty === 'true'));
}

export function createBidPayMenu({ getWorkspace = () => window.Livewire.getByName('crew-bids')[0] } = {}) {
    return {
        open: false,
        openPayAdjustments(event, trigger) {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const workspace = getWorkspace();
            if (!workspace?.packageId) return;

            event.preventDefault();
            this.open = false;
            trigger.focus();
            return workspace.openPayEditor();
        },
    };
}

export async function copyBidText(field, status, clipboard) {
    try {
        await clipboard.writeText(field.value);
        status.textContent = 'Copied';
    } catch {
        field.focus();
        field.select();
        status.textContent = 'Copy unavailable. Select and copy the text above.';
    }
}

export function createBidAction() {
    return {
        pending: false,
        async run(action) {
            if (this.pending) return;
            this.pending = true;
            try {
                return await action();
            } catch {
                this.$dispatch('bid-action-failed');
            } finally {
                this.pending = false;
            }
        },
    };
}

export function createBidSaveStatus({ now = Date.now } = {}) {
    let stopIntercepting;

    return {
        pending: 0,
        failed: false,
        checkedAt: now(),
        hasUnsavedChanges() {
            return this.failed || this.$wire.$get('dirty') || this.$wire.$dirty(['goals', 'daysOff', 'startCity']);
        },
        saveState() {
            if (this.pending > 0) return 'saving';
            if (this.hasUnsavedChanges()) return 'unsaved';
            return this.$wire.lastSavedAt ? 'saved' : 'empty';
        },
        saveLabel() {
            return { saved: 'Local: saved', empty: 'Local: not saved', saving: 'Local: saving', unsaved: 'Local: unsaved' }[this.saveState()];
        },
        saveBadgeLabel() {
            return { saved: 'Saved', empty: 'Not saved', saving: 'Saving', unsaved: 'Unsaved' }[this.saveState()];
        },
        cloudState(submissionState) {
            const localState = this.saveState();
            if (localState === 'saving' || localState === 'unsaved' || submissionState === 'changed') return 'pending';
            if (localState === 'saved' && submissionState === 'matched') return 'synced';
            return 'unconfirmed';
        },
        saveHelp() {
            return {
                saved: 'Your bid list and preferences are saved in this app.',
                empty: 'Add a line or choose preferences to save your local workspace.',
                saving: 'Waiting for the request to finish.',
                unsaved: 'Resolve any validation or conflict message, then retry the local save.',
            }[this.saveState()];
        },
        saveDescription() {
            const savedAt = Date.parse(this.$wire.lastSavedAt);
            let lastSaved = 'No saved changes yet';
            if (Number.isFinite(savedAt)) {
                const seconds = Math.max(0, Math.floor((this.checkedAt - savedAt) / 1000));
                const [amount, unit] = seconds < 3600 ? [Math.floor(seconds / 60), 'minute']
                    : seconds < 86400 ? [Math.floor(seconds / 3600), 'hour'] : [Math.floor(seconds / 86400), 'day'];
                lastSaved = seconds < 60 ? 'Last saved just now' : `Last saved ${amount} ${unit}${amount === 1 ? '' : 's'} ago`;
            }
            if (this.saveState() === 'saving') return `Saving… ${lastSaved}`;
            if (this.saveState() === 'unsaved') return `Unsaved changes. ${lastSaved}`;
            return lastSaved;
        },
        savedTimestamp() {
            const savedAt = Date.parse(this.$wire.lastSavedAt);
            return Number.isFinite(savedAt) ? new Date(savedAt).toISOString().slice(0, 19).replace('T', ' ') + ' UTC' : null;
        },
        refreshTimestamp() {
            this.checkedAt = now();
        },
        initSaveStatus() {
            stopIntercepting = this.$wire.interceptMessage(({ onSend, onSuccess, onError, onFailure, onFinish }) => {
                let sent = false;
                let previousSave;
                let reloading = false;
                onSend(({ payload }) => {
                    sent = true;
                    this.pending++;
                    previousSave = `${this.$wire.packageId}:${this.$wire.revision}`;
                    reloading = payload.calls.some((call) => call.method === 'reloadSaved');
                });
                onSuccess(({ onSync }) => onSync(() => {
                    if (reloading || previousSave !== `${this.$wire.packageId}:${this.$wire.revision}`) this.failed = false;
                    this.refreshTimestamp();
                }));
                onError(() => { this.failed = true; });
                onFailure(() => { this.failed = true; });
                onFinish(() => { if (sent) this.pending = Math.max(0, this.pending - 1); });
            });
        },
        destroySaveStatus() {
            stopIntercepting?.();
        },
    };
}

export function createCrewBidsHeader({ ResizeObserverClass = globalThis.ResizeObserver } = {}) {
    let observer;
    let destroyed = false;

    return {
        ...createBidSaveStatus(),
        filtersOpen: false,
        statusOpen: false,
        listReturnFocus: null,
        toggleStatus() {
            if (this.statusOpen) {
                this.closeStatus(true);
                return;
            }
            this.statusOpen = true;
            this.refreshTimestamp();
            this.$nextTick(() => {
                if (!destroyed && this.statusOpen) this.$refs.statusPanel.focus({ preventScroll: true });
            });
        },
        closeStatus(restoreFocus = false) {
            if (!this.statusOpen) return;
            this.statusOpen = false;
            if (restoreFocus) this.$refs.statusTrigger.focus({ preventScroll: true });
        },
        openList(event) {
            this.closeStatus();
            this.listReturnFocus = event?.currentTarget ?? null;
            this.$wire.showList = true;
        },
        closeList() {
            if (!this.$wire.showList) return;
            this.$wire.showList = false;
            this.$nextTick(() => {
                const target = this.listReturnFocus?.isConnected ? this.listReturnFocus : this.$refs.listTrigger;
                target?.focus({ preventScroll: true });
                this.listReturnFocus = null;
            });
        },
        init() {
            this.initSaveStatus();
            this.$nextTick(() => {
                if (destroyed) return;
                const header = this.$refs.workspaceHeader;
                const updateHeight = () => {
                    if (destroyed) return;
                    this.$el.style.setProperty('--crew-bids-header-height', `${Math.ceil(header.getBoundingClientRect().height)}px`);
                };
                updateHeight();
                observer = new ResizeObserverClass(updateHeight);
                observer.observe(header);
            });
        },
        destroy() {
            this.destroySaveStatus();
            destroyed = true;
            observer?.disconnect();
        },
    };
}

export function createBidLineCard({ selectionText = () => globalThis.getSelection?.()?.toString() ?? '' } = {}) {
    return {
        inspected: '',
        inspectedDate: '',
        selectDate(day) {
            if (!day) return;
            this.inspected = day.dataset.dateDetail;
            this.inspectedDate = day.dataset.date;
        },
        inspectDate(event) {
            const day = event.target.closest('[data-date-detail]');
            if (day) this.selectDate(day);
            else if (!this.inspectedDate) this.selectDate(this.$refs.calendar.querySelector('[data-date-detail]'));
        },
        clearDate() {
            this.inspected = '';
            this.inspectedDate = '';
            this.$refs.calendar.focus();
        },
        handleDateKey(event) {
            if (event.altKey || event.ctrlKey || event.metaKey) return;
            if (event.key === 'Escape') {
                if (!this.inspectedDate) return;
                event.preventDefault();
                this.clearDate();
                return;
            }
            const days = [...this.$refs.calendar.querySelectorAll('[data-date-detail]')];
            if (!days.length) return;
            const current = days.findIndex((day) => day.dataset.date === this.inspectedDate);
            let next;
            switch (event.key) {
                case 'ArrowRight': next = Math.min(current + 1, days.length - 1); break;
                case 'ArrowLeft': next = current < 0 ? days.length - 1 : Math.max(current - 1, 0); break;
                case 'Home': next = 0; break;
                case 'End': next = days.length - 1; break;
                default: return;
            }
            event.preventDefault();
            this.selectDate(days[next]);
        },
        toggleFromCard(event) {
            if (event.defaultPrevented || selectionText() || event.target.closest('a, button, input, select, textarea, summary, details, [role="button"], [contenteditable]')) return;

            this.$refs.primaryDetails.open = !this.$refs.primaryDetails.open;
        },
    };
}

export function createBidGoalPreferences(labels) {
    return {
        labels,
        moveGoal(goal, position) {
            const goals = [...this.$wire.goals];
            const current = goals.indexOf(goal);
            if (current === -1 || !Number.isInteger(position) || position < 0 || position >= goals.length || position === current) return;
            goals.splice(current, 1);
            goals.splice(position, 0, goal);
            this.$wire.goals = goals;
            this.$dispatch('bid-goals-changed');
        },
        sortGoal(goal, list) {
            const rows = [...list.children].filter((row) => row.dataset.goal);
            const position = rows.findIndex((row) => row.dataset.goal === goal);
            if (position === -1 || rows.length !== this.$wire.goals.length) return;

            const originalRows = this.$wire.goals.map((current) => rows.find((row) => row.dataset.goal === current));
            if (originalRows.some((row) => !row)) return;
            // Restore the order Alpine knows before its keyed loop applies the new order.
            originalRows.forEach((row) => list.append(row));
            this.moveGoal(goal, position);
        },
    };
}

export function createBidTripDetails() {
    const tabs = ['timeline', 'layovers', 'metadata'];

    return {
        tab: 'timeline',
        handleTabKey(event) {
            if (event.altKey || event.ctrlKey || event.metaKey) return;
            const current = tabs.indexOf(this.tab);
            let next;
            switch (event.key) {
                case 'ArrowRight': next = (current + 1) % tabs.length; break;
                case 'ArrowLeft': next = (current + tabs.length - 1) % tabs.length; break;
                case 'Home': next = 0; break;
                case 'End': next = tabs.length - 1; break;
                default: return;
            }
            event.preventDefault();
            this.tab = tabs[next];
            this.$nextTick(() => this.$refs[`${this.tab}Tab`].focus());
        },
    };
}

export default function initializeCrewBids() {
    const registerComponents = () => {
        window.Alpine.data('crewBidsHeader', () => createCrewBidsHeader());
        window.Alpine.data('bidLineCard', () => createBidLineCard());
        window.Alpine.data('bidTripDetails', createBidTripDetails);
        window.Alpine.data('bidGoalPreferences', createBidGoalPreferences);
        window.Alpine.data('bidAction', createBidAction);
        window.Alpine.data('bidPayMenu', createBidPayMenu);
    };
    if (window.Alpine) {
        registerComponents();
    } else {
        document.addEventListener('alpine:init', registerComponents, { once: true });
    }
    let pending = 0;
    let failed = false;
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ succeed, fail }) => {
            if (!document.querySelector('[data-crew-bids]')) return;
            pending++;
            succeed(() => {
                pending = Math.max(0, pending - 1);
                failed = false;
                const error = document.querySelector('[data-bid-network-error]');
                if (error) error.hidden = true;
            });
            fail(() => {
                pending = Math.max(0, pending - 1);
                failed = true;
                const error = document.querySelector('[data-bid-network-error]');
                if (error) error.hidden = false;
            });
        });
    });
    window.addEventListener('beforeunload', (event) => {
        if (shouldWarnBeforeLeaving(document.querySelector('[data-crew-bids]'), pending > 0 || failed)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-bid-copy]');
        if (!button) return;
        const field = document.getElementById(button.dataset.bidCopyTarget || 'bid-copy-text');
        const status = button.parentElement.querySelector('[data-bid-copy-status]');
        await copyBidText(field, status, navigator.clipboard);
    });
}
