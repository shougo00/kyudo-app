const pageData = window.historyPageData;
const page = document.querySelector('[data-history-page]');
const typeLabels = { all: '総合', official: '正規連', self: '自主練' };
const normalizeType = type => type === 'match' ? 'official' : (Object.hasOwn(typeLabels, type) ? type : 'all');
let currentType = normalizeType(new URL(location.href).searchParams.get('type') || pageData.type);
const summaries = {
    today: { official: pageData.todayOfficial, self: pageData.todaySelf, all: pageData.todayAll },
    month: { official: pageData.monthOfficial, self: pageData.monthSelf, all: pageData.monthAll },
    year: { official: pageData.yearOfficial, self: pageData.yearSelf, all: pageData.yearAll },
};
const calendarData = pageData.calendar || {};
const monthShotPositions = pageData.monthShotPositions || {};
const dates = [...document.querySelectorAll('#calendar [data-date]')].map(day => day.dataset.date);
const recordedDates = () => dates.filter(date => Number(calendarData[date]?.[currentType]?.shots) > 0);
const numberFormatter = new Intl.NumberFormat('ja-JP', { maximumFractionDigits: 1 });
const formatNumber = value => numberFormatter.format(Number(value) || 0);
const formatRate = stats => Number(stats?.shots) > 0 ? formatNumber(stats.rate) + '%' : '--';
const emptyStats = { shots: 0, hits: 0, rate: 0 };
const chartFilterStorageKey = 'dashboardChartShotFilter';
const thresholds = [4, 8, 12, 16, 20, 24, 28, 32, 36, 40, 60, 80, 100];
let chartShotFilter = loadChartShotFilter();
let overallRateChart = null;

function loadChartShotFilter() {
    try {
        const saved = JSON.parse(localStorage.getItem(chartFilterStorageKey) || '{}');
        return {
            enabled: typeof saved?.enabled === 'boolean' ? saved.enabled : true,
            threshold: thresholds.includes(Number(saved?.threshold)) ? Number(saved.threshold) : 20,
        };
    } catch {
        return { enabled: true, threshold: 20 };
    }
}

function saveChartShotFilter() {
    try {
        localStorage.setItem(chartFilterStorageKey, JSON.stringify(chartShotFilter));
    } catch {
        // The current filter still works when browser storage is unavailable.
    }
}

function monthUrl(month) {
    const url = new URL(window.location.href);
    url.searchParams.set('month', month);
    url.searchParams.set('type', currentType);
    return url.pathname + url.search;
}

function updateNavigation() {
    page.dataset.type = currentType;
    document.querySelectorAll('[data-record-type]').forEach(button => {
        button.setAttribute('aria-pressed', String(button.dataset.recordType === currentType));
    });
    document.querySelectorAll('[data-summary-type]').forEach(label => {
        label.textContent = typeLabels[currentType];
    });
    document.getElementById('prevMonth').href = monthUrl(pageData.prevMonth);
    document.getElementById('nextMonth').href = monthUrl(pageData.nextMonth);
}

function renderSummary() {
    Object.entries(summaries).forEach(([period, stats]) => {
        const summary = document.getElementById(period + '-summary');
        summary.querySelector('[data-summary-rate]').textContent = Number(stats[currentType].shots)
            ? formatNumber(stats[currentType].rate) : '--';
        const count = summary.parentElement.querySelector('[data-summary-count]');
        const hits = document.createElement('b');
        hits.textContent = formatNumber(stats[currentType].hits) + '中';
        count.replaceChildren(formatNumber(stats[currentType].shots) + '射 ', hits);
    });
    const days = recordedDates().length;
    document.getElementById('recordedDays').textContent = days;
    document.getElementById('averageShots').textContent = '1日平均 ' +
        (days ? formatNumber(summaries.month[currentType].shots / days) : '--') + '射';
}

function marker(type) {
    const dot = document.createElement('i');
    dot.className = 'type-dot type-dot-' + type;
    dot.setAttribute('aria-hidden', 'true');
    return dot;
}

