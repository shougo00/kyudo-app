@extends('layouts.user')

@section('content')

@vite(['resources/css/kyudo_results/index.css'])

<div class="result-page">
    

    <div class="summary-area">
        
        @php
            $isTodayPage = \Carbon\Carbon::parse($date)->isToday();

            $selectedTitle = $isTodayPage
                ? '前回の記録（' . \Carbon\Carbon::parse($displayDate)->format('Y年m月d日') . '）'
                : \Carbon\Carbon::parse($displayDate)->format('Y年m月d日');
        @endphp

        @foreach ([
            '今日の記録' => $todaySummary,
            $selectedTitle => $selectedDaySummary,
        ] as $title => $summary)

            <div class="summary-card">
                <h3>{{ $title }}</h3>
                @if ($title === '今日の記録')
                    <div class="summary-row">
                        <span>的中</span>
                        <span>
                            {{ $todayShots }}射中 {{ $todayHits }}中 ({{ $todayHitRate }}%)
                        </span>
                    </div>
                @endif

                @if ($title !== '今日の記録')
                    <div class="summary-row">
                        <span>的中</span>
                        <span>
                            {{ $selectedShots }}射中 {{ $selectedHits }}中 ({{ $selectedHitRate }}%)
                        </span>
                    </div>
                @endif

                <div class="summary-row">
                    <span>記録数</span>
                    <span>{{ $summary['count'] }}回</span>
                </div>

                <div class="summary-row">
                    <span>平均右ひじ</span>
                    <span>{{ $summary['avg_right_elbow'] }}°</span>
                </div>

                <div class="summary-row">
                    <span>平均右脇</span>
                    <span>{{ $summary['avg_right_armpit'] }}°</span>
                </div>

                <div class="summary-row">
                    <span>平均左脇</span>
                    <span>{{ $summary['avg_left_armpit'] }}°</span>
                </div>

                <div class="summary-row">
                    <span>平均会時間</span>
                    <span>{{ $summary['avg_kai_time'] }}秒</span>
                </div>
            </div>

        @endforeach
    </div>

<div class="date-block">

   {{-- 日付移動 --}}
<form method="GET" action="{{ route('kyudo.result.list') }}" class="mb-3">
    <div class="date-nav" style="display:flex; gap:8px; align-items:center;">
        <a href="{{ route('kyudo.result.list', [
            'date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d'),
            'month' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m')
        ]) }}"
           class="btn btn-outline-secondary nav-btn">＜</a>

        <input type="text"
               name="date"
               value="{{ $date }}"
               readonly
               onclick="toggleCalendar(event)"
               class="form-control text-center date-input">

        <a href="{{ route('kyudo.result.list', [
            'date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d'),
            'month' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m')
        ]) }}"
           class="btn btn-outline-secondary nav-btn">＞</a>
    </div>
</form>

<div id="calendarBox" style="{{ request('open') ? 'display:block;' : 'display:none;' }}">

    {{-- 月移動 --}}
    <div class="month-nav">
        <a href="{{ route('kyudo.result.list', [
            'date' => \Carbon\Carbon::parse($prevMonth . '-01')->format('Y-m-d'),
            'month' => $prevMonth,
            'open' => 1
        ]) }}" class="btn btn-sm btn-outline-secondary">＜</a>

        <strong>{{ \Carbon\Carbon::parse($month . '-01')->format('Y年n月') }}</strong>

        <a href="{{ route('kyudo.result.list', [
            'date' => \Carbon\Carbon::parse($nextMonth . '-01')->format('Y-m-d'),
            'month' => $nextMonth,
            'open' => 1
        ]) }}" class="btn btn-sm btn-outline-secondary">＞</a>
    </div>

    {{-- カレンダー --}}
    <div class="calendar-wrapper">
        <div class="calendar">
            @php
                $weekdays = ['日','月','火','水','木','金','土'];
                $firstDay = \Carbon\Carbon::parse($month . '-01');
                $days = $firstDay->daysInMonth;
                $startWeek = $firstDay->dayOfWeek;
            @endphp

            @foreach ($weekdays as $wd)
                <div class="day-header">{{ $wd }}</div>
            @endforeach

            @for ($i = 0; $i < $startWeek; $i++)
                <div class="day empty"></div>
            @endfor

            @for ($i = 1; $i <= $days; $i++)
                @php
                    $dateObj = \Carbon\Carbon::parse($month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT));
                    $dayDate = $dateObj->format('Y-m-d');
                    $dayOfWeek = $dateObj->dayOfWeek;

                    $dayClass = '';
                    if ($dayOfWeek === 0) $dayClass = 'sunday';
                    if ($dayOfWeek === 6) $dayClass = 'saturday';
                    if ($dayDate === $date) $dayClass .= ' selected';

                    $data = $calendar[$dayDate] ?? null;
                    $hasPoseRecord = in_array($dayDate, $poseRecordDates ?? []);
                @endphp

                <a href="{{ route('kyudo.result.list', ['date' => $dayDate, 'month' => $month]) }}"
                   class="day {{ $dayClass }} {{ $hasPoseRecord ? 'has-pose-record' : '' }}"
                   style="text-decoration:none; color:inherit;">

                    <div class="date">{{ $i }}</div>

                    @if ($data && $data['shots'] > 0)
                        <div class="data">{{ $data['hits'] }}/{{ $data['shots'] }}</div>
                        <div class="data">{{ $data['rate'] }}%</div>
                    @endif
                </a>
            @endfor
        </div>
    </div>
</div>
    @forelse ($results as $index => $result)
        <div class="record-card">
            <div class="record-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>{{ $index + 1 }}回目　{{ $result->created_at->format('H:i') }}</span>

                <form action="{{ route('kyudo.results.destroy', $result->id) }}"
                      method="POST"
                      onsubmit="return confirm('この記録を削除しますか？');"
                      style="margin:0;">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="delete-btn">
                        削除
                    </button>
                </form>
            </div>

            <div class="record-grid">
                <div class="record-item">
                    <div class="record-label">右ひじ</div>
                    <div class="record-value">
                        {{ number_format($result->right_elbow_angle, 1) }}°
                    </div>
                </div>

                <div class="record-item">
                    <div class="record-label">右脇</div>
                    <div class="record-value">
                        {{ number_format($result->right_armpit_angle, 1) }}°
                    </div>
                </div>

                <div class="record-item">
                    <div class="record-label">左脇</div>
                    <div class="record-value">
                        {{ number_format($result->left_armpit_angle, 1) }}°
                    </div>
                </div>

                <div class="record-item">
                    <div class="record-label">会時間</div>
                    <div class="record-value">
                        {{ number_format($result->kai_time / 1000, 2) }}秒
                    </div>
                </div>
            </div>
        </div>
    @empty
        <p>この日の記録はありません。</p>
    @endforelse
<script>
function toggleCalendar(e) {
    e.stopPropagation();

    const box = document.getElementById('calendarBox');

    if (box.style.display === 'none' || box.style.display === '') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

document.addEventListener('click', function(e) {
    const box = document.getElementById('calendarBox');
    const input = document.querySelector('.date-input');

    if (!box || !input) return;

    if (!box.contains(e.target) && e.target !== input) {
        box.style.display = 'none';
    }
});
</script>
</div>
@endsection