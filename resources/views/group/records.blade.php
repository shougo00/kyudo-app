@extends('layouts.user')

@section('content')

@vite(['resources/css/group/records.css', 'resources/js/group/records.js'])

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<div class="container-fluid py-3 record-page {{ ($matchSelection ?? null) ? 'match-selection-mode' : '' }}" style="--record-height-extra: {{ max(0, min(120, (int) ($recordHeightExtra ?? 60))) }}px; --match-record-height-extra: {{ max(0, min(120, (int) ($matchRecordHeightExtra ?? 60))) }}px;">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

@php
    $recordLabel = $recordLabel ?? '正規連';
    $basePath = $basePath ?? "/group/{$group->id}/records";
    $addTatePath = $addTatePath ?? "/group/{$group->id}/add-tate";
    $otherRecordPath = $otherRecordPath ?? "/group/{$group->id}/match-records";
    $otherRecordLabel = $otherRecordLabel ?? '試合用記録';
    $practiceType = $practiceType ?? 'official';
    $otherRecordButtonClass = $practiceType === 'match' ? 'btn-outline-success' : 'btn-warning';
    $canEditGroupRecords = (bool) ($canEditGroupRecords ?? true);
    $activeSheetNo = $activeSheetNo ?? 1;
    $sheetNos = $sheetNos ?? collect([1]);
    $isCurrentSheet = $isCurrentSheet ?? true;
    $tateDisplayOffset = $tateDisplayOffset ?? 0;
    $maxTatesPerPage = max(1, (int) ($maxTatesPerPage ?? 5));
    $recordHeightExtra = max(0, min(120, (int) ($recordHeightExtra ?? 60)));
    $matchRecordHeightExtra = max(0, min(120, (int) ($matchRecordHeightExtra ?? 60)));
    $isPageFull = $practiceType !== 'match' && isset($tates) && $tates->count() >= $maxTatesPerPage;
    $matchSelection = $matchSelection ?? null;
    $matchSelectionQuery = '';

    if ($matchSelection) {
        $matchSelectionQuery = '&' . http_build_query([
            'match_team_id' => $matchSelection['team_id'],
            'match_tate_no' => $matchSelection['tate_no'],
            'match_position' => $matchSelection['position'],
        ]);
    }

    $pageTateRangeLabel = '';
    if ($practiceType !== 'match' && isset($tates) && $tates->isNotEmpty()) {
        $pageTateRangeLabel = ($tateDisplayOffset + $tates->min()) . '立〜' . ($tateDisplayOffset + $tates->max()) . '立';
    }
    $divisionLabels = [
        'male' => '男子の部',
        'female' => '女子の部',
        'mixed' => '混合の部',
    ];
    $usesGrades = (bool) ($group->uses_grades ?? false);
    $gradeColors = collect($group->grade_colors ?? []);
    $numericScoreOptions = collect($group->numeric_score_options ?? [
        ['value' => 1, 'color' => '#dbeafe'],
        ['value' => 2, 'color' => '#dcfce7'],
        ['value' => 3, 'color' => '#fef3c7'],
    ])->map(fn($option) => [
        'value' => (int) ($option['value'] ?? 0),
        'color' => $option['color'] ?? '#dbeafe',
    ])->values();
    $numericScoreColorMap = $numericScoreOptions->mapWithKeys(fn($option) => [$option['value'] => $option['color']]);
    $gradeTextColor = function (?string $color) {
        if (!$color || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return '#222222';
        }

        $r = hexdec(substr($color, 1, 2));
        $g = hexdec(substr($color, 3, 2));
        $b = hexdec(substr($color, 5, 2));

        return (($r * 299 + $g * 587 + $b * 114) / 1000) >= 150 ? '#1f2937' : '#ffffff';
    };
    $gradeColorFor = function ($user) use ($usesGrades, $gradeColors) {
        if (!$usesGrades || !$user?->grade_level) {
            return null;
        }

        return $gradeColors->get((string) $user->grade_level) ?? $gradeColors->get((int) $user->grade_level);
    };
    $gradeStyleFor = function ($user) use ($gradeColorFor, $gradeTextColor) {
        $color = $gradeColorFor($user);

        if (!$color) {
            return '';
        }

        return 'background:' . $color . '; color:' . $gradeTextColor($color) . '; border-color:' . $color . ';';
    };
    $numericShotStyle = function ($shot) use ($numericScoreColorMap) {
        if (is_null($shot?->numeric_score)) {
            return '';
        }

        $color = $numericScoreColorMap->get((int) $shot->numeric_score, '#dbeafe');

        return 'background:' . $color . '; border-color:' . $color . '; color:#111;';
    };
    $matchPositionLabel = function (int $position, int $tateSize) {
        if ($position === 1) {
            return '大前';
        }

        if ($tateSize > 1 && $position === $tateSize) {
            return '落';
        }

        return [
            2 => '二的',
            3 => '三的',
            4 => '四的',
            5 => '五的',
            6 => '六的',
            7 => '七的',
            8 => '八的',
            9 => '九的',
            10 => '十的',
            11 => '十一的',
            12 => '十二的',
            13 => '十三的',
            14 => '十四的',
        ][$position] ?? "{$position}的";
    };
    $matchPositionLabels = $matchSelection
        ? collect(range(1, $matchSelection['tate_size']))
            ->mapWithKeys(fn($position) => [$position => $matchPositionLabel($position, $matchSelection['tate_size'])])
        : collect();
    $matchSelectionPayload = $matchSelection ? [
        'teamId' => $matchSelection['team_id'],
        'teamName' => $matchSelection['team_name'],
        'tateNo' => $matchSelection['tate_no'],
        'position' => $matchSelection['position'],
        'tateSize' => $matchSelection['tate_size'],
        'positionLabels' => $matchPositionLabels,
        'backUrl' => $matchSelection['back_url'],
    ] : null;