function renderCalendar() {
    document.querySelectorAll('#calendar [data-date]').forEach(day => {
        const date = day.dataset.date;
        const stats = calendarData[date]?.[currentType] || emptyStats;
        day.classList.toggle('has-record', Number(stats.shots) > 0);
        day.disabled = !pageData.isViewingOwnHistory || currentType === 'all';
        day.querySelector('.day-count').textContent = Number(stats.shots) > 0 ? Number(stats.hits) + '/' + Number(stats.shots) : '';
        day.querySelector('.day-rate').textContent = Number(stats.shots) > 0 ? formatRate(stats) : '';
        const dateLabel = Number(date.slice(5, 7)) + '月' + Number(date.slice(8)) + '日';
        day.setAttribute('aria-label', dateLabel + ' ' + typeLabels[currentType] + ' ' +
            (Number(stats.shots) ? formatNumber(stats.shots) + '射' + formatNumber(stats.hits) + '中 ' + formatRate(stats) : '記録なし'));
        const markers = ['official', 'self']
            .filter(type => (currentType === 'all' || currentType === type) && Number(calendarData[date]?.[type]?.shots) > 0)
            .map(marker);
        day.querySelector('.day-markers').replaceChildren(...markers);
    });
}

function renderShotPositionSummary() {
    const summary = document.querySelector('[data-shot-position-summary]');
    summary.dataset.type = currentType;
    summary.querySelectorAll('[data-shot-position]').forEach(item => {
        const stats = monthShotPositions[currentType]?.[item.dataset.shotPosition] || emptyStats;
        item.querySelector('[data-shot-position-rate]').textContent = formatRate(stats);
        item.querySelector('[data-shot-position-count]').textContent = formatNumber(stats.hits) + '中 / ' + formatNumber(stats.shots) + '射';
        item.querySelector('[data-shot-position-bar]').style.width = Math.min(100, Math.max(0, Number(stats.rate) || 0)) + '%';
    });
}

function renderOverallRateChart() {
    const allDates = recordedDates();
    const chartDates = allDates.filter(date => !chartShotFilter.enabled || Number(calendarData[date][currentType].shots) >= chartShotFilter.threshold);
    const monthStats = summaries.month[currentType];
    document.getElementById('chartMonthAverage').textContent = formatRate(monthStats);
    document.getElementById('chartDayCount').textContent = chartDates.length + ' / ' + allDates.length + '日';
    const canvas = document.getElementById('overallRateChart');
    const empty = document.getElementById('chartEmpty');
    const missingChart = typeof window.Chart !== 'function';
    empty.hidden = chartDates.length > 0 && !missingChart;
    document.getElementById('chartEmptyMessage').textContent = missingChart
        ? 'グラフを読み込めませんでした'
        : allDates.length ? '指定した射数に達した日がありません' : 'この月の記録はありません';
    document.getElementById('showAllChartDays').hidden = missingChart || !allDates.length || chartDates.length > 0;
    canvas.setAttribute('aria-label', typeLabels[currentType] + ' 日別的中率 ' + chartDates.length + '日 / 月間平均 ' + formatRate(monthStats));
    if (overallRateChart) {
        overallRateChart.destroy();
        overallRateChart = null;
    }
    if (missingChart || !chartDates.length) return;

    const accent = getComputedStyle(page).getPropertyValue('--history-accent').trim();
    const tint = getComputedStyle(page).getPropertyValue('--history-tint').trim();
    overallRateChart = new window.Chart(canvas, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: typeLabels[currentType] + '的中率',
                    data: chartDates.map(date => ({ x: Number(date.slice(8)), y: Number(calendarData[date][currentType].rate) })),
                    borderColor: accent,
                    backgroundColor: tint,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: accent,
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointHitRadius: 10,
                    borderWidth: 2,
                    tension: 0,
                    fill: true,
                    order: 1,
                },
                {
                    label: '月間平均',
                    data: [{ x: 1, y: Number(monthStats.rate) }, { x: dates.length, y: Number(monthStats.rate) }],
                    borderColor: '#939fa7',
                    borderDash: [4, 5],
                    borderWidth: 1,
                    pointRadius: 0,
                    pointHitRadius: 0,
                    fill: false,
                    order: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 200 },
            interaction: { mode: 'nearest', intersect: true },
            scales: {
                x: {
                    type: 'linear', min: 1, max: dates.length,
                    grid: { display: false }, border: { display: false },
                    ticks: { stepSize: 5, maxTicksLimit: 7, color: '#77818a', font: { size: 11 }, callback: value => value + '日' },
                },
                y: {
                    min: 0, max: 100,
                    border: { display: false },
                    grid: { color: '#e9edef' },
                    ticks: { stepSize: 25, color: '#77818a', font: { size: 11 }, callback: value => value + '%', padding: 8 },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: context => context.datasetIndex === 0,
                    displayColors: false,
                    backgroundColor: '#29353d',
                    padding: 11,
                    callbacks: {
                        title: items => items.length ? Number(pageData.currentMonth.slice(5)) + '月' + items[0].parsed.x + '日' : '',
                        label: context => {
                            const stats = calendarData[chartDates[context.dataIndex]][currentType];
                            return typeLabels[currentType] + ' ' + formatRate(stats) + ' / ' + formatNumber(stats.shots) + '射 ' + formatNumber(stats.hits) + '中';
                        },
                    },
                },
            },
        },
    });
}

