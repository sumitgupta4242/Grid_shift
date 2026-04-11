<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Mailer.php';
session_start();

$phone = trim($_POST['phone'] ?? '');

if (!$phone) {
    echo json_encode(['success' => false, 'error' => 'Phone number required']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('SELECT id FROM users WHERE phone = ? OR email = ?'); // Accept email or phone for demo
$stmt->execute([$phone, $phone]);
$user = $stmt->fetch();

if (!$user) {
    // For demo purposes, we will auto-create an account if phone doesn't exist so the flow works.
    // In production, you might return "Phone not registered"
    $hash = password_hash(bin2hex(random_bytes(10)), PASSWORD_BCRYPT);
    $ins = $db->prepare('INSERT INTO users (name, email, password_hash, phone, is_verified, location_lat, location_lon, city) VALUES (?,?,?,?,1,0,0,"Unknown")');
    $ins->execute(["Phone User", "phone_" . time() . "@helios.com", $hash, $phone]);
    $userId = (int)$db->lastInsertId();
    
    // Seed default devices for new user
    $defaultDevices = [['Refrigerator', 'Kitchen', 1, 150, 1, '🧊'], ['Wi-Fi Router',  'Network', 2, 20,  1, '📡']];
    $stmtDev = $db->prepare('INSERT INTO devices (user_id, name, category, priority, power_watts, is_essential, icon) VALUES (?,?,?,?,?,?,?)');
    foreach ($defaultDevices as $d) {
        $stmtDev->execute(array_merge([$userId], $d));
    }
    $user = ['id' => $userId];
}

$otp = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

$update = $db->prepare('UPDATE users SET otp_code = ?, otp_expires = ? WHERE id = ?');
$update->execute([$otp, $expires, $user['id']]);

$msg = "Your Helios login code is: {$otp}. It expires in 5 minutes.";
Mailer::sendSMS($phone, $msg);

// Actually set the session in SIMULATE mode so the frontend can catch it since ajax requests don't typically display sessions easily onto the parent window if we redirect. Wait, login_otp.php handles reloading.

echo json_encode(['success' => true, 'simulated' => (defined('SIMULATE_SMS') && SIMULATE_SMS)]);
