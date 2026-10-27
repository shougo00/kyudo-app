import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/group/records.js', import.meta.url), 'utf8');
const guard = source.slice(source.indexOf('function initOfficialRecordTapGuard()'), source.indexOf('const shotSaver ='));
const controls = 'a, button, input, select, textarea, label, summary, [role="button"]';

class Element {
    constructor(classes = [], parent = null, control = false) {
        this.classes = new Set(classes);
        this.classList = {
            contains: name => this.classes.has(name),
            add: (...names) => names.forEach(name => this.classes.add(name)),
            remove: (...names) => names.forEach(name => this.classes.delete(name)),
        };
        this.parent = parent;
        this.control = control;
        this.handlers = {};
        this.scrollLeft = 0;
        this.scrollTop = 0;
        this.dataset = { id: '123', user: '1', result: '', numericScore: '', scoringMode: 'hit_miss' };
        this.style = {};
    }
    closest(selector) {
        if (selector === '.shot-btn' && this.classes.has('shot-btn')) return this;
        if (selector === controls && this.control) return this;
        return this.parent?.closest(selector) || null;
    }
    contains(target) {
        return target === this || Boolean(target?.parent && this.contains(target.parent));
    }
    addEventListener(type, listener, options) {
        (this.handlers[type] ||= []).push({ listener, options });
    }
}

function setup(data = {}) {
    const doc = new Element();
    const area = new Element([], doc);
    const button = new Element(['shot-btn'], area);
    const icon = new Element([], button);
    const other = new Element(['shot-btn'], area);
    other.dataset.id = '124';
    const link = new Element([], area, true);
    const outside = new Element([], doc);
    const updates = [];
    const context = vm.createContext({
        Element, console, URL,
        window: { groupRecordData: data },
        document: Object.assign(doc, {
            querySelector: selector => selector.startsWith('.record-page.') ? area : null,
        }),
        // Only needed when checking this harness against the old implementation.
        usesMobileOfficialLayout: () => false,
        updateShot(target) {
            updates.push(target);
            target.dataset.result = target.dataset.result === 'hit' ? 'miss' : target.dataset.result === 'miss' ? '' : 'hit';
        },
    });
    vm.runInContext(`${guard}\ninitOfficialRecordTapGuard();`, context);
    const touch = (id = 1, x = 40, y = 40) => ({ identifier: id, clientX: x, clientY: y });
    function fire(type, target = icon, props = {}) {
        const event = {
            type, target, touches: [], changedTouches: [], cancelable: true,
            defaultPrevented: false, stopped: false,
            preventDefault() { if (this.cancelable) this.defaultPrevented = true; },
            stopPropagation() { this.stopped = true; },
            ...props,
        };
        for (const current of area.contains(target) ? [doc, area] : [doc]) {
            for (const { listener } of current.handlers[type] || []) listener(event);
            if (event.stopped) break;
        }
        // Simulate the template's inline onclick, after the capture listeners.
        if (type === 'click' && !event.stopped) {
            const shot = target.closest('.shot-btn');
            if (shot && !shot.classList.contains('empty-shot') && !shot.disabled) context.updateShot(shot);
        }
        return event;
    }
    function tap(target = icon, props = {}) {
        fire('touchstart', target, { touches: [touch()] });
        return fire('touchend', target, { changedTouches: [touch()], ...props });
    }
    return { context, doc, area, button, icon, other, link, outside, updates, touch, fire, tap };
}

test('repeated double taps cancel native taps and enter each shot exactly once', () => {
    const { area, button, icon, fire, tap, updates } = setup();
    assert.equal(area.handlers.touchend[0].options.passive, false);
    for (let i = 0; i < 40; i++) {
        assert.equal(tap().defaultPrevented, true);
        assert.equal(fire('click', icon, { pointerType: 'touch', detail: 1 }).defaultPrevented, true);
        assert.equal(tap().defaultPrevented, true);
        assert.equal(fire('click', icon, { detail: 1 }).defaultPrevented, true);
        assert.equal(fire('dblclick').defaultPrevented, true);
    }
    assert.equal(updates.length, 80);
    assert.equal(button.dataset.result, 'miss');
});

test('finger jitter beyond the input threshold still cancels a native double tap', () => {
    const { icon, fire, touch, updates } = setup();
    fire('touchstart', icon, { touches: [touch()] });
    const move = fire('touchmove', icon, { touches: [touch(1, 49, 40)] });
    assert.equal(move.defaultPrevented, false);
    const end = fire('touchend', icon, { changedTouches: [touch(1, 49, 40)] });
    assert.equal(end.defaultPrevented, true);
    assert.equal(fire('click', icon, { pointerType: 'touch', detail: 1 }).defaultPrevented, true);
    assert.equal(updates.length, 0);
});

test('movement only reported at touchend also cancels the tap without changing a shot', () => {
    const { icon, fire, touch, updates } = setup();
    fire('touchstart', icon, { touches: [touch()] });
    assert.equal(fire('touchend', icon, { changedTouches: [touch(1, 40, 50)] }).defaultPrevented, true);
    assert.equal(updates.length, 0);
});

test('small movement is one tap; an actual scroll retains its native end and no input', () => {
    const { area, icon, fire, touch, updates } = setup();
    fire('touchstart', icon, { touches: [touch()] });
    fire('touchmove', icon, { touches: [touch(1, 43, 42)] });
    assert.equal(fire('touchend', icon, { changedTouches: [touch(1, 43, 42)] }).defaultPrevented, true);
    assert.equal(updates.length, 1);
    fire('touchstart', icon, { touches: [touch()] });
    assert.equal(fire('touchmove', icon, { touches: [touch(1, 70, 40)] }).defaultPrevented, false);
    area.scrollLeft = 30;
    assert.equal(fire('touchend', icon, { changedTouches: [touch(1, 70, 40)] }).defaultPrevented, false);
    fire('click', icon, { pointerType: 'touch', detail: 1 });
    assert.equal(updates.length, 1);
});

