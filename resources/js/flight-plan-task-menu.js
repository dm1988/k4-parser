const desktopMediaQuery = '(min-width: 1024px)';
const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'details',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

export function createFlightPlanTaskMenu({ documentObject = document, windowObject = window } = {}) {
    return {
        open: false,
        addedBodyScrollLock: false,
        desktopViewport: null,
        viewportChangeListener: null,

        init() {
            this.desktopViewport = windowObject.matchMedia(desktopMediaQuery);
            this.viewportChangeListener = (event) => {
                if (event.matches) {
                    this.closeMenu({ restoreFocus: false });
                }
            };

            if (typeof this.desktopViewport.addEventListener === 'function') {
                this.desktopViewport.addEventListener('change', this.viewportChangeListener);
            } else {
                this.desktopViewport.addListener(this.viewportChangeListener);
            }
        },

        openMenu() {
            if (this.desktopViewport?.matches) {
                return;
            }

            this.open = true;
            this.lockBodyScroll();
            this.afterNextRender(() => this.focusActiveTask());
        },

        selectTask() {
            this.closeMenu({ restoreFocus: false });
        },

        dismissMenu() {
            if (! this.open) {
                return;
            }

            this.closeMenu();
        },

        closeMenu({ restoreFocus = true } = {}) {
            const wasOpen = this.open;

            this.open = false;
            this.unlockBodyScroll();

            if (wasOpen && restoreFocus) {
                this.afterNextRender(() => this.$refs?.trigger?.focus());
            }
        },

        focusActiveTask() {
            const task = this.$refs?.dialog?.querySelector('[data-flight-plan-active-task]')
                ?? this.$refs?.dialog?.querySelector('[data-flight-plan-task-option]');

            task?.focus();
        },

        trapFocus(event) {
            const focusableElements = this.focusableElements();

            if (focusableElements.length === 0) {
                return;
            }

            const activeIndex = focusableElements.indexOf(documentObject.activeElement);
            const nextIndex = event.shiftKey
                ? (activeIndex <= 0 ? focusableElements.length - 1 : activeIndex - 1)
                : (activeIndex === -1 || activeIndex === focusableElements.length - 1 ? 0 : activeIndex + 1);

            focusableElements[nextIndex].focus();
        },

        focusableElements() {
            return [...(this.$refs?.dialog?.querySelectorAll(focusableSelector) ?? [])];
        },

        lockBodyScroll() {
            if (documentObject.body.classList.contains('overflow-y-hidden')) {
                return;
            }

            documentObject.body.classList.add('overflow-y-hidden');
            this.addedBodyScrollLock = true;
        },

        unlockBodyScroll() {
            if (! this.addedBodyScrollLock) {
                return;
            }

            documentObject.body.classList.remove('overflow-y-hidden');
            this.addedBodyScrollLock = false;
        },

        afterNextRender(callback) {
            if (typeof this.$nextTick === 'function') {
                this.$nextTick(callback);

                return;
            }

            callback();
        },

        destroy() {
            if (this.desktopViewport && this.viewportChangeListener) {
                if (typeof this.desktopViewport.removeEventListener === 'function') {
                    this.desktopViewport.removeEventListener('change', this.viewportChangeListener);
                } else {
                    this.desktopViewport.removeListener(this.viewportChangeListener);
                }
            }

            this.open = false;
            this.unlockBodyScroll();
        },
    };
}

export default function initializeFlightPlanTaskMenu() {
    let registered = false;
    const register = () => {
        if (registered) {
            return;
        }

        window.Alpine.data('flightPlanTaskMenu', createFlightPlanTaskMenu);
        registered = true;
    };

    if (window.Alpine) {
        register();

        return;
    }

    document.addEventListener('alpine:init', register, { once: true });
}
