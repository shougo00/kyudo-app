@extends('layouts.user')

@section('content')

@vite(['resources/css/group_history/index.blade.css', 'resources/js/app.js'])

@php
    $view = $view ?? request('view', 'ranking');
    $scoreTypes = $scoreTypes ?? ['all'];
    $keyword = $keyword ?? '';
    $rankingQuery = ['score_types' => $scoreTypes];
    $monthlyQuery = ['keyword' => $keyword];
    $rankingDetailReturnQuery = [
        'return_view' => 'ranking',
        'return_period' => $period,
        'return_limit' => $limit,
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
                   data-monthly-loading
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

        <form method="GET" class="filter-box">
            <input type="hidden" name="view" value="ranking">

            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-6">
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

                <div class="col-6 col-lg-3">
                    <label class="form-label">期間</label>
                    <select name="period" class="form-select">
                        <option value="today" {{ $period === 'today' ? 'selected' : '' }}>今日</option>
                        <option value="week" {{ $period === 'week' ? 'selected' : '' }}>週間</option>
                        <option value="month" {{ $period === 'month' ? 'selected' : '' }}>月間</option>
                        <option value="year" {{ $period === 'year' ? 'selected' : '' }}>年間</option>
                    </select>
                </div>

                <div class="col-6 col-lg-3">
                    <label class="form-label">表示人数</label>
                    <select name="limit" class="form-select">
                        <option value="5" {{ (string)$limit === '5' ? 'selected' : '' }}>上位5人</option>
                        <option value="10" {{ (string)$limit === '10' ? 'selected' : '' }}>上位10人</option>
                        <option value="20" {{ (string)$limit === '20' ? 'selected' : '' }}>上位20人</option>
                        <option value="all" {{ (string)$limit === 'all' ? 'selected' : '' }}>全員</option>
                    </select>
                </div>

                <div class="col-12 filter-actions">
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
        <div class="history-loading-title">計算中...</div>
        <div class="history-loading-text">月間記録を集計しています</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const loadingOverlay = document.getElementById('historyLoadingOverlay');
    const showMonthlyLoading = () => {
        if (!loadingOverlay) return;

        loadingOverlay.classList.add('show');
        loadingOverlay.setAttribute('aria-hidden', 'false');
    };

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

    const scoreChecks = document.querySelector('[data-score-checks]');
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
