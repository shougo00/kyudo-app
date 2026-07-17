const pageData = window.historyPageData;

let currentType = new URL(location.href).searchParams.get('type') || pageData.type;
if (currentType === 'match') {
    currentType = 'official';
}

const todayData = {
    official: pageData.todayOfficial,
    self: pageData.todaySelf,
    all: pageData.todayAll
};

const monthData = {
    official: pageData.monthOfficial,
    self: pageData.monthSelf,
    all: pageData.monthAll
};

const yearData = {
    official: pageData.yearOfficial,
    self: pageData.yearSelf,
    all: pageData.yearAll
};

const calendarData = pageData.calendar;
const prevMonth = pageData.prevMonth;
const nextMonth = pageData.nextMonth;
const currentMonth = pageData.currentMonth;
const targetUserId = pageData.targetUserId;
const targetGroupId = pageData.targetGroupId;
const isViewingOwnHistory = pageData.isViewingOwnHistory;
const typeLabels = {
    official: '正規連',
    self: '自主練',
    all: '総合'
};
const chartFilterStorageKey = 'dashboardChartShotFilter';
const defaultChartFilter = {
    enabled: true,
    threshold: 20
};
let chartShotFilter = loadChartShotFilter();

document.getElementById('month-label').innerText = new Date(currentMonth+'-01').getMonth()+1 + '月';

function loadChartShotFilter(){
    try {
        const saved = JSON.parse(localStorage.getItem(chartFilterStorageKey) || '{}');
        return {
            enabled: saved.enabled ?? defaultChartFilter.enabled,
            threshold: Number(saved.threshold || defaultChartFilter.threshold)
        };
    } catch (error) {
        return { ...defaultChartFilter };
    }
}

function saveChartShotFilter(){
    localStorage.setItem(chartFilterStorageKey, JSON.stringify(chartShotFilter));
}

function updateButtonStyles(){
    document.getElementById('btn-official').className = currentType==='official' ? 'btn btn-sm btn-danger' : 'btn btn-sm btn-outline-danger';
    document.getElementById('btn-self').className     = currentType==='self'     ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary';
    document.getElementById('btn-all').className      = currentType==='all'      ? 'btn btn-sm btn-success' : 'btn btn-sm btn-outline-success';
}

function updateMonthLinks(){
    const targetParams = !isViewingOwnHistory && targetUserId && targetGroupId
        ? `&user_id=${targetUserId}&group_id=${targetGroupId}`
        : '';

    document.getElementById('prevMonth').href = `?month=${prevMonth}&type=${currentType}${targetParams}`;
    document.getElementById('nextMonth').href = `?month=${nextMonth}&type=${currentType}${targetParams}`;
}

function renderSummary(){
    const t = todayData;
    const m = monthData;
    const y = yearData;
    document.getElementById('today-summary').innerText =
        `総合 ${t.all.shots}射 ${t.all.hits}中 ${t.all.rate}%\n` +
        `正規連 ${t.official.shots}射 ${t.official.hits}中 ${t.official.rate}%\n` +
        `自主練 ${t.self.shots}射 ${t.self.hits}中 ${t.self.rate}%`;
    document.getElementById('month-summary').innerText =
        `総合 ${m.all.shots}射 ${m.all.hits}中 ${m.all.rate}%\n` +
        `正規連 ${m.official.shots}射 ${m.official.hits}中 ${m.official.rate}%\n` +
        `自主練 ${m.self.shots}射 ${m.self.hits}中 ${m.self.rate}%`;
    document.getElementById('year-summary').innerText =
        `総合 ${y.all.shots}射 ${y.all.hits}中 ${y.all.rate}%\n` +
        `正規連 ${y.official.shots}射 ${y.official.hits}中 ${y.official.rate}%\n` +
        `自主練 ${y.self.shots}射 ${y.self.hits}中 ${y.self.rate}%`;
}

function renderCalendar(){
    const cal = document.getElementById('calendar');

    // カレンダー全体の背景
    cal.classList.remove('bg-official','bg-self','bg-all');
    if(currentType==='official') cal.classList.add('bg-official');
    else if(currentType==='self') cal.classList.add('bg-self');
    else cal.classList.add('bg-all');

    document.querySelectorAll('.day').forEach(day=>{
        if(day.classList.contains('empty')) return;
        const date = day.dataset.date;
        const data = calendarData[date]?.[currentType];

        if(data && data.shots > 0){
            day.innerHTML = `<div class="date">${date.split('-')[2]}</div>
                             <div class="data">${data.hits}/${data.shots}</div>
                             <div class="data">${data.rate}%</div>`;
        } else {
            day.innerHTML = `<div class="date">${date.split('-')[2]}</div>`;
        }

        if(currentType !== 'all' && isViewingOwnHistory){
            day.onclick = () => {
                location.href = `/home?date=${date}&type=${currentType}`;
            };
        } else {
            day.onclick = null; // クリック無効
        }
    });
}

function changeType(e,type){
    if(e){
        e.preventDefault();
    }
    currentType = type;
    const url = new URL(window.location);
    url.searchParams.set('type', type);
    window.history.replaceState({}, '', url);
    renderAll();
}

let overallRateChart = null;

function renderOverallRateChart(){
    const labels = [];
    const rates = [];
    const chartType = typeLabels[currentType] ? currentType : 'all';
    const chartLabel = typeLabels[chartType];

    const title = document.querySelector('.rate-chart-title');
    if(title){
        title.innerText = `${chartLabel}的中率グラフ${currentMonth}`;
    }

    Object.keys(calendarData).sort().forEach(date => {
        const data = calendarData[date]?.[chartType];

        if(
            data &&
            Number(data.shots) > 0 &&
            (!chartShotFilter.enabled || Number(data.shots) >= chartShotFilter.threshold)
        ){
            labels.push(Number(date.split('-')[2]) + '日');
            rates.push(Number(data.rate));
        }
    });

    const canvas = document.getElementById('overallRateChart');
    if(!canvas) return;

    if(overallRateChart){
        overallRateChart.destroy();
    }

    overallRateChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: `${chartLabel}的中率`,
                data: rates,
                tension: 0.35,
                fill: false,
                pointRadius: 4,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    ticks: {
                        callback: value => value + '%'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

function renderAll(){
    renderSummary();
    renderCalendar();
    renderOverallRateChart();
    updateButtonStyles();
    updateMonthLinks();
}

function initializeDashboard(){
    const filterEnabledInput = document.getElementById('chartShotFilterEnabled');
    const thresholdSelect = document.getElementById('chartShotThreshold');

    if(filterEnabledInput && thresholdSelect){
        filterEnabledInput.checked = chartShotFilter.enabled;
        thresholdSelect.value = String(chartShotFilter.threshold);
        thresholdSelect.disabled = !chartShotFilter.enabled;

        filterEnabledInput.addEventListener('change', () => {
            chartShotFilter.enabled = filterEnabledInput.checked;
            thresholdSelect.disabled = !chartShotFilter.enabled;
            saveChartShotFilter();
            renderOverallRateChart();
        });

        thresholdSelect.addEventListener('change', () => {
            chartShotFilter.threshold = Number(thresholdSelect.value || defaultChartFilter.threshold);
            saveChartShotFilter();
            renderOverallRateChart();
        });
    }

    document.querySelectorAll('[data-record-type]').forEach(button => {
        button.addEventListener('click', event => {
            changeType(event, button.dataset.recordType);
        });
    });
    renderAll();
    window.changeType = changeType;
}

if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initializeDashboard);
} else {
    initializeDashboard();
}
