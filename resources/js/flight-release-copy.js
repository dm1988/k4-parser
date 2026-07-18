const copyStatusTimeouts = new WeakMap();

const showCopyStatus = (status, message) => {
    const existingTimeouts = copyStatusTimeouts.get(status);

    if (existingTimeouts) {
        window.clearTimeout(existingTimeouts.fadeTimeout);
        window.clearTimeout(existingTimeouts.clearTimeout);
    }

    status.textContent = message;
    status.classList.remove('opacity-0');
    status.classList.add('opacity-100');

    const fadeTimeout = window.setTimeout(() => {
        status.classList.remove('opacity-100');
        status.classList.add('opacity-0');
    }, 50);

    const clearTimeout = window.setTimeout(() => {
        status.textContent = '';
    }, 3050);

    copyStatusTimeouts.set(status, { fadeTimeout, clearTimeout });
};

const copyButtonText = (button) => {
    const output = document.getElementById(button.dataset.copyTarget);

    if (! output) {
        return null;
    }

    return output.value ?? output.textContent?.trim() ?? '';
};

export default function initializeFlightReleaseCopyButtons() {
    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const status = document.getElementById(button.dataset.copyStatus);

            if (! status) {
                return;
            }

            const text = copyButtonText(button);
            const label = button.dataset.copyLabel ?? 'Value';

            if (! text) {
                showCopyStatus(status, `Unable to copy ${label.toLowerCase()}.`);

                return;
            }

            try {
                await navigator.clipboard.writeText(text);
                showCopyStatus(status, `${label} copied.`);
            } catch {
                showCopyStatus(status, `Unable to copy ${label.toLowerCase()}.`);
            }
        });
    });
}
