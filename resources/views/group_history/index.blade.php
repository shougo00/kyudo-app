@extends('layouts.user')

@section('content')

@vite(['resources/css/group_history/index.blade.css', 'resources/js/app.js'])

@php
    $view = $view ?? request('view', 'ranking');
    $scoreTypes = $scoreTypes ?? ['all'];
    $keyword = $keyword ?? '';
    $startDate = $startDate ?? now()->format('Y-m-d');
    $endDate = $endDate ?? $startDate;
    $rankingCalendarMonth = $rankingCalendarMonth ?? \Carbon\Carbon::parse($startDate)->format('Y-m');
    $rankingCalendarCurrentMonth = $rankingCalendarCurrentMonth ?? \Carbon\Carbon::parse($rankingCalendarMonth . '-01')->startOfMonth();
    $rankingCalendarPrevMonth = $rankingCalendarPrevMonth ?? $rankingCalendarCurrentMonth->copy()->subMonth()->format('Y-m');
    $rankingCalendarNextMonth = $rankingCalendarNextMonth ?? $rankingCalendarCurrentMonth->copy()->addMonth()->format('Y-m');
    $rankingLineupDates = $rankingLineupDates ?? [];
    $rankingSelectedStart = min($startDate, $endDate);
    $rankingSelectedEnd = max($startDate, $endDate);
    $rankingCalendarOpen = request()->boolean('calendar_open', false);
    $rankingRangeAnchor = '';
    $requestedRangeAnchor = (string) request('range_anchor', '');

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedRangeAnchor)) {
        [$anchorYear, $anchorMonth, $anchorDay] = array_map('intval', explode('-', $requestedRangeAnchor));

        if (checkdate($anchorMonth, $anchorDay, $anchorYear)) {
            $rankingRangeAnchor = sprintf('%04d-%02d-%02d', $anchorYear, $anchorMonth, $anchorDay);
        }
    }

    $requestedCalendarMode = (string) request('calendar_mode', '');
    $rankingCalendarMode = in_array($requestedCalendarMode, ['single', 'range'], true)
        ? $requestedCalendarMode
        : (in_array($period, ['custom', 'week', 'month', 'year'], true) ? 'range' : 'single');

    if ($rankingRangeAnchor !== '') {
        $rankingCalendarMode = 'range';
    }

    $rankingQuery = [
        'score_types' => $scoreTypes,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'calendar_month' => $rankingCalendarMonth,
    ];

    if ($rankingRangeAnchor !== '') {
        $rankingQuery['range_anchor'] = $rankingRangeAnchor;
        $rankingQuery['calendar_mode'] = 'range';
    }

    $monthlyQuery = ['keyword' => $keyword];
    $rankingDetailReturnQuery = [
        'return_view' => 'ranking',
        'return_period' => $period,
        'return_limit' => $limit,
        'return_start_date' => $startDate,
        'return_end_date' => $endDate,
        'return_calendar_month' => $rankingCalendarMonth,
        'return_score_types' => $scoreTypes,
    ];
    $monthlyDetailReturnQuery = [
        'return_view' => 'monthly',
        'return_month' => $month,
        'return_keyword' => $keyword,
    ];
@endphp

