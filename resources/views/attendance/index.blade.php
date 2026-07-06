@extends('layouts.user')

@section('content')

<div class="container py-3">

<h4>{{ $group->name }}｜{{ $isHost ? '出席管理' : '出欠確認' }}</h4>

<form method="GET" action="/group/{{ $group->id }}/attendance" class="mb-4 text-center">
    <div class="attendance-date-nav d-flex justify-content-center align-items-center gap-3">

        <a href="/group/{{ $group->id }}/attendance?date={{ \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d') }}"
           class="btn btn-outline-secondary">
            ＜
        </a>

        <input type="date"
               name="date"
               value="{{ $date }}"
               onchange="this.form.submit()"
               class="form-control text-center"
               style="max-width:180px;">

        <a href="/group/{{ $group->id }}/attendance?date={{ \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d') }}"
           class="btn btn-outline-secondary">
            ＞
        </a>

    </div>
</form>

@php
    $weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];
@endphp

@if($isHost)
    <div class="host-attendance-grid">
        @foreach($attendanceMembers as $row)
            @php
                $memberUser = $row['user'];
                $lineupMember = $row['member'];
                $status = $lineupMember->is_absent ? 'absent' : ($lineupMember->is_late ? 'late' : 'present');
                $memberAttendanceWeekdays = collect($memberUser->attendance_weekdays ?? [])->map(fn($day) => (int) $day);
            @endphp
            <div class="attendance-card host-attendance-card" data-user-id="{{ $memberUser->id }}">
                <div class="user-name">{{ $memberUser->name }}</div>

                <div class="status-text mb-3" data-member-status="{{ $memberUser->id }}">
                    @if($status === 'absent')
                        現在：欠席
                    @elseif($status === 'late')
                        現在：遅刻
                    @else
                        現在：出席
                    @endif
                </div>

                <div class="attendance-actions host-attendance-actions">
                    <button type="button"
                            data-member-button="{{ $memberUser->id }}"
                            data-status-button="present"
                            class="btn {{ $status === 'present' ? 'btn-success' : 'btn-outline-success' }}"
                            onclick="setMemberAttendance({{ $memberUser->id }}, 'present')">
                        出席
                    </button>
                    <button type="button"
                            data-member-button="{{ $memberUser->id }}"
                            data-status-button="absent"
                            class="btn {{ $status === 'absent' ? 'btn-danger' : 'btn-outline-danger' }}"
                            onclick="setMemberAttendance({{ $memberUser->id }}, 'absent')">
                        欠席
                    </button>
                    <button type="button"
                            data-member-button="{{ $memberUser->id }}"
                            data-status-button="late"
                            class="btn {{ $status === 'late' ? 'btn-warning' : 'btn-outline-warning' }}"
                            onclick="setMemberAttendance({{ $memberUser->id }}, 'late')">
                        遅刻
                    </button>
                </div>

                <div class="text-muted mt-3" data-member-save-status="{{ $memberUser->id }}" style="font-size:13px;">
                    保存済み
                </div>

                <div class="form-check form-switch d-flex justify-content-center align-items-center gap-2 mt-3 mb-2">
                    <input class="form-check-input"
                           type="checkbox"
                           id="allAbsentSwitch{{ $memberUser->id }}"
                           {{ $memberUser->all_absent ? 'checked' : '' }}
                           onchange="setAllAbsent(this.checked, {{ $memberUser->id }})">

                    <label class="form-check-label host-setting-label" for="allAbsentSwitch{{ $memberUser->id }}">
                        全ての日を欠席
                    </label>
                </div>

                <details class="attendance-detail mt-2">
                    <summary>主催設定</summary>

                    <div class="attendance-detail-body">
                        <div class="detail-title">参加する曜日</div>
                        <div class="text-muted detail-note">
                            選択した曜日だけ初期状態を出席にします。未選択なら曜日指定なしです。
                        </div>

                        <div class="weekday-options">
                            @foreach($weekdayLabels as $weekdayValue => $weekdayLabel)
                                <input type="checkbox"
                                       class="btn-check attendance-weekday attendance-weekday-{{ $memberUser->id }}"
                                       id="attendanceWeekday{{ $memberUser->id }}-{{ $weekdayValue }}"
                                       value="{{ $weekdayValue }}"
                                       {{ $memberAttendanceWeekdays->contains($weekdayValue) ? 'checked' : '' }}>
                                <label class="btn btn-outline-primary weekday-btn" for="attendanceWeekday{{ $memberUser->id }}-{{ $weekdayValue }}">
                                    {{ $weekdayLabel }}
                                </label>
                            @endforeach
                        </div>

                        <button type="button" class="btn btn-primary w-100 mt-3 setting-save-btn" onclick="saveWeeklySettings({{ $memberUser->id }})">
                            曜日設定を保存
                        </button>
                    </div>
                </details>
            </div>
        @endforeach
    </div>
