@extends('layouts.user')

@section('content')

@vite(['resources/css/group_history/monthly.css', 'resources/js/app.js'])
<div class="monthly-page">

    <h3>{{ $group->name }} 月間記録</h3>

    <div class="month-nav">
        <a href="{{ route('group.monthlyRecords', ['group' => $group->id, 'month' => $prevMonth]) }}"
           class="btn btn-outline-secondary">
            ＜
        </a>

        <div class="month-title">
            {{ $currentMonth->format('Y年n月') }}
        </div>

        <a href="{{ route('group.monthlyRecords', ['group' => $group->id, 'month' => $nextMonth]) }}"
           class="btn btn-outline-secondary">
            ＞
        </a>
    </div>

    @foreach ($monthlyRecords as $row)
        <div class="record-card">
            <div class="user-name">
                {{ $row['user']->name }}
            </div>

            @if ($row['shots'] > 0)
                <div class="score-row">
                    <span>射数：{{ $row['shots'] }}</span>
                    <span>的中：{{ $row['hits'] }}</span>
                    <span>的中率：{{ $row['rate'] }}%</span>
                </div>
            @else
                <div class="no-record">
                    記録なし
                </div>
            @endif
        </div>
    @endforeach

</div>

@endsection