<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user   = getCurrentUser();
$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Grid settings
    $tariff = (float)($_POST['tariff'] ?? $user['tariff_rate']);
    $panel  = (float)($_POST['panel'] ?? $user['panel_capacity_kw']);
    $batt   = (float)($_POST['batt'] ?? $user['battery_capacity_kwh']);
    $city   = trim($_POST['city'] ?? $user['city']);
    
    // Profile settings
    $name   = trim($_POST['name'] ?? $user['name']);
    $email  = trim($_POST['email'] ?? $user['email']);
    $newPwd = $_POST['new_password'] ?? '';
    
    if ($tariff <= 0 || $panel <= 0) {
        $errorMsg = "Grid values must be greater than zero.";
    } elseif (!$name || !$email) {
        $errorMsg = "Name and Email cannot be empty.";
    } else {
        // Build dynamic query based on if password changed
        $sql = "UPDATE users SET tariff_rate=?, panel_capacity_kw=?, battery_capacity_kwh=?, city=?, name=?, email=?";
        $params = [$tariff, $panel, $batt, $city, $name, $email];
        
        if (strlen($newPwd) >= 6) {
            $sql .= ", password_hash=?";
            $params[] = password_hash($newPwd, PASSWORD_BCRYPT);
        } elseif (!empty($newPwd)) {
            $errorMsg = "Password must be at least 6 characters.";
        }
        
        if (!$errorMsg) {
            $sql .= " WHERE id=?";
            $params[] = $userId;
            
            $stmt = $db->prepare($sql);
            if ($stmt->execute($params)) {
                $successMsg = "Settings and Profile updated successfully.";
                $user = getCurrentUser(); // Refresh local user data
                // Update session
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
            } else {
                $errorMsg = "Failed to update settings.";
            }
        }
    }
}

$pageTitle    = 'System Settings';
$pageSubtitle = 'Configure your solar grid parameters';
$activePage   = 'settings';
include __DIR__ . '/includes/header.php';
?>

<?php if ($successMsg): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<div class="two-col">
    <!-- Grid & Solar Config -->
    <div class="card">
        <form method="POST">
            <div class="settings-group">
                <h3>⚡ Power & Grid Parameters</h3>
                
                <div class="setting-row">
                    <div class="setting-label">
                        <h4>Grid Feed-in Tariff</h4>
                        <p>Rate per kWh paid for exported energy ($)</p>
                    </div>
                    <div class="setting-input">
                        <input type="number" step="0.001" name="tariff" value="<?= htmlspecialchars($user['tariff_rate']) ?>" required>
                    </div>
                </div>
                
                <div class="setting-row">
                    <div class="setting-label">
                        <h4>Solar Panel Capacity</h4>
                        <p>Total installed potential (kW)</p>
                    </div>
                    <div class="setting-input">
                        <input type="number" step="0.1" name="panel" value="<?= htmlspecialchars($user['panel_capacity_kw']) ?>" required>
                    </div>
                </div>

                <div class="setting-row">
                    <div class="setting-label">
                        <h4>Battery Storage Capacity</h4>
                        <p>Total usable battery bank size (kWh)</p>
                    </div>
                    <div class="setting-input">
                        <input type="number" step="0.1" name="batt" value="<?= htmlspecialchars($user['battery_capacity_kwh']) ?>" required>
                    </div>
                </div>
            </div>

            <div class="settings-group">
                <h3>📍 Location Profile</h3>
                
                <div class="setting-row">
                    <div class="setting-label">
                        <h4>Installation City</h4>
                        <p>Used to fetch regional weather data</p>
                    </div>
                    <div class="setting-input">
                        <input type="text" name="city" value="<?= htmlspecialchars($user['city']) ?>" required>
                    </div>
                </div>
                <div class="setting-row">
                    <div class="setting-label">
                        <h4>GPS Coordinates</h4>
                        <p>Auto-assigned during registration</p>
                    </div>
                    <div class="setting-input">
                        <span style="font-size:0.85rem;color:var(--text-muted);">
                            Lat: <?= htmlspecialchars($user['location_lat']) ?>, Lon: <?= htmlspecialchars($user['location_lon']) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:10px;">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </form>
    </div>

    <!-- Account Details -->
    <div class="card">
        <form method="POST">
            <div class="settings-group">
                <h3>👤 Account Profile</h3>
                
                <div class="setting-row">
                    <div class="setting-label">
                        <h4>Full Name</h4>
                        <p>Associated with the account</p>
                    </div>
                    <div class="setting-input">
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                </div>
                <div class="setting-row">
                    <div class="setting-label">
                        <h4>Email Address</h4>
                        <p>Login identifier</p>
                    </div>
                    <div class="setting-input">
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                </div>
                <div class="setting-row">
                    <div class="setting-label">
                        <h4>New Password</h4>
                        <p>Leave blank to keep existing password</p>
                    </div>
                    <div class="setting-input">
                        <input type="password" name="new_password" placeholder="••••••••">
                    </div>
                </div>
                <div class="setting-row">
                    <div class="setting-label">
                        <h4>Account Created</h4>
                        <p>Date of registration</p>
                    </div>
                    <div class="setting-input">
                        <span style="font-size:0.9rem; color:var(--text-primary); padding:11px 0; display:block;"><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:10px;">
                <i class="fas fa-user-edit"></i> Update Profile
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
