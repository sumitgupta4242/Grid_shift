// dashboard.js – Real-time polling + Chart.js rendering
'use strict';

const API_BASE = typeof window.APP_URL !== 'undefined' ? window.APP_URL + '/api' : 'api';

// ── Chart.js global defaults ──────────────────────────────
Chart.defaults.color          = '#5a7a62';
Chart.defaults.borderColor    = 'rgba(255,255,255,0.06)';
Chart.defaults.font.family    = "'Inter', sans-serif";
Chart.defaults.font.size      = 12;

// ── Energy Line Chart ─────────────────────────────────────
let energyChart;
function buildEnergyChart(rows) {
    if (!Array.isArray(rows)) {
        console.error('Invalid chart data:', rows);
        return;
    }
    const labels = rows.map(r => r.hr || r.day || r.label || '');
    const solar  = rows.map(r => parseFloat(r.solar));
    const cons   = rows.map(r => parseFloat(r.consumption));
    const exp    = rows.map(r => parseFloat(r.export || r.grid_export || 0));

    const ctx = document.getElementById('energyChart').getContext('2d');
    if (energyChart) energyChart.destroy();
    energyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Solar (kW)',
                    data: solar,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.08)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                },
                {
                    label: 'Consumption (kW)',
                    data: cons,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.08)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                },
                {
                    label: 'Grid Export (kW)',
                    data: exp,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.06)',
                    fill: false,
                    tension: 0.4,
                    borderWidth: 1.5,
                    borderDash: [5, 3],
                    pointRadius: 2,
                },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16, color: '#a8c4b0' } },
                tooltip: {
                    backgroundColor: '#101c14',
                    borderColor: 'rgba(80,200,120,0.25)',
                    borderWidth: 1,
                    callbacks: {
                        label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toFixed(2)} kW`
                    }
                }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { maxRotation: 0, maxTicksLimit: 12 } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, beginAtZero: true, ticks: { callback: v => v + ' kW' } }
            }
        }
    });
}

// ── Battery Doughnut Gauge ────────────────────────────────
let battChart;
function buildBattGauge(pct) {
    const ctx = document.getElementById('battGauge').getContext('2d');
    const col = pct >= 70 ? '#50c878' : pct >= 35 ? '#f59e0b' : '#ef4444';
    if (battChart) battChart.destroy();
    battChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [pct, 100 - pct],
                backgroundColor: [col, 'rgba(255,255,255,0.05)'],
                borderWidth: 0,
                circumference: 270,
                rotation: -135,
            }]
        },
        options: {
            responsive: false,
            cutout: '78%',
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false },
            }
        },
        plugins: [{
            id: 'centerText',
            afterDraw(chart) {
                const { ctx, chartArea: { width, height, left, top } } = chart;
                ctx.save();
                ctx.font = 'bold 28px Inter';
                ctx.fillStyle = '#e8f5ec';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(Math.round(pct) + '%', left + width / 2, top + height / 2 + 8);
                ctx.restore();
            }
        }]
    });
}

// ── Real-time polling ─────────────────────────────────────
function updateKPI(data) {
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('kpi-solar',   data.solar_kw.toFixed(2));
    set('kpi-battery', data.battery_pct.toFixed(0));
    set('kpi-consume', data.consumption_kw.toFixed(2));
    set('kpi-export',  data.grid_export_kw.toFixed(2));

    const bar = document.getElementById('batt-bar');
    if (bar) bar.style.width = data.battery_pct + '%';

    const label = document.getElementById('batt-pct-label');
    if (label) label.textContent = data.battery_pct.toFixed(1) + '% Charged';

    const cap = parseFloat(window.PANEL_KW || 5.5);
    const stored = (data.battery_pct / 100 * 10).toFixed(2);
    const s = document.getElementById('stored-kwh');
    if (s) s.textContent = stored + ' kWh';

    buildBattGauge(data.battery_pct);
}

async function pollRealtime() {
    try {
        const res  = await fetch(API_BASE + '/get_realtime.php');
        const data = await res.json();
        if (!data.error) updateKPI(data);
    } catch(e) { console.warn('Realtime poll failed', e); }
}

// ── Chart time-range tabs ─────────────────────────────────
window.switchChart = async function(range, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Update chart title if it exists
    const titleEl = btn.closest('.card')?.querySelector('h3');
    if (titleEl) {
        if (range === '24h') titleEl.innerHTML = '⚡ Energy Flow — Last 24 Hours';
        else if (range === '7d') titleEl.innerHTML = '⚡ Energy Flow — Last 7 Days';
        else if (range === '30d') titleEl.innerHTML = '⚡ Energy Flow — Last 30 Days';
    }

    try {
        const res  = await fetch(API_BASE + '/get_chart_data.php?range=' + range);
        const data = await res.json();
        
        if (data.error) {
            console.error('API Error:', data.error);
            return;
        }
        
        buildEnergyChart(data);
    } catch(e) { console.warn('Chart fetch failed', e); }
};

// ── Init ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (typeof CHART_24H !== 'undefined') buildEnergyChart(CHART_24H);
    if (typeof BATTERY_PCT !== 'undefined') buildBattGauge(BATTERY_PCT);
    // Poll every 8 seconds
    setInterval(pollRealtime, 8000);
});
