import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/group_history/index.blade.php', import.meta.url), 'utf8');
const start = blade.indexOf("    const loadingOverlay = document.getElementById('historyLoadingOverlay');");
const script = blade.slice(start, blade.indexOf('    const scoreChecks =', start));

function setup() {
    const classes = new Set();
    const overlay = {
        classList: { add: name => classes.add(name), remove: name => classes.delete(name) },
        setAttribute(name, value) { this[name] = value; },
        querySelector: () => ({ innerText: '' }),
    };
    const button = { disabled: false, innerHTML: '<span>検索する</span>', innerText: '検索する' };
    const element = () => ({
        handlers: {},
        addEventListener(name, callback) { this.handlers[name] = callback; },
        classList: { contains: () => false },
        querySelector: () => button,
    });
    const link = element(), monthlyForm = element(), rankingForm = element();
    const events = {};
    vm.runInNewContext(script, {
        document: {
            getElementById: () => overlay,
            querySelectorAll: selector => ({
                '[data-monthly-loading]': [link],
                '[data-monthly-loading-form]': [monthlyForm],
                '[data-ranking-loading-form]': [rankingForm],
            })[selector],
        },
        window: { addEventListener(name, callback) { events[name] = callback; } },
    });
    return { overlay, classes, button, link, monthlyForm, rankingForm, events };
}

test('returning from monthly records clears the restored loading overlay', () => {
    for (const persisted of [true, false]) {
        const { link, overlay, classes, events } = setup();
        for (let i = 0; i < 3; i++) {
            link.handlers.click({ button: 0 });
            assert.equal(classes.has('show'), true);
            assert.equal(overlay['aria-hidden'], 'false');
            // Simulate a restored page with no new DOMContentLoaded event.
            events.pageshow({ persisted });
            assert.equal(classes.has('show'), false);
            assert.equal(overlay['aria-hidden'], 'true');
        }
    }
});

test('leaving a monthly form clears the overlay before the page is cached', () => {
    const { monthlyForm, classes, events } = setup();
    monthlyForm.handlers.submit();
    assert.equal(classes.has('show'), true);
    events.pagehide({ persisted: true });
    assert.equal(classes.has('show'), false);
    events.pageshow({ persisted: true });
    monthlyForm.handlers.submit();
    assert.equal(classes.has('show'), true);
});

test('returning after a ranking search restores the button and allows another search', () => {
    const { rankingForm, button, classes, events } = setup();
    for (let i = 0; i < 2; i++) {
        rankingForm.handlers.submit();
        assert.equal(button.disabled, true);
        assert.equal(button.innerText, '検索中...');
        button.innerHTML = '検索中...';
        rankingForm.handlers.submit();
        events.pageshow({ persisted: true });
        assert.equal(classes.has('show'), false);
        assert.equal(button.disabled, false);
        assert.equal(button.innerHTML, '<span>検索する</span>');
    }
});

test('opening a separate tab does not show a loading overlay on the current page', () => {
    const { link, classes } = setup();
    for (const event of [{ button: 1 }, { button: 0, ctrlKey: true }, { button: 0, metaKey: true }]) {
        link.handlers.click(event);
        assert.equal(classes.has('show'), false);
    }
});
