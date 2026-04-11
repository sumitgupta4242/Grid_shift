<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user   = getCurrentUser();
$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Monthly totals for the last 12 months
$monthly = $db->prepare("
    SELECT DATE_FORMAT(recorded_at,'%b %Y') AS month_label,
           DATE_FORMAT(recorded_at,'%Y-%m') AS month_key,
           ROUND(SUM(solar_kw),2)       AS solar_kwh,
           ROUND(SUM(consumption_kw),2) AS cons_kwh,
           ROUND(SUM(grid_export_kw),2) AS export_kwh,
           ROUND(SUM(grid_import_kw),2) AS import_kwh
    FROM energy_readings
    WHERE user_id=? AND recorded_at >= NOW() - INTERVAL 12 MONTH
    GROUP BY month_key ORDER BY month_key");
$monthly->execute([$userId]);
$monthlyData = $monthly->fetchAll();

// Weekly breakdown last 8 weeks
$weekly = $db->prepare("
    SELECT WEEK(recorded_at,1) AS wk,
           MIN(DATE(recorded_at)) AS week_start,
           ROUND(SUM(solar_kw),2)       AS solar_kwh,
           ROUND(SUM(consumption_kw),2) AS cons_kwh,
           ROUND(SUM(grid_export_kw),2) AS export_kwh
    FROM energy_readings
    WHERE user_id=? AND recorded_at >= NOW() - INTERVAL 8 WEEK
    GROUP BY wk ORDER BY wk");
$weekly->execute([$userId]);
$weeklyData = $weekly->fetchAll();

// Daily last 30 days
$daily = $db->prepare("
    SELECT DATE_FORMAT(recorded_at,'%d %b') AS day_label,
           ROUND(SUM(solar_kw),2)       AS solar_kwh,
           ROUND(SUM(consumption_kw),2) AS cons_kwh,
           ROUND(AVG(battery_pct),1)    AS avg_batt,
           ROUND(SUM(grid_export_kw),2) AS export_kwh
    FROM energy_readings
    WHERE user_id=? AND recorded_at >= NOW() - INTERVAL 30 DAY
    GROUP BY DATE(recorded_at) ORDER BY DATE(recorded_at)");
$daily->execute([$userId]);
$dailyData = $daily->fetchAll();

// Summary stats
$totals = $db->prepare("
    SELECT ROUND(SUM(solar_kw),1)       AS total_solar,
           ROUND(SUM(consumption_kw),1) AS total_cons,
           ROUND(SUM(grid_export_kw),1) AS total_export,
           ROUND(AVG(battery_pct),1)    AS avg_batt
    FROM energy_readings WHERE user_id=?");
$totals->execute([$userId]);
$totals = $totals->fetch();

$pageTitle    = 'Analytics';
$pageSubtitle = 'Deep-dive into your historical energy data';
$activePage   = 'analytics';
$extraScripts = '<script src="' . APP_URL . '/assets/js/charts.js" defer></script>';
include __DIR__ . '/includes/header.php';
?>

<!-- ══ SUMMARY STATS ════════════════════════════════════════ -->
<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card solar">
        <span class="kpi-icon">☀️</span>
        <div class="kpi-label">Total Generated</div>
        <div class="kpi-value"><?= number_format($totals['total_solar'],0) ?></div>
        <div class="kpi-unit">kWh all-time</div>
    </div>
    <div class="kpi-card consume">
        <span class="kpi-icon">🏠</span>
        <div class="kpi-label">Total Consumed</div>
        <div class="kpi-value"><?= number_format($totals['total_cons'],0) ?></div>
        <div class="kpi-unit">kWh all-time</div>
    </div>
    <div class="kpi-card export">
        <span class="kpi-icon">⚡</span>
        <div class="kpi-label">Grid Exported</div>
        <div class="kpi-value"><?= number_format($totals['total_export'],0) ?></div>
        <div class="kpi-unit">kWh all-time</div>
    </div>
    <div class="kpi-card battery">
        <span class="kpi-icon">🔋</span>
        <div class="kpi-label">Avg Battery</div>
        <div class="kpi-value"><?= number_format($totals['avg_batt'],1) ?></div>
        <div class="kpi-unit">% average</div>
    </div>
    <div class="kpi-card carbon">
        <span class="kpi-icon">📊</span>
        <div class="kpi-label">Self-Sufficiency</div>
        <?php $ss = $totals['total_cons'] > 0 ? min(100, $totals['total_solar'] / $totals['total_cons'] * 100) : 0; ?>
        <div class="kpi-value"><?= number_format($ss,0) ?></div>
        <div class="kpi-unit">% solar coverage</div>
    </div>
</div>

<!-- ══ MONTHLY CHART ════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;">
    <div class="chart-header">
        <h3>📅 Monthly Energy Overview (Last 12 Months)</h3>
        <div class="tab-bar">
            <button class="tab-btn active" id="tab-bar" onclick="showView('monthly',this)">Monthly</button>
            <button class="tab-btn" onclick="showView('weekly',this)">Weekly</button>
            <button class="tab-btn" onclick="showView('daily',this)">Daily</button>
        </div>
    </div>
    <div class="chart-container" style="height:300px;">
        <canvas id="mainAnalyticsChart"></canvas>
    </div>
</div>

<!-- ══ SECONDARY CHARTS ══════════════════════════════════════ -->
<div class="two-col" style="margin-bottom:20px;">
    <div class="card">
        <div class="chart-header"><h3>☀️ Solar vs Consumption Balance</h3></div>
        <div class="chart-container" style="height:220px;">
            <canvas id="balanceChart"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="chart-header"><h3>⚡ Grid Export Trend</h3></div>
        <div class="chart-container" style="height:220px;">
            <canvas id="exportChart"></canvas>
        </div>
    </div>
</div>

<!-- ══ MONTHLY TABLE ════════════════════════════════════════ -->
<div class="card">
    <div class="section-header">
        <div>
            <h2>📋 Monthly Energy Log</h2>
            <p>Detailed breakdown per month</p>
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th><th>Solar (kWh)</th><th>Consumed (kWh)</th>
                    <th>Exported (kWh)</th><th>Imported (kWh)</th>
                    <th>Self-Sufficiency</th><th>Surplus</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monthlyData as $m):
                $selfSuf = $m['cons_kwh'] > 0 ? min(100, $m['solar_kwh'] / $m['cons_kwh'] * 100) : 0;
                $surplus = $m['solar_kwh'] - $m['cons_kwh'];
            ?>
            <tr>
                <td style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($m['month_label']) ?></td>
                <td><span style="color:var(--warning);font-weight:600;"><?= number_format($m['solar_kwh'],1) ?></span></td>
                <td><?= number_format($m['cons_kwh'],1) ?></td>
                <td><span class="badge badge-purple"><?= number_format($m['export_kwh'],1) ?></span></td>
                <td><span class="badge badge-red"><?= number_format($m['import_kwh'],1) ?></span></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="progress-bar" style="width:80px;height:5px;">
                            <div class="progress-fill <?= $selfSuf>=70?'fill-green':($selfSuf>=40?'fill-yellow':'fill-red') ?>" style="width:<?= $selfSuf ?>%"></div>
                        </div>
                        <span style="font-size:.82rem;"><?= number_format($selfSuf,0) ?>%</span>
                    </div>
                </td>
                <td>
                    <?php if ($surplus >= 0): ?>
                    <span class="badge badge-green">+<?= number_format($surplus,1) ?> kWh</span>
                    <?php else: ?>
                    <span class="badge badge-red"><?= number_format($surplus,1) ?> kWh</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart data injected for JS -->
<script>
const MONTHLY_DATA = <?= json_encode(array_values($monthlyData)) ?>;
const WEEKLY_DATA  = <?= json_encode(array_values($weeklyData)) ?>;
const DAILY_DATA   = <?= json_encode(array_values($dailyData)) ?>;
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
