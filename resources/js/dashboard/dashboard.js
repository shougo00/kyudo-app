const pageData = window.historyPageData;

let currentType = new URL(location.href).searchParams.get('type') || pageData.type;

const todayData = {
    official: pageData.todayOfficial,
    self: pageData.todaySelf,
    match: pageData.todayMatch,
    all: pageData.todayAll
};

const monthData = {
    official: pageData.monthOfficial,
    self: pageData.monthSelf,
    match: pageData.monthMatch,
    all: pageData.monthAll
};

const yearData = {
    official: pageData.yearOfficial,
    self: pageData.yearSelf,
    match: pageData.yearMatch,
    all: pageData.yearAll
};

const calendarData = pageData.calendar;
const prevMonth = pageData.prevMonth;
const nextMonth = pageData.nextMonth;
const currentMonth = pageData.currentMonth;
const groupId = pageData.groupId;
const typeLabels = {
    official: '正規連',
    self: '自主練',
    match: '試合',
    all: '総合'
};

document.getElementById('month-label').innerText = new Date(currentMonth+'-01').getMonth()+1 + '月';

function updateButtonStyles(){
    document.getElementById('btn-official').className = currentType==='official' ? 'btn btn-sm btn-danger' : 'btn btn-sm btn-outline-danger';
    document.getElementById('btn-self').className     = currentType==='self'     ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary';
    document.getElementById('btn-match').className    = currentType==='match'    ? 'btn btn-sm btn-warning' : 'btn btn-sm btn-outline-warning';
    document.getElementById('btn-all').className      = currentType==='all'      ? 'btn btn-sm btn-success' : 'btn btn-sm btn-outline-success';
}

function updateMonthLinks(){
    document.getElementById('prevMonth').href = `?month=${prevMonth}&type=${currentType}`;
    document.getElementById('nextMonth').href = `?month=${nextMonth}&type=${currentType}`;
}

function renderSummary(){
    const t = todayData;
    const m = monthData;
    const y = yearData;
    document.getElementById('today-summary').innerText =
        `総合 ${t.all.shots}射 ${t.all.hits}中 ${t.all.rate}%\n` +
        `正規連 ${t.official.shots}射 ${t.official.hits}中 ${t.official.rate}%\n` +
        `自主練 ${t.self.shots}射 ${t.self.hits}中 ${t.self.rate}%\n` +
        `試合 ${t.match.shots}射 ${t.match.hits}中 ${t.match.rate}%`;
    document.getElementById('month-summary').innerText =
        `総合 ${m.all.shots}射 ${m.all.hits}中 ${m.all.rate}%\n` +
        `正規連 ${m.official.shots}射 ${m.official.hits}中 ${m.official.rate}%\n` +
        `自主練 ${m.self.shots}射 ${m.self.hits}中 ${m.self.rate}%\n` +
        `試合 ${m.match.shots}射 ${m.match.hits}中 ${m.match.rate}%`;
    document.getElementById('year-summary').innerText =
        `総合 ${y.all.shots}射 ${y.all.hits}中 ${y.all.rate}%\n` +
        `正規連 ${y.official.shots}射 ${y.official.hits}中 ${y.official.rate}%\n` +
        `自主練 ${y.self.shots}射 ${y.self.hits}中 ${y.self.rate}%\n` +
        `試合 ${y.match.shots}射 ${y.match.hits}中 ${y.match.rate}%`;
}

function renderCalendar(){
    const cal = document.getElementById('calendar');

    // カレンダー全体の背景
    cal.classList.remove('bg-official','bg-self','bg-match','bg-all');
    if(currentType==='official') cal.classList.add('bg-official');
    else if(currentType==='self') cal.classList.add('bg-self');
    else if(currentType==='match') cal.classList.add('bg-match');
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

        if(currentType === 'match' && groupId){
            day.onclick = () => {
                location.href = `/group/${groupId}/match-records?date=${date}`;
            };
        } else if(currentType !== 'all' && currentType !== 'match'){
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

        if(data && Number(data.shots) > 0){
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