test('pinching does not enter a shot or block shrinking, including a second finger outside the table', () => {
    for (const secondOutside of [false, true]) {
        const { icon, outside, fire, touch, tap, updates } = setup();
        const secondTarget = secondOutside ? outside : icon;
        fire('touchstart', icon, { touches: [touch()] });
        assert.equal(fire('touchstart', secondTarget, { touches: [touch(), touch(2, 80)] }).defaultPrevented, false);
        assert.equal(fire('touchmove', icon, { touches: [touch(), touch(2, 60)] }).defaultPrevented, false);
        assert.equal(fire('gesturechange', icon, { scale: 0.7 }).defaultPrevented, false);
        fire('touchend', icon, { touches: [touch(2, 60)], changedTouches: [touch()] });
        fire('touchend', secondTarget, { changedTouches: [touch(2, 60)] });
        fire('click', icon, { pointerType: 'touch', detail: 1 });
        assert.equal(updates.length, 0);
        tap();
        assert.equal(updates.length, 1);
    }
});

test('a canceled touch does not enter a shot and the next tap still works', () => {
    const { icon, fire, touch, tap, updates } = setup();
    fire('touchstart', icon, { touches: [touch()] });
    fire('touchcancel', icon, { changedTouches: [touch()], cancelable: false });
    fire('touchend', icon, { changedTouches: [touch()] });
    fire('click', icon, { pointerType: 'touch', detail: 1 });
    assert.equal(updates.length, 0);
    tap();
    assert.equal(updates.length, 1);
});

test('late compatibility clicks from different shots do not cause extra updates', () => {
    const { icon, other, tap, fire, updates } = setup();
    tap(icon);
    tap(other);
    assert.equal(fire('click', icon, { detail: 1 }).defaultPrevented, true);
    assert.equal(fire('click', other, { sourceCapabilities: { firesTouchEvents: true }, detail: 1 }).defaultPrevented, true);
    assert.equal(updates.length, 2);
});

test('real mouse, pen, and keyboard clicks remain usable after a touch', () => {
    const { icon, tap, fire, updates } = setup();
    tap();
    assert.equal(fire('click', icon, { detail: 0 }).defaultPrevented, false);
    for (const pointerType of ['mouse', 'pen']) {
        fire('pointerdown', icon, { pointerType });
        assert.equal(fire('click', icon, { pointerType, detail: 1 }).defaultPrevented, false);
    }
    assert.equal(updates.length, 4);
});

test('an uncancelable tap falls back to one native click instead of double input', () => {
    const { icon, tap, fire, updates } = setup();
    tap();
    const event = tap(icon, { cancelable: false });
    assert.equal(event.defaultPrevented, false);
    assert.equal(updates.length, 1);
    fire('click', icon, { pointerType: 'touch', detail: 1 });
    assert.equal(updates.length, 2);
});

test('table gaps and empty slots reject taps while real controls keep working', () => {
    const { area, button, icon, link, tap, fire, updates } = setup();
    assert.equal(tap(area).defaultPrevented, true);
    assert.equal(tap(link).defaultPrevented, false);
    assert.equal(fire('click', link, { detail: 1 }).defaultPrevented, false);
    assert.equal(fire('dblclick', link).defaultPrevented, false);
    button.classList.add('empty-shot');
    assert.equal(tap(icon).defaultPrevented, true);
    fire('click', icon, { detail: 1 });
    button.classList.remove('empty-shot');
    button.disabled = true;
    tap(icon);
    assert.equal(updates.length, 0);
});

test('match and lineup selection pages keep their existing input handling', () => {
    for (const data of [{ practiceType: 'match' }, { matchSelection: { position: 1 } }]) {
        const { area, doc } = setup(data);
        assert.deepEqual(Object.keys(area.handlers), []);
        assert.deepEqual(Object.keys(doc.handlers), []);
    }
});

test('touch input uses the real shot saver: two taps send hit then miss in order', async () => {
    const { context, doc, button, tap, fire, icon } = setup();
    const calls = [];
    const score = { innerText: '0中' };
    doc.querySelector = selector => selector.startsWith('meta') ? { content: 'test' } : selector.startsWith('.score[') ? score : null;
    context.updateOfficialMatchTeamHitCounters = () => {};
    context.updateOfficialDailySummary = () => {};
    context.alert = message => assert.fail(message);
    context.fetch = (url, options) => new Promise(resolve => calls.push({ body: JSON.parse(options.body), resolve }));
    const saver = readFileSync(new URL('../../resources/js/shared/shot-saver.js', import.meta.url), 'utf8').replace('export function', 'function');
    vm.runInContext(saver, context);
    vm.runInContext(source.slice(source.indexOf('const shotSaver ='), source.indexOf('function saveMatchAddTateScrollPosition()')), context);
    tap(); tap();
    fire('click', icon, { pointerType: 'touch', detail: 1 });
    assert.equal(button.dataset.result, 'miss');
    assert.equal(score.innerText, '0中');
    const flush = () => new Promise(resolve => setImmediate(resolve));
    await flush();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].body.result, 'hit');
    calls[0].resolve({ ok: true, json: async () => ({ success: true }) });
    await flush();
    assert.equal(calls.length, 2);
    assert.equal(calls[1].body.result, 'miss');
    calls[1].resolve({ ok: true, json: async () => ({ success: true }) });
    await flush();
    assert.equal(context.window.groupRecordPendingShotUpdates.size, 0);
});
