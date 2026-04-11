<?php
// api/get_chart_data.php – Returns historical chart data by range
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (empty($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

$userId = (int)$_SESSION['user_id'];
$range  = $_GET['range'] ?? '24h';
$db     = getDB();

switch ($range) {
    case '7d':
        $sql = "SELECT DATE_FORMAT(recorded_at,'%a %d') AS label,
                       ROUND(SUM(solar_kw),2)       AS solar,
                       ROUND(SUM(consumption_kw),2) AS consumption,
                       ROUND(SUM(grid_export_kw),2) AS export
                FROM energy_readings
                WHERE user_id=? AND recorded_at >= NOW() - INTERVAL 7 DAY
                GROUP BY DATE(recorded_at) ORDER BY DATE(recorded_at)";
        break;
    case '30d':
        $sql = "SELECT DATE_FORMAT(recorded_at,'%d %b') AS label,
                       ROUND(SUM(solar_kw),2)       AS solar,
                       ROUND(SUM(consumption_kw),2) AS consumption,
                       ROUND(SUM(grid_export_kw),2) AS export
                FROM energy_readings
                WHERE user_id=? AND recorded_at >= NOW() - INTERVAL 30 DAY
                GROUP BY DATE(recorded_at) ORDER BY DATE(recorded_at)";
        break;
    default: // 24h
        $sql = "SELECT DATE_FORMAT(recorded_at,'%H:00') AS hr,
                       ROUND(AVG(solar_kw),2)       AS solar,
                       ROUND(AVG(consumption_kw),2) AS consumption,
                       ROUND(AVG(grid_export_kw),2) AS export
                FROM energy_readings
                WHERE user_id=? AND recorded_at >= NOW() - INTERVAL 24 HOUR
                GROUP BY hr ORDER BY hr";
        break;
}

$stmt = $db->prepare($sql);
$stmt->execute([$userId]);
echo json_encode($stmt->fetchAll());
