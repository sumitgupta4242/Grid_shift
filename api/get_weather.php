<?php
// api/get_weather.php – Fetches weather forecast from OpenWeatherMap
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (empty($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

$stmt = $db->prepare("SELECT location_lat,location_lon,city FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$lat = $user['location_lat'] ?? 28.6139;
$lon = $user['location_lon'] ?? 77.2090;

// If API key is set, fetch real weather
if (OWM_API_KEY !== 'YOUR_OPENWEATHERMAP_API_KEY') {
    $url = OWM_FORECAST_URL . "?lat={$lat}&lon={$lon}&cnt=40&appid=" . OWM_API_KEY;
    $ctx = stream_context_create(['http'=>['timeout'=>8]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw) {
        echo $raw; exit;
    }
}

// ── Fallback: Generate simulated 30-day weather ──────────
$days    = [];
$baseTs  = strtotime('today');
$seasons = ['hot'=>38,'mild'=>28,'cool'=>20]; // Temp simulation
for ($i = 0; $i < 30; $i++) {
    $ts      = $baseTs + ($i * 86400);
    $month   = (int)date('n', $ts);
    // India: hot (Mar-Jun), monsoon (Jul-Sep), cool (Oct-Feb)
    if ($month >= 3 && $month <= 6)        $tempBase = 35 + mt_rand(-5,10);
    elseif ($month >= 7 && $month <= 9)    $tempBase = 28 + mt_rand(-3,6);
    else                                   $tempBase = 20 + mt_rand(-3,8);

    // Monsoon simulates heavy clouds
    $cloudBase = ($month >= 7 && $month <= 9) ? mt_rand(55,90) : mt_rand(5,70);
    $humidity  = ($month >= 7 && $month <= 9) ? mt_rand(70,95) : mt_rand(30,65);

    $days[] = [
        'dt'   => $ts,
        'main' => [
            'temp'     => $tempBase + 273.15, // Kelvin (matches OWM format)
            'humidity' => $humidity,
        ],
        'clouds' => ['all' => $cloudBase],
        'weather'=> [['description' => $cloudBase > 60 ? 'overcast clouds' : ($cloudBase > 30 ? 'scattered clouds' : 'clear sky')]],
    ];
}
echo json_encode(['list' => $days, 'simulated' => true]);
