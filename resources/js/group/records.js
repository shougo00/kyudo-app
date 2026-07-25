function reloadAndPrint() {
    waitForPendingShotUpdates().then(() => {
    const url = new URL(window.location.href);
    url.searchParams.set('print', '1');
    window.location.href = url.toString();
    });
}

window.addEventListener('load', () => {
    const url = new URL(window.location.href);
    const isPrintRequest = url.searchParams.get('print') === '1';

    if (isPrintRequest) {
        url.searchParams.delete('print');
        history.replaceState(null, '', url.toString());

        printAfterReady();
    }

    initMatchSelectionScrollBridge();
    initRecordPageOuterScroll();
    initMatchSelectionPositionLinks();
    initOfficialMatchTeamControls();
});

function waitForPendingShotUpdates() {
    const pendingUpdates = window.groupRecordPendingShotUpdates;

    if (!pendingUpdates || pendingUpdates.size === 0) {
        return Promise.resolve();
    }

    return Promise.allSettled(Array.from(pendingUpdates));
}

function updateOfficialMatchTeamHitCounters(counterKeys, delta) {
    if (!counterKeys || !delta) {
        return;
    }

    String(counterKeys)
        .split(',')
        .map(key => key.trim())
        .filter(Boolean)
        .forEach(key => {
            document.querySelectorAll(`[data-official-match-team-hit-counter="${key}"]`).forEach(counterEl => {
                let count = parseInt(counterEl.innerText) || 0;
                count += delta;
                if (count < 0) count = 0;
                counterEl.innerText = count + '中';
            });
        });
}

function officialMatchTeamControlsStorageKey() {
    const url = new URL(window.location.href);
    const groupId = window.groupRecordData?.groupId || 'unknown';
    const date = url.searchParams.get('date') || '';

    return `officialMatchTeamControlsOpen:${groupId}:${date}`;
}

function initOfficialMatchTeamControls() {
    const controls = document.getElementById('official-match-team-controls');

    if (!controls) {
        return;
    }

    const url = new URL(window.location.href);
    const storageKey = officialMatchTeamControlsStorageKey();
    const shouldOpen = url.hash === '#official-match-team-controls'
        || sessionStorage.getItem(storageKey) === '1';

    controls.open = shouldOpen;

    controls.addEventListener('toggle', () => {
        sessionStorage.setItem(storageKey, controls.open ? '1' : '0');
    });
}

async function printAfterReady() {
    await waitForPendingShotUpdates();

    if (document.fonts && document.fonts.ready) {
        await document.fonts.ready;
    }

    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    setTimeout(() => window.print(), 250);
}

let matchOfficialRecordSelecting = false;
let matchSelectionTouchMoved = false;

function selectOfficialRecordForMatch(el, event) {
    if (event) {
        event.preventDefault();
    }

    if (event && matchSelectionTouchMoved) {
        matchSelectionTouchMoved = false;
        return;
    }

    const selection = window.groupRecordData?.matchSelection;
    const recordId = el?.dataset?.matchRecordId;
    const assignedPosition = Number(selection?.position || 0);

    if (!selection || !recordId || !assignedPosition || matchOfficialRecordSelecting) {
        return;
    }

    matchOfficialRecordSelecting = true;
    el.classList.add('saving');
    const currentUrl = new URL(window.location.href);
    const payload = {
        date: currentUrl.searchParams.get('date'),
        tate_no: selection.tateNo,
        position: assignedPosition,
        record_id: recordId,
        return_to: selection.returnTo,
        sheet_no: currentUrl.searchParams.get('sheet_no'),
    };

    if (currentUrl.searchParams.has('compact_empty_slots')) {
        payload.compact_empty_slots = currentUrl.searchParams.get('compact_empty_slots');
    }

    fetch(`/match-teams/${selection.teamId}/official-record`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(payload),
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok || !data?.ok) {
                throw new Error(data?.message || '割り当てに失敗しました');
            }

            applyMatchOfficialRecordAssignment(el, {
                position: assignedPosition,
                recordId,
                userName: data.assigned?.user_name || el.dataset.matchUserName || '',
                officialTateNo: data.assigned?.official_tate_no || el.dataset.matchOfficialTate || '',
                nextPosition: data.next_position,
            });
        })
        .catch(error => {
            alert(error.message || '割り当てに失敗しました');
        })
        .finally(() => {
            matchOfficialRecordSelecting = false;
            el.classList.remove('saving');
        });
}

function initMatchSelectionPositionLinks() {
    if (!window.groupRecordData?.matchSelection) {
        return;
    }

    document.querySelectorAll('[data-match-select-position]').forEach(link => {
        link.addEventListener('click', event => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button) {
                return;
            }

            event.preventDefault();

            if (matchOfficialRecordSelecting) {
                return;
            }

            setMatchSelectionPosition(Number(link.dataset.matchSelectPosition || 0));
        });
    });

    syncMatchSelectionUi();
}

function applyMatchOfficialRecordAssignment(el, assignment) {
    const selectedPosition = Number(assignment.position || 0);
    const recordId = String(assignment.recordId || '');
    const positionLink = findMatchSelectionPositionLink(selectedPosition);

    if (!positionLink || !recordId) {
        return;
    }

    const oldRecordId = positionLink.dataset.assignedRecordId || '';

    document.querySelectorAll('[data-match-select-position]').forEach(link => {
        if (
            link !== positionLink &&
            link.dataset.assignedRecordId &&
            String(link.dataset.assignedRecordId) === recordId
        ) {
            setMatchSelectionPositionAssignment(link, null);
        }
    });

    setMatchSelectionPositionAssignment(positionLink, {
        recordId,
        userName: assignment.userName,
        officialTateNo: assignment.officialTateNo,
    });

    if (oldRecordId && oldRecordId !== recordId) {
        syncMatchRecordChoiceClasses();
    }

    const nextPosition = assignment.nextPosition
        ? Number(assignment.nextPosition)
        : selectedPosition;

    setMatchSelectionPosition(nextPosition, { replaceUrl: true });
}

