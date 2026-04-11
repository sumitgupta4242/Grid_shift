<?php
// api/get_realtime.php – Returns simulated live sensor data as JSON
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (empty($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

// Get user's panel capacity
$stmt = $db->prepare("SELECT panel_capacity_kw, battery_capacity_kwh FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$hour = (int)date('H');
// Simulate solar curve (sinusoidal peak at noon)
if ($hour >= 6 && $hour <= 19) {
    $solarBase   = sin(($hour - 6) / 13.0 * M_PI) * $user['panel_capacity_kw'];
    $cloudFactor = 0.65 + (mt_rand(0, 35) / 100);
    $solarKw     = round($solarBase * $cloudFactor, 3);
} else {
    $solarKw = 0.000;
}

// Consumption varies by time
if ($hour >= 6 && $hour <= 9)        $consKw = round(1.5 + (mt_rand(0,150)/100), 3);
elseif ($hour >= 17 && $hour <= 22)  $consKw = round(2.5 + (mt_rand(0,250)/100), 3);
elseif ($hour >= 23 || $hour <= 5)   $consKw = round(0.3 + (mt_rand(0,40)/100), 3);
else                                  $consKw = round(0.8 + (mt_rand(0,100)/100), 3);

// Battery
$battPct     = round(45 + (mt_rand(0, 50)), 2);
// Grid export/import
$exportKw    = max(0, round($solarKw - $consKw, 3));
$importKw    = max(0, round($consKw - $solarKw, 3));
$temperature = round(22 + (mt_rand(0, 160)/10), 2);
$cloudCover  = mt_rand(5, 75);

// Save to DB
$ins = $db->prepare("INSERT INTO energy_readings (user_id,solar_kw,consumption_kw,battery_pct,grid_export_kw,grid_import_kw,temperature,cloud_cover,recorded_at)
                     VALUES (?,?,?,?,?,?,?,?,NOW())");
$ins->execute([$userId,$solarKw,$consKw,$battPct,$exportKw,$importKw,$temperature,$cloudCover]);

echo json_encode([
    'solar_kw'       => $solarKw,
    'consumption_kw' => $consKw,
    'battery_pct'    => $battPct,
    'grid_export_kw' => $exportKw,
    'grid_import_kw' => $importKw,
    'temperature'    => $temperature,
    'cloud_cover'    => $cloudCover,
    'timestamp'      => date('H:i:s'),
    'panel_capacity' => $user['panel_capacity_kw'],
]);
