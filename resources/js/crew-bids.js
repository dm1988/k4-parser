export function shouldWarnBeforeLeaving(root, pending) {
    return Boolean(root && (pending || root.dataset.dirty === 'true' || root.dataset.preferencesDirty === 'true'));
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

export function createCrewBidsHeader({ ResizeObserverClass = globalThis.ResizeObserver } = {}) {
    let observer;
    let destroyed = false;

    return {
        filtersOpen: false,
        init() {
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
