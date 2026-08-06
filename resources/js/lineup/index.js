const grid = document.getElementById('grid');
const pool = document.getElementById('pool');
const tateSelect = document.getElementById('tateSize');
const source = document.getElementById('membersSource');
const saveStatus = document.getElementById('saveStatus');
const memberSearch = document.getElementById('memberSearch');
const lineupSummary = document.getElementById('lineupSummary');
const selectedMemberLabel = document.getElementById('selectedMemberLabel');
const poolCount = document.getElementById('poolCount');
const poolTools = document.getElementById('poolTools');
const compactEmptyCellsToggle = document.getElementById('compactEmptyCellsToggle');
const officialRecordsReturnLink = document.getElementById('officialRecordsReturnLink');
const canEditLineup = window.lineupData?.canEdit !== false;
const canDragLineupMembers = false;

let dragged = null;
let selectedMember = null;
let saveTimer = null;
let longPressTimer = null;
let longPressed = false;
let extraRows = 0;
let currentTateSize = parseInt(tateSelect.value);
let currentPoolFilter = 'all';

function makeMember(sourceEl) {
    const div = document.createElement('div');
    div.className = 'member';

    if (sourceEl.dataset.gender === 'male') {
        div.classList.add('male');
    }

    if (sourceEl.dataset.gender === 'female') {
        div.classList.add('female');
    }

    if (sourceEl.dataset.gradeColor) {
        div.classList.add('grade-colored');
        div.style.backgroundColor = sourceEl.dataset.gradeColor;
        div.style.borderColor = sourceEl.dataset.gradeColor;
        div.style.color = sourceEl.dataset.gradeTextColor || '#222';
    }

    if (sourceEl.classList.contains('absent')) {
        div.classList.add('absent');
    }

    if (sourceEl.classList.contains('late')) {
        div.classList.add('late');
    }

    div.draggable = canEditLineup && canDragLineupMembers;
    div.dataset.id = sourceEl.dataset.id;
    div.dataset.hasRecord = sourceEl.dataset.hasRecord || '0';
    div.dataset.inLatestMatch = sourceEl.dataset.inLatestMatch || '0';
    div.dataset.latestMatchColor = sourceEl.dataset.latestMatchColor || '';
    div.dataset.latestMatchPositionLabel = sourceEl.dataset.latestMatchPositionLabel || '';
    div.dataset.gender = sourceEl.dataset.gender || '';
    div.dataset.gradeLevel = sourceEl.dataset.gradeLevel || '';
    const memberName = sourceEl.textContent.trim();
    div.dataset.displayName = memberName;
    div.dataset.name = memberName.toLowerCase();

    if (div.dataset.inLatestMatch === '1' && div.dataset.latestMatchPositionLabel) {
        div.classList.add('has-latest-match-position-label');

        const positionLabel = document.createElement('span');
        positionLabel.className = 'latest-match-position-label';
        positionLabel.textContent = div.dataset.latestMatchPositionLabel;
        div.appendChild(positionLabel);
    }

    const nameLabel = document.createElement('span');
    nameLabel.className = 'member-name';
    nameLabel.textContent = memberName;
    div.appendChild(nameLabel);

    if (memberName.length >= 5) {
        div.classList.add('long-name');
    }
    if (div.dataset.inLatestMatch === '1') {
        div.classList.add('in-latest-match');
        if (div.dataset.latestMatchColor) {
            div.style.setProperty('--latest-match-color', div.dataset.latestMatchColor);
        }
    }

    if (!canEditLineup) {
        return div;
    }

    if (canDragLineupMembers) {
        // ===== PCドラッグ =====
        div.addEventListener('dragstart', () => {
            dragged = div;
            setTimeout(() => div.style.opacity = '0.5', 0);
        });

        div.addEventListener('dragend', () => {
            div.style.opacity = '1';
            dragged = null;
            clearDragOver();
        });
    }

    // ===== スマホ長押し（欠席） =====
    div.addEventListener('touchstart', () => {
        longPressed = false;
        longPressTimer = setTimeout(() => {
            longPressed = true;
            if (!cycleAttendance(div)) return;

            selectMember(null);
            updatePoolView();
            autoSave();
        }, 600);
    }, { passive: true });

    div.addEventListener('touchend', () => {
        clearTimeout(longPressTimer);
    });

    div.addEventListener('touchmove', () => {
        clearTimeout(longPressTimer);
    });

    // ===== PCダブルクリック（欠席）←これ追加 =====
    div.addEventListener('dblclick', (e) => {
        e.stopPropagation();
        if (!cycleAttendance(div)) return;

        selectMember(null);
        updatePoolView();
        autoSave();
    });

    // ===== タップ（選択・入れ替え） =====
    div.addEventListener('click', (e) => {
        e.stopPropagation();

        if (longPressed) {
            longPressed = false;
            return;
        }

        // 入れ替え
        if (selectedMember && selectedMember !== div) {
            if (!swapMembers(selectedMember, div)) return;

            selectMember(null);
            updatePoolView();
            autoSave();
            return;
        }

        // 選択ON/OFF
        if (selectedMember === div) {
            selectMember(null);
        } else {
            selectMember(div);
        }
    });

    return div;
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

function isPlacedMember(member) {
    return member?.parentElement?.classList.contains('cell');
}

function isPoolElement(element) {
    return element?.id === 'pool' || element?.classList.contains('pool');
}

function hasEnteredRecord(member) {
    return member?.dataset.hasRecord === '1';
}

function isInLatestMatch(member) {
    return member?.dataset.inLatestMatch === '1';
}

function confirmRemoveRecordedMember(member) {
    if (!isPlacedMember(member) || !hasEnteredRecord(member)) {
        return true;
    }

    return window.confirm('現在の記録ページに的中が入っている人を選択外に移動すると、記録が一覧に残らなくなります。よろしいですか？');
}

function selectMember(member) {
    document.querySelectorAll('.member.selected').forEach(el => {
        el.classList.remove('selected');
    });

    selectedMember = member;

    document.querySelectorAll('.cell, .pool').forEach(el => {
        el.classList.remove('tap-target');
    });

    if (member) {
        member.classList.add('selected');
        document.querySelectorAll('.cell').forEach(el => el.classList.add('tap-target'));
        pool.classList.add('tap-target');
        if (selectedMemberLabel) {
            selectedMemberLabel.innerText = `選択中: ${member.dataset.displayName || member.textContent.trim()}`;
        }
    } else if (selectedMemberLabel) {
        selectedMemberLabel.innerText = '選択なし';
    }
}

function moveSelectedTo(target) {
    if (!selectedMember) return;

    if (target.classList.contains('cell')) {
        const existing = target.querySelector('.member');

        if (existing && existing !== selectedMember) {
            // 空いてないマスなら入れ替え
            if (!swapMembers(selectedMember, existing)) return;
        } else {
            target.appendChild(selectedMember);
        }
    }

    if (target.id === 'pool') {
        if (!confirmRemoveRecordedMember(selectedMember)) return;

        pool.appendChild(selectedMember);
        sortPoolMembers();
    }

    selectMember(null);
    updatePoolView();
    autoSave();
}

function getCellsRightToLeft() {
    const size = parseInt(tateSelect.value);
    const cells = Array.from(document.querySelectorAll('#grid .cell'));
    let ordered = [];

    for (let i = 0; i < cells.length; i += size) {
        const row = cells.slice(i, i + size);
        ordered = ordered.concat(row.reverse());
    }

    return ordered;
}

function getRealMembers() {
    return Array.from(document.querySelectorAll('#grid .member, #pool .member'));
}

function updateOfficialRecordsReturnLink() {
    if (!officialRecordsReturnLink || !compactEmptyCellsToggle) {
        return;
    }

    const url = new URL(officialRecordsReturnLink.href, window.location.origin);
    url.searchParams.set('compact_empty_slots', compactEmptyCellsToggle.checked ? '1' : '0');
    officialRecordsReturnLink.href = `${url.pathname}${url.search}${url.hash}`;
}

function clearDragOver() {
    document.querySelectorAll('.drag-over').forEach(el => {
        el.classList.remove('drag-over');
    });
}

function getMaxSavedPosition() {
    let max = 0;

    source.querySelectorAll('.source-member').forEach(el => {
        const pos = parseInt(el.dataset.position);
        if (!isNaN(pos)) {
            max = Math.max(max, pos);
        }
    });

    return max;
}
function renderGrid(minRows = 0) {
    const size = parseInt(tateSelect.value);
    const total = source.querySelectorAll('.source-member').length;

    const baseRows = Math.max(1, Math.ceil(total / size));
    const savedRows = Math.ceil(getMaxSavedPosition() / size);

    const rows = Math.max(baseRows, savedRows, minRows) + extraRows;

    grid.innerHTML = '';
    grid.style.gridTemplateColumns = `repeat(${size}, 1fr)`;

    for (let i = 0; i < rows * size; i++) {
        const cell = document.createElement('div');
        cell.className = 'cell';

        const num = document.createElement('span');
        num.className = 'cell-number';
        cell.appendChild(num);

        if (canEditLineup) {
            cell.addEventListener('click', () => {
                moveSelectedTo(cell);
            });

            if (canDragLineupMembers) {
                cell.addEventListener('dragover', e => {
                    e.preventDefault();
                    clearDragOver();
                    cell.classList.add('drag-over');
                });

                cell.addEventListener('drop', e => {
                    e.preventDefault();

                    if (!dragged) return;

                    const existing = cell.querySelector('.member');

                    if (existing && existing !== dragged) {
                        if (!swapMembers(dragged, existing)) {
                            clearDragOver();
                            return;
                        }
                    } else {
                        cell.appendChild(dragged);
                    }

                    clearDragOver();
                    updatePoolView();
                    autoSave();
                });
            }
        }

        grid.appendChild(cell);
    }

    setCellNumbers();
}
function getCellsRightToLeftBySize(size) {
    const cells = Array.from(document.querySelectorAll('#grid .cell'));
    let ordered = [];

    for (let i = 0; i < cells.length; i += size) {
        const row = cells.slice(i, i + size);
        ordered = ordered.concat(row.reverse());
    }

    return ordered;
}

function rerenderKeepingMembers(oldSize, newSize) {
    const items = [];
    let maxNewPosition = 0;

    getCellsRightToLeftBySize(oldSize).forEach((cell, index) => {
        const member = cell.querySelector('.member');

        if (member) {
            const oldPosition = index + 1;
            const oldTateNo = Math.ceil(oldPosition / oldSize);
            const indexInTate = ((oldPosition - 1) % oldSize) + 1;

            let newPosition = ((oldTateNo - 1) * newSize) + indexInTate;

            if (newSize < oldSize && indexInTate > newSize) {
                newPosition = null;
            }

            if (newPosition) {
                maxNewPosition = Math.max(maxNewPosition, newPosition);
            }

            items.push({
                member: member,
                position: newPosition
            });
        }
    });

    pool.querySelectorAll('.member').forEach(member => {
        items.push({
            member: member,
            position: null
        });
    });

    const requiredRows = Math.ceil(maxNewPosition / newSize);

    renderGrid(requiredRows);

    const cells = getCellsRightToLeft();

    items.forEach(item => {
        if (item.position && cells[item.position - 1]) {
            cells[item.position - 1].appendChild(item.member);
        } else {
            pool.appendChild(item.member);
        }
    });
}

function addLineupRow() {
    if (!canEditLineup) return;

    extraRows++;
    rerenderKeepingMembers(currentTateSize, currentTateSize);
    autoSave();
}

function setCellNumbers() {
    const cells = getCellsRightToLeft();

    cells.forEach((cell, index) => {
        const num = cell.querySelector('.cell-number');
        if (num) num.textContent = index + 1;
    });
}

function initMembers(reset = false) {
    pool.innerHTML = '';
    renderGrid();

    Array.from(source.querySelectorAll('.source-member')).forEach(sourceEl => {
        const member = makeMember(sourceEl);
        const position = reset ? null : sourceEl.dataset.position;

        if (position) {
            const cells = getCellsRightToLeft();
            const cell = cells[parseInt(position) - 1];

            if (cell) cell.appendChild(member);
            else pool.appendChild(member);
        } else {
            pool.appendChild(member);
        }
    });

    updatePoolView();
}

function sortPoolMembers() {
    const members = Array.from(pool.querySelectorAll('.member'));
    const usesGrades = Boolean(window.lineupData?.usesGrades);

    members
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

if (canEditLineup) {
    pool.addEventListener('click', () => {
        moveSelectedTo(pool);
    });

    if (canDragLineupMembers) {
        pool.addEventListener('dragover', e => {
            e.preventDefault();
            clearDragOver();
            pool.classList.add('drag-over');
        });

        pool.addEventListener('drop', e => {
            e.preventDefault();

            if (dragged) {
                if (!confirmRemoveRecordedMember(dragged)) {
                    clearDragOver();
                    return;
                }

                pool.appendChild(dragged);
            }

            clearDragOver();
            updatePoolView();
            autoSave();
        });
    }
}

if (canEditLineup) {
    tateSelect.addEventListener('change', () => {
        const newSize = parseInt(tateSelect.value);

        rerenderKeepingMembers(currentTateSize, newSize);

        currentTateSize = newSize;
        autoSave();
    });
}

if (compactEmptyCellsToggle) {
    updateOfficialRecordsReturnLink();
    compactEmptyCellsToggle.addEventListener('change', updateOfficialRecordsReturnLink);
}

function randomize() {
    if (!canEditLineup) return;

    // 未配置欄で現在表示されている人だけ対象
    const members = Array.from(pool.querySelectorAll('.member'))
        .filter(member =>
            memberMatchesFilter(member) &&
            !isInLatestMatch(member) &&
            !member.classList.contains('absent') &&
            !member.classList.contains('late')
        );

    const absentMembers = Array.from(pool.querySelectorAll('.member'))
    .filter(member =>
        memberMatchesFilter(member) &&
        (member.classList.contains('absent') ||
            member.classList.contains('late'))
    );

    for (let i = members.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [members[i], members[j]] = [members[j], members[i]];
    }

    // 空いているマスだけ取得
    const emptyCells = getCellsRightToLeft()
        .filter(cell => !cell.querySelector('.member'));

    members.forEach((member, index) => {
        if (emptyCells[index]) {
            member.classList.remove('hidden-by-filter');
            emptyCells[index].appendChild(member);
        }
    });

    absentMembers.forEach(member => {
        pool.appendChild(member);
    });

    updatePoolView();
    autoSave();
}

function autoSave() {
    if (!canEditLineup) return;

    saveStatus.innerText = '保存中...';

    clearTimeout(saveTimer);

    saveTimer = setTimeout(() => {
        save(false);
    }, 150);
}

function save(showAlert = false) {
    if (!canEditLineup) return;

    let list = [];
    const cells = getCellsRightToLeft();

    cells.forEach((cell, index) => {
        const member = cell.querySelector('.member');

        if (member) {
            list.push({
                id: member.dataset.id,
                position: index + 1,
                absent: member.classList.contains('absent'),
                late: member.classList.contains('late')
            });
        }
    });

    document.querySelectorAll('#pool .member').forEach(member => {
       list.push({
            id: member.dataset.id,
            position: null,
            absent: member.classList.contains('absent'),
            late: member.classList.contains('late')
        });
    });

    fetch(`/lineup/${window.lineupData.lineupId}/save`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            members: list,
            tate_size: tateSelect.value
        })
    })
    .then(res => res.json())
    .then(() => {
        saveStatus.innerText = '保存済み';
        if (showAlert) alert('保存OK');
    })
    .catch(() => {
        saveStatus.innerText = '保存失敗';
        if (showAlert) alert('保存に失敗しました');
    });
}
function clearAll() {
    if (!canEditLineup) return;

    if (!confirm('本当に全員を未配置にしますか？\nページを切り替えていない場合、現在の立順が保存されません。')) {
        return;
    }

    const recordedMembers = Array.from(document.querySelectorAll('#grid .member'))
        .filter(hasEnteredRecord);

    if (recordedMembers.length > 0
        && !confirm('現在の記録ページに的中が入っている人を選択外に移動すると、記録が一覧に残らなくなります。よろしいですか？')) {
        return;
    }

    document.querySelectorAll('#grid .member').forEach(member => {
        pool.appendChild(member);
    });

    selectMember(null);
    updatePoolView();
    autoSave();
}
document.addEventListener('DOMContentLoaded', function () {
    const flashes = document.querySelectorAll('.flash-message');

    flashes.forEach(flash => {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.5s, transform 0.5s';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';

            setTimeout(() => {
                flash.remove();
            }, 500);
        }, 2000);
    });
});