@else
<div class="attendance-card">

    <div class="user-name">
        {{ $user->name }}
    </div>

    <div class="status-text mb-3" id="statusText">
        @if($member->is_absent)
            現在：欠席
        @elseif($member->is_late)
            現在：遅刻
        @else
            現在：出席
        @endif
    </div>

    <div class="attendance-actions">
        <button type="button"
                id="presentBtn"
                class="btn {{ (!$member->is_absent && !$member->is_late) ? 'btn-success' : 'btn-outline-success' }}"
                onclick="setAttendance('present')">
            出席
        </button>
        <button type="button"
                id="absentBtn"
                class="btn {{ $member->is_absent ? 'btn-danger' : 'btn-outline-danger' }}"
                onclick="setAttendance('absent')">
            欠席
        </button>
        <button type="button"
                id="lateBtn"
                class="btn {{ $member->is_late ? 'btn-warning' : 'btn-outline-warning' }}"
                onclick="setAttendance('late')">
            遅刻
        </button>

       
    </div>

    <div id="saveStatus" class="text-muted mt-3" style="font-size:13px;">
        保存済み
    </div>

    <div class="form-check form-switch d-flex justify-content-center align-items-center gap-2 mb-3">
        <input class="form-check-input"
            type="checkbox"
            id="allAbsentSwitch"
            {{ $user->all_absent ? 'checked' : '' }}
            onchange="setAllAbsent(this.checked)">

        <label class="form-check-label" for="allAbsentSwitch">
            全ての日を欠席にする
        </label>
    </div>

    @php
        $attendanceWeekdays = collect($user->attendance_weekdays ?? [])->map(fn($day) => (int) $day);
    @endphp

    <details class="attendance-detail mt-3">
        <summary>詳細設定</summary>

        <div class="attendance-detail-body">
            <div class="detail-title">参加する曜日</div>
            <div class="text-muted detail-note">
                選択した曜日だけ初期状態を出席にします。未選択なら曜日指定なしです。
            </div>

            <div class="weekday-options">
                @foreach($weekdayLabels as $weekdayValue => $weekdayLabel)
                    <input type="checkbox"
                           class="btn-check attendance-weekday"
                           id="attendanceWeekday{{ $weekdayValue }}"
                           value="{{ $weekdayValue }}"
                           {{ $attendanceWeekdays->contains($weekdayValue) ? 'checked' : '' }}>
                    <label class="btn btn-outline-primary weekday-btn" for="attendanceWeekday{{ $weekdayValue }}">
                        {{ $weekdayLabel }}
                    </label>
                @endforeach
            </div>

            <button type="button" class="btn btn-primary w-100 mt-3" onclick="saveWeeklySettings()">
                曜日設定を保存
            </button>
        </div>
    </details>

    <div class="mt-4 p-3 border rounded bg-light text-center">
        <div class="fw-bold mb-2">
            LINE 連携
        </div>

        @if($user->line_user_id)
            <div class="text-success fw-bold">
                連携済み
            </div>
            <div class="text-muted" style="font-size:13px;">
                LINEグループで「今日休みます」「明日やすみます」「5月10日休みます」
                <br>
                などと送信すると、該当日の出欠が自動で欠席として登録されます。
            </div>
        @else
            <div class="text-muted mb-2" style="font-size:13px;">
                LINEグループで下の文字を送信してください。
                <br>
                ※送信前に専用Botがグループに招待されている必要があります。
            </div>

            <input type="text"
                class="form-control text-center mb-2"
                value="連携{{ $user->line_link_code }}"
                readonly>

            <div class="text-muted" style="font-size:13px;">
                例：連携{{ $user->line_link_code }}
            </div>
        @endif
    </div>
    <div class="mt-3">

        <div class="fw-bold mb-2" style="font-size:14px;">
            Botの追加方法
        </div>

        <div class="text-muted" style="font-size:13px; line-height:1.7;">
            ① 下のボタンからBotを追加<br>
            ② LINEグループにBotを招待<br>
        </div>

        <a href="https://line.me/R/ti/p/@349uxqew"
        target="_blank"
        class="btn btn-success w-100 mt-2">
            LINE Botを追加
        </a>

    </div>
</div>
@endif

<style>
.attendance-card {
    max-width: 400px;
    margin: 0 auto;
    padding: 24px;
    border: 1px solid #ddd;
    border-radius: 12px;
    text-align: center;
    background: #fff;
}

.host-attendance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 14px;
}

.host-attendance-card {
    max-width: none;
    padding: 18px;
}

.user-name {
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 10px;
}

.status-text {
    font-size: 16px;
    font-weight: bold;
}

.attendance-actions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.attendance-card .btn {
    min-width: 0;
    padding: 12px;
    font-size: 18px;
    white-space: nowrap;
}

