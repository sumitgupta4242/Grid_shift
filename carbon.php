<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user   = getCurrentUser();
$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Get cumulative carbon data
$stmt = $db->prepare("SELECT 
    SUM(solar_kwh) as lifetime_solar,
    SUM(co2_saved_kg) as lifetime_co2,
    SUM(coal_equivalent_kg) as lifetime_coal,
    SUM(trees_equivalent) as lifetime_trees
    FROM carbon_offsets WHERE user_id=?");
$stmt->execute([$userId]);
$lifetime = $stmt->fetch();

// Get last 30 days data for table
$historyStmt = $db->prepare("SELECT * FROM carbon_offsets WHERE user_id=? AND offset_date >= CURDATE() - INTERVAL 30 DAY ORDER BY offset_date DESC");
$historyStmt->execute([$userId]);
$history = $historyStmt->fetchAll();

$pageTitle    = 'Carbon Tracker';
$pageSubtitle = 'Monitor your environmental impact';
$activePage   = 'carbon';
include __DIR__ . '/includes/header.php';
?>

<!-- ══ CO2 HERO METRIC ══════════════════════════════════════ -->
<div class="co2-hero">
    <div class="co2-number"><?= number_format($lifetime['lifetime_co2'] ?? 0, 1) ?></div>
    <div class="co2-unit">kg of CO₂ Emissions Prevented</div>
    <div class="co2-desc">By generating <?= number_format($lifetime['lifetime_solar'] ?? 0, 1) ?> kWh of clean solar energy over your lifetime.</div>
</div>

<!-- ══ IMPACT EQUIVALENTS ═══════════════════════════════════ -->
<div class="two-col" style="margin-bottom:24px;">
    <div class="card" style="text-align:center;padding:32px 20px;">
        <div style="font-size:3rem;margin-bottom:12px;">🪴</div>
        <div style="font-size:2rem;font-weight:800;color:var(--text-primary);"><?= number_format($lifetime['lifetime_trees'] ?? 0, 1) ?></div>
        <div style="font-size:.9rem;color:var(--text-muted);margin-top:6px;">Trees Planted Equivalent</div>
        <div style="font-size:.75rem;color:var(--text-secondary);margin-top:10px;">Based on average annual absorption of 21.77 kg CO₂ per tree.</div>
    </div>
    <div class="card" style="text-align:center;padding:32px 20px;">
        <div style="font-size:3rem;margin-bottom:12px;">⛏️</div>
        <div style="font-size:2rem;font-weight:800;color:var(--text-primary);"><?= number_format($lifetime['lifetime_coal'] ?? 0, 1) ?></div>
        <div style="font-size:.9rem;color:var(--text-muted);margin-top:6px;">kg of Coal Burn Avoided</div>
        <div style="font-size:.75rem;color:var(--text-secondary);margin-top:10px;">Offsetting grid reliance minimizes coal power plant operations.</div>
    </div>
</div>

<!-- ══ RECENT HISTORY TABLE ═════════════════════════════════ -->
<div class="card">
    <div class="section-header">
        <div>
            <h2>📅 Recent Impact (Last 30 Days)</h2>
            <p>Daily environmental contribution log</p>
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Clean Energy (kWh)</th>
                    <th>CO₂ Saved (kg)</th>
                    <th>Coal Avoided (kg)</th>
                    <th>Trees Equivalent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td style="font-weight:500;"><?= date('D, d M Y', strtotime($h['offset_date'])) ?></td>
                    <td><span style="color:var(--warning);font-weight:600;"><?= number_format($h['solar_kwh'], 1) ?></span></td>
                    <td><span class="badge badge-green"><?= number_format($h['co2_saved_kg'], 1) ?></span></td>
                    <td><?= number_format($h['coal_equivalent_kg'], 2) ?></td>
                    <td><?= number_format($h['trees_equivalent'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($history)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">No recent data.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
