<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user       = getCurrentUser();
$db         = getDB();
$userId     = (int)$_SESSION['user_id'];

// Latest reading
$latest = $db->prepare("SELECT * FROM energy_readings WHERE user_id=? ORDER BY recorded_at DESC LIMIT 1");
$latest->execute([$userId]);
$now = $latest->fetch();

// Today's totals
$today = $db->prepare("SELECT
    COALESCE(SUM(solar_kw),0)       AS solar_total,
    COALESCE(SUM(consumption_kw),0) AS cons_total,
    COALESCE(SUM(grid_export_kw),0) AS export_total,
    COALESCE(AVG(battery_pct),0)    AS avg_batt
    FROM energy_readings
    WHERE user_id=? AND DATE(recorded_at)=CURDATE()");
$today->execute([$userId]);
$todayStats = $today->fetch();

// Active devices
$devStmt = $db->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(power_watts),0) AS total_w FROM devices WHERE user_id=? AND is_on=1");
$devStmt->execute([$userId]);
$devStats = $devStmt->fetch();

// Monthly CO2 saved
$co2Stmt = $db->prepare("SELECT COALESCE(SUM(co2_saved_kg),0) AS total FROM carbon_offsets WHERE user_id=? AND MONTH(offset_date)=MONTH(CURDATE())");
$co2Stmt->execute([$userId]);
$co2Month = $co2Stmt->fetchColumn();

