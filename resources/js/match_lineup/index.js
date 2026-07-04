const grid = document.getElementById('matchGrid');
const pool = document.getElementById('matchPool');
const source = document.getElementById('membersSource');
const saveStatus = document.getElementById('saveStatus');

let dragged = null;
let selectedMember = null;
let saveTimer = null;
let longPressTimer = null;
let longPressed = false;

function makeMember(sourceEl) {
    const div = document.createElement('div');
    div.className = 'match-member';
    div.draggable = true;
    div.dataset.userId = sourceEl.dataset.userId;
    div.textContent = sourceEl.textContent.trim();

    if (sourceEl.dataset.gender === 'male') div.classList.add('male');
    if (sourceEl.dataset.gender === 'female') div.classList.add('female');
    if (sourceEl.dataset.gradeColor) {
        div.classList.add('grade-colored');
        div.style.backgroundColor = sourceEl.dataset.gradeColor;
        div.style.borderColor = sourceEl.dataset.gradeColor;
        div.style.color = sourceEl.dataset.gradeTextColor || '#222';
    }
    if (sourceEl.dataset.late === '1' || sourceEl.classList.contains('late')) div.classList.add('late');
    if (sourceEl.dataset.absent === '1' || sourceEl.classList.contains('absent')) div.classList.add('absent');
    if (div.textContent.length >= 5) div.classList.add('long-name');

    function cycleAttendance() {
        if (!div.classList.contains('late') && !div.classList.contains('absent')) {
            div.classList.add('late');
            pool.appendChild(div);
            sortPoolMembers();
            return;
        }

        if (div.classList.contains('late')) {
            div.classList.remove('late');
            div.classList.add('absent');
            pool.appendChild(div);
            sortPoolMembers();
            return;
        }

        div.classList.remove('absent');
        sortPoolMembers();
    }

    div.addEventListener('dragstart', () => {
        dragged = div;
        setTimeout(() => div.style.opacity = '0.5', 0);
    });

    div.addEventListener('dragend', () => {
        div.style.opacity = '1';
        dragged = null;
        clearDragOver();
    });

    div.addEventListener('touchstart', () => {
        longPressed = false;
        longPressTimer = setTimeout(() => {
            longPressed = true;
            cycleAttendance();
            selectMember(null);
            autoSave();
        }, 600);
    }, { passive: true });

    div.addEventListener('touchend', () => clearTimeout(longPressTimer));
    div.addEventListener('touchmove', () => clearTimeout(longPressTimer));

    div.addEventListener('dblclick', (event) => {
        event.stopPropagation();
        cycleAttendance();
        selectMember(null);
        autoSave();
    });

    div.addEventListener('click', (event) => {
        event.stopPropagation();

        if (longPressed) {
            longPressed = false;
            return;
        }

        if (selectedMember && selectedMember !== div) {
            swapMembers(selectedMember, div);
            selectMember(null);
            autoSave();
            return;
        }

        selectMember(selectedMember === div ? null : div);
    });

    return div;
}

function swapMembers(a, b) {
    const aParent = a.parentElement;
    const bParent = b.parentElement;
    const aNext = a.nextSibling;
    const bNext = b.nextSibling;

    if (aParent === bParent) {
        bParent.insertBefore(a, bNext);
        aParent.insertBefore(b, aNext);
    } else {
        aParent.insertBefore(b, aNext);
        bParent.insertBefore(a, bNext);
    }
}

function selectMember(member) {
    document.querySelectorAll('.match-member.selected').forEach(el => el.classList.remove('selected'));
    selectedMember = member;

    document.querySelectorAll('.match-cell, .match-pool').forEach(el => el.classList.remove('tap-target'));

    if (member) {
        member.classList.add('selected');
        document.querySelectorAll('.match-cell').forEach(el => el.classList.add('tap-target'));
        pool.classList.add('tap-target');
    }
}

