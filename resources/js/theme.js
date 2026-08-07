const validThemes = new Set(['light', 'dark', 'system']);
const systemDarkMode = window.matchMedia('(prefers-color-scheme: dark)');

function storedTheme() {
    try {
        const theme = localStorage.getItem('theme');

        return validThemes.has(theme) ? theme : 'system';
    } catch {
        return 'system';
    }
}

function persistTheme(theme) {
    try {
        localStorage.setItem('theme', theme);
    } catch {
        // The selected theme still applies for the current page when storage is unavailable.
    }
}

function applyTheme(theme) {
    const isDarkMode = theme === 'dark' || (theme === 'system' && systemDarkMode.matches);

    document.documentElement.classList.toggle('dark', isDarkMode);
    document.documentElement.style.colorScheme = isDarkMode ? 'dark' : 'light';

    document.querySelectorAll('[data-theme-selector]').forEach((selector) => {
        selector.value = theme;
    });
}

function initializeThemeSelectors() {
    applyTheme(storedTheme());

    document.addEventListener('change', (event) => {
        if (! event.target.matches('[data-theme-selector]')) {
            return;
        }

        const theme = event.target.value;

        if (! validThemes.has(theme)) {
            return;
        }

        persistTheme(theme);
        applyTheme(theme);
    });

    const handleSystemThemeChange = () => {
        if (storedTheme() === 'system') {
            applyTheme('system');
        }
    };

    if (typeof systemDarkMode.addEventListener === 'function') {
        systemDarkMode.addEventListener('change', handleSystemThemeChange);
    } else {
        systemDarkMode.addListener(handleSystemThemeChange);
    }
}

initializeThemeSelectors();
