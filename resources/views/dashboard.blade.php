@extends('layouts.user')

@section('content')
@php
    $typeLabels = ['all' => '総合', 'official' => '正規連', 'self' => '自主練'];
    $currentTypeLabel = $typeLabels[$type] ?? '総合';
    $selectedMonth = \Carbon\Carbon::parse($month.'-01');
    $todayDate = now()->format('Y-m-d');
    $monthStats = ['all' => $monthAll, 'official' => $monthOfficial, 'self' => $monthSelf];
    $todayStats = ['all' => $todayAll, 'official' => $todayOfficial, 'self' => $todaySelf];
    $yearStats = ['all' => $yearAll, 'official' => $yearOfficial, 'self' => $yearSelf];
@endphp
<script>
window.historyPageData = {{ \Illuminate\Support\Js::from([
    'type' => $type,
    'todayOfficial' => $todayOfficial, 'todaySelf' => $todaySelf, 'todayAll' => $todayAll,
    'monthOfficial' => $monthOfficial, 'monthSelf' => $monthSelf, 'monthAll' => $monthAll,
    'monthShotPositions' => $monthShotPositions,
    'yearOfficial' => $yearOfficial, 'yearSelf' => $yearSelf, 'yearAll' => $yearAll,
    'calendar' => $calendar, 'prevMonth' => $prevMonth, 'nextMonth' => $nextMonth,
    'currentMonth' => $month, 'todayDate' => $todayDate,
    'targetUserId' => $targetUser->id, 'targetGroupId' => $targetGroup?->id,
    'isViewingOwnHistory' => $isViewingOwnHistory, 'recordUrl' => route('home'),
]) }};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@vite(['resources/css/dashboard/dashboard.css', 'resources/js/dashboard/dashboard.js'])

