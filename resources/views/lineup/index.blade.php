@extends('layouts.user')

@section('content')

@vite(['resources/css/lineup/index.css', 'resources/js/lineup/index.js'])

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
@php
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
<div class="container py-3">

<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="lineup-title mb-0">
        {{ $group->name }}｜立順設定
    </h4>

    <a href="/group/{{ $group->id }}/records?date={{ $date }}"
       class="btn btn-success">
        記録に戻る
    </a>
</div>

{{-- 日付移動 --}}
<form method="GET" action="/group/{{ $group->id }}/lineup" class="mb-3">
    <div class="date-nav">
        <a href="/group/{{ $group->id }}/lineup?date={{ \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d') }}"
           class="btn btn-outline-secondary nav-btn">＜</a>

        <input type="date"
               name="date"
               value="{{ $date }}"
               onchange="this.form.submit()"
               class="form-control text-center date-input">

        <a href="/group/{{ $group->id }}/lineup?date={{ \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d') }}"
           class="btn btn-outline-secondary nav-btn">＞</a>
    </div>
</form>

{{-- 操作ボタン --}}
<div class="lineup-toolbar mb-2">
    <select id="tateSize" class="form-select toolbar-item">
        @for($i = 3; $i <= 15; $i++)
            <option value="{{ $i }}" {{ $lineup->tate_size == $i ? 'selected' : '' }}>
                {{ $i }}人立
            </option>
        @endfor
    </select>
    <button type="button" class="btn btn-outline-primary toolbar-btn" onclick="addLineupRow()">
        ＋列追加
    </button>
    <form method="POST" action="/lineup/{{ $lineup->id }}/copy-previous" class="toolbar-form">
        @csrf
        <button type="submit" class="btn btn-outline-info toolbar-btn">
            前回コピー
        </button>
    </form>
    <button type="button" class="btn btn-secondary toolbar-btn" onclick="randomize()">
        ランダム配置
    </button>
    <button type="button" class="btn btn-outline-danger toolbar-btn" onclick="clearAll()">
    全員未配置
    </button>
</div>

<div id="saveStatus" class="save-status mb-2">
    保存済み
</div>

<div class="lineup-status-bar">
    <div id="lineupSummary" class="lineup-summary">配置 0 / 未配置 0</div>
    <div id="selectedMemberLabel" class="selected-member-label">選択なし</div>
</div>

@if(session('success'))
    <div class="alert alert-success flash-message">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger flash-message">
        {{ session('error') }}
    </div>
@endif
<div id="grid" class="grid"></div>

<hr>

<p class="text-muted text-end operation-help">
    スマホ：長押しで欠席、遅刻 / PC：ダブルクリックで欠席、遅刻
</p>

<div class="pool-panel">
    <button type="button" class="pool-toggle" onclick="togglePoolPanel()">
        <span>未配置</span>
        <strong id="poolCount">0人</strong>
    </button>

    <div id="poolTools" class="pool-tools">
        <input type="search"
               id="memberSearch"
               class="form-control"
               placeholder="名前で検索">

        <div class="filter-buttons">
            <button type="button" class="filter-btn active" data-filter="all" onclick="setPoolFilter('all')">全員</button>
            <button type="button" class="filter-btn" data-filter="male" onclick="setPoolFilter('male')">男子</button>
            <button type="button" class="filter-btn" data-filter="female" onclick="setPoolFilter('female')">女子</button>
            <button type="button" class="filter-btn" data-filter="active" onclick="setPoolFilter('active')">出席</button>
            <button type="button" class="filter-btn" data-filter="unavailable" onclick="setPoolFilter('unavailable')">遅欠</button>
        </div>
    </div>

    <div id="pool" class="pool"></div>
</div>

<div id="membersSource" style="display:none;">
@foreach($members as $m)
    <div class="source-member
    {{ $m->is_absent ? 'absent' : '' }}
    {{ $m->is_late ? 'late' : '' }}"
         data-id="{{ $m->id }}"
         data-position="{{ $m->position }}"
         data-has-record="{{ ($recordedUserIds ?? collect())->contains($m->user_id) ? 1 : 0 }}"
         data-gender="{{ $m->user->gender }}"
         data-grade-level="{{ $m->user->grade_level }}"
         data-grade-color="{{ $gradeColorFor($m->user) }}"
         data-grade-text-color="{{ $gradeTextColor($gradeColorFor($m->user)) }}">
        {{ $m->user->name }}
    </div>
@endforeach
</div>

</div>
<script>
window.lineupData = {
    lineupId: {{ $lineup->id }},
};
</script>
@endsection