function setMatchSelectionPosition(position, options = {}) {
    const selection = window.groupRecordData?.matchSelection;

    if (!selection || !position) {
        return;
    }

    const nextPosition = Math.max(1, Math.min(Number(selection.tateSize || 1), Number(position)));
    selection.position = nextPosition;

    if (options.replaceUrl !== false) {
        const url = new URL(window.location.href);
        url.searchParams.set('match_position', nextPosition);
        history.replaceState(null, '', url.toString());
    }

    syncMatchSelectionUi();
}

function syncMatchSelectionUi() {
    const selection = window.groupRecordData?.matchSelection;

    if (!selection) {
        return;
    }

    const activePosition = Number(selection.position || 1);
    const heading = document.querySelector('[data-match-selection-heading]');
    const activePositionLabel = getMatchPositionLabel(activePosition);

    if (heading) {
        heading.textContent = `${selection.teamName} / ${selection.tateNo}立目 / ${activePositionLabel}`;
    }

    document.querySelectorAll('[data-match-select-position]').forEach(link => {
        const position = Number(link.dataset.matchSelectPosition || 0);
        link.classList.toggle('active', position === activePosition);

        const url = new URL(window.location.href);
        url.searchParams.set('match_position', position);
        link.href = url.toString();
        link.dataset.positionUrl = url.toString();
    });

    document.querySelectorAll('.official-sheet-tabs a').forEach(link => {
        const url = new URL(link.href);
        url.searchParams.set('match_position', activePosition);
        link.href = url.toString();
    });

    syncMatchSelectionCurrentText();
    syncMatchRecordChoiceClasses();
}

function syncMatchSelectionCurrentText() {
    const activeLink = findMatchSelectionPositionLink(window.groupRecordData?.matchSelection?.position);
    const current = document.querySelector('[data-match-selection-current]');

    if (!current) {
        return;
    }

    const name = activeLink?.dataset.assignedUserName || '';
    const officialTate = activeLink?.dataset.assignedOfficialTate || '';

    current.hidden = !name;

    const nameEl = current.querySelector('[data-match-selection-current-name]');
    const tateEl = current.querySelector('[data-match-selection-current-tate]');

    if (nameEl) {
        nameEl.textContent = name;
    }

    if (tateEl) {
        tateEl.textContent = officialTate;
    }
}

function syncMatchRecordChoiceClasses() {
    const selection = window.groupRecordData?.matchSelection;

    if (!selection) {
        return;
    }

    const assignedRecordIds = new Set(
        Array.from(document.querySelectorAll('[data-match-select-position]'))
            .map(link => link.dataset.assignedRecordId)
            .filter(Boolean)
            .map(String)
    );
    const assignedRecordLabels = new Map(
        Array.from(document.querySelectorAll('[data-match-select-position]'))
            .filter(link => link.dataset.assignedRecordId)
            .map(link => [String(link.dataset.assignedRecordId), link.dataset.positionLabel || getMatchPositionLabel(link.dataset.matchSelectPosition)])
    );
    const activeRecordId = findMatchSelectionPositionLink(selection.position)?.dataset.assignedRecordId || '';

    document.querySelectorAll('.user-column.match-record-choice').forEach(column => {
        const recordId = String(column.dataset.matchRecordId || '');
        const assignedLabel = assignedRecordLabels.get(recordId) || '';

        column.classList.toggle('assigned', assignedRecordIds.has(recordId));
        column.classList.toggle('current', !!activeRecordId && recordId === String(activeRecordId));
        column.title = `${selection.teamName} ${selection.tateNo}立目 ${getMatchPositionLabel(selection.position)}に割り当て`;
        updateMatchRecordChoicePositionLabel(column, assignedLabel);
    });
}

function setMatchSelectionPositionAssignment(link, assignment) {
    const position = Number(link.dataset.matchSelectPosition || 0);
    const name = assignment?.userName || '';
    const positionLabel = link.dataset.positionLabel || getMatchPositionLabel(position);

    link.classList.toggle('filled', !!assignment?.recordId);
    link.dataset.assignedRecordId = assignment?.recordId || '';
    link.dataset.assignedUserName = name;
    link.dataset.assignedOfficialTate = assignment?.officialTateNo || '';

    let nameEl = link.querySelector('.match-record-select-name');

    if (name) {
        if (!nameEl) {
            nameEl = document.createElement('span');
            nameEl.className = 'match-record-select-name';
            link.appendChild(nameEl);
        }

        nameEl.textContent = name;
    } else if (nameEl) {
        nameEl.remove();
    }

    const label = `${positionLabel}を選択${name ? `：${name}` : ''}`;
    link.setAttribute('aria-label', label);
    link.title = label;
}

function updateMatchRecordChoicePositionLabel(column, label) {
    let labelEl = column.querySelector('.match-record-choice-position-label');

    if (label) {
        if (!labelEl) {
            labelEl = document.createElement('span');
            labelEl.className = 'match-record-choice-position-label';
            column.prepend(labelEl);
        }

        labelEl.textContent = label;
    } else if (labelEl) {
        labelEl.remove();
    }
}

