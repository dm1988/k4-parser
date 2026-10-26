export function createFlightDelayForm({ documentObject = document, windowObject = window } = {}) {
    return {
        busy: false,
        clientError: '',
        editVersion: 0,
        allowedClick: null,
        allowNavigation: false,

        get fields() {
            return [...this.$root.querySelectorAll('[data-delay-input]')];
        },

        get dirty() {
            void this.editVersion;
            const saved = this.$wire.savedInputs;
            return this.fields.some((field) => field.validity?.badInput
                || field.value !== (saved[field.dataset.delayInput] ?? ''));
        },

        init() {
            this.clickListener = (event) => this.guardClick(event);
            this.navigateListener = (event) => this.guardNavigation(event);
            this.unloadListener = (event) => {
                if (this.dirty) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            documentObject.addEventListener('click', this.clickListener, true);
            documentObject.addEventListener('livewire:navigate', this.navigateListener);
            windowObject.addEventListener('beforeunload', this.unloadListener);
        },

        destroy() {
            documentObject.removeEventListener('click', this.clickListener, true);
            documentObject.removeEventListener('livewire:navigate', this.navigateListener);
            windowObject.removeEventListener('beforeunload', this.unloadListener);
        },

        inspectInput(event) {
            this.editVersion++;
            this.clientError = '';
            if (event.target.validity?.badInput) {
                event.stopImmediatePropagation();
                this.clientError = 'Complete the UTC date and time, or clear the field.';
            }
        },

        validateClient(event) {
            if (this.fields.some((field) => field.validity?.badInput)) {
                event?.preventDefault();
                event?.stopImmediatePropagation();
                this.clientError = 'Complete the UTC date and time, or clear the field.';
                return false;
            }
            return true;
        },

        async flush() {
            if (this.busy || !this.validateClient()) {
                return false;
            }
            this.busy = true;
            this.clientError = '';
            try {
                for (const field of this.fields) {
                    this.$wire.$set('inputs.' + field.dataset.delayInput, field.value, false);
                }
                return await this.$wire.savePending();
            } catch {
                this.clientError = 'Could not save. Your input is still here; retry before leaving.';
                return false;
            } finally {
                this.busy = false;
            }
        },

        async generateReport() {
            if (await this.flush()) {
                this.busy = true;
                try {
                    await this.$wire.generateReport();
                } catch {
                    this.clientError = 'Could not generate the report. Retry when the connection is restored.';
                } finally {
                    this.busy = false;
                }
            }
        },

        async guardClick(event) {
            const target = event.target?.closest?.('a[href], button');
            if (!target || this.$root.contains(target) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }
            if (this.allowedClick === target) {
                this.allowedClick = null;
                return;
            }
            const action = target.getAttribute('wire:click') ?? '';
            const leavesForm = target.matches('a[href]') && target.target !== '_blank'
                || /^(selectTask\(|clearResults\b|extractAnotherFlightPlan\b)/.test(action);
            if (!leavesForm || !this.dirty) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            if (await this.flush()) {
                this.allowedClick = target;
                target.click();
            }
        },

        async guardNavigation(event) {
            if (this.allowNavigation || !this.dirty) {
                return;
            }
            event.preventDefault();
            if (await this.flush()) {
                this.allowNavigation = true;
                if (event.detail.history) {
                    windowObject.location.replace(event.detail.url.toString());
                } else {
                    windowObject.Livewire.navigate(event.detail.url.toString());
                }
            }
        },
    };
}

export default function initializeFlightDelayForm() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('flightDelayForm', () => createFlightDelayForm());
    });
}
