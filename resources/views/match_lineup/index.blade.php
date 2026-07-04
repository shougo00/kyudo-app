@extends('layouts.user')

@section('content')

@vite(['resources/css/match_lineup/index.css', 'resources/js/match_lineup/index.js'])

@php
    $divisionLabels = [
        'male' => '男子の部',
        'female' => '女子の部',
        'mixed' => '混合の部',
    ];
    $usesGrades = (bool) ($group->uses_grades ?? false);
    $gradeColors = collect($group->grade_colors ?? []);
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
@endphp

<div class="match-lineup-page py-3">
    <div class="match-lineup-header">
        <h4 class="mb-0">{{ $group->name }}｜試合立順</h4>
        <a href="/group/{{ $group->id }}/match-records?date={{ $date }}{{ $selectedTeam ? '&team_id='.$selectedTeam->id : '' }}" class="btn btn-success">
            試合記録へ
        </a>
    </div>

    <form method="GET" action="/group/{{ $group->id }}/match-lineup" class="date-nav mb-3">
        <a href="/group/{{ $group->id }}/match-lineup?date={{ \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d') }}" class="btn btn-outline-secondary nav-btn">＜</a>
        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="form-control text-center date-input">
        <a href="/group/{{ $group->id }}/match-lineup?date={{ \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d') }}" class="btn btn-outline-secondary nav-btn">＞</a>
    </form>

    <form method="POST" action="/group/{{ $group->id }}/match-teams" class="team-create">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="text" name="name" class="form-control" placeholder="チーム名" required>
        <select name="division" class="form-select">
            <option value="male">男子の部</option>
            <option value="female">女子の部</option>
            <option value="mixed">混合の部</option>
        </select>
        <select name="tate_size" class="form-select">
            @for($i = 1; $i <= 15; $i++)
                <option value="{{ $i }}" {{ $i === 3 ? 'selected' : '' }}>{{ $i }}人立</option>
            @endfor
        </select>
        <button class="btn btn-primary">チーム作成</button>
    </form>

    @if($errors->any())
        <div class="alert alert-danger mt-2">{{ $errors->first() }}</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success mt-2">{{ session('success') }}</div>
    @endif

    @if($teams->isNotEmpty())
        <div class="team-tabs">
            @foreach($teams as $team)
                <a href="/group/{{ $group->id }}/match-lineup?date={{ $date }}&team_id={{ $team->id }}&tate_no={{ $tateNo }}"
                   class="team-tab {{ $selectedTeam?->id === $team->id ? 'active' : '' }}">
                    {{ $team->name }}
                    <small>{{ $divisionLabels[$team->division] ?? '混合の部' }}</small>
                </a>
            @endforeach
        </div>
    @endif

    @if($selectedTeam)
        <form method="POST" action="/match-teams/{{ $selectedTeam->id }}" class="team-settings">
            @csrf
            @method('PATCH')
            <input type="text" name="name" value="{{ $selectedTeam->name }}" class="form-control" required>
            <select name="division" class="form-select">
                <option value="male" {{ $selectedTeam->division === 'male' ? 'selected' : '' }}>男子の部</option>
                <option value="female" {{ $selectedTeam->division === 'female' ? 'selected' : '' }}>女子の部</option>
                <option value="mixed" {{ $selectedTeam->division === 'mixed' ? 'selected' : '' }}>混合の部</option>
            </select>
            <select name="tate_size" class="form-select">
                @for($i = 1; $i <= 15; $i++)
                    <option value="{{ $i }}" {{ $selectedTeam->tate_size == $i ? 'selected' : '' }}>{{ $i }}人立</option>
                @endfor
            </select>
            <button class="btn btn-outline-primary">設定保存</button>
        </form>

        <div class="tate-toolbar">
            <a href="/group/{{ $group->id }}/match-lineup?date={{ $date }}&team_id={{ $selectedTeam->id }}&tate_no={{ max(1, $tateNo - 1) }}" class="btn btn-outline-secondary">＜</a>
            <div class="tate-current">{{ $tateNo }}立目</div>
            <a href="/group/{{ $group->id }}/match-lineup?date={{ $date }}&team_id={{ $selectedTeam->id }}&tate_no={{ $tateNo + 1 }}" class="btn btn-outline-secondary">＞</a>
        </div>

        <div id="saveStatus" class="save-status">保存済み</div>
        <div id="matchGrid" class="match-grid"></div>
        <p class="text-muted text-end operation-help">
            スマホ：長押しで欠席、遅刻 / PC：ダブルクリックで欠席、遅刻
        </p>

        <h5 class="pool-title">選手</h5>
        <div id="matchPool" class="match-pool"></div>

        <div id="membersSource" hidden>
            @foreach($group->users->filter(fn($user) => $selectedTeam->division === 'mixed' || $user->gender === $selectedTeam->division) as $user)
                @php
                    $saved = $selectedTeam->members
                        ->where('tate_no', $tateNo)
                        ->firstWhere('user_id', $user->id);
                    $attendance = ($matchAttendanceByUserId ?? collect())->get($user->id);
                @endphp
                <div class="source-member"
                     data-user-id="{{ $user->id }}"
                     data-position="{{ $saved?->position }}"
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

        <script>
            window.matchLineupData = {
                teamId: {{ $selectedTeam->id }},
                tateNo: {{ $tateNo }},
                tateSize: {{ $selectedTeam->tate_size }},
                date: @json($date),
            };
        </script>
    @else
        <div class="alert alert-warning mt-3">
            まずチームを作成してください。
        </div>
    @endif
</div>

@endsection