document.addEventListener('DOMContentLoaded', () => {
    initMembers(false);

    const flashes = document.querySelectorAll('.flash-message');

    flashes.forEach(flash => {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.5s, transform 0.5s';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';

            setTimeout(() => {
                flash.remove();
            }, 500);
        }, 2000);
    });
});
function cycleAttendance(div) {

    // 出席 → 遅刻
    if (!div.classList.contains('late') &&
        !div.classList.contains('absent')) {

        if (!confirmRemoveRecordedMember(div)) {
            return false;
        }

        div.classList.add('late');
        pool.appendChild(div);
        sortPoolMembers();
        return true;
    }

    // 遅刻 → 欠席
    if (div.classList.contains('late')) {
        div.classList.remove('late');
        div.classList.add('absent');
        pool.appendChild(div);
        sortPoolMembers();
        return true;
    }

    // 欠席 → 出席
    if (div.classList.contains('absent')) {
        div.classList.remove('absent');
        sortPoolMembers();
    }

    updatePoolView();
    return true;
}

function memberMatchesFilter(member) {
    const keyword = (memberSearch?.value || '').trim().toLowerCase();
    const name = member.dataset.name || member.textContent.trim().toLowerCase();

    if (keyword && !name.includes(keyword)) {
        return false;
    }

    if (currentPoolFilter === 'male') return member.dataset.gender === 'male';
    if (currentPoolFilter === 'female') return member.dataset.gender === 'female';
    if (currentPoolFilter === 'active') {
        return !member.classList.contains('absent') && !member.classList.contains('late');
    }
    if (currentPoolFilter === 'unavailable') {
        return member.classList.contains('absent') || member.classList.contains('late');
    }

    return true;
}