@endphp

<script>
window.numericScoreOptions = @json($numericScoreOptions);
window.groupRecordData = {
    groupId: {{ $group->id }},
    usesGrades: @json($usesGrades),
    canEdit: @json($canEditGroupRecords),
    matchSelection: @json($matchSelectionPayload),
};
</script>

<div class="record-title-bar">
    <h4>{{ $group->name }}（{{ $recordLabel }}）</h4>

    @unless($matchSelection)
        <div class="record-title-actions">
            <a href="{{ $otherRecordPath }}?date={{ $date }}&month={{ $month }}" class="btn {{ $otherRecordButtonClass }}">
                {{ $otherRecordLabel }}
            </a>
            @if($practiceType === 'match')
                <button type="button" class="btn btn-primary" onclick="openMatchTeamCreateModal()">
                    ＋ チーム作成
                </button>
            @endif
            <button type="button" class="btn btn-outline-primary" onclick="reloadAndPrint()">
                印刷
            </button>
            @if($practiceType !== 'match' && $isCurrentSheet)
                <a href="/group/{{ $group->id }}/lineup?date={{ $date }}&month={{ $month }}&sheet_no={{ $activeSheetNo }}" class="btn btn-secondary">
                    立順
                </a>
            @endif
        </div>
    @endunless

</div>

@if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

@if($matchSelection)
    @php
        $assignedMembers = $matchSelection['assigned_members'];
        $currentAssignedMember = $assignedMembers->get($matchSelection['position']);
        $currentMatchPositionLabel = $matchPositionLabel($matchSelection['position'], $matchSelection['tate_size']);
    @endphp
    <div class="match-record-select-panel">
        <div class="match-record-select-main">
            <span class="match-record-select-label">試合立順に割り当て</span>
            <strong data-match-selection-heading>{{ $matchSelection['team_name'] }} / {{ $matchSelection['tate_no'] }}立目 / {{ $currentMatchPositionLabel }}</strong>
            <span class="match-record-select-current"
                  data-match-selection-current
                  {{ $currentAssignedMember?->officialRecord ? '' : 'hidden' }}>
                現在：<span data-match-selection-current-name>{{ $currentAssignedMember?->user?->name }}</span>
                <span data-match-selection-current-tate>{{ $currentAssignedMember?->officialRecord?->tate_no }}</span>立目
            </span>
        </div>

        <div class="match-record-select-positions">
            @for($position = 1; $position <= $matchSelection['tate_size']; $position++)
                @php
                    $assignedMember = $assignedMembers->get($position);
                    $assignedName = $assignedMember?->officialRecord ? $assignedMember->user?->name : null;
                    $positionLabel = $matchPositionLabel($position, $matchSelection['tate_size']);
                    $positionUrl = $basePath . '?' . http_build_query([
                        'date' => $date,
                        'month' => $month,
                        'sheet_no' => $activeSheetNo,
                        'match_team_id' => $matchSelection['team_id'],
                        'match_tate_no' => $matchSelection['tate_no'],
                        'match_position' => $position,
                    ]);
                @endphp
                <a href="{{ $positionUrl }}"
                   class="match-record-select-position {{ $position === $matchSelection['position'] ? 'active' : '' }} {{ $assignedMember?->officialRecord ? 'filled' : '' }}"
                   data-match-select-position="{{ $position }}"
                   data-position-url="{{ $positionUrl }}"
                   data-assigned-record-id="{{ $assignedMember?->officialRecord?->id }}"
                   data-assigned-user-name="{{ $assignedName }}"
                   data-assigned-official-tate="{{ $assignedMember?->officialRecord?->tate_no }}"
                   data-position-label="{{ $positionLabel }}"
                   aria-label="{{ $positionLabel }}を選択{{ $assignedName ? '：' . $assignedName : '' }}"
                   title="{{ $positionLabel }}を選択{{ $assignedName ? '：' . $assignedName : '' }}">
                    <span class="match-record-select-number">{{ $positionLabel }}</span>
                    @if($assignedName)
                        <span class="match-record-select-name">{{ $assignedName }}</span>
                    @endif
                </a>
            @endfor
        </div>

        <a href="{{ $matchSelection['back_url'] }}" class="btn btn-sm btn-outline-secondary">試合記録へ戻る</a>
    </div>
@endif

