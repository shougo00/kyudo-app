import { createShotSaver } from '../shared/shot-saver.js';

document.addEventListener('DOMContentLoaded', () => {
    const datePicker = document.getElementById('date-picker');

    if (datePicker) {
        datePicker.addEventListener('change', function() {
            this.form.requestSubmit();
        });
    }

    const container = document.getElementById('records-container');
    if (!container) return;

    const shotSaver = createShotSaver();
    const numericStyles = new WeakMap();
    let type = container.dataset.type;
    const readonly = container.dataset.readonly === '1';
    const shotUrlTemplate = container.dataset.shotUrl || '/shots/__ID__';
    const recordUrlTemplate = container.dataset.recordUrl || '/records/__ID__';

    function updateBackground() {
        container.classList.remove('self-bg', 'official-bg');
        container.classList.add(type === 'self' ? 'self-bg' : 'official-bg');
    }

    function initShotButtons() {
        document.querySelectorAll('.shot-btn').forEach(btn => {
            btn.addEventListener('click', shotClickHandler);
        });
    }

    function initDeleteButtons() {
        document.querySelectorAll('.delete-record').forEach(btn => {
            btn.addEventListener('click', deleteClickHandler);
        });
    }

    function shotValue(btn) {
        return { result: btn.dataset.result || null, numeric_score: btn.dataset.numericScore === '' || btn.dataset.numericScore == null ? null : Number(btn.dataset.numericScore) };
    }

    function renderShot(btn, value) {
        btn.dataset.result = value.result || '';
        btn.dataset.numericScore = value.numeric_score == null ? '' : String(value.numeric_score);
        btn.removeAttribute('style');
        btn.innerHTML = value.result === 'hit' ? '<i class="fa-regular fa-circle"></i>'
            : value.result === 'miss' ? '<i class="fas fa-xmark"></i>' : '＋';
        btn.classList.remove('shot-hit', 'shot-miss', 'shot-none', 'shot-numeric');
        btn.classList.add(value.result === 'hit' ? 'shot-hit' : value.result === 'miss' ? 'shot-miss' : 'shot-none');
        if (value.numeric_score != null) {
            btn.innerText = value.numeric_score;
            btn.classList.remove('shot-none');
            btn.classList.add('shot-numeric');
            const style = numericStyles.get(btn);
            if (style != null) btn.setAttribute('style', style);
        }
        updateRecordResult(btn.dataset.record);
        updateSummary();
    }

    function shotClickHandler() {
        const btn = this;
        const previous = shotValue(btn);
        if (previous.numeric_score != null) numericStyles.set(btn, btn.getAttribute('style'));
        const next = { result: previous.result === 'hit' ? 'miss' : previous.result === 'miss' ? null : 'hit', numeric_score: null };
        renderShot(btn, next);
        shotSaver.save(btn, shotUrlTemplate.replace('__ID__', btn.dataset.id), previous, next, value => renderShot(btn, value));
    }

    function updateRecordResult(recordId) {
        let buttons = document.querySelectorAll(`[data-record='${recordId}']`);
        let hits = 0;
        let shots = 0;
        let numericTotal = 0;

        buttons.forEach(b => {
            if (b.dataset.result === 'hit' || b.dataset.result === 'miss') {
                shots++;
            }

            if (b.dataset.result === 'hit') hits++;

            let numericScore = parseInt(b.dataset.numericScore || '0', 10);
            if (!Number.isNaN(numericScore)) {
                numericTotal += numericScore;
            }
        });

        let result = document.getElementById(`result-${recordId}`);
        let hitCount = result?.querySelector('.hit-count');
        let numericTotalEl = result?.querySelector('.numeric-total');

        if (hitCount) {
            hitCount.innerText = hits + '/' + shots;
        }

        if (numericTotalEl) {
            numericTotalEl.innerText = numericTotal + '点';
            numericTotalEl.classList.toggle('d-none', numericTotal <= 0);
        }
    }

    async function deleteClickHandler() {
        let btn = this;

        if (!confirm('この立を削除しますか？')) return;

        if (!await shotSaver.wait()) return;
        let recordId = btn.dataset.id;

        fetch(recordUrlTemplate.replace('__ID__', recordId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.closest('.card').remove();

                document.querySelectorAll('.card').forEach((card, index) => {
                    card.querySelector('strong').innerText = (index + 1) + '立目';
                });

                updateSummary();
            } else {
                alert(data.message || '削除に失敗しました');
            }
        })
        .catch(err => console.error(err));
    }

    function updateSummary() {
        let buttons = document.querySelectorAll('.shot-btn');
        let totalShots = 0;
        let totalHits = 0;

        buttons.forEach(btn => {
            let result = btn.dataset.result;

            if (result === 'hit' || result === 'miss') {
                totalShots++;

                if (result === 'hit') {
                    totalHits++;
                }
            }
        });

        let rate = totalShots > 0 ? (totalHits / totalShots) * 100 : 0;

        document.querySelector('#summary .shots').innerText = totalShots + '射';
        document.querySelector('#summary .hits').innerText = totalHits + '中';
        document.querySelector('#summary .rate').innerText = rate.toFixed(1) + '％';
    }

    updateBackground();
    if (!readonly) {
        initShotButtons();
        initDeleteButtons();
    }
});
