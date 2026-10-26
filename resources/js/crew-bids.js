export function shouldWarnBeforeLeaving(root, pending) {
    return Boolean(root && (pending || root.dataset.dirty === 'true'));
}

export async function copyBidText(field, status, clipboard) {
    try {
        await clipboard.writeText(field.value);
        status.textContent = 'Copied';
    } catch {
        field.focus();
        field.select();
        status.textContent = 'Copy unavailable. Select and copy the list above.';
    }
}

export default function initializeCrewBids() {
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
        const field = document.getElementById('bid-copy-text');
        const status = button.parentElement.querySelector('[data-bid-copy-status]');
        await copyBidText(field, status, navigator.clipboard);
    });
}