@if($practiceType === 'match')
    <div class="match-control-layer">
        <div id="matchTeamCreateModal" class="match-lineup-modal" hidden>
            <div class="match-lineup-dialog match-team-create-dialog">
                <div class="match-lineup-dialog-head">
                    <div>
                        <strong>チーム作成</strong>
                        <span>{{ \Carbon\Carbon::parse($date)->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}</span>
                    </div>
                    <button type="button" class="btn-close" aria-label="閉じる" onclick="closeMatchTeamCreateModal()"></button>
                </div>

                <form method="POST" action="/group/{{ $group->id }}/match-teams" class="match-create-form">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">

                    <div class="match-create-field">
                        <label>チーム名</label>
                        <input type="text" name="name" class="form-control" placeholder="例：Aチーム" required>
                    </div>

                    <div class="match-create-field">
                        <label>部門</label>
                        <select name="division" class="form-select">
                            <option value="male">男子の部</option>
                            <option value="female">女子の部</option>
                            <option value="mixed">混合の部</option>
                        </select>
                    </div>

                    <div class="match-create-field">
                        <label>人数</label>
                        <select name="tate_size" class="form-select">
                            @for($i = 1; $i <= 15; $i++)
                                <option value="{{ $i }}" {{ $i === 3 ? 'selected' : '' }}>{{ $i }}人立</option>
                            @endfor
                        </select>
                    </div>

                    <div class="match-create-actions">
                        <button type="button" class="btn btn-outline-secondary" onclick="closeMatchTeamCreateModal()">キャンセル</button>
                        <button class="btn btn-primary">作成</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="matchLineupSources" hidden>
            @foreach(($teams ?? collect()) as $team)
                @foreach(($matchTeamTates->get($team->id, collect())) as $sourceTateNo)
                    <div class="lineup-source"
                         data-team-id="{{ $team->id }}"
                         data-team-name="{{ $team->name }}"
                         data-date="{{ $date }}"
                         data-tate-no="{{ $sourceTateNo }}"
                         data-tate-size="{{ $team->tate_size }}">
                        @foreach($group->users->where('is_admin', false)->filter(fn($user) => $team->division === 'mixed' || $user->gender === $team->division) as $user)
                            @php
                                $saved = $team->members
                                    ->where('tate_no', $sourceTateNo)
                                    ->firstWhere('user_id', $user->id);
                                $attendance = ($matchAttendanceByUserId ?? collect())->get($user->id);
                                $hasEnteredRecord = $saved?->officialRecord
                                    ? $saved->officialRecord->shots->contains(fn($shot) => !is_null($shot->result) || !is_null($shot->numeric_score))
                                    : (($records[$team->id][$user->id] ?? collect()))
                                    ->where('tate_no', $sourceTateNo)
                                    ->contains(fn($record) => $record->shots->contains(fn($shot) => !is_null($shot->result) || !is_null($shot->numeric_score)));
                            @endphp
                            <div class="source-member"
                                 data-user-id="{{ $user->id }}"
                                 data-position="{{ $saved?->position }}"
                                 data-has-record="{{ $hasEnteredRecord ? 1 : 0 }}"
                                 data-gender="{{ $user->gender }}"
                                 data-grade-level="{{ $user->grade_level }}"
                                 data-grade-color="{{ $gradeColorFor($user) }}"
                                 data-grade-text-color="{{ $gradeTextColor($gradeColorFor($user)) }}"
                                 data-late="{{ $attendance?->is_late ? 1 : 0 }}"
                                 data-absent="{{ $attendance?->is_absent ? 1 : 0 }}"
                                 class="{{ $attendance?->is_late ? 'late' : '' }} {{ $attendance?->is_absent ? 'absent' : '' }}">
                                {{ $user->name }}
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>

        <div id="matchLineupModal" class="match-lineup-modal" hidden>
            <div class="match-lineup-dialog">
                <div class="match-lineup-dialog-head">
                    <div>
                        <strong id="matchLineupModalTitle">立順編集</strong>
                        <span id="matchLineupSaveStatus">保存済み</span>
                    </div>
                    <button type="button" class="btn-close" aria-label="閉じる" onclick="closeMatchLineupModal()"></button>
                </div>
                <div id="inlineMatchGrid" class="inline-match-grid"></div>
                <p class="text-muted text-end operation-help">
                    スマホ：長押しで欠席、遅刻 / PC：ダブルクリックで欠席、遅刻
                </p>
                <div class="inline-pool-wrap">
                    <div class="inline-pool-title">交代・未配置</div>
                    <div id="inlineMatchPool" class="inline-match-pool"></div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="date-calendar-wrap">
    {{-- 日付移動 --}}
    <form method="GET" action="{{ $basePath }}" class="mb-2 text-center">
        <div class="d-flex justify-content-center align-items-center gap-3">

            <a href="{{ $basePath }}?date={{ \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d') }}&month={{ \Carbon\Carbon::parse($date)->subDay()->format('Y-m') }}"
            class="btn btn-outline-secondary">
                ＜
            </a>

            <input type="text"
                value="{{ $date }}"
                readonly
                onclick="toggleCalendar(event)"
                class="form-control text-center"
                style="max-width:180px; cursor:pointer; background:white;">

            <a href="{{ $basePath }}?date={{ \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d') }}&month={{ \Carbon\Carbon::parse($date)->addDay()->format('Y-m') }}"
            class="btn btn-outline-secondary">
                ＞
            </a>

        </div>
    </form>

    {{-- カレンダー --}}
    <div id="calendarBox" style="{{ request('open') ? 'display:block;' : 'display:none;' }}">

        <div class="month-nav">
            <a href="{{ $basePath }}?date={{ \Carbon\Carbon::parse($prevMonth . '-01')->format('Y-m-d') }}&month={{ $prevMonth }}&open=1"
            class="btn btn-sm btn-outline-secondary">＜</a>

            <strong>{{ \Carbon\Carbon::parse($month . '-01')->format('Y年n月') }}</strong>

            <a href="{{ $basePath }}?date={{ \Carbon\Carbon::parse($nextMonth . '-01')->format('Y-m-d') }}&month={{ $nextMonth }}&open=1"
            class="btn btn-sm btn-outline-secondary">＞</a>
        </div>

        <div class="calendar-wrapper">
            <div class="calendar">
                @php
                    $weekdays = ['日','月','火','水','木','金','土'];
                    $firstDay = \Carbon\Carbon::parse($month . '-01');
                    $days = $firstDay->daysInMonth;
                    $startWeek = $firstDay->dayOfWeek;
                @endphp

                @foreach($weekdays as $wd)
                    <div class="day-header">{{ $wd }}</div>
                @endforeach

                @for($i = 0; $i < $startWeek; $i++)
                    <div class="day empty"></div>
                @endfor

                @for($i = 1; $i <= $days; $i++)
                    @php
                        $dateObj = \Carbon\Carbon::parse($month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT));
                        $dayDate = $dateObj->format('Y-m-d');
                        $dayOfWeek = $dateObj->dayOfWeek;

                        $dayClass = '';
                        if ($dayOfWeek === 0) $dayClass .= ' sunday';
                        if ($dayOfWeek === 6) $dayClass .= ' saturday';
                        if ($dayDate === $date) $dayClass .= ' selected';

                        $hasLineup = in_array($dayDate, $lineupDates ?? []);
                    @endphp

                    <a href="{{ $basePath }}?date={{ $dayDate }}&month={{ $month }}"
                    class="day {{ $dayClass }} {{ $hasLineup ? 'has-lineup' : '' }}">
                        <div class="date">{{ $i }}</div>

                        @if($hasLineup)
                            <div class="data">立順あり</div>
                        @endif
                    </a>
                @endfor
            </div>
        </div>
    </div>
