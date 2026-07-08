@extends('layouts.user')

@section('content')

@vite(['resources/css/home/home.css', 'resources/js/home/home.js'])
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

@php
    $numericScoreOptions = collect($numericScoreOptions ?? [])->values();
    $numericScoreColorMap = $numericScoreOptions->mapWithKeys(fn($option) => [(int) ($option['value'] ?? 0) => $option['color'] ?? '#dbeafe']);
    $numericShotStyle = function ($shot) use ($numericScoreColorMap) {
        if (is_null($shot?->numeric_score)) {
            return '';
        }

        $color = $numericScoreColorMap->get((int) $shot->numeric_score, '#dbeafe');

        return 'background:' . $color . '; border-color:' . $color . '; color:#111;';
    };
    $participantRouteParams = $participantQuery ? ['participants' => $participantQuery] : [];
@endphp

<div class="container py-3 self-bg group-self-record-page"
     id="records-container"
     data-type="self"
     data-shot-url="{{ route('group.self-shots.update', ['group' => $group->id, 'shot' => '__ID__']) }}"
     data-record-url="{{ route('group.self-records.destroy', ['group' => $group->id, 'record' => '__ID__']) }}">

    <div class="record-control-sticky">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
            <div>
                <h4 class="mb-1">グループ的中記録（自主練）</h4>
                <div class="text-muted small">{{ $group->name }}</div>
            </div>

            <div class="summary-text text-end" id="summary">
                <span class="shots">{{ $totalShots }}射</span>
                <span class="hits">{{ $totalHits }}中</span>
                <span class="rate">{{ number_format($hitRate, 1) }}％</span>
            </div>
        </div>

        <div class="self-record-strip mb-2">
            <div>
                <div class="self-record-label">今日の自主練</div>
                <div class="text-muted small">
                    {{ $activeMembers->count() }}人記録中
                </div>
            </div>

            <details class="self-member-picker">
                <summary>参加者を追加</summary>
                <div class="self-member-picker-list">
                    @forelse($availableMembers as $member)
                        @php
                            $nextParticipantQuery = $activeMembers->pluck('id')->push($member->id)->unique()->implode(',');
                        @endphp
                        <a href="{{ route('group.self-records', ['group' => $group->id, 'date' => $date, 'user_id' => $member->id, 'participants' => $nextParticipantQuery]) }}">
                            {{ $member->name }}
                            @if($group->uses_grades && $member->grade_level)
                                <small>{{ $member->grade_level }}年</small>
                            @endif
                        </a>
                    @empty
                        <span class="text-muted small">追加できるメンバーはいません。</span>
                    @endforelse
                </div>
            </details>
        </div>

        <div class="group-member-tabs mb-2">
            @forelse($activeMembers as $member)
                <a href="{{ route('group.self-records', array_merge(['group' => $group->id, 'date' => $date, 'user_id' => $member->id], $participantRouteParams)) }}"
                   class="group-member-tab {{ $selectedUser?->id === $member->id ? 'active' : '' }}">
                    {{ $member->name }}
                    @if($group->uses_grades && $member->grade_level)
                        <small>{{ $member->grade_level }}年</small>
                    @endif
                </a>
            @empty
                <span class="text-muted small">まだ今日の自主練メンバーはいません。参加者を追加してください。</span>
            @endforelse
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <a href="{{ route('group.self-records', array_merge(['group' => $group->id, 'date' => $prevDate, 'user_id' => $selectedUser?->id], $participantRouteParams)) }}" class="btn btn-outline-secondary">＜</a>

            <form id="date-form" method="GET" action="{{ route('group.self-records', $group) }}">
                <input type="hidden" name="user_id" value="{{ $selectedUser?->id }}">
                @if($participantQuery)
                    <input type="hidden" name="participants" value="{{ $participantQuery }}">
                @endif
                <input type="date" name="date" value="{{ $date }}" class="form-control text-center" id="date-picker">
            </form>

            <a href="{{ route('group.self-records', array_merge(['group' => $group->id, 'date' => $nextDate, 'user_id' => $selectedUser?->id], $participantRouteParams)) }}" class="btn btn-outline-secondary">＞</a>
        </div>

        @if($selectedUser)
            <form method="POST" action="{{ route('group.self-records.store', $group) }}" class="mb-0">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="user_id" value="{{ $selectedUser->id }}">
                @if($participantQuery)
                    <input type="hidden" name="participants" value="{{ $participantQuery }}">
                @endif
                <button class="btn btn-primary w-100">＋ {{ $selectedUser->name }} の立を追加</button>
            </form>
        @endif
    </div>

    @if($members->isEmpty())
        <div class="alert alert-warning">グループメンバーがいません。</div>
    @elseif(!$selectedUser)
        <div class="self-empty-state">
            @if($activeMembers->isEmpty())
                <strong>自主練する人を選んで始めます。</strong>
                <p>上の「参加者を追加」から、この日に自主練するメンバーを選んでください。</p>
            @else
                <strong>記録する人を選んでください。</strong>
                <p>上の参加者タブから、記録を入力するメンバーを選んでください。</p>
            @endif
        </div>
    @endif

    <div class="record-card-list">
        @foreach($records as $record)
            @php
                $recordNumericTotal = $record->shots->sum(fn($shot) => (int) ($shot->numeric_score ?? 0));
            @endphp
            <div class="card mb-3 p-2" id="record-{{ $record->id }}" data-record-id="{{ $record->id }}">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <strong>{{ $record->tate_no }}立目</strong>
                        <button class="delete-record ms-2" data-id="{{ $record->id }}" title="立を削除">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    <span id="result-{{ $record->id }}" class="record-result">
                        <span class="hit-count">{{ $record->shots->where('result', 'hit')->count() }}/4</span>
                        <span class="numeric-total {{ $recordNumericTotal > 0 ? '' : 'd-none' }}">{{ $recordNumericTotal }}点</span>
                    </span>
                </div>
                <div class="d-flex justify-content-around mt-2">
                    @foreach($record->shots as $shot)
                        <button class="shot-btn {{ $shot->result == 'hit' ? 'shot-hit' : '' }} {{ $shot->result == 'miss' ? 'shot-miss' : '' }} {{ $shot->result == null && is_null($shot->numeric_score) ? 'shot-none' : '' }} {{ !is_null($shot->numeric_score) ? 'shot-numeric' : '' }}"
                                data-id="{{ $shot->id }}"
                                data-record="{{ $record->id }}"
                                data-result="{{ $shot->result }}"
                                data-numeric-score="{{ $shot->numeric_score }}"
                                style="{{ $numericShotStyle($shot) }}"
                                title="クリックで入力">
                            @if(!is_null($shot->numeric_score))
                                {{ $shot->numeric_score }}
                            @elseif($shot->result == 'hit')
                                <i class="fa-regular fa-circle"></i>
                            @elseif($shot->result == 'miss')
                                <i class="fas fa-xmark"></i>
                            @else
                                ＋
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<style>
.group-member-tabs {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
}

