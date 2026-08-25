import assert from 'node:assert/strict';
import test from 'node:test';

import initializeReleaseSummaryScroll from '../../resources/js/release-summary-scroll.js';

test('a successful flight-plan event scrolls to the release summary anchor', (context) => {
    const originalDocument = Object.getOwnPropertyDescriptor(globalThis, 'document');
    const originalWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');
    const listeners = new Map();
    const scrollCalls = [];

    context.after(() => {
        for (const [property, descriptor] of Object.entries({
            document: originalDocument,
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
            value: {
                getElementById(id) {
                    return id === 'release-summary'
                        ? { scrollIntoView: (options) => scrollCalls.push(options) }
                        : null;
                },
            },
        },
        window: {
            configurable: true,
            value: {
                addEventListener(eventName, listener) {
                    listeners.set(eventName, listener);
                },
                requestAnimationFrame(callback) {
                    callback();
                },
            },
        },
    });

    initializeReleaseSummaryScroll();
    initializeReleaseSummaryScroll();
    listeners.get('scroll-to-release-summary')();

    assert.equal(listeners.size, 1);
    assert.deepEqual(scrollCalls, [{ behavior: 'smooth', block: 'start' }]);
});
