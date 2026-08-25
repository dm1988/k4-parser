export default function initializeReleaseSummaryScroll() {
    if (window.releaseSummaryScrollInitialized) {
        return;
    }

    window.releaseSummaryScrollInitialized = true;

    window.addEventListener('scroll-to-release-summary', () => {
        window.requestAnimationFrame(() => {
            document.getElementById('release-summary')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });
    });
}