function renderAll() {
    updateNavigation();
    renderSummary();
    renderCalendar();
    renderShotPositionSummary();
    renderOverallRateChart();
}

function changeType(event, type) {
    event?.preventDefault();
    currentType = normalizeType(type);
    const url = new URL(window.location.href);
    url.searchParams.set('type', currentType);
    window.history.replaceState({}, '', url);
    renderAll();
}

function syncChartFilter() {
    document.getElementById('chartShotFilterEnabled').checked = chartShotFilter.enabled;
    document.getElementById('chartShotThreshold').value = String(chartShotFilter.threshold);
    document.getElementById('chartShotThreshold').disabled = !chartShotFilter.enabled;
}

function initializeDashboard() {
    syncChartFilter();
    document.getElementById('chartShotFilterEnabled').addEventListener('change', event => {
        chartShotFilter.enabled = event.target.checked;
        syncChartFilter();
        saveChartShotFilter();
        renderOverallRateChart();
    });
    document.getElementById('chartShotThreshold').addEventListener('change', event => {
        chartShotFilter.threshold = Number(event.target.value);
        saveChartShotFilter();
        renderOverallRateChart();
    });
    document.getElementById('showAllChartDays').addEventListener('click', () => {
        chartShotFilter.enabled = false;
        syncChartFilter();
        saveChartShotFilter();
        renderOverallRateChart();
    });
    document.querySelectorAll('[data-record-type]').forEach(button => {
        button.addEventListener('click', event => changeType(event, button.dataset.recordType));
    });
    document.getElementById('historyMonth').addEventListener('change', event => {
        if (event.target.checkValidity() && /^\d{4}-\d{2}$/.test(event.target.value)) {
            window.location.href = monthUrl(event.target.value);
        }
    });
    document.querySelectorAll('#calendar [data-date]').forEach(day => {
        day.addEventListener('click', () => {
            if (!pageData.isViewingOwnHistory || currentType === 'all') return;
            const url = new URL(pageData.recordUrl, window.location.origin);
            url.searchParams.set('date', day.dataset.date);
            url.searchParams.set('type', currentType);
            window.location.href = url.href;
        });
        day.addEventListener('keydown', event => {
            const offset = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[event.key];
            if (!offset) return;
            const date = dates[dates.indexOf(day.dataset.date) + offset];
            if (!date) return;
            event.preventDefault();
            document.querySelector('#calendar [data-date="' + date + '"]').focus();
        });
    });
    const backButton = document.querySelector('[data-history-back-button]');
    backButton?.addEventListener('click', event => {
        if (!document.referrer || window.history.length <= 1) return;
        const referrerUrl = new URL(document.referrer, window.location.origin);
        const fallbackUrl = new URL(backButton.href, window.location.origin);
        if (referrerUrl.origin === window.location.origin && referrerUrl.pathname === fallbackUrl.pathname) {
            event.preventDefault();
            window.history.back();
        }
    });
    renderAll();
    window.changeType = changeType;
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDashboard);
} else {
    initializeDashboard();
}