.self-record-strip {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.self-record-label {
    font-size: 12px;
    font-weight: 800;
    color: #0d6efd;
}

.self-member-picker {
    position: relative;
    flex: 0 0 auto;
}

.self-member-picker summary {
    list-style: none;
    cursor: pointer;
    border: 1px solid #0d6efd;
    border-radius: 7px;
    padding: 6px 10px;
    background: #0d6efd;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
}

.self-member-picker summary::-webkit-details-marker {
    display: none;
}

.self-member-picker-list {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    z-index: 40;
    display: grid;
    gap: 4px;
    width: min(76vw, 320px);
    max-height: 280px;
    overflow: auto;
    padding: 8px;
    border: 1px solid #cfe2ff;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
}

.self-member-picker-list a {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 6px;
    color: #0d6efd;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
}

.self-member-picker-list a:hover {
    background: #eef6ff;
}

.self-member-picker-list small {
    color: #6c757d;
}

.group-member-tab {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 34px;
    padding: 6px 10px;
    border: 1px solid #9ec5fe;
    border-radius: 7px;
    background: #fff;
    color: #0d6efd;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
}

.group-member-tab small {
    color: inherit;
    font-size: 11px;
    opacity: 0.85;
}

.group-member-tab.active {
    background: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
}

.self-empty-state {
    border: 1px dashed #9ec5fe;
    border-radius: 10px;
    background: #f8fbff;
    padding: 18px;
    text-align: center;
    color: #495057;
}

.self-empty-state strong {
    display: block;
    color: #0d6efd;
    margin-bottom: 4px;
}

.self-empty-state p {
    margin: 0;
    font-size: 13px;
}

@media (max-width: 600px) {
    .self-record-strip {
        align-items: stretch;
    }
}
</style>

@endsection
