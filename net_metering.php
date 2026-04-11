<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/NetMeter.php';
requireLogin();

$user   = getCurrentUser();
$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$meter  = new NetMeter($db, $userId);

$records = $meter->getAllRecords();
$currentMonth = $records[0] ?? null;

$totalExported = array_sum(array_column($records, 'exported_kwh'));
$totalImported = array_sum(array_column($records, 'imported_kwh'));
$totalCredits = array_sum(array_column($records, 'credit_amount'));
$totalSavings = array_sum(array_column($records, 'net_savings'));

$pageTitle    = 'Net Metering';
$pageSubtitle = 'Grid export tracking & credit estimation';
$activePage   = 'net_metering';
include __DIR__ . '/includes/header.php';
?>

<!-- ══ LIFETIME METRICS ═════════════════════════════════════ -->
<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card export">
        <span class="kpi-icon">⚡</span>
        <div class="kpi-label">Total Exported</div>
        <div class="kpi-value"><?= number_format($totalExported, 1) ?></div>
        <div class="kpi-unit">kWh lifetime</div>
    </div>
    <div class="kpi-card consume">
        <span class="kpi-icon">🔌</span>
        <div class="kpi-label">Total Imported</div>
        <div class="kpi-value"><?= number_format($totalImported, 1) ?></div>
        <div class="kpi-unit">kWh lifetime</div>
    </div>
    <div class="kpi-card battery">
        <span class="kpi-icon">💵</span>
        <div class="kpi-label">Earned Credits</div>
        <div class="kpi-value">$<?= number_format($totalCredits, 2) ?></div>
        <div class="kpi-unit">lifetime</div>
    </div>
    <div class="kpi-card carbon">
        <span class="kpi-icon">💰</span>
        <div class="kpi-label">Net Savings</div>
        <div class="kpi-value">$<?= number_format($totalSavings, 2) ?></div>
        <div class="kpi-unit">lifetime</div>
    </div>
</div>

<!-- ══ CURRENT MONTH ESTIMATE ═══════════════════════════════ -->
<?php if ($currentMonth): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-title">📅 Current Month Estimate (<?= date('F Y', strtotime($currentMonth['month_year'].'-01')) ?>)</div>
    <div class="three-col" style="margin-top:16px;">
        <div class="meter-card">
            <div class="meter-value"><?= number_format($currentMonth['exported_kwh'], 1) ?></div>
            <div class="meter-label">kWh Exported</div>
        </div>
        <div class="meter-card" style="background:linear-gradient(135deg, rgba(239,68,68,0.1), rgba(245,158,11,0.1)); border-color:rgba(239,68,68,0.25);">
            <div class="meter-value"><?= number_format($currentMonth['imported_kwh'], 1) ?></div>
            <div class="meter-label">kWh Imported</div>
        </div>
        <div class="meter-card" style="background:linear-gradient(135deg, rgba(80,200,120,0.1), rgba(16,185,129,0.1)); border-color:rgba(80,200,120,0.25);">
            <div class="meter-value">$<?= number_format($currentMonth['net_savings'], 2) ?></div>
            <div class="meter-label">Est. Net Savings (at $<?= number_format($user['tariff_rate'],3) ?>/kWh)</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══ HISTORICAL TABLE ═════════════════════════════════════ -->
<div class="card">
    <div class="section-header">
        <div>
            <h2>📝 Billing History</h2>
            <p>Monthly net metering breakdown</p>
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Billing Month</th>
                    <th>Exported (kWh)</th>
                    <th>Imported (kWh)</th>
                    <th>Net Generation (kWh)</th>
                    <th>Earned Credits</th>
                    <th>Net Savings</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): 
                    $netGen = $r['exported_kwh'] - $r['imported_kwh'];
                ?>
                <tr>
                    <td style="font-weight:600;color:var(--text-primary)"><?= date('M Y', strtotime($r['month_year'].'-01')) ?></td>
                    <td><span class="badge badge-purple"><?= number_format($r['exported_kwh'], 1) ?></span></td>
                    <td><span class="badge badge-red"><?= number_format($r['imported_kwh'], 1) ?></span></td>
                    <td>
                        <?php if ($netGen > 0): ?>
                            <span style="color:var(--accent)">+<?= number_format($netGen, 1) ?></span>
                        <?php else: ?>
                            <span style="color:var(--danger)"><?= number_format($netGen, 1) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>$<?= number_format($r['credit_amount'], 2) ?></td>
                    <td style="font-weight:700;color:var(--accent)">$<?= number_format($r['net_savings'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No billing history available yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
