// Requests for one shot run in order; unrelated shots save independently.
export function createShotSaver() {
    const states = new Map();
    const pending = new Set();
    let failures = 0;
    async function wait() {
        const before = failures;
        while (pending.size) await Promise.all([...pending]);
        return failures === before;
    }
    document.addEventListener('click', async event => {
        const link = event.target.closest?.('a[href]');
        if (!pending.size || !link || event.defaultPrevented || event.button !== 0 ||
            event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ||
            link.target || link.hasAttribute('download') || link.getAttribute('href').startsWith('#')) return;
        event.preventDefault();
        if (await wait()) window.location.assign(link.href);
    });
    const waitingForms = new WeakSet();
    document.addEventListener('submit', async event => {
        if (!pending.size || event.defaultPrevented) return;
        event.preventDefault();
        const form = event.target;
        if (waitingForms.has(form)) return;
        waitingForms.add(form);
        try {
            if (!await wait()) return;
            // Validation and confirmation already ran; do not dispatch submit again.
            const submitter = event.submitter;
            const overrides = [];
            let hidden;
            for (const [buttonAttribute, formAttribute] of [
                ['formaction', 'action'], ['formmethod', 'method'],
                ['formenctype', 'enctype'], ['formtarget', 'target'],
            ]) {
                if (!submitter?.hasAttribute(buttonAttribute)) continue;
                overrides.push([formAttribute, form.getAttribute(formAttribute)]);
                form.setAttribute(formAttribute, submitter.getAttribute(buttonAttribute));
            }
            if (submitter?.name) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value;
                form.appendChild(hidden);
            }
            try {
                HTMLFormElement.prototype.submit.call(form);
            } finally {
                hidden?.remove();
                overrides.forEach(([attribute, value]) => {
                    if (value === null) form.removeAttribute(attribute);
                    else form.setAttribute(attribute, value);
                });
            }
        } finally {
            waitingForms.delete(form);
        }
    });
    return {
        pending, wait,
        save(el, url, previous, next, render) {
            const key = String(el.dataset.id);
            let state = states.get(key);
            if (!state) {
                state = { confirmed: previous, tail: Promise.resolve(), generation: 0 };
                states.set(key, state);
            }
            const generation = state.generation;
            const request = state.tail.then(async () => {
                if (generation !== state.generation) return;
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json', 'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(next),
                    });
                    const data = await response.json();
                    if (!response.ok || data.success !== true) throw new Error('Save failed');
                    state.confirmed = next;
                } catch {
                    failures++;
                    state.generation++;
                    render(state.confirmed);
                    alert('記録の保存を確認できませんでした。表示を最後に保存確認できた状態に戻しました。通信が途切れた可能性があります。');
                }
            }).finally(() => pending.delete(request));
            state.tail = request;
            pending.add(request);
            return request;
        },
    };
}
