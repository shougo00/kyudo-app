import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const flush = () => new Promise(resolve => setImmediate(resolve));
function setup(mode = 'group', numeric = false) {
    const calls = [], alerts = [], events = {};
    const classes = { add() {}, remove() {}, toggle() {} };
    const el = { dataset: { id: '123', user: '1', record: '7', recordId: '7', tateCounter: '1-1', result: '', numericScore: '', scoringMode: numeric ? 'numeric' : 'hit_miss' }, classList: classes, style: {}, getAttribute: () => null, removeAttribute() {}, setAttribute() {}, addEventListener(name, fn) { this[name] = fn; } };
    const score = { innerText: numeric ? '0点' : '0中' }, total = { innerText: score.innerText };
    const summary = { shots: {}, hits: {}, rate: {} }, hitCount = {};
    const template = mode === 'self' ? '/group/1/self-shots/__ID__' : '/shots/__ID__';
    const context = vm.createContext({
        URL, console, setTimeout, clearTimeout, setInterval, clearInterval,
        window: { location: { assign(url) { context.destination = url; } }, addEventListener() {}, numericScoreOptions: [{ value: 9, color: 'red' }, { value: 8, color: 'blue' }] },
        document: {
            addEventListener(name, fn) { events[name] = fn; },
            getElementById(id) {
                if (id === 'records-container') return { dataset: { type: 'self', shotUrl: template }, classList: classes };
                if (id === 'result-7') return { querySelector: sel => sel === '.hit-count' ? hitCount : null };
                return null;
            },
            querySelector(selector) {
                if (selector.startsWith('meta')) return { content: 'token' };
                if (selector.startsWith('.score')) return score;
                if (selector.startsWith('[data-shot-counter')) return total;
                if (selector.startsWith('#summary')) return summary[selector.split(' .')[1]];
                return null;
            },
            querySelectorAll: selector => selector === '.shot-btn' || selector.startsWith('[data-record=') || selector.includes('.score-wrapper .shot-btn') ? [el] : [],
        },
        HTMLFormElement: { prototype: { submit() { this.nativeSubmit(); } } },
        alert: message => alerts.push(message),
        fetch: (url, options) => new Promise((resolve, reject) => calls.push({ url, payload: JSON.parse(options.body), resolve, reject })),
    });
    vm.runInContext(readFileSync(new URL('../../resources/js/shared/shot-saver.js', import.meta.url), 'utf8').replace('export function', 'function'), context);
    vm.runInContext(readFileSync(new URL(`../../resources/js/${mode === 'group' ? 'group/records.js' : 'home/home.js'}`, import.meta.url), 'utf8').replace(/^import .*;\r?\n/m, ''), context);
    if (mode !== 'group') events.DOMContentLoaded();
    const click = (target = el) => mode === 'group' ? context.updateShot(target) : target.click();
    const success = call => call.resolve({ ok: true, json: async () => ({ success: true }) });
    return { context, el, score, total, summary, hitCount, calls, alerts, events, click, success };
}
for (const mode of ['group', 'home', 'self']) {
    test(`${mode}: rapid clicks save in order`, async () => {
        const { el, calls, click, success } = setup(mode);
        click(); click();
        assert.equal(el.dataset.result, 'miss');
        await flush();
        assert.equal(calls.length, 1);
        assert.equal(calls[0].payload.result, 'hit');
        success(calls[0]); await flush();
        assert.equal(calls.length, 2);
        assert.equal(calls[1].payload.result, 'miss');
        success(calls[1]); await flush();
    });
    test(`${mode}: failures restore confirmed values and cancel queued clicks`, async () => {
        for (const networkError of [false, true]) {
            const { el, calls, alerts, click, success, score, summary } = setup(mode);
            click(); click(); click(); await flush();
            success(calls[0]); await flush();
            if (networkError) calls[1].reject(new Error('offline'));
            else calls[1].resolve({ ok: false, json: async () => ({}) });
            await flush();
            assert.equal(calls.length, 2);
            assert.equal(el.dataset.result, 'hit');
            assert.equal(mode === 'group' ? score.innerText : summary.hits.innerText, '1中');
            assert.equal(alerts.length, 1);
            click(); await flush();
            assert.equal(calls[2].payload.result, 'miss');
            success(calls[2]); await flush();
        }
    });
}
test('different shots save independently and printing waits for all queued writes', async () => {
    const { context, el, calls, click, success } = setup();
    const b = { ...el, dataset: { ...el.dataset, id: '124' } };
    click(); click(); click(b);
    let done = false;
    const waiting = context.waitForPendingShotUpdates().then(() => { done = true; });
    await flush(); assert.equal(calls.length, 2);
    success(calls[1]); await flush(); assert.equal(done, false);
    success(calls[0]); await flush(); success(calls[2]); await waiting;
    assert.equal(context.window.groupRecordPendingShotUpdates.size, 0);
});
test('numeric failure restores the confirmed points and counters', async () => {
    const { el, calls, click, success, score, total } = setup('group', true);
    click(); click(); await flush(); success(calls[0]); await flush();
    calls[1].reject(new Error('offline')); await flush();
    assert.equal(el.dataset.numericScore, '9');
    assert.equal(el.style.backgroundColor, 'red');
    assert.equal(score.innerText, '9点'); assert.equal(total.innerText, '9点');
});
test('links wait for saves and remain on the page after failure', async () => {
    for (const failure of [false, true]) {
        const { context, calls, click, success, events } = setup();
        click();
        const link = { href: '/next', getAttribute: () => '/next', hasAttribute: () => false };
        const navigation = events.click({ target: { closest: () => link }, button: 0, preventDefault() {} });
        await flush(); assert.equal(context.destination, undefined);
        if (failure) calls[0].reject(new Error('offline')); else success(calls[0]);
        await navigation; assert.equal(context.destination, failure ? undefined : '/next');
    }
});
test('next-sheet confirmation runs once and save failure cancels submission', async () => {
    for (const failure of [false, true]) {
        const { context, calls, click, success, events } = setup();
        click(); let confirms = 0, submitted = 0;
        context.confirm = () => { confirms++; return true; };
        const blade = readFileSync(new URL('../../resources/views/group/records.blade.php', import.meta.url), 'utf8');
        const start = blade.indexOf('function handleOfficialSheetSwitchSubmit(');
        vm.runInContext(blade.slice(start, blade.indexOf('</script>', start)), context);
        const form = { nativeSubmit() { submitted++; } };
        assert.equal(context.handleOfficialSheetSwitchSubmit(form, 'Switch?'), true);
        const submission = events.submit({ target: form, preventDefault() {} });
        const duplicate = events.submit({ target: form, preventDefault() {} });
        await flush(); assert.equal(submitted, 0);
        if (failure) calls[0].reject(new Error('offline')); else success(calls[0]);
        await Promise.all([submission, duplicate]);
        assert.equal(confirms, 1); assert.equal(submitted, failure ? 0 : 1);
    }
});
test('readonly and assignment pages cannot update shots', async () => {
    for (const groupRecordData of [{ canEdit: false }, { matchSelection: { position: 1 } }]) {
        const { context, calls, click } = setup(); context.window.groupRecordData = groupRecordData;
        click(); await flush(); assert.equal(calls.length, 0);
    }
});