function getMatchPositionLabel(position) {
    const labels = window.groupRecordData?.matchSelection?.positionLabels || {};
    const key = String(position || '');

    return labels[key] || labels[position] || `${position}番`;
}

function findMatchSelectionPositionLink(position) {
    const targetPosition = Number(position || 0);

    return Array.from(document.querySelectorAll('[data-match-select-position]'))
        .find(link => Number(link.dataset.matchSelectPosition || 0) === targetPosition) || null;
}

function initMatchSelectionScrollBridge() {
    const page = document.querySelector('.record-page.match-selection-mode');
    const scrollArea = document.querySelector('.score-scroll');

    if (!page || !scrollArea) {
        return;
    }

    let lastX = 0;
    let lastY = 0;
    let startX = 0;
    let startY = 0;

    scrollArea.addEventListener('wheel', event => {
        const isHorizontal = Math.abs(event.deltaX) > Math.abs(event.deltaY) || event.shiftKey;

        if (isHorizontal) {
            return;
        }

        if (canScrollElementVertically(scrollArea, event.deltaY)) {
            return;
        }

        window.scrollBy({
            top: event.deltaY,
            left: 0,
            behavior: 'auto',
        });
        event.preventDefault();
    }, { passive: false });

    scrollArea.addEventListener('touchstart', event => {
        if (event.touches.length !== 1) {
            return;
        }

        startX = event.touches[0].clientX;
        startY = event.touches[0].clientY;
        lastX = startX;
        lastY = startY;
        matchSelectionTouchMoved = false;
    }, { passive: true });

    scrollArea.addEventListener('touchmove', event => {
        if (event.touches.length !== 1) {
            return;
        }

        const x = event.touches[0].clientX;
        const y = event.touches[0].clientY;
        const totalX = x - startX;
        const totalY = y - startY;
        const isVertical = Math.abs(totalY) > Math.abs(totalX) + 6;

        if (Math.abs(totalX) > 8 || Math.abs(totalY) > 8) {
            matchSelectionTouchMoved = true;
        }

        if (!isVertical) {
            lastX = x;
            lastY = y;
            return;
        }

        const deltaY = lastY - y;

        if (canScrollElementVertically(scrollArea, deltaY)) {
            lastX = x;
            lastY = y;
            return;
        }

        window.scrollBy({
            top: deltaY,
            left: 0,
            behavior: 'auto',
        });

        lastX = x;
        lastY = y;
        event.preventDefault();
    }, { passive: false });

    scrollArea.addEventListener('touchend', () => {
        if (matchSelectionTouchMoved) {
            setTimeout(() => {
                matchSelectionTouchMoved = false;
            }, 120);
        }
    }, { passive: true });
}

function canScrollElementVertically(element, deltaY) {
    const maxScrollTop = element.scrollHeight - element.clientHeight;

    if (maxScrollTop <= 1 || deltaY === 0) {
        return false;
    }

    if (deltaY > 0) {
        return element.scrollTop < maxScrollTop - 1;
    }

    return element.scrollTop > 1;
}

function initRecordPageOuterScroll() {
    const page = document.querySelector('.record-page');

    if (!page) {
        return;
    }

    const innerScrollSelector = [
        '.score-scroll',
        '.match-score-scroll',
        '.official-sheet-tabs',
        '.calendar-wrapper',
        '.match-lineup-modal',
        '.match-lineup-dialog',
        '.inline-match-pool',
        '.match-team-board',
    ].join(',');

    page.addEventListener('wheel', event => {
        if (event.target.closest(innerScrollSelector)) {
            return;
        }

        const isHorizontal = Math.abs(event.deltaX) > Math.abs(event.deltaY) || event.shiftKey;

        if (isHorizontal) {
            window.scrollBy({
                top: 0,
                left: event.deltaX || event.deltaY,
                behavior: 'auto',
            });
            event.preventDefault();
            return;
        }

        window.scrollBy({
            top: event.deltaY,
            left: 0,
            behavior: 'auto',
        });
        event.preventDefault();
    }, { passive: false });

    let lastY = 0;
    let startTarget = null;

    page.addEventListener('touchstart', event => {
        if (event.touches.length !== 1) {
            return;
        }

        startTarget = event.target;
        lastY = event.touches[0].clientY;
    }, { passive: true });

    page.addEventListener('touchmove', event => {
        if (event.touches.length !== 1 || startTarget?.closest(innerScrollSelector)) {
            return;
        }

        const y = event.touches[0].clientY;

        window.scrollBy({
            top: lastY - y,
            left: 0,
            behavior: 'auto',
        });

        lastY = y;
        event.preventDefault();
    }, { passive: false });
}
    
