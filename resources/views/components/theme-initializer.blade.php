<script data-theme-initializer>
    (() => {
        const systemDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
        let theme = 'system';

        try {
            const storedTheme = localStorage.getItem('theme');

            if (['light', 'dark', 'system'].includes(storedTheme)) {
                theme = storedTheme;
            }
        } catch {
            // The system preference remains the safe default when storage is unavailable.
        }

        const isDarkMode = theme === 'dark' || (theme === 'system' && systemDarkMode);

        document.documentElement.classList.toggle('dark', isDarkMode);
        document.documentElement.style.colorScheme = isDarkMode ? 'dark' : 'light';
    })();
</script>
