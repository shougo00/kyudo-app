document.addEventListener('DOMContentLoaded', () => {
    const datePicker = document.getElementById('date-picker');

    if (datePicker) {
        datePicker.addEventListener('change', function() {
            this.form.submit();
        });
    }

    const container = document.getElementById('records-container');
    if (!container) return;

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

    function shotClickHandler() {
        let btn = this;
        let current = btn.dataset.result;
        let next = current === 'hit' ? 'miss' : current === 'miss' ? '' : 'hit';

        btn.dataset.result = next;
        btn.dataset.numericScore = '';
        btn.removeAttribute('style');

        btn.innerHTML =
            next === 'hit'
                ? '<i class="fa-regular fa-circle"></i>'
                : next === 'miss'
                ? '<i class="fas fa-xmark"></i>'
                : '＋';

        btn.classList.remove('shot-hit','shot-miss','shot-none','shot-numeric');
        btn.classList.add(next === 'hit' ? 'shot-hit' : next === 'miss' ? 'shot-miss' : 'shot-none');

        let recordId = btn.dataset.record;
        updateRecordResult(recordId);

        fetch(shotUrlTemplate.replace('__ID__', btn.dataset.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                result: next || null
            })
        }).catch(err => console.error(err));

        updateSummary();
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

    function deleteClickHandler() {
        let btn = this;

        if (!confirm('この立を削除しますか？')) return;

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
                alert('削除に失敗しました');
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
