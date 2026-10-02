import assert from 'node:assert/strict';
import test from 'node:test';

import { createFlightPlanTaskMenu } from '../../resources/js/flight-plan-task-menu.js';

function createElement(documentObject) {
    return {
        focusCount: 0,
        focus() {
            this.focusCount += 1;
            documentObject.activeElement = this;
        },
    };
}

function createEnvironment({ desktop = false, bodyInitiallyLocked = false } = {}) {
    const bodyClasses = new Set(bodyInitiallyLocked ? ['overflow-y-hidden'] : []);
    const mediaListeners = new Set();
    const documentObject = {
        activeElement: null,
        body: {
            classList: {
                add(className) {
                    bodyClasses.add(className);
                },
                contains(className) {
                    return bodyClasses.has(className);
                },
                remove(className) {
                    bodyClasses.delete(className);
                },
            },
        },
    };
    const mediaQuery = {
        matches: desktop,
        addEventListener(eventName, listener) {
            assert.equal(eventName, 'change');
            mediaListeners.add(listener);
        },
        removeEventListener(eventName, listener) {
            assert.equal(eventName, 'change');
            mediaListeners.delete(listener);
        },
    };
    const windowObject = {
        matchMedia(query) {
            assert.equal(query, '(min-width: 1024px)');

            return mediaQuery;
        },
    };
    const trigger = createElement(documentObject);
    const closeButton = createElement(documentObject);
    const firstTask = createElement(documentObject);
    const activeTask = createElement(documentObject);
    const dialog = {
        querySelector(selector) {
            if (selector === '[data-flight-plan-active-task]') {
                return activeTask;
            }

            if (selector === '[data-flight-plan-task-option]') {
                return firstTask;
            }

            return null;
        },
        querySelectorAll() {
            return [closeButton, firstTask, activeTask];
        },
    };
    const menu = createFlightPlanTaskMenu({ documentObject, windowObject });

    menu.$nextTick = (callback) => callback();
    menu.$refs = { dialog, trigger };
    menu.init();

    return {
        activeTask,
        bodyClasses,
        closeButton,
        documentObject,
        firstTask,
        mediaListeners,
        menu,
        trigger,
        crossIntoDesktop() {
            mediaQuery.matches = true;
            mediaListeners.forEach((listener) => listener({ matches: true }));
        },
    };
}

test('opening the mobile task menu locks scrolling and focuses the active task', () => {
    const environment = createEnvironment();

    environment.menu.openMenu();

    assert.equal(environment.menu.open, true);
    assert.equal(environment.bodyClasses.has('overflow-y-hidden'), true);
    assert.equal(environment.activeTask.focusCount, 1);
});

test('Escape or the close control dismisses the menu and restores the trigger focus', () => {
    const environment = createEnvironment();

    environment.menu.openMenu();
    environment.menu.dismissMenu();

    assert.equal(environment.menu.open, false);
    assert.equal(environment.bodyClasses.has('overflow-y-hidden'), false);
    assert.equal(environment.trigger.focusCount, 1);
});

test('selecting a task closes the menu without moving focus to the trigger', () => {
    const environment = createEnvironment();

    environment.menu.openMenu();
    environment.menu.selectTask();

    assert.equal(environment.menu.open, false);
    assert.equal(environment.bodyClasses.has('overflow-y-hidden'), false);
    assert.equal(environment.trigger.focusCount, 0);
});

test('Tab and Shift+Tab cycle focus within the full-screen dialog', () => {
    const environment = createEnvironment();

    environment.documentObject.activeElement = environment.activeTask;
    environment.menu.trapFocus({ shiftKey: false });
    assert.equal(environment.closeButton.focusCount, 1);

    environment.menu.trapFocus({ shiftKey: true });
    assert.equal(environment.activeTask.focusCount, 1);
});

test('crossing into the desktop breakpoint closes the menu and releases scrolling', () => {
    const environment = createEnvironment();

    environment.menu.openMenu();
    environment.crossIntoDesktop();

    assert.equal(environment.menu.open, false);
    assert.equal(environment.bodyClasses.has('overflow-y-hidden'), false);
    assert.equal(environment.trigger.focusCount, 0);
});

test('component teardown removes listeners and only releases its own scroll lock', () => {
    const environment = createEnvironment();

    environment.menu.openMenu();
    environment.menu.destroy();

    assert.equal(environment.mediaListeners.size, 0);
    assert.equal(environment.menu.open, false);
    assert.equal(environment.bodyClasses.has('overflow-y-hidden'), false);

    const preLockedEnvironment = createEnvironment({ bodyInitiallyLocked: true });
    preLockedEnvironment.menu.openMenu();
    preLockedEnvironment.menu.destroy();

    assert.equal(preLockedEnvironment.bodyClasses.has('overflow-y-hidden'), true);
});

test('the controller does not open the mobile menu at desktop width', () => {
    const environment = createEnvironment({ desktop: true });

    environment.menu.openMenu();

    assert.equal(environment.menu.open, false);
    assert.equal(environment.bodyClasses.has('overflow-y-hidden'), false);
    assert.equal(environment.activeTask.focusCount, 0);
});
