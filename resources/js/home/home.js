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

    function updateBackground() {
        container.classList.remove('self-bg','official-bg');
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

        btn.innerHTML =
            next === 'hit'
                ? '<i class="fa-regular fa-circle"></i>'
                : next === 'miss'
                ? '<i class="fas fa-xmark"></i>'
                : '＋';

        btn.classList.remove('shot-hit','shot-miss','shot-none');
        btn.classList.add(next === 'hit' ? 'shot-hit' : next === 'miss' ? 'shot-miss' : 'shot-none');

        let recordId = btn.dataset.record;
        let parent = document.querySelectorAll(`[data-record='${recordId}']`);
        let hits = 0;

        parent.forEach(b => {
            if (b.dataset.result === 'hit') hits++;
        });

        document.getElementById(`result-${recordId}`).innerText = hits + '/4';

        fetch(`/shots/${btn.dataset.id}`, {
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

    function deleteClickHandler() {
        let btn = this;

        if (!confirm('この立を削除しますか？')) return;

        let recordId = btn.dataset.id;

        fetch(`/records/${recordId}`, {
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
    initShotButtons();
    initDeleteButtons();
});