</div>

@if($practiceType !== 'match')
    <div class="official-sheet-panel">
        <div class="official-sheet-head">
            <div class="official-sheet-current">
                <span class="official-sheet-label">記録ページ</span>
                <div class="official-sheet-title-line">
                    <strong>{{ $activeSheetNo }}ページ目</strong>
                    <label class="record-mode-switch">
                        <input type="checkbox"
                               data-mode-toggle="official"
                               data-date="{{ $date }}"
                               data-sheet-no="{{ $activeSheetNo }}"
                               {{ ($activeSheetScoringMode ?? 'hit_miss') === 'numeric' ? 'checked' : '' }}
                               {{ $canEditGroupRecords ? '' : 'disabled' }}
                               onchange="toggleScoringMode(this)">
                        <span>数字</span>
                    </label>
                    @if($pageTateRangeLabel)
                        <span class="official-sheet-range">{{ $pageTateRangeLabel }}</span>
                    @endif
                </div>
                @if(!$isCurrentSheet)
                    <span class="official-sheet-status saved">保存済み</span>
                @endif
            </div>

            <div class="official-sheet-tabs">
                @foreach($sheetNos as $sheetNo)
                    <a href="{{ $basePath }}?date={{ $date }}&month={{ $month }}&sheet_no={{ $sheetNo }}{{ $matchSelectionQuery }}"
                       class="{{ (int) $sheetNo === (int) $activeSheetNo ? 'active' : '' }}">
                        {{ $sheetNo }}
                    </a>
                @endforeach
            </div>

            @if($isCurrentSheet && $canEditGroupRecords)
                <div class="official-sheet-actions">
                    <form method="POST"
                          action="/group/{{ $group->id }}/records/switch-sheet"
                          onsubmit="return handleOfficialSheetSwitchSubmit(this, '{{ $activeSheetNo }}ページ目の記録と立順を保存して、新しいページへ切り替えますか？')">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="hidden" name="sheet_no" value="{{ $activeSheetNo }}">
                        <button class="btn btn-outline-primary">
                            <i class="fa-solid fa-file-arrow-up"></i>
                            次ページへ
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endif

@if($practiceType !== 'match')
    <script>
        function handleOfficialSheetSwitchSubmit(form, confirmMessage) {
            const hasEnteredShot = Array.from(document.querySelectorAll('.score-wrapper .shot-btn[data-result]'))
                .some((shot) => {
                    const hasHitMiss = shot.dataset.result === 'hit' || shot.dataset.result === 'miss';
                    const hasNumeric = shot.dataset.numericScore !== '' && shot.dataset.numericScore != null;

                    return hasHitMiss || hasNumeric;
                });

            if (!hasEnteredShot) {
                alert('先にこのページで記録を1つ以上入力してください。');
                return false;
            }

            if (!confirm(confirmMessage)) {
                return false;
            }

            const pendingUpdates = window.groupRecordPendingShotUpdates;

            if (pendingUpdates && pendingUpdates.size > 0) {
                const submitButton = form.querySelector('button[type="submit"], button:not([type])');

                if (submitButton) {
                    submitButton.disabled = true;
                }

                Promise.allSettled(Array.from(pendingUpdates)).then(() => {
                    HTMLFormElement.prototype.submit.call(form);
                });

                return false;
            }

            return true;
        }
    </script>
@endif

@if($practiceType === 'match' && !$selectedTeam)
    <div class="alert alert-warning">
        この日はまだ試合チームが作成されていません。
    </div>
@elseif($practiceType === 'match' && ($teams ?? collect())->every(fn($team) => ($matchTeamTates->get($team->id, collect()))->isEmpty()))
    <div class="alert alert-warning">
        まだ立順が設定されていません。
    </div>
@elseif($practiceType !== 'match' && $lineupSlots->isEmpty())
    <div class="alert alert-warning">
        この日はまだ立順が設定されていません。
    </div>
@endif

<div class="score-scroll {{ $practiceType === 'match' ? 'match-score-scroll' : '' }}">

<div class="score-wrapper">