.attendance-detail {
    text-align: left;
    border: 1px solid #e1e5ea;
    border-radius: 8px;
    padding: 12px;
}

.attendance-detail summary {
    cursor: pointer;
    font-weight: 700;
    text-align: center;
}

.attendance-detail-body {
    padding-top: 12px;
}

.detail-title {
    font-size: 14px;
    font-weight: 700;
}

.detail-note {
    font-size: 12px;
    line-height: 1.6;
}

.weekday-options {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    margin-top: 10px;
}

.weekday-btn {
    min-width: 0 !important;
    padding: 8px 0 !important;
    font-size: 14px !important;
}

.host-setting-label {
    font-size: 13px;
}

.host-attendance-card .attendance-detail {
    padding: 10px;
}

.host-attendance-card .attendance-detail summary {
    font-size: 14px;
}

.host-attendance-card .setting-save-btn {
    font-size: 14px;
    padding: 9px 10px;
}

@media (max-width: 900px) {
    .host-attendance-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .host-attendance-card {
        padding: 14px;
    }

    .host-attendance-card .user-name {
        font-size: 18px;
        min-height: 1.4em;
        overflow-wrap: anywhere;
    }

    .host-attendance-card .status-text {
        font-size: 14px;
        margin-bottom: 10px !important;
    }

    .host-attendance-card .btn {
        padding: 9px 4px;
        font-size: 15px;
    }
}

@media (max-width: 600px) {
    .container.py-3 {
        padding-left: 8px;
        padding-right: 8px;
    }

    .container.py-3 > h4 {
        font-size: 18px;
        line-height: 1.4;
    }

    .attendance-date-nav {
        gap: 6px !important;
    }

    .attendance-date-nav .btn {
        min-width: 42px;
        padding: 8px 10px;
    }

    .attendance-date-nav input[type="date"] {
        max-width: none !important;
        min-width: 0;
        flex: 1 1 auto;
        font-size: 14px;
    }

    .host-attendance-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .attendance-card {
        max-width: none;
        padding: 14px;
    }

    .attendance-card .btn {
        padding: 10px 4px;
        font-size: 15px;
    }

    .user-name {
        font-size: 19px;
        overflow-wrap: anywhere;
    }

    .status-text {
        font-size: 14px;
    }

    .weekday-options {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>

<script>
function setMemberAttendance(userId, status) {
    const buttons = document.querySelectorAll(`[data-member-button="${userId}"]`);
    const statusText = document.querySelector(`[data-member-status="${userId}"]`);
    const saveStatus = document.querySelector(`[data-member-save-status="${userId}"]`);

    buttons.forEach(button => {
        const buttonStatus = button.dataset.statusButton;
        button.className =
            buttonStatus === 'present' ? 'btn btn-outline-success' :
            buttonStatus === 'late' ? 'btn btn-outline-warning' :
            'btn btn-outline-danger';
    });

    const activeButton = document.querySelector(`[data-member-button="${userId}"][data-status-button="${status}"]`);
    if (activeButton) {
        activeButton.className =
            status === 'present' ? 'btn btn-success' :
            status === 'late' ? 'btn btn-warning' :
            'btn btn-danger';
    }

    if (statusText) {
        statusText.innerText =
            status === 'present' ? '現在：出席' :
            status === 'late' ? '現在：遅刻' :
            '現在：欠席';
    }

    if (saveStatus) {
        saveStatus.innerText = '保存中...';
    }

    fetch('/group/{{ $group->id }}/attendance', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            date: '{{ $date }}',
            user_id: userId,
            status: status
        })
    })
    .then(res => {
        if (!res.ok) throw new Error('save failed');
        return res.json();
    })
    .then(() => {
        if (saveStatus) {
            saveStatus.innerText = '保存済み';
        }
    })
    .catch(() => {
        if (saveStatus) {
            saveStatus.innerText = '保存失敗';
        }
    });
}

function setAttendance(status) {
    const presentBtn = document.getElementById('presentBtn');
    const lateBtn = document.getElementById('lateBtn');
    const absentBtn = document.getElementById('absentBtn');
    const statusText = document.getElementById('statusText');
    const saveStatus = document.getElementById('saveStatus');

    presentBtn.className = 'btn btn-outline-success';
    lateBtn.className = 'btn btn-outline-warning';
    absentBtn.className = 'btn btn-outline-danger';

    if (status === 'present') {
        presentBtn.className = 'btn btn-success';
        statusText.innerText = '現在：出席';
    }

    if (status === 'late') {
        lateBtn.className = 'btn btn-warning';
        statusText.innerText = '現在：遅刻';
    }

    if (status === 'absent') {
        absentBtn.className = 'btn btn-danger';
        statusText.innerText = '現在：欠席';
    }

    saveStatus.innerText = '保存中...';

    fetch('/group/{{ $group->id }}/attendance', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            date: '{{ $date }}',
            status: status
        })
    })
    .then(res => res.json())
    .then(() => {
        saveStatus.innerText = '保存済み';
    })
    .catch(() => {
        saveStatus.innerText = '保存失敗';
    });
}
function statusSaveElement(userId = null) {
    return userId
        ? document.querySelector(`[data-member-save-status="${userId}"]`)
        : document.getElementById('saveStatus');
}

