import assert from 'node:assert/strict';
import test from 'node:test';

import initializeFlightReleaseCopyButtons from '../../resources/js/flight-release-copy.js';

class FakeClassList {
    constructor(classes = []) {
        this.classes = new Set(classes);
    }

    add(...classes) {
        classes.forEach((className) => this.classes.add(className));
    }

    contains(className) {
        return this.classes.has(className);
    }

    remove(...classes) {
        classes.forEach((className) => this.classes.delete(className));
    }
}

const makeButton = (target, label, status) => ({
    dataset: {
        copyLabel: label,
        copyStatus: status,
        copyTarget: target,
    },
    closest: () => null,
});

const installBrowserGlobals = (context, elements, writeText) => {
    const originalDocument = Object.getOwnPropertyDescriptor(globalThis, 'document');
    const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    const originalWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');
    const listeners = [];
    const fakeDocument = {
        addEventListener(event, listener) {
            if (event === 'click') {
                listeners.push(listener);
            }
        },
        getElementById: (id) => elements.get(id) ?? null,
    };

    context.after(() => {
        for (const [property, descriptor] of Object.entries({
            document: originalDocument,
            navigator: originalNavigator,
            window: originalWindow,
        })) {
            if (descriptor) {
                Object.defineProperty(globalThis, property, descriptor);
            } else {
                delete globalThis[property];
            }
        }
    });

    Object.defineProperties(globalThis, {
        document: {
            configurable: true,
            value: fakeDocument,
        },
        navigator: {
            configurable: true,
            value: {
                clipboard: { writeText },
            },
        },
        window: {
            configurable: true,
            value: globalThis,
        },
    });

    return {
        async click(button) {
            const target = {
                closest: (selector) => selector === '[data-copy-target]' ? button : null,
            };

            await Promise.all(listeners.map((listener) => listener({ target })));
        },
        listeners,
    };
};

test('a copy button inserted after initialization works and repeated copies remain visible', async (context) => {
    const elements = new Map();
    const writes = [];
    const browser = installBrowserGlobals(context, elements, async (value) => writes.push(value));

    initializeFlightReleaseCopyButtons();

    const button = makeButton('departure-output', 'Departure', 'departure-status');
    const status = { classList: new FakeClassList(['opacity-0']), textContent: '' };

    elements.set('departure-output', { textContent: 'PANC' });
    elements.set('departure-status', status);

    await browser.click(button);
    await browser.click(button);

    assert.deepEqual(writes, ['PANC', 'PANC']);
    assert.equal(status.textContent, 'Departure copied.');
    assert.equal(status.classList.contains('opacity-100'), true);
    assert.equal(status.classList.contains('opacity-0'), false);
});

test('initializing twice does not register duplicate delegated listeners', async (context) => {
    const button = makeButton('route-output', 'Route', 'route-status');
    const elements = new Map([
        ['route-output', { value: 'DCT Q139 TEST' }],
        ['route-status', { classList: new FakeClassList(['opacity-0']), textContent: '' }],
    ]);
    let writeCount = 0;
    const browser = installBrowserGlobals(context, elements, async () => {
        writeCount++;
    });

    initializeFlightReleaseCopyButtons();
    initializeFlightReleaseCopyButtons();
    await browser.click(button);

    assert.equal(browser.listeners.length, 1);
    assert.equal(writeCount, 1);
});