function updateShot(el){
    if (window.groupRecordData && window.groupRecordData.canEdit === false) {
        return;
    }

    const id = el.dataset.id;
    if(!id){
        alert('立順編集から正規連の1立を選択してください');
        return;
    }

    const userId = el.dataset.user;
    const recordId = el.dataset.recordId;
    const tateCounterKey = el.dataset.tateCounter;
    const current = el.dataset.result;
    const scoringMode = el.dataset.scoringMode || 'hit_miss';

    if (scoringMode === 'numeric') {
        updateNumericShot(el, id, userId, recordId, tateCounterKey);
        return;
    }

    const next =
        current==='hit' ? 'miss' :
        current==='miss' ? '' :
        'hit';

    el.dataset.result = next;

    el.innerHTML =
        next==='hit'
        ? '<i class="fa-regular fa-circle"></i>'
        : next==='miss'
        ? '<i class="fas fa-xmark"></i>'
        : '＋';

    el.classList.remove('shot-hit','shot-miss','shot-none');

    if(next==='hit') el.classList.add('shot-hit');
    else if(next==='miss') el.classList.add('shot-miss');
    else el.classList.add('shot-none');

    const scoreEl = document.querySelector(`.score[data-user-id="${userId}"]`);

    if(scoreEl){
        let count = parseInt(scoreEl.innerText) || 0;

        if(current !== 'hit' && next === 'hit') count++;
        if(current === 'hit' && next !== 'hit') count--;

        if(count < 0) count = 0;

        scoreEl.innerText = count + '中';
    }

    if(recordId){
        const counterEl = document.querySelector(`[data-shot-counter="${recordId}"]`);

        if(counterEl){
            let count = parseInt(counterEl.innerText) || 0;

            if(current !== 'hit' && next === 'hit') count++;
            if(current === 'hit' && next !== 'hit') count--;
            if(count < 0) count = 0;

            counterEl.innerText = count + '中';
        }
    }

    if(tateCounterKey){
        const tateCounterEl = document.querySelector(`[data-tate-hit-counter="${tateCounterKey}"]`);

        if(tateCounterEl){
            let count = parseInt(tateCounterEl.innerText) || 0;

            if(current !== 'hit' && next === 'hit') count++;
            if(current === 'hit' && next !== 'hit') count--;
            if(count < 0) count = 0;

            tateCounterEl.innerText = count + '中';
        }
    }

    updateOfficialMatchTeamHitCounters(
        el.dataset.officialMatchTeamCounters,
        (current !== 'hit' && next === 'hit') ? 1 : ((current === 'hit' && next !== 'hit') ? -1 : 0)
    );

    window.groupRecordPendingShotUpdates = window.groupRecordPendingShotUpdates || new Set();

    const request = fetch(`/group/shot/${id}`,{
        method:'POST',
        headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ result: next })
    }).finally(() => {
        window.groupRecordPendingShotUpdates.delete(request);
    });

    window.groupRecordPendingShotUpdates.add(request);
}

function updateNumericShot(el, id, userId, recordId, tateCounterKey) {
    const options = Array.isArray(window.numericScoreOptions) ? window.numericScoreOptions : [];
    if (options.length === 0) return;

    const currentScore = el.dataset.numericScore === '' || el.dataset.numericScore == null
        ? null
        : parseInt(el.dataset.numericScore, 10);
    const currentIndex = options.findIndex(option => parseInt(option.value, 10) === currentScore);
    const nextOption = currentIndex === -1
        ? options[0]
        : (options[currentIndex + 1] || null);
    const previousScore = currentScore || 0;
    const nextScore = nextOption ? parseInt(nextOption.value, 10) : null;

    el.dataset.numericScore = nextScore == null ? '' : String(nextScore);
    el.dataset.result = '';
    el.classList.remove('shot-hit', 'shot-miss', 'shot-none');
    el.classList.add('shot-numeric');

    if (nextOption) {
        el.innerText = nextScore;
        el.style.backgroundColor = nextOption.color;
        el.style.borderColor = nextOption.color;
        el.style.color = '#111';
    } else {
        el.innerText = '＋';
        el.classList.add('shot-none');
        el.classList.remove('shot-numeric');
        el.style.backgroundColor = '';
        el.style.borderColor = '';
        el.style.color = '';
    }

    const delta = (nextScore || 0) - previousScore;

    const scoreEl = document.querySelector(`.score[data-user-id="${userId}"]`);
    if (scoreEl) {
        let count = parseInt(scoreEl.innerText) || 0;
        count += delta;
        if (count < 0) count = 0;
        scoreEl.innerText = count + '点';
    }

    if (recordId) {
        const counterEl = document.querySelector(`[data-shot-counter="${recordId}"]`);
        if (counterEl) {
            let count = parseInt(counterEl.innerText) || 0;
            count += delta;
            if (count < 0) count = 0;
            counterEl.innerText = count + '点';
        }
    }

    if (tateCounterKey) {
        const tateCounterEl = document.querySelector(`[data-tate-hit-counter="${tateCounterKey}"]`);
        if (tateCounterEl) {
            let count = parseInt(tateCounterEl.innerText) || 0;
            count += delta;
            if (count < 0) count = 0;
            tateCounterEl.innerText = count + '点';
        }
    }

    window.groupRecordPendingShotUpdates = window.groupRecordPendingShotUpdates || new Set();

    const request = fetch(`/group/shot/${id}`,{
        method:'POST',
        headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            result: null,
            numeric_score: nextScore
        })
    }).finally(() => {
        window.groupRecordPendingShotUpdates.delete(request);
    });

    window.groupRecordPendingShotUpdates.add(request);
}

function saveMatchAddTateScrollPosition() {
    const scrollArea = document.querySelector('.match-score-scroll') || document.querySelector('.score-scroll');

    if (!scrollArea) {
        return;
    }

    sessionStorage.setItem('matchAddTateScrollLeft', String(scrollArea.scrollLeft));
    sessionStorage.setItem('matchAddTateScrollTop', String(scrollArea.scrollTop));
    sessionStorage.setItem(
        'matchAddTateScrollMode',
        scrollArea.classList.contains('match-score-scroll') ? 'match' : 'official'
    );
}

function clearMatchAddTateScrollPosition() {
    sessionStorage.removeItem('matchAddTateScrollLeft');
    sessionStorage.removeItem('matchAddTateScrollTop');
    sessionStorage.removeItem('matchAddTateScrollMode');
}