<div class="history-page">

    <div class="title-bar">
        <h3>{{ $group->name }} 記録</h3>

        @if ($view === 'monthly')
            <div class="d-flex gap-2">
                <a href="{{ route('group.monthlyCsv', [
                        'group' => $group->id,
                        'month' => $month,
                    ] + $monthlyQuery) }}"
                   class="btn btn-outline-success btn-sm">
                    CSV出力
                </a>

                <button type="button"
                        class="btn btn-outline-primary btn-sm"
                        onclick="window.print()">
                    印刷
                </button>
            </div>
        @endif
    </div>

    <div class="page-tabs">
        <a href="{{ route('group.history', [
                'group' => $group->id,
                'view' => 'ranking',
                'period' => $period,
                'limit' => $limit
            ] + $rankingQuery) }}"
           class="page-tab {{ $view === 'ranking' ? 'active' : '' }}">
            ランキング
        </a>

        <a href="{{ route('group.history', [
                'group' => $group->id,
                'view' => 'monthly',
                'month' => $month ?? now()->format('Y-m'),
            ] + $monthlyQuery) }}"
           data-monthly-loading
           class="page-tab {{ $view === 'monthly' ? 'active' : '' }}">
            月間記録
        </a>
    </div>

    @if ($view === 'ranking')

        <form method="GET" class="filter-box" data-ranking-loading-form>
            <input type="hidden" name="view" value="ranking">
            <input type="hidden"
                   name="period"
                   value="{{ $period }}"
                   data-ranking-period-value>
            <input type="hidden"
                   name="calendar_month"
                   value="{{ $rankingCalendarMonth }}"
                   data-ranking-calendar-month>
            <input type="hidden"
                   name="calendar_mode"
                   value="{{ $rankingCalendarMode }}"
                   data-ranking-calendar-mode-value>
            <input type="hidden"
                   name="range_anchor"
                   value="{{ $rankingRangeAnchor }}"
                   data-ranking-range-anchor>

            <div class="row g-2 align-items-end ranking-filter-row">
                <div class="col-12 col-lg-4">
                    <label class="form-label">集計</label>
                    <div class="score-checks" data-score-checks>
                        <label class="score-check score-check-all {{ in_array('all', $scoreTypes, true) ? 'active' : '' }}">
                            <input type="checkbox"
                                   name="score_types[]"
                                   value="all"
                                   data-score-all
                                   {{ in_array('all', $scoreTypes, true) ? 'checked' : '' }}>
                            <span>総合</span>
                        </label>

                        @foreach ($availableScoreTypes as $type => $label)
                            <label class="score-check score-check-detail {{ in_array($type, $scoreTypes, true) ? 'active' : '' }}">
                                <input type="checkbox"
                                       name="score_types[]"
                                       value="{{ $type }}"
                                       data-score-detail
                                       {{ in_array($type, $scoreTypes, true) ? 'checked' : '' }}>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-12 col-md-5 col-lg-3 ranking-date-fields"
                     data-ranking-date-fields>
                    <label class="form-label">指定期間</label>
                    <input type="hidden"
                           id="ranking-start-date"
                           name="start_date"
                           value="{{ $startDate }}">
                    <input type="hidden"
                           id="ranking-end-date"
                           name="end_date"
                           value="{{ $endDate }}">

                    <div class="ranking-calendar-toggle-bar">
                        <div class="ranking-date-summary" data-ranking-date-summary></div>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary ranking-calendar-toggle {{ $rankingCalendarOpen ? 'active' : '' }}"
                                data-ranking-calendar-toggle
                                aria-label="{{ $rankingCalendarOpen ? 'カレンダーを閉じる' : 'カレンダーを開く' }}"
                                title="{{ $rankingCalendarOpen ? 'カレンダーを閉じる' : 'カレンダーを開く' }}"
                                aria-expanded="{{ $rankingCalendarOpen ? 'true' : 'false' }}">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="ranking-calendar-box {{ $rankingCalendarOpen ? '' : 'd-none' }}"
                         data-ranking-calendar>
                        <div class="ranking-calendar-toolbar">
                            <div class="ranking-calendar-mode" data-ranking-calendar-mode>
                                <button type="button"
                                        class="ranking-mode-button {{ $rankingCalendarMode === 'single' ? 'active' : '' }}"
                                        data-ranking-select-mode="single">
                                    1日
                                </button>
                                <button type="button"
                                        class="ranking-mode-button {{ $rankingCalendarMode === 'range' ? 'active' : '' }}"
                                        data-ranking-select-mode="range">
                                    範囲
                                </button>
                            </div>
                        </div>

                        <div class="ranking-calendar-month-nav">
                            <a href="{{ route('group.history', [
                                    'group' => $group->id,
                                    'view' => 'ranking',
                                    'period' => $period,
                                    'limit' => $limit,
                                    'calendar_month' => $rankingCalendarPrevMonth,
                                    'calendar_open' => 1,
                                ] + $rankingQuery) }}"
                               data-ranking-calendar-month-link
                               data-calendar-month="{{ $rankingCalendarPrevMonth }}"
                               class="btn btn-sm btn-outline-secondary">
                                ＜
                            </a>

                            <strong>{{ $rankingCalendarCurrentMonth->format('Y年n月') }}</strong>

                            <a href="{{ route('group.history', [
                                    'group' => $group->id,
                                    'view' => 'ranking',
                                    'period' => $period,
                                    'limit' => $limit,
                                    'calendar_month' => $rankingCalendarNextMonth,
                                    'calendar_open' => 1,
                                ] + $rankingQuery) }}"
                               data-ranking-calendar-month-link
                               data-calendar-month="{{ $rankingCalendarNextMonth }}"
                               class="btn btn-sm btn-outline-secondary">
                                ＞
                            </a>
                        </div>

                        <div class="ranking-calendar-wrapper">
                            <div class="ranking-calendar-grid">
                                @php
                                    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
                                    $rankingMonthDays = $rankingCalendarCurrentMonth->daysInMonth;
                                    $rankingStartWeek = $rankingCalendarCurrentMonth->dayOfWeek;
                                @endphp

                                @foreach ($weekdays as $weekday)
                                    <div class="ranking-calendar-day-header">{{ $weekday }}</div>
                                @endforeach

                                @for ($i = 0; $i < $rankingStartWeek; $i++)
                                    <div class="ranking-calendar-empty"></div>
                                @endfor

                                @for ($day = 1; $day <= $rankingMonthDays; $day++)
                                    @php
                                        $dateObj = $rankingCalendarCurrentMonth->copy()->day($day);
                                        $dayDate = $dateObj->format('Y-m-d');
                                        $dayClass = '';

                                        if ($dateObj->dayOfWeek === 0) $dayClass .= ' sunday';
                                        if ($dateObj->dayOfWeek === 6) $dayClass .= ' saturday';

                                        $hasLineup = in_array($dayDate, $rankingLineupDates, true);
                                        $isSelectedDay = $period === 'date'
                                            ? $dayDate === $startDate
                                            : ($period === 'custom' && $dayDate >= $rankingSelectedStart && $dayDate <= $rankingSelectedEnd);

                                        if ($isSelectedDay) $dayClass .= ' selected';
                                        if ($period === 'custom' && $dayDate === $rankingSelectedStart) $dayClass .= ' range-start';
                                        if ($period === 'custom' && $dayDate === $rankingSelectedEnd) $dayClass .= ' range-end';
                                    @endphp

                                    <button type="button"
                                            class="ranking-calendar-day {{ $dayClass }} {{ $hasLineup ? 'has-lineup' : '' }}"
                                            data-ranking-calendar-day
                                            data-date="{{ $dayDate }}"
                                            aria-label="{{ $dateObj->format('Y年n月j日') }}">
                                        <span class="date">{{ $day }}</span>

                                        @if ($hasLineup)
                                            <span class="data">立順あり</span>
                                        @endif
                                    </button>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4 col-lg-3 ranking-period-fields">
                    <label class="form-label">期間</label>
                    <div class="ranking-preset-tabs" data-ranking-preset-tabs>
                        @foreach ([
                            'today' => '今日',
                            'week' => '週間',
                            'month' => '月間',
                            'year' => '年間',
                        ] as $preset => $label)
                            <button type="button"
                                    class="ranking-preset-tab {{ $period === $preset ? 'active' : '' }}"
                                    data-ranking-preset="{{ $preset }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                    <label class="form-label">表示人数</label>
                    <select name="limit" class="form-select">
                        <option value="5" {{ (string)$limit === '5' ? 'selected' : '' }}>上位5人</option>
                        <option value="10" {{ (string)$limit === '10' ? 'selected' : '' }}>上位10人</option>
                        <option value="20" {{ (string)$limit === '20' ? 'selected' : '' }}>上位20人</option>
                        <option value="all" {{ (string)$limit === 'all' ? 'selected' : '' }}>全員</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-12 filter-actions">
                    <button type="submit" class="btn btn-dark">検索</button>
                </div>
            </div>
        </form>

        <div class="section-title">男子の部</div>

        @forelse ($maleRanking as $index => $row)
            @include('group_history.partials.rank_card', [
                'rank' => $index + 1,
                'row' => $row,
                'scoreTypes' => $scoreTypes,
                'detailReturnQuery' => $rankingDetailReturnQuery,
            ])
        @empty
            <p>男子の記録はありません。</p>
        @endforelse

        <div class="section-title">女子の部</div>

        @forelse ($femaleRanking as $index => $row)
            @include('group_history.partials.rank_card', [
                'rank' => $index + 1,
                'row' => $row,
                'scoreTypes' => $scoreTypes,
                'detailReturnQuery' => $rankingDetailReturnQuery,
            ])
        @empty
            <p>女子の記録はありません。</p>
        @endforelse

    @else

        <form method="GET" class="filter-box monthly-filter" data-monthly-loading-form>
            <input type="hidden" name="view" value="monthly">
            <input type="hidden" name="month" value="{{ $month }}">

            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-9">
                    <label class="form-label">名前検索</label>
                    <input type="search"
                           name="keyword"
                           value="{{ $keyword }}"
                           class="form-control"
                           placeholder="名前で検索">
                </div>
                <div class="col-12 col-md-3 filter-actions">
                    <button type="submit" class="btn btn-dark w-100">検索</button>
                </div>
            </div>
        </form>

        <div class="month-nav">
            <a href="{{ route('group.history', [
                    'group' => $group->id,
                    'view' => 'monthly',
                    'month' => $prevMonth,
                ] + $monthlyQuery) }}"
               data-monthly-loading
               class="btn btn-outline-secondary">
                ＜
            </a>

            <div class="month-title">
                {{ $currentMonth->format('Y年n月') }}
            </div>

            <a href="{{ route('group.history', [
                    'group' => $group->id,
                    'view' => 'monthly',
                    'month' => $nextMonth,
                ] + $monthlyQuery) }}"
               data-monthly-loading
               class="btn btn-outline-secondary">
                ＞
            </a>
        </div>

        {{-- 画面表示用カード --}}
        @foreach ($monthlyRecords as $row)
            <div class="rank-card monthly-card">
                <div class="rank-info">

                    <div class="name-with-action">
                        <div class="user-name">
                            {{ $row['user']->name }}
                        </div>
                        <a href="{{ route('dashboard', [
                                'group_id' => $group->id,
                                'user_id' => $row['user']->id,
                                'month' => $month,
                            ] + $monthlyDetailReturnQuery) }}"
                           class="detail-link">
                            詳細
                        </a>
                    </div>

                    <div class="score-line">
                        <span>総合</span>
                        <span>
                            {{ $row['all']['shots'] }}射
                            {{ $row['all']['hits'] }}中
                            {{ $row['all']['rate'] }}%
                        </span>
                    </div>

                    <div class="score-line">
                        <span>正規練</span>
                        <span>
                            {{ $row['official']['shots'] }}射
                            {{ $row['official']['hits'] }}中
                            {{ $row['official']['rate'] }}%
                        </span>
                    </div>

                    <div class="score-line">
                        <span>自主練</span>
                        <span>
                            {{ $row['self']['shots'] }}射
                            {{ $row['self']['hits'] }}中
                            {{ $row['self']['rate'] }}%
                        </span>
                    </div>

                </div>
            </div>
        @endforeach

        {{-- 印刷用表 --}}
        <div class="print-area">
            <h3 style="text-align:center;">
                {{ $group->name }} 月間記録（{{ $currentMonth->format('Y年n月') }}）
            </h3>

            <table class="print-table">
                <thead>
                    <tr>
                        <th rowspan="2">名前</th>
                        <th rowspan="2">学年</th>
                        <th colspan="3">正規練</th>
                        <th colspan="3">自主練</th>
                        <th colspan="3">総合</th>
                    </tr>
                    <tr>
                        <th>射数</th>
                        <th>的中数</th>
                        <th>的中率</th>

                        <th>射数</th>
                        <th>的中数</th>
                        <th>的中率</th>

                        <th>射数</th>
                        <th>的中数</th>
                        <th>的中率</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($monthlyRecords as $row)
                        <tr>
                            <td class="name-col">{{ $row['user']->name }}</td>
                            <td>{{ $row['user']->grade_level ? $row['user']->grade_level . '学年' : '' }}</td>

                            <td>{{ $row['official']['shots'] }}</td>
                            <td>{{ $row['official']['hits'] }}</td>
                            <td>{{ $row['official']['rate'] }}%</td>

                            <td>{{ $row['self']['shots'] }}</td>
                            <td>{{ $row['self']['hits'] }}</td>
                            <td>{{ $row['self']['rate'] }}%</td>

                            <td>{{ $row['all']['shots'] }}</td>
                            <td>{{ $row['all']['hits'] }}</td>
                            <td>{{ $row['all']['rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @endif

</div>

<div class="history-loading-overlay" id="historyLoadingOverlay" aria-live="polite" aria-hidden="true">
    <div class="history-loading-box">
        <div class="history-loading-spinner"></div>
        <div class="history-loading-title" data-history-loading-title>計算中...</div>
        <div class="history-loading-text" data-history-loading-text>月間記録を集計しています</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const loadingOverlay = document.getElementById('historyLoadingOverlay');
    const showHistoryLoading = (title = '計算中...', text = '月間記録を集計しています') => {
        if (!loadingOverlay) return;

        const titleEl = loadingOverlay.querySelector('[data-history-loading-title]');
        const textEl = loadingOverlay.querySelector('[data-history-loading-text]');

        if (titleEl) titleEl.innerText = title;
        if (textEl) textEl.innerText = text;

        loadingOverlay.classList.add('show');
        loadingOverlay.setAttribute('aria-hidden', 'false');
    };
    const showMonthlyLoading = () => showHistoryLoading('計算中...', '月間記録を集計しています');

    document.querySelectorAll('[data-monthly-loading]').forEach(link => {
        link.addEventListener('click', event => {
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey ||
                link.classList.contains('active')
            ) {
                return;
            }

            showMonthlyLoading();
        });
    });

    document.querySelectorAll('[data-monthly-loading-form]').forEach(form => {
        form.addEventListener('submit', showMonthlyLoading);
    });

    document.querySelectorAll('[data-ranking-loading-form]').forEach(form => {
        form.addEventListener('submit', () => {
            const submitButton = form.querySelector('button[type="submit"]');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerText = '検索中...';
            }

            showHistoryLoading('検索中...', 'ランキングを集計しています');
        });
    });

    const scoreChecks = document.querySelector('[data-score-checks]');
    const rankingForm = document.querySelector('[data-ranking-loading-form]');
    const periodValueInput = document.querySelector('[data-ranking-period-value]');
    const calendarModeInput = document.querySelector('[data-ranking-calendar-mode-value]');
    const rangeAnchorInput = document.querySelector('[data-ranking-range-anchor]');
    const presetButtons = [...document.querySelectorAll('[data-ranking-preset]')];
    const modeButtons = [...document.querySelectorAll('[data-ranking-select-mode]')];
    const summaryEl = document.querySelector('[data-ranking-date-summary]');
    const rankingDateFields = document.querySelector('[data-ranking-date-fields]');
    const startDateInput = document.getElementById('ranking-start-date');
    const endDateInput = document.getElementById('ranking-end-date');
    const rankingCalendar = document.querySelector('[data-ranking-calendar]');
    const calendarToggleButton = document.querySelector('[data-ranking-calendar-toggle]');
    const calendarMonthLinks = [...document.querySelectorAll('[data-ranking-calendar-month-link]')];
    const limitSelect = rankingForm?.querySelector('select[name="limit"]');
    let rankingRangeAnchor = rangeAnchorInput?.value || null;
    let rankingSelectionMode = calendarModeInput?.value || '';

    if (!['single', 'range'].includes(rankingSelectionMode)) {
        rankingSelectionMode = rankingRangeAnchor || ['custom', 'week', 'month', 'year'].includes(periodValueInput?.value || 'today')
            ? 'range'
            : 'single';
    }

    let rankingCalendarOpen = rankingCalendar ? !rankingCalendar.classList.contains('d-none') : false;

    const orderedDates = (start, end) => start <= end ? [start, end] : [end, start];
    const formatDate = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };
    const dateDaysAgo = days => {
        const date = new Date();
        date.setHours(0, 0, 0, 0);
        date.setDate(date.getDate() - days);

        return formatDate(date);
    };
    const formatDateLabel = date => {
        if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return '';

        const [year, month, day] = date.split('-');
        return `${year}/${month}/${day}`;
    };
    const currentPeriod = () => periodValueInput?.value || 'today';
    const setRankingPeriod = period => {
        if (periodValueInput) {
            periodValueInput.value = period;
        }
    };
    const setRankingSelectionMode = mode => {
        rankingSelectionMode = mode;

        if (calendarModeInput) {
            calendarModeInput.value = mode;
        }
    };
    const setRankingRangeAnchor = date => {
        rankingRangeAnchor = date || null;

        if (rangeAnchorInput) {
            rangeAnchorInput.value = date || '';
        }
    };
    const setPresetDateRange = period => {
        if (!startDateInput || !endDateInput) return;

        const today = dateDaysAgo(0);
        const starts = {
            today,
            week: dateDaysAgo(6),
            month: dateDaysAgo(29),
            year: dateDaysAgo(364),
        };

        startDateInput.value = starts[period] || today;
        endDateInput.value = today;
    };
    const syncPeriodFromDateInputs = () => {
        if (!startDateInput) return;

        const start = startDateInput.value;
        const end = endDateInput?.value || start;
        const [rangeStart, rangeEnd] = orderedDates(start, end);

        startDateInput.value = rangeStart;
        if (endDateInput) endDateInput.value = rangeEnd;

        setRankingPeriod(rangeStart === rangeEnd ? 'date' : 'custom');
    };
    const syncPresetButtons = () => {
        const period = currentPeriod();

        presetButtons.forEach(button => {
            button.classList.toggle('active', button.dataset.rankingPreset === period);
        });
    };
    const syncModeButtons = () => {
        modeButtons.forEach(button => {
            button.classList.toggle('active', button.dataset.rankingSelectMode === rankingSelectionMode);
        });
    };
    const syncRankingSummary = () => {
        if (!summaryEl || !startDateInput) return;

        const start = startDateInput.value;
        const end = endDateInput?.value || start;
        const [rangeStart, rangeEnd] = orderedDates(start, end);

        summaryEl.innerText = rangeStart === rangeEnd
            ? formatDateLabel(rangeStart)
            : `${formatDateLabel(rangeStart)} - ${formatDateLabel(rangeEnd)}`;
    };
    const syncCalendarVisibility = () => {
        if (!rankingCalendar || !calendarToggleButton) return;

        rankingCalendar.classList.toggle('d-none', !rankingCalendarOpen);
        rankingDateFields?.classList.toggle('calendar-open', rankingCalendarOpen);
        calendarToggleButton.classList.toggle('active', rankingCalendarOpen);
        calendarToggleButton.setAttribute('aria-label', rankingCalendarOpen ? 'カレンダーを閉じる' : 'カレンダーを開く');
        calendarToggleButton.setAttribute('title', rankingCalendarOpen ? 'カレンダーを閉じる' : 'カレンダーを開く');
        calendarToggleButton.setAttribute('aria-expanded', rankingCalendarOpen ? 'true' : 'false');
    };

    const syncRankingCalendar = () => {
        if (!rankingCalendar || !startDateInput) return;

        const period = currentPeriod();
        const start = startDateInput.value;
        const end = endDateInput?.value || start;
        const [rangeStart, rangeEnd] = orderedDates(start, end);

        rankingCalendar.querySelectorAll('[data-ranking-calendar-day]').forEach(day => {
            const date = day.dataset.date;
            const selected = period === 'date' || period === 'today'
                ? date === start
                : ['custom', 'week', 'month', 'year'].includes(period)
                    && Boolean(rangeStart)
                    && Boolean(rangeEnd)
                    && date >= rangeStart
                    && date <= rangeEnd;

            day.classList.toggle('selected', selected);
            day.classList.toggle('range-start', period !== 'date' && period !== 'today' && date === rangeStart);
            day.classList.toggle('range-end', period !== 'date' && period !== 'today' && date === rangeEnd);
            day.classList.toggle('range-pending', rankingSelectionMode === 'range' && rankingRangeAnchor === date);
        });
    };

    const syncRankingControls = () => {
        syncPresetButtons();
        syncModeButtons();
        syncRankingSummary();
        syncRankingCalendar();
        syncCalendarVisibility();
    };
    const syncCurrentRankingQuery = (url, calendarMonth = null) => {
        url.searchParams.set('view', 'ranking');
        url.searchParams.set('period', currentPeriod());
        url.searchParams.set('calendar_open', '1');
        url.searchParams.set('calendar_mode', rankingSelectionMode);

        if (calendarMonth) {
            url.searchParams.set('calendar_month', calendarMonth);
        }

        if (rankingRangeAnchor) {
            url.searchParams.set('range_anchor', rankingRangeAnchor);
        } else {
            url.searchParams.delete('range_anchor');
        }

        if (startDateInput?.value) {
            url.searchParams.set('start_date', startDateInput.value);
            url.searchParams.set('end_date', endDateInput?.value || startDateInput.value);
        }

        if (limitSelect?.value) {
            url.searchParams.set('limit', limitSelect.value);
        }

        [...url.searchParams.keys()]
            .filter(key => key === 'score_types[]' || key.startsWith('score_types['))
            .forEach(key => url.searchParams.delete(key));

        const checkedScoreTypes = rankingForm
            ? [...rankingForm.querySelectorAll('input[name="score_types[]"]:checked')].map(input => input.value)
            : [];
        const scoreTypes = checkedScoreTypes.length ? checkedScoreTypes : ['all'];

        scoreTypes.forEach(value => url.searchParams.append('score_types[]', value));
    };

    calendarToggleButton?.addEventListener('click', () => {
        rankingCalendarOpen = !rankingCalendarOpen;
        syncRankingControls();
    });

    calendarMonthLinks.forEach(link => {
        link.addEventListener('click', event => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            event.preventDefault();

            const url = new URL(link.href, window.location.origin);
            syncCurrentRankingQuery(url, link.dataset.calendarMonth);
            window.location.href = url.toString();
        });
    });

    document.addEventListener('click', event => {
        if (!rankingCalendarOpen || !rankingDateFields || rankingDateFields.contains(event.target)) return;

        rankingCalendarOpen = false;
        syncRankingControls();
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !rankingCalendarOpen) return;

        rankingCalendarOpen = false;
        syncRankingControls();
        calendarToggleButton?.focus();
    });

    presetButtons.forEach(button => {
        button.addEventListener('click', () => {
            const period = button.dataset.rankingPreset;

            if (!period) return;

            setRankingRangeAnchor(null);
            setRankingSelectionMode(period === 'today' ? 'single' : 'range');
            rankingCalendarOpen = false;
            setRankingPeriod(period);
            setPresetDateRange(period);
            syncRankingControls();
        });
    });

    modeButtons.forEach(button => {
        button.addEventListener('click', () => {
            const mode = button.dataset.rankingSelectMode;

            if (mode !== 'single' && mode !== 'range') return;

            setRankingSelectionMode(mode);
            setRankingRangeAnchor(null);
            rankingCalendarOpen = true;

            if (mode === 'single') {
                setRankingPeriod('date');
                if (endDateInput && startDateInput) endDateInput.value = startDateInput.value;
            } else {
                syncPeriodFromDateInputs();
            }

            syncRankingControls();
        });
    });

    [startDateInput, endDateInput].forEach(input => {
        input?.addEventListener('change', () => {
            setRankingRangeAnchor(null);
            syncPeriodFromDateInputs();
            syncRankingControls();
        });
    });

    rankingCalendar?.querySelectorAll('[data-ranking-calendar-day]').forEach(day => {
        day.addEventListener('click', () => {
            if (!startDateInput) return;

            const date = day.dataset.date;

            if (rankingSelectionMode === 'single') {
                setRankingRangeAnchor(null);
                rankingCalendarOpen = false;
                setRankingPeriod('date');
                startDateInput.value = date;
                if (endDateInput) endDateInput.value = date;
                syncRankingControls();
                return;
            }

            if (!rankingRangeAnchor) {
                setRankingRangeAnchor(date);
                setRankingPeriod('date');
                startDateInput.value = date;
                if (endDateInput) endDateInput.value = date;
            } else {
                const [rangeStart, rangeEnd] = orderedDates(rankingRangeAnchor, date);
                setRankingPeriod(rangeStart === rangeEnd ? 'date' : 'custom');
                startDateInput.value = rangeStart;
                if (endDateInput) endDateInput.value = rangeEnd;
                setRankingRangeAnchor(null);
                rankingCalendarOpen = false;
            }

            syncRankingControls();
        });
    });

    syncRankingControls();

    if (currentPeriod() === 'custom') {
        setRankingSelectionMode('range');
        syncRankingControls();
    }

    if (!scoreChecks) return;

    const allInput = scoreChecks.querySelector('[data-score-all]');
    const detailInputs = [...scoreChecks.querySelectorAll('[data-score-detail]')];

    const syncScoreChecks = () => {
        scoreChecks.querySelectorAll('.score-check').forEach(label => {
            const input = label.querySelector('input');
            label.classList.toggle('active', input.checked);
        });
    };

    allInput.addEventListener('change', () => {
        if (allInput.checked) {
            detailInputs.forEach(input => input.checked = false);
        }
        syncScoreChecks();
    });

    detailInputs.forEach(input => {
        input.addEventListener('change', () => {
            if (input.checked) {
                allInput.checked = false;
            }
            if (!allInput.checked && detailInputs.every(detail => !detail.checked)) {
                allInput.checked = true;
            }
            syncScoreChecks();
        });
    });

    syncScoreChecks();
});
</script>

@endsection
