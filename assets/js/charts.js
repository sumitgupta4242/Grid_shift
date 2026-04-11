// charts.js – Analytics page charts
'use strict';

Chart.defaults.color       = '#5a7a62';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
Chart.defaults.font.family = "'Inter', sans-serif";

const PALETTE = {
    solar:   { border: '#f59e0b', bg: 'rgba(245,158,11,0.12)' },
    cons:    { border: '#3b82f6', bg: 'rgba(59,130,246,0.12)' },
    export:  { border: '#8b5cf6', bg: 'rgba(139,92,246,0.10)' },
    import:  { border: '#ef4444', bg: 'rgba(239,68,68,0.10)'  },
    battery: { border: '#50c878', bg: 'rgba(80,200,120,0.10)' },
};

const tooltipStyles = {
    backgroundColor: '#101c14',
    borderColor: 'rgba(80,200,120,0.25)',
    borderWidth: 1,
};

// ── Main analytics chart (switchable) ─────────────────────
let mainChart;

function buildMainChart(labels, solarArr, consArr, exportArr) {
    const ctx = document.getElementById('mainAnalyticsChart').getContext('2d');
    if (mainChart) mainChart.destroy();
    mainChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Solar Generated (kWh)', data: solarArr,  backgroundColor: PALETTE.solar.bg,  borderColor: PALETTE.solar.border,  borderWidth: 2, borderRadius: 5 },
                { label: 'Consumed (kWh)',         data: consArr,   backgroundColor: PALETTE.cons.bg,   borderColor: PALETTE.cons.border,   borderWidth: 2, borderRadius: 5 },
                { label: 'Grid Export (kWh)',       data: exportArr, backgroundColor: PALETTE.export.bg, borderColor: PALETTE.export.border, borderWidth: 2, borderRadius: 5 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16, color: '#a8c4b0' } },
                tooltip: { ...tooltipStyles }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, beginAtZero: true, ticks: { callback: v => v + ' kWh' } }
            }
        }
    });
}

// ── Balance radar chart ───────────────────────────────────
function buildBalanceChart() {
    const ctx = document.getElementById('balanceChart').getContext('2d');
    const totSolar  = MONTHLY_DATA.reduce((s,r) => s + parseFloat(r.solar_kwh||0), 0);
    const totCons   = MONTHLY_DATA.reduce((s,r) => s + parseFloat(r.cons_kwh||0), 0);
    const totExp    = MONTHLY_DATA.reduce((s,r) => s + parseFloat(r.export_kwh||0), 0);
    const totImp    = MONTHLY_DATA.reduce((s,r) => s + parseFloat(r.import_kwh||0), 0);
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Self-Consumed Solar','Grid Export','Grid Import'],
            datasets: [{
                data: [
                    Math.max(0, totSolar - totExp),
                    totExp,
                    totImp
                ],
                backgroundColor: [PALETTE.solar.border, PALETTE.export.border, PALETTE.import.border],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, color: '#a8c4b0' } },
                tooltip: { ...tooltipStyles, callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed.toFixed(0)} kWh` } }
            }
        }
    });
}

// ── Export bar chart ──────────────────────────────────────
function buildExportChart() {
    const ctx  = document.getElementById('exportChart').getContext('2d');
    const labs = MONTHLY_DATA.map(r => r.month_label);
    const data = MONTHLY_DATA.map(r => parseFloat(r.export_kwh||0));
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labs,
            datasets: [{
                label: 'Grid Export (kWh)',
                data,
                borderColor: PALETTE.export.border,
                backgroundColor: PALETTE.export.bg,
                fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { ...tooltipStyles } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, beginAtZero: true, ticks: { callback: v => v + ' kWh' } }
            }
        }
    });
}

// ── View toggle ───────────────────────────────────────────
window.showView = function(view, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    let labels, solar, cons, exp;
    if (view === 'monthly') {
        labels = MONTHLY_DATA.map(r => r.month_label);
        solar  = MONTHLY_DATA.map(r => parseFloat(r.solar_kwh||0));
        cons   = MONTHLY_DATA.map(r => parseFloat(r.cons_kwh||0));
        exp    = MONTHLY_DATA.map(r => parseFloat(r.export_kwh||0));
    } else if (view === 'weekly') {
        labels = WEEKLY_DATA.map(r => 'Wk ' + r.wk);
        solar  = WEEKLY_DATA.map(r => parseFloat(r.solar_kwh||0));
        cons   = WEEKLY_DATA.map(r => parseFloat(r.cons_kwh||0));
        exp    = WEEKLY_DATA.map(r => parseFloat(r.export_kwh||0));
    } else {
        labels = DAILY_DATA.map(r => r.day_label);
        solar  = DAILY_DATA.map(r => parseFloat(r.solar_kwh||0));
        cons   = DAILY_DATA.map(r => parseFloat(r.cons_kwh||0));
        exp    = DAILY_DATA.map(r => parseFloat(r.export_kwh||0));
    }
    buildMainChart(labels, solar, cons, exp);
};

// ── Init ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (typeof MONTHLY_DATA !== 'undefined' && MONTHLY_DATA.length) {
        buildMainChart(
            MONTHLY_DATA.map(r => r.month_label),
            MONTHLY_DATA.map(r => parseFloat(r.solar_kwh||0)),
            MONTHLY_DATA.map(r => parseFloat(r.cons_kwh||0)),
            MONTHLY_DATA.map(r => parseFloat(r.export_kwh||0))
        );
        buildBalanceChart();
        buildExportChart();
    }
});
