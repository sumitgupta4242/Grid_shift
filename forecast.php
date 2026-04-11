<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Forecast.php';
requireLogin();

$user   = getCurrentUser();
$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$engine = new Forecast($db, $userId, $user);

// Try to get stored forecasts; if none, trigger generation
$stored = $engine->getStored(30);
$isSimulated = true;

if (empty($stored)) {
    // Generate inline simulated forecasts
    $fakeDays = [];
    for ($i = 0; $i < 30; $i++) {
        $ts    = strtotime("+{$i} days");
        $month = (int)date('n', $ts);
        $tempC = ($month >= 3 && $month <= 6) ? (32 + mt_rand(-4,8)) : (($month >= 7 && $month <= 9) ? (27+mt_rand(-2,5)) : (18+mt_rand(-2,6)));
        $cloud = ($month >= 7 && $month <= 9) ? mt_rand(55,90) : mt_rand(5,70);
        $fakeDays[] = [
            'dt'     => $ts,
            'main'   => ['temp' => ($tempC + 273.15), 'humidity' => mt_rand(35,70)],
            'clouds' => ['all'  => $cloud],
        ];
    }
    $engine->generateForecasts($fakeDays);
    $stored = $engine->getStored(30);
}

// Find best 3 days (highest predicted kWh)
usort($stored, fn($a,$b) => $b['predicted_kwh'] <=> $a['predicted_kwh']);
$bestDates = array_slice(array_column($stored,'forecast_date'), 0, 3);
usort($stored, fn($a,$b) => $a['forecast_date'] <=> $b['forecast_date']);

// Summary stats
$totalForecastKwh = array_sum(array_column($stored, 'predicted_kwh'));
$avgCloud         = count($stored) ? round(array_sum(array_column($stored,'cloud_cover_pct')) / count($stored), 0) : 0;
$bestDay          = $bestDates[0] ?? null;
$sunnySays        = count(array_filter($stored, fn($r) => $r['cloud_cover_pct'] <= 30));

$pageTitle    = 'AI Forecast';
$pageSubtitle = '30-day solar generation prediction powered by weather intelligence';
$activePage   = 'forecast';
include __DIR__ . '/includes/header.php';
?>

<!-- ══ INFO ALERT ════════════════════════════════════════════ -->
<?php if ($isSimulated): ?>
<div class="alert alert-info" style="margin-bottom:20px;">
    <i class="fas fa-circle-info"></i>
    <div>Forecast is generated using a <strong>physics-based regression model</strong> (simulated weather). Add your OpenWeatherMap API key in <code>config/config.php</code> for live weather data.</div>
</div>
<?php endif; ?>

<!-- ══ FORECAST KPI STRIP ════════════════════════════════════ -->
<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card solar">
        <span class="kpi-icon">☀️</span>
        <div class="kpi-label">30-Day Forecast</div>
        <div class="kpi-value"><?= number_format($totalForecastKwh,0) ?></div>
        <div class="kpi-unit">kWh predicted</div>
    </div>
    <div class="kpi-card battery">
        <span class="kpi-icon">🌤️</span>
        <div class="kpi-label">Sunny Days</div>
        <div class="kpi-value"><?= $sunnySays ?></div>
        <div class="kpi-unit">days ≤ 30% clouds</div>
    </div>
    <div class="kpi-card consume">
        <span class="kpi-icon">☁️</span>
        <div class="kpi-label">Avg Cloud Cover</div>
        <div class="kpi-value"><?= $avgCloud ?></div>
        <div class="kpi-unit">% average</div>
    </div>
    <div class="kpi-card export">
        <span class="kpi-icon">🏆</span>
        <div class="kpi-label">Best Day</div>
        <div class="kpi-value" style="font-size:1.3rem;"><?= $bestDay ? date('d M', strtotime($bestDay)) : '—' ?></div>
        <div class="kpi-unit">peak generation day</div>
    </div>
</div>

<!-- ══ FORECAST CHART ════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;">
    <div class="chart-header">
        <h3>🤖 30-Day Solar Generation Forecast</h3>
        <span class="badge badge-blue"><i class="fas fa-microchip"></i> ML Model Active</span>
    </div>
    <div class="chart-container" style="height:280px;">
        <canvas id="forecastChart"></canvas>
    </div>
</div>

<!-- ══ BEST DAYS ALERT ═══════════════════════════════════════ -->
<?php if (!empty($bestDates)): ?>
<div class="alert alert-success" style="margin-bottom:20px;">
    <i class="fas fa-star"></i>
    <div>
        <strong>Best upcoming days for high-energy tasks:</strong>
        <?php foreach ($bestDates as $bd): ?>
        <span class="badge badge-green" style="margin-left:6px;"><?= date('D, d M', strtotime($bd)) ?></span>
        <?php endforeach; ?>
        <br><small style="margin-top:4px;display:block;color:inherit;opacity:.8">Schedule EV charging, washing machine & water heater on these days.</small>
    </div>
</div>
<?php endif; ?>

<!-- ══ FORECAST CARDS GRID ═══════════════════════════════════ -->
<div class="section-header" style="margin-bottom:14px;">
    <div><h2>📅 Day-by-Day Forecast</h2><p>Click any card for detailed recommendation</p></div>
    <button class="btn btn-outline btn-sm" onclick="refreshForecast()"><i class="fas fa-rotate"></i> Refresh</button>
</div>