<div class="history-page" data-history-page data-type="{{ $type }}">
    <header class="history-header">
        <div>
            <h1>的中履歴</h1>
            <p>{{ $targetUser->name }}<span class="history-header-divider" aria-hidden="true"></span>{{ $selectedMonth->format('Y年n月') }}</p>
        </div>
        @if(!$isViewingOwnHistory)
            <a href="{{ route('group.history', ['group' => $targetGroup->id] + ($historyBackQuery ?? ['view' => 'monthly', 'month' => $month])) }}"
               data-history-back-button class="history-back-button">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> 戻る
            </a>
        @else
            <a href="{{ route('home') }}" class="history-back-button">
                <i class="bi bi-pencil-square" aria-hidden="true"></i> 的中記録
            </a>
        @endif
    </header>

    <div class="history-toolbar">
        <div class="month-nav" aria-label="表示月">
            <a href="{{ request()->fullUrlWithQuery(['month' => $prevMonth]) }}" id="prevMonth" class="history-icon-button" aria-label="前の月" title="前の月">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </a>
            <label class="history-month-field">
                <span class="visually-hidden">表示する月</span>
                <input type="month" id="historyMonth" value="{{ $month }}" min="1900-01" max="9999-12" required>
            </label>
            <a href="{{ request()->fullUrlWithQuery(['month' => $nextMonth]) }}" id="nextMonth" class="history-icon-button" aria-label="次の月" title="次の月">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
        </div>
        <div class="type-switch" role="group" aria-label="練習区分">
            @foreach($typeLabels as $key => $label)
                <button type="button" data-record-type="{{ $key }}" id="btn-{{ $key }}" aria-pressed="{{ $type === $key ? 'true' : 'false' }}">
                    <span class="type-dot type-dot-{{ $key }}" aria-hidden="true"></span>{{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <section class="history-summary" aria-label="的中成績" aria-live="polite">
        @foreach([
            ['key' => 'month', 'label' => $selectedMonth->format('n月').'の的中率', 'data' => $monthStats[$type]],
            ['key' => 'today', 'label' => '今日', 'data' => $todayStats[$type]],
            ['key' => 'year', 'label' => $selectedMonth->format('Y年').'の累計', 'data' => $yearStats[$type]],
        ] as $summary)
            <div class="summary-box summary-{{ $summary['key'] }}">
                <h2>{{ $summary['label'] }} <span data-summary-type>{{ $currentTypeLabel }}</span></h2>
                <div class="summary-value" id="{{ $summary['key'] }}-summary">
                    <strong data-summary-rate>{{ $summary['data']['shots'] ? $summary['data']['rate'] : '--' }}</strong><span>%</span>
                </div>
                <p data-summary-count>{{ number_format($summary['data']['shots']) }}射 <b>{{ number_format($summary['data']['hits']) }}中</b></p>
            </div>
        @endforeach
        <div class="summary-box summary-days">
            <h2>月間記録日数</h2>
            <div class="summary-value"><strong id="recordedDays">{{ collect($calendar)->filter(fn ($day) => $day[$type]['shots'] > 0)->count() }}</strong><span>日</span></div>
            <p id="averageShots">1日平均 --射</p>
        </div>
    </section>

    <div class="history-workspace">
        <section class="history-section history-calendar-section" aria-labelledby="calendarTitle">
            <div class="history-section-heading">
                <h2 id="calendarTitle">記録カレンダー</h2>
                <span class="history-period">{{ $selectedMonth->format('Y年n月') }}</span>
            </div>
            <div class="calendar" id="calendar" role="group" aria-label="{{ $selectedMonth->format('Y年n月') }}の記録">
                @foreach(['日', '月', '火', '水', '木', '金', '土'] as $weekday)
                    <span class="day-header">{{ $weekday }}</span>
                @endforeach
                @for($i = 0; $i < $selectedMonth->dayOfWeek; $i++)
                    <div class="day empty" aria-hidden="true"></div>
                @endfor
                @for($i = 1; $i <= $selectedMonth->daysInMonth; $i++)
                    @php
                        $dateObj = $selectedMonth->copy()->day($i);
                        $date = $dateObj->format('Y-m-d');
                        $dayStats = $calendar[$date][$type] ?? null;
                    @endphp
                    <button type="button" class="day {{ $dateObj->dayOfWeek === 0 ? 'sunday' : ($dateObj->dayOfWeek === 6 ? 'saturday' : '') }} {{ $date === $todayDate ? 'is-today' : '' }}"
                            data-date="{{ $date }}" aria-label="{{ $dateObj->format('n月j日') }}" @if(!$isViewingOwnHistory || $type === 'all') disabled @endif @if($date === $todayDate) aria-current="date" @endif>
                        <span class="date">{{ $i }}</span>
                        <span class="day-count">{{ $dayStats && $dayStats['shots'] ? $dayStats['hits'].'/'.$dayStats['shots'] : '' }}</span>
                        <span class="day-rate">{{ $dayStats && $dayStats['shots'] ? $dayStats['rate'].'%' : '' }}</span>
                        <span class="day-markers" aria-hidden="true"></span>
                    </button>
                @endfor
                @for($i = 0; $i < (7 - ($selectedMonth->dayOfWeek + $selectedMonth->daysInMonth) % 7) % 7; $i++)
                    <div class="day empty" aria-hidden="true"></div>
                @endfor
            </div>
            <div class="calendar-legend">
                <span><i class="type-dot type-dot-official" aria-hidden="true"></i>正規連</span>
                <span><i class="type-dot type-dot-self" aria-hidden="true"></i>自主練</span>
            </div>
        </section>

        <div class="history-analysis">
            <section class="history-section rate-chart-section" aria-labelledby="rateChartTitle">
                <div class="history-section-heading">
                    <h2 id="rateChartTitle">的中率の推移</h2>
                    <span class="history-period">{{ $selectedMonth->format('n月') }} <span data-summary-type>{{ $currentTypeLabel }}</span></span>
                </div>
                <div class="chart-legend">
                    <span><i class="legend-line" aria-hidden="true"></i>日別的中率</span>
                    <span><i class="legend-line legend-average" aria-hidden="true"></i>月間平均 <b id="chartMonthAverage">--</b></span>
                </div>
                <div class="chart-wrap">
                    <canvas id="overallRateChart" role="img" aria-label="日別的中率の折れ線グラフ"></canvas>
                    <div class="chart-empty" id="chartEmpty" hidden>
                        <i class="bi bi-bar-chart" aria-hidden="true"></i>
                        <p id="chartEmptyMessage">この月の記録はありません</p>
                        <button type="button" id="showAllChartDays" hidden>すべての記録日を表示</button>
                    </div>
                </div>
                <div class="chart-filter-controls">
                    <label class="chart-filter-toggle">
                        <input type="checkbox" id="chartShotFilterEnabled" checked>
                        <span>最低射数</span>
                    </label>
                    <label class="chart-filter-threshold">
                        <span class="visually-hidden">グラフの最低射数</span>
                        <select id="chartShotThreshold">
                            @foreach([4, 8, 12, 16, 20, 24, 28, 32, 36, 40, 60, 80, 100] as $shotThreshold)
                                <option value="{{ $shotThreshold }}" {{ $shotThreshold === 20 ? 'selected' : '' }}>{{ $shotThreshold }}射以上</option>
                            @endforeach
                        </select>
                    </label>
                    <span id="chartDayCount" class="chart-day-count"></span>
                </div>
            </section>


        </div>
    </div>

    <section class="history-section shot-position-summary" data-shot-position-summary aria-labelledby="shotPositionTitle">
        <div class="history-section-heading">
            <h2 id="shotPositionTitle" data-shot-position-summary-title>月間射順別的中率</h2>
            <span class="history-period" data-summary-type>{{ $currentTypeLabel }}</span>
        </div>
        <div class="shot-position-rates">
            @for($shotNo = 1; $shotNo <= 4; $shotNo++)
                @php $stats = $monthShotPositions[$type][$shotNo]; @endphp
                <div class="shot-position-rate" data-shot-position="{{ $shotNo }}">
                    <h3>{{ $shotNo }}射目</h3>
                    <strong data-shot-position-rate>{{ $stats['shots'] ? $stats['rate'].'%' : '--' }}</strong>
                    <div class="shot-position-track" aria-hidden="true"><span data-shot-position-bar style="width: {{ $stats['rate'] }}%"></span></div>
                    <small data-shot-position-count>{{ $stats['hits'] }}中 / {{ $stats['shots'] }}射</small>
                </div>
            @endfor
        </div>
    </section>
</div>
@endsection