function moveSelectedTo(target) {
    if (!selectedMember) return;

    if (target.classList.contains('match-cell')) {
        const existing = target.querySelector('.match-member');

        if (existing && existing !== selectedMember) {
            swapMembers(selectedMember, existing);
        } else {
            target.appendChild(selectedMember);
        }
    }

    if (target.id === 'matchPool') {
        pool.appendChild(selectedMember);
        sortPoolMembers();
    }

    selectMember(null);
    autoSave();
}

function sortPoolMembers() {
    Array.from(pool.querySelectorAll('.match-member'))
        .sort((a, b) => {
            const aUnavailable = a.classList.contains('absent') || a.classList.contains('late');
            const bUnavailable = b.classList.contains('absent') || b.classList.contains('late');

            return Number(aUnavailable) - Number(bUnavailable);
        })
        .forEach(member => pool.appendChild(member));
}

function clearDragOver() {
    document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
}

function renderGrid() {
    if (!grid || !pool || !source || !window.matchLineupData) return;

    const size = window.matchLineupData.tateSize;
    grid.innerHTML = '';
    grid.style.gridTemplateColumns = `repeat(${size}, 1fr)`;

    for (let i = 1; i <= size; i++) {
        const cell = document.createElement('div');
        cell.className = 'match-cell';
        cell.dataset.position = i;

        const num = document.createElement('span');
        num.className = 'cell-number';
        num.textContent = i;
        cell.appendChild(num);

        cell.addEventListener('click', () => moveSelectedTo(cell));
        cell.addEventListener('dragover', event => {
            event.preventDefault();
            clearDragOver();
            cell.classList.add('drag-over');
        });
        cell.addEventListener('drop', event => {
            event.preventDefault();
            if (!dragged) return;

            const existing = cell.querySelector('.match-member');
            if (existing && existing !== dragged) {
                swapMembers(dragged, existing);
            } else {
                cell.appendChild(dragged);
            }

            clearDragOver();
            autoSave();
        });

        grid.appendChild(cell);
    }
}

function initMembers() {
    if (!source) return;

    pool.innerHTML = '';
    renderGrid();

    const cells = Array.from(document.querySelectorAll('.match-cell'));

    source.querySelectorAll('.source-member').forEach(sourceEl => {
        const member = makeMember(sourceEl);
        const position = parseInt(sourceEl.dataset.position);

        if (!member.classList.contains('absent') && !member.classList.contains('late') && position && cells[position - 1]) {
            cells[position - 1].appendChild(member);
        } else {
            pool.appendChild(member);
        }
    });

    sortPoolMembers();
}

function autoSave() {
    if (!saveStatus) return;

    saveStatus.innerText = '保存中...';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 150);
}

function save() {
    const members = [];

    document.querySelectorAll('.match-cell').forEach(cell => {
        const member = cell.querySelector('.match-member');

        if (member) {
            members.push({
                user_id: member.dataset.userId,
                position: cell.dataset.position,
                absent: member.classList.contains('absent'),
                late: member.classList.contains('late'),
            });
        }
    });

    pool.querySelectorAll('.match-member').forEach(member => {
        members.push({
            user_id: member.dataset.userId,
            position: null,
            absent: member.classList.contains('absent'),
            late: member.classList.contains('late'),
        });
    });

    fetch(`/match-teams/${window.matchLineupData.teamId}/tate`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            date: window.matchLineupData.date,
            tate_no: window.matchLineupData.tateNo,
            members,
        }),
    })
        .then(res => res.json())
        .then(() => {
            saveStatus.innerText = '保存済み';
        })
        .catch(() => {
            saveStatus.innerText = '保存失敗';
        });
}

if (pool) {
    pool.addEventListener('click', () => moveSelectedTo(pool));
    pool.addEventListener('dragover', event => {
        event.preventDefault();
        clearDragOver();
        pool.classList.add('drag-over');
    });
    pool.addEventListener('drop', event => {
        event.preventDefault();
        if (dragged) {
            pool.appendChild(dragged);
            sortPoolMembers();
        }
        clearDragOver();
        autoSave();
    });
}

document.addEventListener('DOMContentLoaded', initMembers);
