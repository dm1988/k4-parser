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

const makeButton = (target, label, status) => {
    let clickListener;

    return {
        dataset: {
            copyLabel: label,
            copyStatus: status,
            copyTarget: target,
        },
        addEventListener(event, listener) {
            if (event === 'click') {
                clickListener = listener;
            }
        },
        async click() {
            await clickListener();
        },
    };
};

const wait = (milliseconds) => new Promise((resolve) => {
    setTimeout(resolve, milliseconds);
});

test('the copy status remains visible when a button is reused after three copy actions', async (context) => {
    const originalDocument = Object.getOwnPropertyDescriptor(globalThis, 'document');
    const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    const originalWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');

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

    const buttons = [
        makeButton('departure-output', 'Departure', 'departure-status'),
        makeButton('destination-output', 'Destination', 'destination-status'),
        makeButton('alternate-output', 'Alternate', 'alternate-status'),
    ];
    const elements = new Map([
        ['departure-output', { textContent: 'KCVG' }],
        ['destination-output', { textContent: 'KJFK' }],
        ['alternate-output', { textContent: 'KEWR' }],
        ['departure-status', { classList: new FakeClassList(['opacity-0']), textContent: '' }],
        ['destination-status', { classList: new FakeClassList(['opacity-0']), textContent: '' }],
        ['alternate-status', { classList: new FakeClassList(['opacity-0']), textContent: '' }],
    ]);

    Object.defineProperties(globalThis, {
        document: {
            configurable: true,
            value: {
                getElementById: (id) => elements.get(id) ?? null,
                querySelectorAll: () => buttons,
            },
        },
        navigator: {
            configurable: true,
            value: {
                clipboard: {
                    writeText: async () => {},
                },
            },
        },
        window: {
            configurable: true,
            value: globalThis,
        },
    });

    initializeFlightReleaseCopyButtons();

    await buttons[0].click();
    await buttons[1].click();
    await buttons[2].click();
    await buttons[0].click();
    await wait(100);

    const departureStatus = elements.get('departure-status');

    assert.equal(departureStatus.textContent, 'Departure copied.');
    assert.equal(departureStatus.classList.contains('opacity-100'), true);
    assert.equal(departureStatus.classList.contains('opacity-0'), false);
});
