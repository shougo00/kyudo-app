@extends('layouts.user')

@section('content')
@php
    $typeLabels = [
        'official' => '正規連',
        'self' => '自主練',
        'all' => '総合',
    ];
    $currentTypeLabel = $typeLabels[$type] ?? '総合';
@endphp
<script>
window.historyPageData = {
    type: @json($type),
    todayOfficial: @json($todayOfficial),
    todaySelf: @json($todaySelf),
    todayAll: @json($todayAll),
    monthOfficial: @json($monthOfficial),
    monthSelf: @json($monthSelf),
    monthAll: @json($monthAll),
    yearOfficial: @json($yearOfficial),
    yearSelf: @json($yearSelf),
    yearAll: @json($yearAll),
    calendar: @json($calendar),
    prevMonth: @json($prevMonth),
    nextMonth: @json($nextMonth),
    currentMonth: @json($month),
    targetUserId: @json($targetUser->id),
    targetGroupId: @json($targetGroup?->id),
    isViewingOwnHistory: @json($isViewingOwnHistory)
};
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@vite(['resources/css/dashboard/dashboard.css', 'resources/js/dashboard/dashboard.js'])
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">


<div class="container py-2">
    @if(!$isViewingOwnHistory)
        <div class="history-viewing-user">
            <div>
                <span class="history-viewing-label">表示中</span>
                <strong>{{ $targetUser->name }} の的中履歴</strong>
            </div>
            <a href="{{ route('group.history', [
                    'group' => $targetGroup->id,
                ] + ($historyBackQuery ?? [
                    'view' => 'monthly',
                    'month' => $month,
                ])) }}"
               class="history-back-button">
                戻る
            </a>
        </div>
    @endif

    <!-- 今日 & 月間 & 年間 -->
    <div class="summary-wrapper">
        <div class="summary-box text-start" style="flex:1;">
            <div class="summary-title">今日の記録</div>
            <div class="summary-value" id="today-summary"></div>
        </div>
        <div class="summary-box text-center" style="flex:1;">
            <div class="summary-title">月間（<span id="month-label"></span>）</div>
            <div class="summary-value" id="month-summary"></div>
        </div>
        <div class="summary-box text-end" style="flex:1;">
            <div class="summary-title">年間（<span id="year-label">{{ date('Y') }}</span>）</div>
            <div class="summary-value" id="year-summary"></div>
        </div>
    </div>

    <!-- 月切替 -->
    <div class="month-nav">
        <a href="#" id="prevMonth" class="btn btn-sm btn-outline-secondary">＜</a>
        <strong>{{ $month }}</strong>
        <a href="#" id="nextMonth" class="btn btn-sm btn-outline-secondary">＞</a>
    </div>

    <!-- タイプ切替ボタン -->
    <div class="type-switch">
        <button type="button" data-record-type="official" id="btn-official" class="btn btn-sm btn-outline-danger">正規連</button>
        <button type="button" data-record-type="self" id="btn-self" class="btn btn-sm btn-outline-primary">自主練</button>
        <button type="button" data-record-type="all" id="btn-all" class="btn btn-sm btn-outline-success">総合</button>
    </div>

    <!-- カレンダー -->
    <div class="calendar-wrapper">
        <div class="calendar" id="calendar">
            @php
                $weekdays = ['日','月','火','水','木','金','土'];
                $firstDay = \Carbon\Carbon::parse($month.'-01');
                $days = $firstDay->daysInMonth;
                $startWeek = $firstDay->dayOfWeek;
            @endphp

            {{-- 曜日ヘッダー --}}
            @foreach($weekdays as $wd)
                <div class="day-header">{{ $wd }}</div>
            @endforeach

            {{-- 空セル --}}
            @for($i = 0; $i < $startWeek; $i++)
                <div class="day empty"></div>
            @endfor

            {{-- 日付 --}}
           @for($i = 1; $i <= $days; $i++)
                @php
                    $dateObj = \Carbon\Carbon::parse($month.'-'.str_pad($i,2,'0',STR_PAD_LEFT));
                    $date = $dateObj->format('Y-m-d');
                    $dayOfWeek = $dateObj->dayOfWeek;
                    $dayClass = '';
                    if($dayOfWeek === 0) $dayClass = 'sunday';
                    elseif($dayOfWeek === 6) $dayClass = 'saturday';
                @endphp
                <div class="day {{ $dayClass }}" data-date="{{ $date }}">
                    <div class="date">{{ $i }}</div>
                </div>
            @endfor
        </div>
    </div>
    <div class="rate-chart-card">
        <div class="rate-chart-title">{{ $currentTypeLabel }}的中率グラフ{{ $month }}</div>
        <div class="chart-wrap">
            <canvas id="overallRateChart"></canvas>
        </div>
        <div class="chart-filter-controls">
            <label class="chart-filter-toggle">
                <input type="checkbox" id="chartShotFilterEnabled" checked>
                <span>グラフは指定射数以上の日だけ表示</span>
            </label>

            <label class="chart-filter-threshold">
                <select id="chartShotThreshold">
                    @foreach ([4, 8, 12, 16, 20, 24, 28, 32, 36, 40, 60, 80, 100] as $shotThreshold)
                        <option value="{{ $shotThreshold }}" {{ $shotThreshold === 20 ? 'selected' : '' }}>
                            {{ $shotThreshold }}射以上
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

</div>

@endsection