function scrollRight() {
    const el = document.querySelector('.score-scroll');
    if (!el) return;

    if (el.classList.contains('match-score-scroll')) {
        const selectedMatchTate = document.querySelector('[data-selected-match-tate="1"]');

        if (selectedMatchTate) {
            clearMatchAddTateScrollPosition();
            window.matchAddTateRestorePosition = null;

            requestAnimationFrame(() => {
                scrollMatchTateColumnIntoView(el, selectedMatchTate);
            });
            return;
        }

        if (window.matchAddTateRestorePosition === undefined) {
            const savedLeft = sessionStorage.getItem('matchAddTateScrollLeft');
            const savedTop = sessionStorage.getItem('matchAddTateScrollTop');
            const savedMode = sessionStorage.getItem('matchAddTateScrollMode');
            window.matchAddTateRestorePosition = savedLeft !== null || savedTop !== null
                ? {
                    left: savedLeft !== null ? (parseInt(savedLeft, 10) || 0) : 0,
                    top: savedMode === 'match' ? 0 : (parseInt(savedTop || '0', 10) || 0),
                }
                : null;
            clearMatchAddTateScrollPosition();
        }

        if (window.matchAddTateRestorePosition !== null) {
            requestAnimationFrame(() => {
                el.scrollLeft = window.matchAddTateRestorePosition.left;
                el.scrollTop = window.matchAddTateRestorePosition.top;
            });
            return;
        }

        el.scrollLeft = 0;
        el.scrollTop = 0;
        return;
    }

    if (window.matchAddTateRestorePosition === undefined) {
        const savedLeft = sessionStorage.getItem('matchAddTateScrollLeft');
        const savedTop = sessionStorage.getItem('matchAddTateScrollTop');
        const savedMode = sessionStorage.getItem('matchAddTateScrollMode');

        window.matchAddTateRestorePosition = savedMode === 'official' && (savedLeft !== null || savedTop !== null)
            ? {
                left: parseInt(savedLeft || '0', 10) || 0,
                top: parseInt(savedTop || '0', 10) || 0,
            }
            : null;

        if (savedMode === 'official') {
            clearMatchAddTateScrollPosition();
        }
    }

    if (window.matchAddTateRestorePosition !== null) {
        requestAnimationFrame(() => {
            el.scrollLeft = window.matchAddTateRestorePosition.left;
            el.scrollTop = window.matchAddTateRestorePosition.top;
        });
        return;
    }

    el.scrollLeft = el.scrollWidth;
    el.scrollTop = el.scrollHeight;
}

function scrollMatchTateColumnIntoView(scrollArea, element) {
    const areaRect = scrollArea.getBoundingClientRect();
    const elementRect = element.getBoundingClientRect();

    scrollArea.scrollLeft += elementRect.left - areaRect.left - 12;
    scrollArea.scrollTop = 0;
}

window.addEventListener('load', () => {
    const url = new URL(window.location.href);

    if (url.searchParams.get('print') === '1') {
        return;
    }

    [50, 200, 500].forEach(delay => {
        setTimeout(scrollRight, delay);
    });
});

document.querySelectorAll('[data-match-add-tate-form]').forEach(form => {
    form.addEventListener('submit', () => {
        saveMatchAddTateScrollPosition();
    });
});

function toggleCalendar(event) {
    event.stopPropagation();

    const box = document.getElementById('calendarBox');

    if (!box) return;

    box.style.display = box.style.display === 'block' ? 'none' : 'block';
}
window.updateShot = updateShot;
window.selectOfficialRecordForMatch = selectOfficialRecordForMatch;
window.toggleCalendar = toggleCalendar;
window.reloadAndPrint = reloadAndPrint;
window.scrollRight = scrollRight;

function toggleScoringMode(input) {
    const mode = input.checked ? 'numeric' : 'hit_miss';
    const isOfficial = input.dataset.modeToggle === 'official';
    const scopeSelector = isOfficial
        ? '.shot-btn[data-scoring-mode]'
        : `.shot-btn[data-tate-counter="${input.dataset.teamId}-${input.dataset.tateNo}"]`;
    const scopeShots = [...document.querySelectorAll(scopeSelector)];
    const hasHitMiss = scopeShots.some(shot => shot.dataset.result === 'hit' || shot.dataset.result === 'miss');
    const hasNumeric = scopeShots.some(shot => shot.dataset.numericScore !== '' && shot.dataset.numericScore != null);

    if (mode === 'numeric' && hasHitMiss) {
        input.checked = false;
        alert(isOfficial
            ? 'このページに○×の記録が入っているため、数字モードに切り替えできません。'
            : 'この立に○×の記録が入っているため、数字モードに切り替えできません。');
        return;
    }

    if (mode === 'hit_miss' && hasNumeric) {
        input.checked = true;
        alert(isOfficial
            ? 'このページに数字の記録が入っているため、○×モードに戻せません。'
            : 'この立に数字の記録が入っているため、○×モードに戻せません。');
        return;
    }

    const url = isOfficial
        ? `/group/${window.groupRecordData.groupId}/records/scoring-mode`
        : `/match-teams/${input.dataset.teamId}/tate-scoring-mode`;
    const body = isOfficial
        ? {
            date: input.dataset.date,
            sheet_no: input.dataset.sheetNo,
            scoring_mode: mode,
        }
        : {
            date: input.dataset.date,
            tate_no: input.dataset.tateNo,
            scoring_mode: mode,
        };

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(body)
    })
        .then(res => res.json())
        .then(data => {
            if (data && data.ok) {
                window.location.reload();
                return;
            }

            input.checked = !input.checked;
            alert(data?.message || 'モードを切り替えできません');
        })
        .catch(() => {
            input.checked = !input.checked;
            alert('モードの保存に失敗しました');
        });
}

window.toggleScoringMode = toggleScoringMode;