function setAllAbsent(isAllAbsent, userId = null) {
    const saveStatus = statusSaveElement(userId);

    if (saveStatus) {
        saveStatus.innerText = '保存中...';
    }

    const weekdaySelector = userId
        ? `.attendance-weekday-${userId}`
        : '.attendance-weekday';

    document.querySelectorAll(weekdaySelector).forEach(input => {
        input.checked = false;
    });

    fetch('/group/{{ $group->id }}/attendance/all-absent', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            all_absent: isAllAbsent,
            date: '{{ $date }}',
            user_id: userId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status) {
            applyAttendanceStatus(data.status, userId);
        }

        if (saveStatus) {
            saveStatus.innerText = isAllAbsent
                ? '全ての日を欠席にしました'
                : '全ての日の欠席を解除しました';
        }
    })
    .catch(() => {
        if (saveStatus) {
            saveStatus.innerText = '保存失敗';
        }
    });
}

function applyAttendanceStatus(status, userId = null) {
    if (userId) {
        const buttons = document.querySelectorAll(`[data-member-button="${userId}"]`);
        const statusText = document.querySelector(`[data-member-status="${userId}"]`);

        buttons.forEach(button => {
            const buttonStatus = button.dataset.statusButton;
            button.className =
                buttonStatus === 'present' ? 'btn btn-outline-success' :
                buttonStatus === 'late' ? 'btn btn-outline-warning' :
                'btn btn-outline-danger';
        });

        const activeButton = document.querySelector(`[data-member-button="${userId}"][data-status-button="${status}"]`);
        if (activeButton) {
            activeButton.className =
                status === 'present' ? 'btn btn-success' :
                status === 'late' ? 'btn btn-warning' :
                'btn btn-danger';
        }

        if (statusText) {
            statusText.innerText =
                status === 'present' ? '現在：出席' :
                status === 'late' ? '現在：遅刻' :
                '現在：欠席';
        }

        return;
    }

    const presentBtn = document.getElementById('presentBtn');
    const lateBtn = document.getElementById('lateBtn');
    const absentBtn = document.getElementById('absentBtn');
    const statusText = document.getElementById('statusText');

    presentBtn.className = 'btn btn-outline-success';
    lateBtn.className = 'btn btn-outline-warning';
    absentBtn.className = 'btn btn-outline-danger';

    if (status === 'present') {
        presentBtn.className = 'btn btn-success';
        statusText.innerText = '現在：出席';
    }

    if (status === 'late') {
        lateBtn.className = 'btn btn-warning';
        statusText.innerText = '現在：遅刻';
    }

    if (status === 'absent') {
        absentBtn.className = 'btn btn-danger';
        statusText.innerText = '現在：欠席';
    }
}

function saveWeeklySettings(userId = null) {
    const saveStatus = statusSaveElement(userId);
    const weekdaySelector = userId
        ? `.attendance-weekday-${userId}:checked`
        : '.attendance-weekday:checked';
    const weekdays = Array.from(document.querySelectorAll(weekdaySelector))
        .map(input => parseInt(input.value));

    if (saveStatus) {
        saveStatus.innerText = '保存中...';
    }

    fetch('/group/{{ $group->id }}/attendance/weekly', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            date: '{{ $date }}',
            attendance_weekdays: weekdays,
            user_id: userId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status) {
            applyAttendanceStatus(data.status, userId);
        }

        const allAbsentSwitch = userId
            ? document.getElementById(`allAbsentSwitch${userId}`)
            : document.getElementById('allAbsentSwitch');
        if (allAbsentSwitch && typeof data.all_absent === 'boolean') {
            allAbsentSwitch.checked = data.all_absent;
        }

        if (saveStatus) {
            saveStatus.innerText = '曜日設定を保存しました';
        }
    })
    .catch(() => {
        if (saveStatus) {
            saveStatus.innerText = '保存失敗';
        }
    });
}

function copyAttendanceUrl() {
    const urlInput = document.getElementById('attendanceUrl');
    const copyStatus = document.getElementById('copyStatus');

    navigator.clipboard.writeText(urlInput.value)
        .then(() => {
            copyStatus.innerText = 'URLをコピーしました';
        })
        .catch(() => {
            urlInput.select();
            document.execCommand('copy');
            copyStatus.innerText = 'URLをコピーしました';
        });
}
</script>

@endsection
