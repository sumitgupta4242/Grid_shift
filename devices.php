<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Device.php';
requireLogin();

$user   = getCurrentUser();
$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$mgr    = new Device($db, $userId);
$devices = $mgr->getAll();

// Group by category
$grouped = [];
foreach ($devices as $d) {
    $grouped[$d['category']][] = $d;
}

$totalLoadKw  = $mgr->totalLoadKw();
$activeCount  = count(array_filter($devices, fn($d) => $d['is_on']));
$totalDevices = count($devices);

$pageTitle    = 'IoT Devices';
$pageSubtitle = 'Smart appliance control & load balancing';
$activePage   = 'devices';
$extraScripts = '<script src="' . APP_URL . '/assets/js/devices.js" defer></script>';
include __DIR__ . '/includes/header.php';
?>

<!-- ══ LOAD SUMMARY ══════════════════════════════════════════ -->
<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card solar">
        <span class="kpi-icon">🔌</span>
        <div class="kpi-label">Active Devices</div>
        <div class="kpi-value" id="active-count"><?= $activeCount ?></div>
        <div class="kpi-unit">of <?= $totalDevices ?> total</div>
    </div>
    <div class="kpi-card consume">
        <span class="kpi-icon">⚡</span>
        <div class="kpi-label">Total Load</div>
        <div class="kpi-value" id="total-load"><?= number_format($totalLoadKw,2) ?></div>
        <div class="kpi-unit">kW active</div>
    </div>
    <div class="kpi-card battery">
        <span class="kpi-icon">🛡️</span>
        <div class="kpi-label">Essential Devices</div>
        <div class="kpi-value"><?= count(array_filter($devices, fn($d) => $d['is_essential'])) ?></div>
        <div class="kpi-unit">always protected</div>
    </div>
    <div class="kpi-card export">
        <span class="kpi-icon">📉</span>
        <div class="kpi-label">Sheddable Load</div>
        <div class="kpi-value"><?= number_format(array_sum(array_map(fn($d)=>$d['is_on']&&!$d['is_essential']?$d['power_watts']:0,$devices))/1000,2) ?></div>
        <div class="kpi-unit">kW can be shed</div>
    </div>
</div>

<!-- ══ AUTO-SHED ALERT ═══════════════════════════════════════ -->
<div class="alert alert-info" style="margin-bottom:20px;">
    <i class="fas fa-robot"></i>
    <div>
        <strong>Smart Load Balancing:</strong> When solar output falls below <?= AUTO_SWITCH_THRESHOLD ?>% of panel capacity (<?= number_format($user['panel_capacity_kw'] * AUTO_SWITCH_THRESHOLD/100, 2) ?> kW),
        non-essential devices are flagged for auto-shedding in priority order.
    </div>
</div>

<!-- ══ ADD DEVICE FORM ═══════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-title">➕ Add New Device</div>
    <form id="add-device-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end;">
        <div>
            <label style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:4px;">Device Name</label>
            <input type="text" name="name" placeholder="e.g. Ceiling Fan" required
                   style="width:100%;padding:9px 12px;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-primary);font-size:.88rem;font-family:inherit;outline:none;">
        </div>
        <div>
            <label style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:4px;">Category</label>
            <select name="category" style="width:100%;padding:9px 12px;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-primary);font-size:.88rem;font-family:inherit;outline:none;">
                <option>Kitchen</option><option>HVAC</option><option>Lighting</option>
                <option>Appliance</option><option>Electronics</option><option>Network</option>
                <option>Transport</option><option>Multimedia</option><option>General</option>
            </select>
        </div>
        <div>
            <label style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:4px;">Power (Watts)</label>
            <input type="number" name="watts" placeholder="100" min="1" max="15000" required
                   style="width:100%;padding:9px 12px;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-primary);font-size:.88rem;font-family:inherit;outline:none;">
        </div>
        <div>
            <label style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:4px;">Priority (1=highest)</label>
            <input type="number" name="priority" value="5" min="1" max="20"
                   style="width:100%;padding:9px 12px;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-primary);font-size:.88rem;font-family:inherit;outline:none;">
        </div>
        <div>
            <label style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:4px;">Icon (emoji)</label>
            <input type="text" name="icon" value="🔌" maxlength="4"
                   style="width:100%;padding:9px 12px;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-primary);font-size:.88rem;font-family:inherit;outline:none;text-align:center;">
        </div>
        <div>
            <label style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:4px;">&nbsp;</label>
            <button type="submit" class="btn btn-primary" style="width:100%;">
                <i class="fas fa-plus"></i> Add Device
            </button>
        </div>
    </form>
    <div id="add-result" style="margin-top:10px;"></div>
</div>

<!-- ══ DEVICE CARDS BY CATEGORY ══════════════════════════════ -->
<?php foreach ($grouped as $category => $catDevices): ?>
<div style="margin-bottom:20px;">
    <div class="section-header" style="margin-bottom:12px;">
        <div><h2><?= htmlspecialchars($category) ?> Devices</h2></div>
        <span class="badge badge-blue"><?= count($catDevices) ?> device<?= count($catDevices)!==1?'s':'' ?></span>
    </div>
    <div class="device-grid">
    <?php foreach ($catDevices as $d): ?>
    <div class="device-card <?= $d['is_on'] ? 'on' : 'off' ?>" id="device-<?= $d['id'] ?>">
        <div class="device-header">
            <div>
                <span class="device-icon"><?= htmlspecialchars($d['icon']) ?></span>
                <?php if ($d['is_essential']): ?>
                <span class="essential-badge">Essential</span>
                <?php else: ?>
                <span class="priority-badge">Priority <?= $d['priority'] ?></span>
                <?php endif; ?>
            </div>
            <label class="toggle" title="<?= $d['is_essential']&&$d['is_on'] ? 'Essential – cannot turn off' : 'Toggle device' ?>">
                <input type="checkbox" class="device-toggle" data-id="<?= $d['id'] ?>"
                       <?= $d['is_on'] ? 'checked' : '' ?>
                       <?= $d['is_essential'] && $d['is_on'] ? 'disabled' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="device-name"><?= htmlspecialchars($d['name']) ?></div>
        <div class="device-meta"><?= htmlspecialchars($d['location'] ?? 'Home') ?></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
            <div class="device-power"><?= $d['power_watts'] >= 1000 ? number_format($d['power_watts']/1000,1).' kW' : $d['power_watts'].' W' ?></div>
            <div style="display:flex;gap:6px;">
                <?php if (!$d['is_essential']): ?>
                <button class="btn btn-danger btn-sm btn-icon" onclick="deleteDevice(<?= $d['id'] ?>)" title="Remove device">
                    <i class="fas fa-trash-can"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div style="margin-top:10px;font-size:.78rem;color:<?= $d['is_on'] ? 'var(--accent)' : 'var(--text-muted)' ?>">
            <i class="fas fa-circle" style="font-size:.5rem;"></i>
            <?= $d['is_on'] ? 'Active – consuming ' . ($d['power_watts'] >= 1000 ? number_format($d['power_watts']/1000,1).' kW' : $d['power_watts'].' W') : 'Off – standby' ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
const API = '<?= APP_URL ?>/api/device_toggle.php';
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