function updatePoolView() {
    sortPoolMembers();

    const placed = document.querySelectorAll('#grid .member').length;
    const unplaced = document.querySelectorAll('#pool .member').length;
    const total = placed + unplaced;

    if (lineupSummary) {
        lineupSummary.innerText = `配置 ${placed} / 未配置 ${unplaced} / 合計 ${total}`;
    }

    if (poolCount) {
        poolCount.innerText = `${unplaced}人`;
    }

    document.querySelectorAll('#pool .member').forEach(member => {
        member.classList.toggle('hidden-by-filter', !memberMatchesFilter(member));
    });

    document.querySelectorAll('#grid .member').forEach(member => {
        member.classList.remove('hidden-by-filter');
    });
}

function setPoolFilter(filter) {
    currentPoolFilter = filter;

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });

    updatePoolView();
}

function togglePoolPanel() {
    if (!pool || !poolTools) return;

    pool.classList.toggle('pool-collapsed');
    poolTools.classList.toggle('pool-collapsed');
}

if (memberSearch) {
    memberSearch.addEventListener('input', updatePoolView);
}

window.addLineupRow = addLineupRow;
window.randomize = randomize;
window.clearAll = clearAll;
window.setPoolFilter = setPoolFilter;
window.togglePoolPanel = togglePoolPanel;
