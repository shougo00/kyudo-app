@extends('layouts.user')

@section('content')

@vite(['resources/css/group/records.css', 'resources/js/group/records.js'])

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<div class="container-fluid py-3 record-page" style="--record-height-extra: {{ max(0, min(120, (int) ($recordHeightExtra ?? 60))) }}px; --match-record-height-extra: {{ max(0, min(120, (int) ($matchRecordHeightExtra ?? 60))) }}px;">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

@php
    $recordLabel = $recordLabel ?? '正規連';
    $basePath = $basePath ?? "/group/{$group->id}/records";
    $addTatePath = $addTatePath ?? "/group/{$group->id}/add-tate";
    $otherRecordPath = $otherRecordPath ?? "/group/{$group->id}/match-records";
    $otherRecordLabel = $otherRecordLabel ?? '試合用記録';
    $practiceType = $practiceType ?? 'official';
    $activeSheetNo = $activeSheetNo ?? 1;
    $sheetNos = $sheetNos ?? collect([1]);
    $isCurrentSheet = $isCurrentSheet ?? true;
    $tateDisplayOffset = $tateDisplayOffset ?? 0;
    $maxTatesPerPage = max(1, (int) ($maxTatesPerPage ?? 5));
    $recordHeightExtra = max(0, min(120, (int) ($recordHeightExtra ?? 60)));
    $matchRecordHeightExtra = max(0, min(120, (int) ($matchRecordHeightExtra ?? 60)));
    $isPageFull = $practiceType !== 'match' && isset($tates) && $tates->count() >= $maxTatesPerPage;
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
@endphp

<script>
window.numericScoreOptions = @json($numericScoreOptions);
window.groupRecordData = {
    groupId: {{ $group->id }},
    usesGrades: @json($usesGrades),
};
</script>

<div class="record-title-bar">
    <h4>{{ $group->name }}（{{ $recordLabel }}）</h4>

    <div class="record-title-actions">
        <a href="{{ $otherRecordPath }}?date={{ $date }}&month={{ $month }}" class="btn btn-outline-success">
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
            <a href="/group/{{ $group->id }}/lineup?date={{ $date }}" class="btn btn-secondary">
                立順
            </a>
        @endif
    </div>

