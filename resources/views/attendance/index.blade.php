@extends('layouts.user')

@section('content')

<div class="container py-3">

<h4>{{ $group->name }}｜出欠確認</h4>

<form method="GET" action="/group/{{ $group->id }}/attendance" class="mb-4 text-center">
    <div class="d-flex justify-content-center align-items-center gap-3">

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

    <div class="d-flex gap-2 justify-content-center flex-wrap">
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

.user-name {
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 10px;
}

.status-text {
    font-size: 16px;
    font-weight: bold;
}

.attendance-card .btn {
    min-width: 120px;
    padding: 12px;
    font-size: 18px;
}
</style>

<script>
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
function setAllAbsent(isAllAbsent) {
    const saveStatus = document.getElementById('saveStatus');

    saveStatus.innerText = '保存中...';

    fetch('/group/{{ $group->id }}/attendance/all-absent', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            all_absent: isAllAbsent
        })
    })
    .then(res => res.json())
    .then(() => {
        saveStatus.innerText = isAllAbsent
            ? '全ての日を欠席にしました'
            : '全ての日の欠席を解除しました';
    })
    .catch(() => {
        saveStatus.innerText = '保存失敗';
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