@if($practiceType === 'match')
<div class="match-team-board">
@foreach(($teams ?? collect()) as $team)
    @php
        $teamTates = $matchTeamTates->get($team->id, collect());
        $teamSlots = $matchTeamSlots->get($team->id, collect());
    @endphp

    <section class="match-team-panel" id="match-team-{{ $team->id }}">
        <div class="match-team-panel-head">
            <div>
                <h5>{{ $team->name }}</h5>
                <p>
                    {{ $divisionLabels[$team->division] ?? '混合の部' }} / {{ $team->tate_size }}人立
                    @if($team->trashed())
                        / 解散済み
                    @endif
                </p>
                @if(!$team->trashed())
                    <form method="POST" action="/match-teams/{{ $team->id }}" class="match-team-disband-inline" onsubmit="return confirm('このチームを解散しますか？記録は残ります。')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">解散</button>
                    </form>
                @endif
            </div>
            <div class="match-team-actions">
                @if(!$team->trashed())
                    <form method="POST" action="{{ $addTatePath }}" data-match-add-tate-form>
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="hidden" name="team_id" value="{{ $team->id }}">
                        <button class="btn btn-sm btn-primary">＋立</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="match-team-tates">
        @if($teamTates->isEmpty())
            <div class="alert alert-warning mb-0">
                まだ立がありません。右上の＋立から作成してください。
            </div>
        @endif
        @foreach($teamTates as $tateNo)
            @php
                $slots = $teamSlots->get($tateNo, collect());
                $tateMeta = optional($matchTateMetas->get($team->id))->get($tateNo);
                $matchScoringMode = $tateMeta?->scoring_mode ?? 'hit_miss';
                $hasOfficialLinkedRecords = collect($slots)->contains(fn($slot) => ($slot->record_source ?? null) === 'official');
                $tateUsesNumeric = collect($slots)->contains(fn($slot) => ($slot->scoring_mode ?? null) === 'numeric')
                    || (!$hasOfficialLinkedRecords && $matchScoringMode === 'numeric');
                $tateTotalHits = 0;
                $tateNumericTotal = 0;

                foreach ($slots as $slotForHits) {
                    if (!$slotForHits->is_empty && $slotForHits->user && $slotForHits->record) {
                        $hitRecord = $slotForHits->record;

                        $tateTotalHits += $hitRecord->shots->where('result', 'hit')->count();
                        $tateNumericTotal += $hitRecord->shots->sum(fn($shot) => (int) ($shot->numeric_score ?? 0));
                    }
                }

                $elapsedSeconds = (int) $tateMeta?->elapsed_seconds;
                $timerStartedAt = $tateMeta?->timer_started_at
                    ? \Carbon\Carbon::parse($tateMeta->timer_started_at)
                    : null;
                $isTimerRunning = (bool) (($tateMeta?->is_timer_running ?? false) && $timerStartedAt);

                if ($isTimerRunning) {
                    $elapsedSeconds += max(0, now()->timestamp - $timerStartedAt->timestamp);
                }

                $elapsedLabel = sprintf('%02d:%02d', floor($elapsedSeconds / 60), $elapsedSeconds % 60);
                $isSelectedMatchTate = (int) ($selectedTeam?->id ?? 0) === (int) $team->id
                    && (int) $selectedTateNo === (int) $tateNo;
                $shouldScrollToMatchTate = request()->filled('tate_no') && $isSelectedMatchTate;
            @endphp

            <div class="match-vertical-tate {{ $isSelectedMatchTate ? 'selected' : '' }}"
                 data-match-team-id="{{ $team->id }}"
                 data-match-tate-no="{{ $tateNo }}"
                 @if($shouldScrollToMatchTate) data-selected-match-tate="1" @endif>
                <div class="match-vertical-tate-head">
                    <div>
                        <strong>{{ $tateNo }}立目</strong>
                        <span class="match-tate-hit-total" data-tate-hit-counter="{{ $team->id }}-{{ $tateNo }}">
                            {{ $tateUsesNumeric ? $tateNumericTotal . '点' : $tateTotalHits . '中' }}
                        </span>
                    </div>
                    <div class="match-tate-tools">
                        @if(!$hasOfficialLinkedRecords)
                            <label class="record-mode-switch small-switch">
                                <input type="checkbox"
                                       data-mode-toggle="match"
                                       data-team-id="{{ $team->id }}"
                                       data-date="{{ $date }}"
                                       data-tate-no="{{ $tateNo }}"
                                       {{ $matchScoringMode === 'numeric' ? 'checked' : '' }}
                                       onchange="toggleScoringMode(this)">
                                <span>数字</span>
                            </label>
                        @endif
                        <div class="match-timer"
                             data-team-id="{{ $team->id }}"
                             data-date="{{ $date }}"
                             data-tate-no="{{ $tateNo }}"
                             data-elapsed="{{ $elapsedSeconds }}"
                             data-running="{{ $isTimerRunning ? 1 : 0 }}">
                            <span class="match-timer-display">{{ $elapsedLabel }}</span>
                            <button type="button"
                                    class="btn btn-sm {{ $isTimerRunning ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                    onclick="toggleMatchTimer(this)">
                                {{ $isTimerRunning ? '停止' : '開始' }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetMatchTimer(this)">リセット</button>
                        </div>
                        @if($team->trashed())
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                立順編集
                            </button>
                        @else
                            <a href="/group/{{ $group->id }}/records?date={{ $date }}&month={{ $month }}&match_team_id={{ $team->id }}&match_tate_no={{ $tateNo }}&match_position=1"
                               class="btn btn-sm btn-outline-secondary">
                                立順編集
                            </a>
                        @endif
                    </div>
                </div>

                <div class="match-vertical-row {{ $team->tate_size <= 5 ? 'compact-tate' : '' }}">
                    @foreach($slots as $slot)
                        @if($slot->is_empty)
                            <div class="match-vertical-column empty-column">
                                @for($i=1;$i<=4;$i++)
                                    <div class="shot-btn empty-shot">空</div>
                                @endfor
                                <div class="match-vertical-name empty-name">{{ $slot->position }} 空き</div>
                            </div>
                        @else
                            @php
                                $user = $slot->user;
                                $record = $slot->record ?? null;
                                $recordScoringMode = ($slot->scoring_mode ?? null) ?: $matchScoringMode;
                                $tateHitCount = $record
                                    ? $record->shots->where('result', 'hit')->count()
                                    : 0;
                                $tatePointCount = $record
                                    ? $record->shots->sum(fn($shot) => (int) ($shot->numeric_score ?? 0))
                                    : 0;
                            @endphp

                            <div class="match-vertical-column">
                                @for($i=1;$i<=4;$i++)
                                    @php
                                        $shot = $record
                                            ? $record->shots->where('shot_no',$i)->first()
                                            : null;
                                    @endphp

                                    <div class="shot-btn
                                        {{ $shot?->result=='hit'?'shot-hit':'' }}
                                        {{ $shot?->result=='miss'?'shot-miss':'' }}
                                        {{ !$shot || ($shot->result==null && !(($recordScoringMode === 'numeric') && !is_null($shot?->numeric_score)))?'shot-none':'' }}
                                        {{ $recordScoringMode === 'numeric' && !is_null($shot?->numeric_score) ? 'shot-numeric' : '' }}"
                                        style="{{ $recordScoringMode === 'numeric' ? $numericShotStyle($shot) : '' }}"
                                        data-id="{{ $shot->id ?? '' }}"
                                        data-record-id="{{ $record?->id }}"
                                        data-tate-counter="{{ $team->id }}-{{ $tateNo }}"
                                        data-user="{{ $user->id }}"
                                        data-result="{{ $shot?->result ?? '' }}"
                                        data-numeric-score="{{ $shot?->numeric_score }}"
                                        data-scoring-mode="{{ $recordScoringMode }}"
                                        onclick="updateShot(this)">

                                        @if($recordScoringMode === 'numeric' && !is_null($shot?->numeric_score))
                                            {{ $shot->numeric_score }}
                                        @elseif($shot?->result=='hit')
                                            <i class="fa-regular fa-circle"></i>
                                        @elseif($shot?->result=='miss')
                                            <i class="fas fa-xmark"></i>
                                        @else
                                        ＋
                                    @endif
                                </div>
                                @endfor

                                <div class="match-vertical-name" style="{{ $gradeStyleFor($user) }}">
                                    <span>{{ $slot->position }}</span>
                                    {{ $user->name }}
                                    <strong data-shot-counter="{{ $record?->id }}">
                                        {{ $recordScoringMode === 'numeric' ? $tatePointCount . '点' : $tateHitCount . '中' }}
                                    </strong>
                                    @if(($slot->record_source ?? null) === 'official')
                                        <small>{{ $slot->official_tate_no }}立</small>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
        </div>
    </section>
@endforeach
</div>
@else

@php
    $nameSlots = $lineupSlots;

    if($tates->isNotEmpty()) {
        $nameSlots = ($officialTateSlots ?? collect())->get($tates->first(), $lineupSlots);
    }

    $nameTateSize = $tates->isNotEmpty()
        ? ($officialTateSizes ?? collect())->get($tates->first(), $tateSize)
        : $tateSize;
    $nameTateSize = max(1, (int) $nameTateSize);
@endphp

@if($lineupSlots->isNotEmpty())
    <div class="score-header official-score-header">
        <div class="tate-label"></div>
        @foreach($nameSlots as $slot)
            @php
                $headerUser = $slot->user;
                $headerNumericCount = $headerUser
                    ? (($records[$headerUser->id] ?? collect())
                        ->sum(fn($record) => $record->shots->sum(fn($shot) => (int) ($shot->numeric_score ?? 0))))
                    : 0;
            @endphp
            <div class="score {{ (($loop->index + 1) % $nameTateSize == 0) ? 'tate-border' : '' }}"
                 data-user-id="{{ $headerUser?->id }}">
                @if($headerUser)
                    {{ ($activeSheetScoringMode ?? 'hit_miss') === 'numeric'
                        ? ($headerNumericCount . '点')
                        : (($hitCounts[$headerUser->id] ?? 0) . '中') }}
                @else
                    -
                @endif
            </div>
        @endforeach
    </div>
@endif

@if($lineupSlots->isNotEmpty())
<div class="tate-area">
@foreach($tates as $tateNo)
    @php
        $rowSlots = ($officialTateSlots ?? collect())->get($tateNo, $lineupSlots);
        $rowTateSize = ($officialTateSizes ?? collect())->get($tateNo, $tateSize);
        $rowTateSize = max(1, (int) $rowTateSize);
        $displayTateNo = $tateDisplayOffset + $tateNo;
    @endphp

    <div class="tate-row">

        <div class="tate-label">
            <span>{{ $displayTateNo }}</span>
        </div>

        @foreach($rowSlots as $slot)

            @if($slot->is_empty)

                <div class="user-column empty-column {{ (($loop->index + 1) % $rowTateSize == 0) ? 'tate-border' : '' }}">
                    @for($i=1;$i<=4;$i++)
                        <div class="shot-btn empty-shot">空</div>

                        @if($i == 4 && !$loop->parent->first)
                            <div class="shot-separator"></div>
                        @endif
                    @endfor
                </div>

            @else

                @php
                    $user = $slot->user;
                    $userRecords = $records[$user->id] ?? collect();
                    $record = $userRecords->where('tate_no', $tateNo)->first();
                    $assignedRecordMember = ($matchSelection && $record)
                        ? $matchSelection['assigned_members']->first(fn($member) => (int) $member->official_record_id === (int) $record->id)
                        : null;
                    $isMatchRecordChoice = (bool) ($matchSelection && $record);
                    $isCurrentMatchRecordChoice = (bool) ($assignedRecordMember && (int) $assignedRecordMember->position === (int) $matchSelection['position']);
                    $assignedPositionLabel = $assignedRecordMember
                        ? $matchPositionLabel((int) $assignedRecordMember->position, $matchSelection['tate_size'])
                        : null;
                @endphp

                <div class="user-column {{ (($loop->index + 1) % $rowTateSize == 0) ? 'tate-border' : '' }} {{ $isMatchRecordChoice ? 'match-record-choice' : '' }} {{ $assignedRecordMember ? 'assigned' : '' }} {{ $isCurrentMatchRecordChoice ? 'current' : '' }}"
                     @if($isMatchRecordChoice)
                         data-match-record-id="{{ $record->id }}"
                         data-match-user-name="{{ $user->name }}"
                         data-match-official-tate="{{ $record->tate_no }}"
                         title="{{ $matchSelection['team_name'] }} {{ $matchSelection['tate_no'] }}立目 {{ $currentMatchPositionLabel }}に割り当て"
                         onclick="selectOfficialRecordForMatch(this, event)"
                     @endif>

                    @if($assignedPositionLabel)
                        <span class="match-record-choice-position-label">{{ $assignedPositionLabel }}</span>
                    @endif

                    @for($i=1;$i<=4;$i++)
                        @php
                            $shot = $record
                                ? $record->shots->where('shot_no',$i)->first()
                                : null;
                        @endphp

                        <div class="shot-btn
                            {{ $shot?->result=='hit'?'shot-hit':'' }}
                            {{ $shot?->result=='miss'?'shot-miss':'' }}
                            {{ !$shot || ($shot->result==null && !(($activeSheetScoringMode ?? 'hit_miss') === 'numeric' && !is_null($shot?->numeric_score)))?'shot-none':'' }}
                            {{ ($activeSheetScoringMode ?? 'hit_miss') === 'numeric' && !is_null($shot?->numeric_score) ? 'shot-numeric' : '' }}"
                            style="{{ ($activeSheetScoringMode ?? 'hit_miss') === 'numeric' ? $numericShotStyle($shot) : '' }}"
                            data-id="{{ $shot->id ?? '' }}"
                            data-user="{{ $user->id }}"
                            data-result="{{ $shot?->result ?? '' }}"
                            data-numeric-score="{{ $shot?->numeric_score }}"
                            data-scoring-mode="{{ $activeSheetScoringMode ?? 'hit_miss' }}"
                            {{ !$matchSelection && $canEditGroupRecords ? 'onclick=updateShot(this)' : '' }}>

                            @if(($activeSheetScoringMode ?? 'hit_miss') === 'numeric' && !is_null($shot?->numeric_score))
                                {{ $shot->numeric_score }}
                            @elseif($shot?->result=='hit')
                                <i class="fa-regular fa-circle"></i>
                            @elseif($shot?->result=='miss')
                                <i class="fas fa-xmark"></i>
                            @else
                                ＋
                            @endif
                        </div>

                        @if($i == 4 && !$loop->parent->first)
                            <div class="shot-separator"></div>
                        @endif
                    @endfor

                </div>

            @endif

        @endforeach

    </div>

@endforeach
</div>

@if($nameSlots->isNotEmpty())
    <div class="name-row official-name-row">
        <div class="tate-label name-row-label">名前</div>
        @foreach($nameSlots as $slot)
            <div class="tate-user-name {{ $slot->is_empty ? 'empty-name' : '' }} {{ (($loop->index + 1) % $nameTateSize == 0) ? 'tate-border' : '' }}"
                 style="{{ !$slot->is_empty ? $gradeStyleFor($slot->user) : '' }}">
                <span>{{ $slot->position }}</span>
                {{ $slot->is_empty ? '空き' : $slot->user->name }}
            </div>
        @endforeach
    </div>
@endif
@endif

@endif

</div>
</div>
</div>

{{-- 印刷専用レイアウト --}}
<div class="print-only">

@php
    // 5立ごと
    $printTatePages = collect($tates)->chunk(5);

    // 1枚あたりの人数
    // 見切れる場合は 8 → 7 にしてください
    $printMemberPages = collect($lineupSlots)->chunk(17);
@endphp

@if($practiceType === 'match')
    @foreach(($teams ?? collect()) as $printTeam)
        @foreach(($matchTeamTates->get($printTeam->id, collect())) as $tateNo)
            @php
                $slots = $matchTeamSlots->get($printTeam->id, collect())->get($tateNo, collect());
                $tateTotalHits = 0;

                foreach ($slots as $slotForPrintTotal) {
                    if (!$slotForPrintTotal->is_empty && $slotForPrintTotal->user && $slotForPrintTotal->record) {
                        $printTotalRecord = $slotForPrintTotal->record;

                        $tateTotalHits += $printTotalRecord->shots->where('result', 'hit')->count();
                    }
                }
            @endphp

            <div class="print-page">
                <div class="print-title">
                    {{ $group->name }}（{{ $recordLabel }}）<br>
                    {{ $printTeam->name }} / {{ $divisionLabels[$printTeam->division] ?? '混合の部' }} / {{ $printTeam->tate_size }}人立 / {{ $tateNo }}立目 / {{ $tateTotalHits }}中<br>
                    {{ \Carbon\Carbon::parse($date)->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}
                </div>

                <div class="print-score-header">
                    <div class="print-tate-label"></div>
                    @foreach($slots as $slot)
                        @php
                            $printUser = $slot->user;
                            $printRecord = $printUser ? ($slot->record ?? null) : null;
                            $printHitCount = $printRecord
                                ? $printRecord->shots->where('result', 'hit')->count()
                                : 0;
                        @endphp

                        <div class="print-score {{ (($loop->index + 1) % $printTeam->tate_size == 0) ? 'print-tate-border' : '' }}">
                            {{ $slot->is_empty ? '-' : $printHitCount . '中' }}
                        </div>
                    @endforeach
                </div>

                <div class="print-tate-area">
                    <div class="print-tate-row">
                        <div class="print-tate-label">{{ $tateNo }}</div>
                        @foreach($slots as $slot)
                            @php
                                $user = $slot->user;
                                $record = $user ? ($slot->record ?? null) : null;
                            @endphp

                            <div class="print-user-column {{ (($loop->index + 1) % $printTeam->tate_size == 0) ? 'print-tate-border' : '' }}">
                                @for($i=1;$i<=4;$i++)
                                    @php
                                        $shot = $record
                                            ? $record->shots->where('shot_no',$i)->first()
                                            : null;
                                    @endphp
                                    <div class="print-shot">
                                        @if($shot?->result=='hit')
                                            ○
                                        @elseif($shot?->result=='miss')
                                            ×
                                        @endif
                                    </div>
                                @endfor
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="print-name-row">
                    <div class="print-name-spacer"></div>
                    @foreach($slots as $slot)
                        <div class="print-name {{ (($loop->index + 1) % $printTeam->tate_size == 0) ? 'print-tate-border' : '' }}">
                            {{ $slot->is_empty ? '空き' : $slot->user->name }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endforeach
@else
    @if($tates->isNotEmpty())
        @php
            $allPrintSlots = ($officialTateSlots ?? collect())->get($tates->first(), $lineupSlots);
            $printTateSize = ($officialTateSizes ?? collect())->get($tates->first(), $tateSize);
            $printTateSize = max(1, (int) $printTateSize);
            $printTatePages = collect($tates)->chunk(5);
            $printMemberPages = collect($allPrintSlots)->chunk(17);
        @endphp

        @foreach($printTatePages as $printTatePage)
            @foreach($printMemberPages as $printSlots)
                <div class="print-page">
                    <div class="print-title">
                        {{ $group->name }}（{{ $recordLabel }}）<br>
                        {{ \Carbon\Carbon::parse($date)->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}
                        @if($printTatePages->count() > 1 || $printMemberPages->count() > 1)
                            / {{ $tateDisplayOffset + $printTatePage->first() }}〜{{ $tateDisplayOffset + $printTatePage->last() }}立
                            / {{ $printSlots->first()?->position ?? 1 }}〜{{ $printSlots->last()?->position ?? $printSlots->count() }}番
                        @endif
                    </div>

                    <div class="print-score-header">
                        <div class="print-tate-label"></div>

                        @foreach($printSlots as $slot)
                            @php
                                $user = $slot->user;
                                $hitCount = 0;
                                $slotPosition = (int) ($slot->position ?? $loop->iteration);

                                if ($user) {
                                    foreach ($printTatePage as $scoreTateNo) {
                                        $scoreRecord = ($records[$user->id] ?? collect())
                                            ->where('tate_no', $scoreTateNo)
                                            ->first();

                                        if ($scoreRecord) {
                                            $hitCount += $scoreRecord->shots->where('result', 'hit')->count();
                                        }
                                    }
                                }
                            @endphp

                            <div class="print-score {{ ($slotPosition % $printTateSize == 0) ? 'print-tate-border' : '' }}">
                                {{ $slot->is_empty ? '-' : $hitCount . '中' }}
                            </div>
                        @endforeach
                    </div>

                    <div class="print-tate-area">
                        @foreach($printTatePage as $tateNo)
                            @php
                                $displayTateNo = $tateDisplayOffset + $tateNo;
                            @endphp

                            <div class="print-tate-row">
                                <div class="print-tate-label">{{ $displayTateNo }}</div>

                                @foreach($printSlots as $slot)
                                    @php
                                        $user = $slot->user;
                                        $slotPosition = (int) ($slot->position ?? $loop->iteration);
                                        $record = $user
                                            ? ($records[$user->id] ?? collect())->where('tate_no', $tateNo)->first()
                                            : null;
                                    @endphp

                                    <div class="print-user-column {{ ($slotPosition % $printTateSize == 0) ? 'print-tate-border' : '' }}">
                                        @for($i=1;$i<=4;$i++)
                                            @php
                                                $shot = $record
                                                    ? $record->shots->where('shot_no',$i)->first()
                                                    : null;
                                            @endphp

                                            <div class="print-shot">
                                                @if($shot?->result=='hit')
                                                    ○
                                                @elseif($shot?->result=='miss')
                                                    ×
                                                @endif
                                            </div>
                                        @endfor
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="print-name-row">
                        <div class="print-name-spacer"></div>

                        @foreach($printSlots as $slot)
                            @php
                                $slotPosition = (int) ($slot->position ?? $loop->iteration);
                            @endphp
                            <div class="print-name {{ ($slotPosition % $printTateSize == 0) ? 'print-tate-border' : '' }}">
                                {{ $slot->is_empty ? '空き' : $slot->user->name }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach
    @endif
@endif

</div>

@endsection