let inlineDragged = null;
let inlineSelected = null;
let inlineSaveTimer = null;
let inlineLongPressTimer = null;
let inlineLongPressed = false;

function initInlineMatchLineup() {
    const data = window.inlineMatchLineupData;
    const grid = document.getElementById('inlineMatchGrid');
    let pool = document.getElementById('inlineMatchPool');
    const source = data
        ? document.querySelector(`.lineup-source[data-team-id="${data.teamId}"][data-tate-no="${data.tateNo}"]`)
        : null;
    const status = document.getElementById('matchLineupSaveStatus');

    if (!data || !grid || !pool || !source) return;

    const freshPool = pool.cloneNode(false);
    pool.parentNode.replaceChild(freshPool, pool);
    pool = freshPool;

    function clearTargets() {
        document.querySelectorAll('.inline-drag-over').forEach(el => {
            el.classList.remove('inline-drag-over');
        });
    }

    function selectMember(member) {
        document.querySelectorAll('.inline-member.selected').forEach(el => {
            el.classList.remove('selected');
        });

        inlineSelected = member;

        document.querySelectorAll('.inline-match-cell, .inline-match-pool').forEach(el => {
            el.classList.remove('tap-target');
        });

        if (member) {
            member.classList.add('selected');
            document.querySelectorAll('.inline-match-cell').forEach(el => el.classList.add('tap-target'));
            pool.classList.add('tap-target');
        }
    }

    function isPlacedMember(member) {
        return member?.parentElement?.classList.contains('inline-match-cell');
    }

    function isPoolElement(element) {
        return element?.id === 'inlineMatchPool' || element?.classList.contains('inline-match-pool');
    }

    function hasEnteredRecord(member) {
        return member?.dataset.hasRecord === '1';
    }

    function confirmRemoveRecordedMember(member) {
        if (!isPlacedMember(member) || !hasEnteredRecord(member)) {
            return true;
        }

        return window.confirm('記録が入っている人を選択外に移動すると、記録が一覧に残らなくなります。よろしいですか？');
    }

    function swapMembers(a, b) {
        const aParent = a.parentElement;
        const bParent = b.parentElement;
        const aNext = a.nextSibling;
        const bNext = b.nextSibling;

        if ((isPoolElement(bParent) && !confirmRemoveRecordedMember(a))
            || (isPoolElement(aParent) && !confirmRemoveRecordedMember(b))) {
            return false;
        }

        if (aParent === bParent) {
            bParent.insertBefore(a, bNext);
            aParent.insertBefore(b, aNext);
        } else {
            aParent.insertBefore(b, aNext);
            bParent.insertBefore(a, bNext);
        }

        return true;
    }

    function moveSelectedTo(target) {
        if (!inlineSelected) return;

        if (target.classList.contains('inline-match-cell')) {
            const existing = target.querySelector('.inline-member');

            if (existing && existing !== inlineSelected) {
                if (!swapMembers(inlineSelected, existing)) return;
            } else {
                target.appendChild(inlineSelected);
            }
        }

        if (target.id === 'inlineMatchPool') {
            if (!confirmRemoveRecordedMember(inlineSelected)) return;

            pool.appendChild(inlineSelected);
            sortInlinePoolMembers();
        }

        selectMember(null);
        autoSaveInlineLineup();
    }

    function sortInlinePoolMembers() {
        const usesGrades = Boolean(window.groupRecordData?.usesGrades);

        Array.from(pool.querySelectorAll('.inline-member'))
            .sort((a, b) => {
                const aUnavailable = a.classList.contains('absent') || a.classList.contains('late');
                const bUnavailable = b.classList.contains('absent') || b.classList.contains('late');
                const unavailableOrder = Number(aUnavailable) - Number(bUnavailable);

                if (unavailableOrder !== 0) {
                    return unavailableOrder;
                }

                if (usesGrades) {
                    const aGrade = parseInt(a.dataset.gradeLevel || '0', 10);
                    const bGrade = parseInt(b.dataset.gradeLevel || '0', 10);
                    const gradeOrder = bGrade - aGrade;

                    if (gradeOrder !== 0) {
                        return gradeOrder;
                    }
                }

                return (a.dataset.name || '').localeCompare(b.dataset.name || '', 'ja');
            })
            .forEach(member => pool.appendChild(member));
    }

    function makeMember(sourceEl) {
        const member = document.createElement('div');
        member.className = 'inline-member';
        member.draggable = true;
        member.dataset.userId = sourceEl.dataset.userId;
        member.dataset.hasRecord = sourceEl.dataset.hasRecord || '0';
        member.dataset.gradeLevel = sourceEl.dataset.gradeLevel || '';
        member.dataset.name = sourceEl.textContent.trim().toLowerCase();
        member.textContent = sourceEl.textContent.trim();

        if (sourceEl.dataset.gender === 'male') member.classList.add('male');
        if (sourceEl.dataset.gender === 'female') member.classList.add('female');
        if (sourceEl.dataset.gradeColor) {
            member.classList.add('grade-colored');
            member.style.backgroundColor = sourceEl.dataset.gradeColor;
            member.style.borderColor = sourceEl.dataset.gradeColor;
            member.style.color = sourceEl.dataset.gradeTextColor || '#222';
        }
        if (sourceEl.dataset.late === '1' || sourceEl.classList.contains('late')) member.classList.add('late');
        if (sourceEl.dataset.absent === '1' || sourceEl.classList.contains('absent')) member.classList.add('absent');
        if (member.textContent.length >= 5) member.classList.add('long-name');

        function cycleInlineAttendance() {
            if (!member.classList.contains('late') && !member.classList.contains('absent')) {
                if (!confirmRemoveRecordedMember(member)) {
                    return false;
                }

                member.classList.add('late');
                pool.appendChild(member);
                sortInlinePoolMembers();
                return true;
            }

            if (member.classList.contains('late')) {
                member.classList.remove('late');
                member.classList.add('absent');
                pool.appendChild(member);
                sortInlinePoolMembers();
                return true;
            }

            member.classList.remove('absent');
            sortInlinePoolMembers();
            return true;
        }

        member.addEventListener('dragstart', () => {
            inlineDragged = member;
            setTimeout(() => member.style.opacity = '0.5', 0);
        });

        member.addEventListener('dragend', () => {
            member.style.opacity = '1';
            inlineDragged = null;
            clearTargets();
        });

        member.addEventListener('touchstart', () => {
            inlineLongPressed = false;
            inlineLongPressTimer = setTimeout(() => {
                inlineLongPressed = true;
                if (!cycleInlineAttendance()) return;

                selectMember(null);
                autoSaveInlineLineup();
            }, 600);
        }, { passive: true });

        member.addEventListener('touchend', () => {
            clearTimeout(inlineLongPressTimer);
        });

        member.addEventListener('touchmove', () => {
            clearTimeout(inlineLongPressTimer);
        });

        member.addEventListener('dblclick', event => {
            event.stopPropagation();
            if (!cycleInlineAttendance()) return;

            selectMember(null);
            autoSaveInlineLineup();
        });

        member.addEventListener('click', event => {
            event.stopPropagation();

            if (inlineLongPressed) {
                inlineLongPressed = false;
                return;
            }

            if (inlineSelected && inlineSelected !== member) {
                if (!swapMembers(inlineSelected, member)) return;

                selectMember(null);
                autoSaveInlineLineup();
                return;
            }

            selectMember(inlineSelected === member ? null : member);
        });

        return member;
    }

    function saveInlineLineup() {
        const members = [];

        document.querySelectorAll('.inline-match-cell').forEach(cell => {
            const member = cell.querySelector('.inline-member');

            if (member) {
                members.push({
                    user_id: member.dataset.userId,
                    position: cell.dataset.position,
                    absent: member.classList.contains('absent'),
                    late: member.classList.contains('late'),
                });
            }
        });

        pool.querySelectorAll('.inline-member').forEach(member => {
            members.push({
                user_id: member.dataset.userId,
                position: null,
                absent: member.classList.contains('absent'),
                late: member.classList.contains('late'),
            });
        });

        fetch(`/match-teams/${data.teamId}/tate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                date: data.date,
                tate_no: data.tateNo,
                members,
            })
        })
            .then(res => res.json())
            .then(() => {
                if (status) status.innerText = '立順保存済み';
                const modal = document.getElementById('matchLineupModal');
                if (modal) modal.dataset.dirty = '1';
            })
            .catch(() => {
                if (status) status.innerText = '立順保存失敗';
            });
    }

    function autoSaveInlineLineup() {
        if (status) status.innerText = '立順保存中...';
        clearTimeout(inlineSaveTimer);
        inlineSaveTimer = setTimeout(saveInlineLineup, 250);
    }

    grid.innerHTML = '';
    pool.innerHTML = '';
    grid.style.gridTemplateColumns = `repeat(${data.tateSize}, 1fr)`;
    grid.style.direction = 'rtl';

    for (let i = 1; i <= data.tateSize; i++) {
        const cell = document.createElement('div');
        cell.className = 'inline-match-cell';
        cell.dataset.position = i;
        cell.style.direction = 'ltr';
        cell.innerHTML = `<span class="inline-cell-number">${i}</span>`;

        cell.addEventListener('click', () => moveSelectedTo(cell));
        cell.addEventListener('dragover', event => {
            event.preventDefault();
            clearTargets();
            cell.classList.add('inline-drag-over');
        });
        cell.addEventListener('drop', event => {
            event.preventDefault();
            if (!inlineDragged) return;

            const existing = cell.querySelector('.inline-member');

            if (existing && existing !== inlineDragged) {
                if (!swapMembers(inlineDragged, existing)) {
                    clearTargets();
                    return;
                }
            } else {
                cell.appendChild(inlineDragged);
            }

            clearTargets();
            autoSaveInlineLineup();
        });

        grid.appendChild(cell);
    }

    const cells = Array.from(document.querySelectorAll('.inline-match-cell'));

    source.querySelectorAll('.source-member').forEach(sourceEl => {
        const member = makeMember(sourceEl);
        const position = parseInt(sourceEl.dataset.position);

        if (!member.classList.contains('absent') && !member.classList.contains('late') && position && cells[position - 1]) {
            cells[position - 1].appendChild(member);
        } else {
            pool.appendChild(member);
        }
    });

    sortInlinePoolMembers();

    pool.addEventListener('click', () => moveSelectedTo(pool));
    pool.addEventListener('dragover', event => {
        event.preventDefault();
        clearTargets();
        pool.classList.add('inline-drag-over');
    });
    pool.addEventListener('drop', event => {
        event.preventDefault();
        if (inlineDragged) {
            if (!confirmRemoveRecordedMember(inlineDragged)) {
                clearTargets();
                return;
            }

            pool.appendChild(inlineDragged);
            sortInlinePoolMembers();
        }
        clearTargets();
        autoSaveInlineLineup();
    });
}

function openMatchLineupModal(teamId, tateNo) {
    const source = document.querySelector(`.lineup-source[data-team-id="${teamId}"][data-tate-no="${tateNo}"]`);
    const modal = document.getElementById('matchLineupModal');
    const title = document.getElementById('matchLineupModalTitle');
    const status = document.getElementById('matchLineupSaveStatus');

    if (!source || !modal) return;

    window.inlineMatchLineupData = {
        teamId,
        date: source.dataset.date,
        tateNo,
        tateSize: parseInt(source.dataset.tateSize),
    };

    modal.hidden = false;
    modal.dataset.dirty = '';
    document.body.classList.add('modal-open');

    if (title) title.innerText = `${source.dataset.teamName} ${tateNo}立目`;
    if (status) status.innerText = '保存済み';

    initInlineMatchLineup();
}

function closeMatchLineupModal() {
    const modal = document.getElementById('matchLineupModal');
    if (!modal) return;

    const shouldReload = modal.dataset.dirty === '1';
    modal.hidden = true;
    document.body.classList.remove('modal-open');

    if (shouldReload) {
        saveMatchAddTateScrollPosition();
        window.location.reload();
    }
}

window.openMatchLineupModal = openMatchLineupModal;
window.closeMatchLineupModal = closeMatchLineupModal;

function openMatchTeamCreateModal() {
    const modal = document.getElementById('matchTeamCreateModal');
    if (!modal) return;

    modal.hidden = false;
    document.body.classList.add('modal-open');

    const nameInput = modal.querySelector('input[name="name"]');
    if (nameInput) {
        setTimeout(() => nameInput.focus(), 50);
    }
}

function closeMatchTeamCreateModal() {
    const modal = document.getElementById('matchTeamCreateModal');
    if (!modal) return;

    modal.hidden = true;
    document.body.classList.remove('modal-open');
}

window.openMatchTeamCreateModal = openMatchTeamCreateModal;
window.closeMatchTeamCreateModal = closeMatchTeamCreateModal;

function initMatchTeamBoardScroll() {
    const scrollArea = document.querySelector('.match-score-scroll');
    const board = document.querySelector('.match-team-board');
    const target = scrollArea || board;
    if (!target || !board) return;

    target.addEventListener('wheel', event => {
        if (window.matchMedia('(pointer: coarse)').matches) return;

        const isMostlyHorizontal = Math.abs(event.deltaX) > Math.abs(event.deltaY);
        const canScrollX = target.scrollWidth > target.clientWidth;

        if (canScrollX && event.shiftKey && !isMostlyHorizontal) {
            target.scrollLeft += event.deltaY;
            event.preventDefault();
            return;
        }

        if (canScrollX && isMostlyHorizontal) {
            target.scrollLeft += event.deltaX;
            event.preventDefault();
        }
    }, { passive: false });
}

window.addEventListener('load', initMatchTeamBoardScroll);

const matchTimerIntervals = new Map();

function formatMatchTime(seconds) {
    const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
    const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
    return `${mins}:${secs}`;
}

function getTimerBox(button) {
    return button.closest('.match-timer');
}

function matchTimerKey(box) {
    return `${box.dataset.teamId}-${box.dataset.date}-${box.dataset.tateNo}`;
}

function saveMatchTimer(box, isRunning = false) {
    if (!box) return;

    box.dataset.running = isRunning ? '1' : '0';

    fetch(`/match-teams/${box.dataset.teamId}/tate-timer`, {
        method: 'POST',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            date: box.dataset.date,
            tate_no: box.dataset.tateNo,
            elapsed_seconds: parseInt(box.dataset.elapsed || '0'),
            is_running: isRunning,
        })
    });
}

function startMatchTimer(box) {
    if (!box) return;

    const key = matchTimerKey(box);
    const button = box.querySelector('.btn-outline-success, .btn-outline-danger');

    if (matchTimerIntervals.has(key)) {
        return;
    }

    box.dataset.running = '1';

    if (button) {
        button.innerText = '停止';
        button.classList.remove('btn-outline-success');
        button.classList.add('btn-outline-danger');
    }

    matchTimerIntervals.set(key, setInterval(() => {
        const elapsed = parseInt(box.dataset.elapsed || '0') + 1;
        box.dataset.elapsed = elapsed;
        const display = box.querySelector('.match-timer-display');
        if (display) display.innerText = formatMatchTime(elapsed);
    }, 1000));
}

function stopMatchTimer(box) {
    if (!box) return;

    const key = matchTimerKey(box);
    const button = box.querySelector('.btn-outline-danger, .btn-outline-success');

    if (matchTimerIntervals.has(key)) {
        clearInterval(matchTimerIntervals.get(key));
        matchTimerIntervals.delete(key);
    }

    box.dataset.running = '0';

    if (button) {
        button.innerText = '開始';
        button.classList.remove('btn-outline-danger');
        button.classList.add('btn-outline-success');
    }
}

function toggleMatchTimer(button) {
    const box = getTimerBox(button);
    if (!box) return;

    const key = matchTimerKey(box);

    if (matchTimerIntervals.has(key)) {
        stopMatchTimer(box);
        saveMatchTimer(box, false);
        return;
    }

    startMatchTimer(box);
    saveMatchTimer(box, true);
}

function resetMatchTimer(button) {
    const box = getTimerBox(button);
    if (!box) return;

    stopMatchTimer(box);
    box.dataset.elapsed = 0;
    const display = box.querySelector('.match-timer-display');

    if (display) display.innerText = '00:00';

    saveMatchTimer(box, false);
}

function initMatchTimers() {
    document.querySelectorAll('.match-timer[data-running="1"]').forEach(box => {
        startMatchTimer(box);
    });
}

window.addEventListener('load', initMatchTimers);
window.toggleMatchTimer = toggleMatchTimer;
window.resetMatchTimer = resetMatchTimer;