</div>

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
                                $hasEnteredRecord = (($records[$team->id][$user->id] ?? collect()))
                                    ->where('tate_no', $sourceTateNo)
                                    ->contains(fn($record) => $record->shots->contains(fn($shot) => !is_null($shot->result)));
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
                    <a href="{{ $basePath }}?date={{ $date }}&month={{ $month }}&sheet_no={{ $sheetNo }}"
                       class="{{ (int) $sheetNo === (int) $activeSheetNo ? 'active' : '' }}">
                        {{ $sheetNo }}
                    </a>
                @endforeach
            </div>

            @if($isCurrentSheet)
                <div class="official-sheet-actions">
                    @if(!$isPageFull)
                        <form method="POST" action="{{ $addTatePath }}">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date }}">
                            <input type="hidden" name="sheet_no" value="{{ $activeSheetNo }}">
                            <button class="btn btn-primary">
                                <i class="fa-solid fa-plus"></i>
                                立を追加
                            </button>
                        </form>
                    @else
                        <span class="official-sheet-limit">{{ $maxTatesPerPage }}立まで</span>
                    @endif

                    <form method="POST"
                          action="/group/{{ $group->id }}/records/switch-sheet"
                          onsubmit="return confirm('{{ $activeSheetNo }}ページ目の記録と立順を保存して、新しいページへ切り替えますか？')">
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
                $tateTotalHits = 0;

                foreach ($slots as $slotForHits) {
                    if (!$slotForHits->is_empty && $slotForHits->user) {
                        $hitRecord = ($records[$team->id][$slotForHits->user->id] ?? collect())
                            ->where('tate_no', $tateNo)
                            ->first();

                        if ($hitRecord) {
                            $tateTotalHits += $hitRecord->shots->where('result', 'hit')->count();
                        }
                    }
                }

                $tateMeta = optional($matchTateMetas->get($team->id))->get($tateNo);
                $matchScoringMode = $tateMeta?->scoring_mode ?? 'hit_miss';
                $tateNumericTotal = 0;

                foreach ($slots as $slotForNumericTotal) {
                    if (!$slotForNumericTotal->is_empty && $slotForNumericTotal->user) {
                        $numericRecord = ($records[$team->id][$slotForNumericTotal->user->id] ?? collect())
                            ->where('tate_no', $tateNo)
                            ->first();

                        if ($numericRecord) {
                            $tateNumericTotal += $numericRecord->shots->sum(fn($shot) => (int) ($shot->numeric_score ?? 0));
                        }
                    }
                }

                $elapsedSeconds = (int) $tateMeta?->elapsed_seconds;
                $elapsedLabel = sprintf('%02d:%02d', floor($elapsedSeconds / 60), $elapsedSeconds % 60);
            @endphp

            <div class="match-vertical-tate">
                <div class="match-vertical-tate-head">
                    <div>
                        <strong>{{ $tateNo }}立目</strong>
                        <span class="match-tate-hit-total" data-tate-hit-counter="{{ $team->id }}-{{ $tateNo }}">
                            {{ $matchScoringMode === 'numeric' ? $tateNumericTotal . '点' : $tateTotalHits . '中' }}
                        </span>
                    </div>
                    <div class="match-tate-tools">
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
                        <div class="match-timer"
                             data-team-id="{{ $team->id }}"
                             data-date="{{ $date }}"
                             data-tate-no="{{ $tateNo }}"
                             data-elapsed="{{ $elapsedSeconds }}">
                            <span class="match-timer-display">{{ $elapsedLabel }}</span>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleMatchTimer(this)">開始</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetMatchTimer(this)">リセット</button>
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                onclick="openMatchLineupModal({{ $team->id }}, {{ $tateNo }})"
                                {{ $team->trashed() ? 'disabled' : '' }}>
                            立順編集
                        </button>
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
                                $userRecords = $records[$team->id][$user->id] ?? collect();
                                $record = $userRecords->where('tate_no', $tateNo)->first();
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
                                        {{ !$shot || ($shot->result==null && !(($matchScoringMode === 'numeric') && !is_null($shot?->numeric_score)))?'shot-none':'' }}
                                        {{ $matchScoringMode === 'numeric' && !is_null($shot?->numeric_score) ? 'shot-numeric' : '' }}"
                                        style="{{ $matchScoringMode === 'numeric' ? $numericShotStyle($shot) : '' }}"
                                        data-id="{{ $shot->id ?? '' }}"
                                        data-record-id="{{ $record?->id }}"
                                        data-tate-counter="{{ $team->id }}-{{ $tateNo }}"
                                        data-user="{{ $user->id }}"
                                        data-result="{{ $shot?->result ?? '' }}"
                                        data-numeric-score="{{ $shot?->numeric_score }}"
                                        data-scoring-mode="{{ $matchScoringMode }}"
                                        onclick="updateShot(this)">

                                        @if($matchScoringMode === 'numeric' && !is_null($shot?->numeric_score))
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
                                        {{ $matchScoringMode === 'numeric' ? $tatePointCount . '点' : $tateHitCount . '中' }}
                                    </strong>
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
                @endphp

                <div class="user-column {{ (($loop->index + 1) % $rowTateSize == 0) ? 'tate-border' : '' }}">

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
                            onclick="updateShot(this)">

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
                    if (!$slotForPrintTotal->is_empty && $slotForPrintTotal->user) {
                        $printTotalRecord = ($records[$printTeam->id][$slotForPrintTotal->user->id] ?? collect())
                            ->where('tate_no', $tateNo)
                            ->first();

                        if ($printTotalRecord) {
                            $tateTotalHits += $printTotalRecord->shots->where('result', 'hit')->count();
                        }
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
                            $printRecord = $printUser
                                ? ($records[$printTeam->id][$printUser->id] ?? collect())->where('tate_no', $tateNo)->first()
                                : null;
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
                                $record = $user
                                    ? ($records[$printTeam->id][$user->id] ?? collect())->where('tate_no', $tateNo)->first()
                                    : null;
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
            $printSlots = ($officialTateSlots ?? collect())->get($tates->first(), $lineupSlots);
            $printTateSize = ($officialTateSizes ?? collect())->get($tates->first(), $tateSize);
            $printTateSize = max(1, (int) $printTateSize);
            $pageTotalHits = 0;

            foreach ($tates as $printTateNoForTotal) {
                foreach ($printSlots as $slotForTotal) {
                    if (!$slotForTotal->is_empty && $slotForTotal->user) {
                        $printTotalRecord = ($records[$slotForTotal->user->id] ?? collect())
                            ->where('tate_no', $printTateNoForTotal)
                            ->first();

                        if ($printTotalRecord) {
                            $pageTotalHits += $printTotalRecord->shots->where('result', 'hit')->count();
                        }
                    }
                }
            }
        @endphp

        <div class="print-page">
            <div class="print-title">
                {{ $group->name }}（{{ $recordLabel }}）<br>
                {{ \Carbon\Carbon::parse($date)->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}
            </div>

            <div class="print-score-header">
                <div class="print-tate-label"></div>

                @foreach($printSlots as $slot)
                    @php
                        $user = $slot->user;
                        $hitCount = 0;

                        if ($user) {
                            foreach ($tates as $scoreTateNo) {
                                $scoreRecord = ($records[$user->id] ?? collect())
                                    ->where('tate_no', $scoreTateNo)
                                    ->first();

                                if ($scoreRecord) {
                                    $hitCount += $scoreRecord->shots->where('result', 'hit')->count();
                                }
                            }
                        }
                    @endphp

                    <div class="print-score {{ (($loop->index + 1) % $printTateSize == 0) ? 'print-tate-border' : '' }}">
                        {{ $slot->is_empty ? '-' : $hitCount . '中' }}
                    </div>
                @endforeach
            </div>

            <div class="print-tate-area">
                @foreach($tates as $tateNo)
                    @php
                        $displayTateNo = $tateDisplayOffset + $tateNo;
                    @endphp

                <div class="print-tate-row">
                    <div class="print-tate-label">{{ $displayTateNo }}</div>

                    @foreach($printSlots as $slot)
                        @php
                            $user = $slot->user;
                            $record = $user
                                ? ($records[$user->id] ?? collect())->where('tate_no', $tateNo)->first()
                                : null;
                        @endphp

                        <div class="print-user-column {{ (($loop->index + 1) % $printTateSize == 0) ? 'print-tate-border' : '' }}">
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
                    <div class="print-name {{ (($loop->index + 1) % $printTateSize == 0) ? 'print-tate-border' : '' }}">
                        {{ $slot->is_empty ? '空き' : $slot->user->name }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endif

</div>

@endsection