// Last 24h readings for the real-time chart (hourly)
$chartStmt = $db->prepare("
    SELECT DATE_FORMAT(recorded_at,'%H:00') AS hr,
           ROUND(AVG(solar_kw),2)        AS solar,
           ROUND(AVG(consumption_kw),2)  AS consumption,
           ROUND(AVG(grid_export_kw),2)  AS export
    FROM energy_readings
    WHERE user_id=? AND recorded_at >= NOW() - INTERVAL 24 HOUR
    GROUP BY hr ORDER BY hr");
$chartStmt->execute([$userId]);
$chartRows = $chartStmt->fetchAll();

// Low priority devices that could be auto-switched off
$autoSwitch = $db->prepare("SELECT name,power_watts FROM devices WHERE user_id=? AND is_on=1 AND is_essential=0 ORDER BY priority DESC LIMIT 3");
$autoSwitch->execute([$userId]);
$switchable = $autoSwitch->fetchAll();

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Real-time solar & grid overview — ' . date('D, d M Y');
$activePage   = 'dashboard';

$extraScripts = '<script src="' . APP_URL . '/assets/js/dashboard.js" defer></script>';
include __DIR__ . '/includes/header.php';
?>

<!-- ══ KPI STRIP ═══════════════════════════════════════════ -->
<div class="kpi-grid">
    <div class="kpi-card solar">
        <span class="kpi-icon">☀️</span>
        <div class="kpi-label">Solar Output</div>
        <div class="kpi-value" id="kpi-solar"><?= number_format($now['solar_kw'] ?? 0, 2) ?></div>
        <div class="kpi-unit">kW current</div>
        <div class="kpi-change up"><i class="fas fa-arrow-trend-up"></i> <?= number_format($todayStats['solar_total'], 1) ?> kWh today</div>
    </div>
    <div class="kpi-card battery">
        <span class="kpi-icon">🔋</span>
        <div class="kpi-label">Battery Level</div>
        <div class="kpi-value" id="kpi-battery"><?= number_format($now['battery_pct'] ?? 0, 0) ?></div>
        <div class="kpi-unit">% charged</div>
        <div style="margin-top:8px;">
            <div class="progress-bar"><div class="progress-fill fill-green" id="batt-bar" style="width:<?= $now['battery_pct'] ?? 0 ?>%"></div></div>
        </div>
    </div>
    <div class="kpi-card consume">
        <span class="kpi-icon">🏠</span>
        <div class="kpi-label">Consumption</div>
        <div class="kpi-value" id="kpi-consume"><?= number_format($now['consumption_kw'] ?? 0, 2) ?></div>
        <div class="kpi-unit">kW now</div>
        <div class="kpi-change down"><i class="fas fa-bolt"></i> <?= number_format($todayStats['cons_total'], 1) ?> kWh today</div>
    </div>
    <div class="kpi-card export">
        <span class="kpi-icon">⚡</span>
        <div class="kpi-label">Grid Export</div>
        <div class="kpi-value" id="kpi-export"><?= number_format($now['grid_export_kw'] ?? 0, 2) ?></div>
        <div class="kpi-unit">kW → grid</div>
        <div class="kpi-change up"><i class="fas fa-arrow-trend-up"></i> <?= number_format($todayStats['export_total'], 1) ?> kWh today</div>
    </div>
    <div class="kpi-card carbon">
        <span class="kpi-icon">🌿</span>
        <div class="kpi-label">CO₂ Saved</div>
        <div class="kpi-value"><?= number_format($co2Month, 1) ?></div>
        <div class="kpi-unit">kg this month</div>
        <div class="kpi-change up"><i class="fas fa-leaf"></i> <?= number_format($co2Month / 21.77, 1) ?> trees equiv.</div>
    </div>
</div>

<!-- ══ SOLAR FLOW ANIMATION ════════════════════════════════ -->
<div class="flow-bar" style="margin-bottom:20px;"></div>

<!-- ══ MAIN CHART + BATTERY GAUGE ══════════════════════════ -->
<div class="chart-grid" style="margin-bottom:20px;">
    <div class="card">
        <div class="chart-header">
            <h3>⚡ Energy Flow — Last 24 Hours</h3>
            <div class="tab-bar">
                <button class="tab-btn active" onclick="switchChart('24h',this)">24h</button>
                <button class="tab-btn" onclick="switchChart('7d',this)">7 Days</button>
                <button class="tab-btn" onclick="switchChart('30d',this)">30 Days</button>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="energyChart"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-title">🔋 Battery Status</div>
        <div class="gauge-wrap" style="height:200px;">
            <canvas id="battGauge" width="180" height="180"></canvas>
            <div class="gauge-label" id="batt-pct-label"><?= number_format($now['battery_pct'] ?? 0, 1) ?>% Charged</div>
        </div>
        <div style="margin-top:16px;">
            <div class="stat-row">
                <span class="stat-row-label">Capacity</span>
                <span class="stat-row-value"><?= $user['battery_capacity_kwh'] ?> kWh</span>
            </div>
            <div class="stat-row">
                <span class="stat-row-label">Stored Energy</span>
                <span class="stat-row-value" id="stored-kwh"><?= number_format(($now['battery_pct'] ?? 0) / 100 * $user['battery_capacity_kwh'], 2) ?> kWh</span>
            </div>
            <div class="stat-row">
                <span class="stat-row-label">Status</span>
                <span class="stat-row-value">
                    <?php if (($now['battery_pct'] ?? 0) >= 80): ?>
                        <span class="badge badge-green">● Optimal</span>
                    <?php elseif (($now['battery_pct'] ?? 0) >= 40): ?>
                        <span class="badge badge-yellow">● Moderate</span>
                    <?php else: ?>
                        <span class="badge badge-red">● Low</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ══ DEVICE SUMMARY + SMART ALERTS ═══════════════════════ -->
<div class="two-col" style="margin-bottom:20px;">

    <div class="card">
        <div class="card-title">🔌 Active Devices Summary</div>
        <div style="display:flex;align-items:center;gap:24px;margin-bottom:16px;">
            <div style="text-align:center;">
                <div style="font-size:2.4rem;font-weight:800;color:var(--accent)"><?= $devStats['cnt'] ?></div>
                <div style="font-size:.78rem;color:var(--text-muted)">Devices ON</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:2.4rem;font-weight:800;color:var(--warning)"><?= number_format($devStats['total_w'] / 1000, 2) ?></div>
                <div style="font-size:.78rem;color:var(--text-muted)">kW Load</div>
            </div>
            <div style="flex:1;">
                <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:6px;">Load vs Solar</div>
                <?php
                $solarNow = $now['solar_kw'] ?? 1;
                $loadPct  = $solarNow > 0 ? min(100, ($devStats['total_w']/1000) / $solarNow * 100) : 100;
                ?>
                <div class="progress-bar" style="height:10px;">
                    <div class="progress-fill <?= $loadPct > 80 ? 'fill-red' : ($loadPct > 50 ? 'fill-yellow' : 'fill-green') ?>" style="width:<?= $loadPct ?>%"></div>
                </div>
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px;"><?= number_format($loadPct,0) ?>% of solar output</div>
            </div>
        </div>
        <a href="<?= APP_URL ?>/devices.php" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">
            <i class="fas fa-sliders"></i> Manage Devices
        </a>
    </div>

    <div class="card">
        <div class="card-title">🤖 Smart Automation Alerts</div>
        <?php if (($now['solar_kw'] ?? 0) < $user['panel_capacity_kw'] * AUTO_SWITCH_THRESHOLD / 100): ?>
        <div class="alert alert-warning" style="margin-bottom:10px;">
            <i class="fas fa-triangle-exclamation"></i>
            <div>Solar output below <?= AUTO_SWITCH_THRESHOLD ?>% threshold. Consider shedding non-essential loads.</div>
        </div>
        <?php foreach ($switchable as $sw): ?>
        <div class="stat-row">
            <span class="stat-row-label">💡 <?= htmlspecialchars($sw['name']) ?></span>
            <span class="badge badge-yellow"><?= $sw['power_watts'] ?>W — can shed</span>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check"></i>
            <div>System running optimally. Solar is sufficiently covering current load.</div>
        </div>
        <div class="stat-row">
            <span class="stat-row-label">Auto-switch threshold</span>
            <span class="stat-row-value"><?= AUTO_SWITCH_THRESHOLD ?>% panel capacity</span>
        </div>
        <div class="stat-row">
            <span class="stat-row-label">Panel capacity</span>
            <span class="stat-row-value"><?= $user['panel_capacity_kw'] ?> kW</span>
        </div>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/forecast.php" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;margin-top:12px;">
            <i class="fas fa-chart-line"></i> View 30-Day Forecast
        </a>
    </div>
</div>

<!-- ══ RECENT HOURLY DATA TABLE ════════════════════════════ -->
<div class="card">
    <div class="section-header">
        <div>
            <h2>📋 Recent Hourly Readings</h2>
            <p>Last 24 hours of energy data</p>
        </div>
        <a href="<?= APP_URL ?>/analytics.php" class="btn btn-outline btn-sm">View Full History</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th><th>Solar (kW)</th><th>Consumption (kW)</th>
                    <th>Battery %</th><th>Grid Export</th><th>Grid Import</th><th>Temp °C</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $recent = $db->prepare("SELECT * FROM energy_readings WHERE user_id=? ORDER BY recorded_at DESC LIMIT 12");
            $recent->execute([$userId]);
            foreach ($recent->fetchAll() as $r):
            ?>
            <tr>
                <td><?= date('H:i', strtotime($r['recorded_at'])) ?></td>
                <td><span style="color:var(--warning);font-weight:600;"><?= number_format($r['solar_kw'],2) ?></span></td>
                <td><?= number_format($r['consumption_kw'],2) ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="progress-bar" style="width:60px;height:4px;display:inline-block;">
                            <div class="progress-fill fill-green" style="width:<?= $r['battery_pct'] ?>%"></div>
                        </div>
                        <?= number_format($r['battery_pct'],0) ?>%
                    </div>
                </td>
                <td><span class="badge badge-purple"><?= number_format($r['grid_export_kw'],2) ?> kW</span></td>
                <td><span class="badge badge-red"><?= number_format($r['grid_import_kw'],2) ?> kW</span></td>
                <td><?= number_format($r['temperature'],1) ?>°C</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden data for JS -->
<script>
const CHART_24H = <?= json_encode($chartRows) ?>;
const BATTERY_PCT = <?= $now['battery_pct'] ?? 50 ?>;
const PANEL_KW    = <?= $user['panel_capacity_kw'] ?>;
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