<div class="forecast-grid" style="margin-bottom:24px;" id="forecast-grid">
<?php foreach ($stored as $f):
    $isBest = in_array($f['forecast_date'], $bestDates);
    $icon   = Forecast::weatherIcon((int)$f['cloud_cover_pct']);
    $col    = $f['predicted_kwh'] >= 20 ? 'fill-green' : ($f['predicted_kwh'] >= 12 ? 'fill-yellow' : 'fill-red');
?>
<div class="forecast-card <?= $isBest ? 'best' : '' ?>" 
     onclick="showRec('<?= htmlspecialchars(addslashes($f['recommendation'])) ?>','<?= date('D, d M',strtotime($f['forecast_date'])) ?>')"
     style="cursor:pointer;" title="Click for recommendation">
    <?php if ($isBest): ?>
    <div style="font-size:.65rem;font-weight:700;color:var(--warning);margin-bottom:4px;text-transform:uppercase;letter-spacing:.06em;">⭐ Best Day</div>
    <?php endif; ?>
    <div class="forecast-day"><?= date('D', strtotime($f['forecast_date'])) ?><br><?= date('d M', strtotime($f['forecast_date'])) ?></div>
    <span class="forecast-icon"><?= $icon ?></span>
    <div class="forecast-kwh"><?= number_format($f['predicted_kwh'],1) ?> kWh</div>
    <div class="forecast-cloud">☁ <?= $f['cloud_cover_pct'] ?>% &nbsp; 🌡 <?= number_format($f['temperature'],0) ?>°C</div>
    <div class="forecast-conf" style="margin-top:8px;">
        <div class="progress-bar"><div class="progress-fill <?= $col ?>" style="width:<?= $f['confidence_pct'] ?>%"></div></div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:3px;"><?= $f['confidence_pct'] ?>% confidence</div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ══ RECOMMENDATION MODAL ══════════════════════════════════ -->
<div id="rec-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--card-bg);border:1px solid var(--border-accent);border-radius:20px;padding:32px;max-width:500px;width:90%;position:relative;">
        <button onclick="document.getElementById('rec-modal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text-muted);font-size:1.2rem;cursor:pointer;">✕</button>
        <div style="font-size:2rem;margin-bottom:12px;">🤖</div>
        <h3 id="rec-date" style="font-size:1rem;color:var(--accent);margin-bottom:10px;"></h3>
        <p id="rec-text" style="font-size:.92rem;color:var(--text-secondary);line-height:1.7;"></p>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);font-size:.8rem;color:var(--text-muted);">
            💡 Tip: High-energy tasks include EV charging (7.4kW), water heater (2kW), and washing machine (500W).
        </div>
    </div>
</div>

<!-- Chart data -->
<script>
const FORECAST_DATA = <?= json_encode(array_values($stored)) ?>;

function showRec(text, date) {
    document.getElementById('rec-date').textContent = date + ' — AI Recommendation';
    document.getElementById('rec-text').textContent = text;
    document.getElementById('rec-modal').style.display = 'flex';
}

async function refreshForecast() {
    const btn = event.target;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    try {
        const r = await fetch('<?= APP_URL ?>/api/get_forecast.php');
        await r.json();
        window.location.reload();
    } catch(e) { btn.innerHTML = '<i class="fas fa-rotate"></i> Refresh'; }
}

document.addEventListener('DOMContentLoaded', () => {
    const ctx    = document.getElementById('forecastChart').getContext('2d');
    const labels = FORECAST_DATA.map(r => {
        const d = new Date(r.forecast_date);
        return d.toLocaleDateString('en-IN', {day:'numeric',month:'short'});
    });
    const kwh    = FORECAST_DATA.map(r => parseFloat(r.predicted_kwh));
    const clouds = FORECAST_DATA.map(r => parseInt(r.cloud_cover_pct));
    const colors = kwh.map(v => v >= 20 ? 'rgba(80,200,120,0.7)' : v >= 12 ? 'rgba(245,158,11,0.7)' : 'rgba(239,68,68,0.7)');

    new Chart(ctx, {
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Predicted kWh',
                    data: kwh,
                    backgroundColor: colors,
                    borderColor: colors.map(c => c.replace('0.7','1')),
                    borderWidth: 1,
                    borderRadius: 6,
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: 'Cloud Cover %',
                    data: clouds,
                    borderColor: '#94a3b8',
                    backgroundColor: 'transparent',
                    borderWidth: 1.5,
                    borderDash: [4,3],
                    pointRadius: 2,
                    tension: 0.4,
                    yAxisID: 'y2',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode:'index', intersect:false },
            plugins: {
                legend: { position:'bottom', labels:{ boxWidth:12, padding:16, color:'#a8c4b0' } },
                tooltip: {
                    backgroundColor:'#101c14', borderColor:'rgba(80,200,120,0.25)', borderWidth:1,
                    callbacks: {
                        label: c => c.datasetIndex===0 ? ` Predicted: ${c.parsed.y.toFixed(1)} kWh` : ` Cloud Cover: ${c.parsed.y}%`
                    }
                }
            },
            scales: {
                x: { grid:{ color:'rgba(255,255,255,0.04)' }, ticks:{ maxRotation:45, font:{ size:10 } } },
                y: { grid:{ color:'rgba(255,255,255,0.04)' }, beginAtZero:true, ticks:{ callback:v => v+' kWh' }, position:'left' },
                y2:{ grid:{ display:false }, beginAtZero:true, max:100, ticks:{ callback:v => v+'%' }, position:'right' }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
