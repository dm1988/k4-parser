import assert from 'node:assert/strict';
import test from 'node:test';

const themeModuleUrl = new URL('../../resources/js/theme.js', import.meta.url);
let moduleImportNumber = 0;

function createBrowserEnvironment({ storage = new Map(), systemPrefersDark }) {
    const documentListeners = new Map();
    const selectors = [{ value: '' }, { value: '' }];
    const classes = new Set();

    globalThis.localStorage = {
        getItem(key) {
            return storage.get(key) ?? null;
        },
        setItem(key, value) {
            storage.set(key, value);
        },
    };

    globalThis.window = {
        matchMedia() {
            return {
                matches: systemPrefersDark,
                addEventListener() {},
            };
        },
    };

    globalThis.document = {
        documentElement: {
            classList: {
                contains(className) {
                    return classes.has(className);
                },
                toggle(className, force) {
                    if (force) {
                        classes.add(className);
                    } else {
                        classes.delete(className);
                    }
                },
            },
            style: {},
        },
        addEventListener(eventName, listener) {
            documentListeners.set(eventName, listener);
        },
        querySelectorAll() {
            return selectors;
        },
    };

    return {
        classes,
        selectors,
        storage,
        selectTheme(theme) {
            documentListeners.get('change')({
                target: {
                    matches: (selector) => selector === '[data-theme-selector]',
                    value: theme,
                },
            });
        },
        navigate() {
            let swapCallback;

            documentListeners.get('livewire:navigating')({
                detail: {
                    onSwap(callback) {
                        swapCallback = callback;
                    },
                },
            });

            classes.delete('dark');
            document.documentElement.style.colorScheme = 'light';
            selectors.forEach((selector) => {
                selector.value = '';
            });

            swapCallback();

            const themeAfterSwap = {
                isDark: classes.has('dark'),
                colorScheme: document.documentElement.style.colorScheme,
            };

            documentListeners.get('livewire:navigated')();

            return themeAfterSwap;
        },
    };
}

async function loadThemeModule() {
    moduleImportNumber += 1;
    await import(`${themeModuleUrl.href}?test=${moduleImportNumber}`);
}

test.afterEach(() => {
    delete globalThis.document;
    delete globalThis.localStorage;
    delete globalThis.window;
});

test('it resolves system mode from the emulated operating system preference', async (testContext) => {
    for (const [systemPrefersDark, expectedColorScheme] of [[true, 'dark'], [false, 'light']]) {
        await testContext.test(`system ${expectedColorScheme} mode`, async () => {
            const environment = createBrowserEnvironment({
                storage: new Map([['theme', 'system']]),
                systemPrefersDark,
            });

            await loadThemeModule();

            assert.equal(environment.classes.has('dark'), systemPrefersDark);
            assert.equal(document.documentElement.style.colorScheme, expectedColorScheme);
            assert.deepEqual(environment.selectors.map((selector) => selector.value), ['system', 'system']);
        });
    }
});

test('it persists an explicit theme that overrides the operating system preference', async (testContext) => {
    for (const [explicitTheme, systemPrefersDark] of [['light', true], ['dark', false]]) {
        await testContext.test(`${explicitTheme} overrides the system preference`, async () => {
            const storage = new Map([['theme', 'system']]);
            const environment = createBrowserEnvironment({ storage, systemPrefersDark });

            await loadThemeModule();
            environment.selectTheme(explicitTheme);

            assert.equal(storage.get('theme'), explicitTheme);
            assert.equal(environment.classes.has('dark'), explicitTheme === 'dark');
            assert.equal(document.documentElement.style.colorScheme, explicitTheme);

            const reloadedEnvironment = createBrowserEnvironment({ storage, systemPrefersDark });

            await loadThemeModule();

            assert.equal(reloadedEnvironment.classes.has('dark'), explicitTheme === 'dark');
            assert.equal(document.documentElement.style.colorScheme, explicitTheme);
            assert.deepEqual(
                reloadedEnvironment.selectors.map((selector) => selector.value),
                [explicitTheme, explicitTheme],
            );
        });
    }
});

test('it reapplies the stored theme while Livewire swaps pages', async () => {
    const environment = createBrowserEnvironment({
        storage: new Map([['theme', 'dark']]),
        systemPrefersDark: false,
    });

    await loadThemeModule();

    const themeAfterSwap = environment.navigate();

    assert.deepEqual(themeAfterSwap, {
        isDark: true,
        colorScheme: 'dark',
    });
    assert.equal(environment.classes.has('dark'), true);
    assert.equal(document.documentElement.style.colorScheme, 'dark');
    assert.deepEqual(environment.selectors.map((selector) => selector.value), ['dark', 'dark']);
});
