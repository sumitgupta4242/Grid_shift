<?php
// api/get_forecast.php – Runs ML prediction and returns JSON
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/Forecast.php';
if (empty($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Fetch weather (internal API call)
$weatherUrl = APP_URL . '/api/get_weather.php';
// Use file_get_contents with cookie header to pass session
$opts = ['http' => ['header' => "Cookie: " . session_name() . "=" . session_id()]];
$ctx  = stream_context_create($opts);
$raw  = @file_get_contents($weatherUrl, false, $ctx);

if (!$raw) {
    // Inline fallback weather data
    $weatherData = ['list'=>[],'simulated'=>true];
    for ($i=0; $i<30; $i++) {
        $weatherData['list'][] = [
            'dt'     => strtotime("+{$i} days"),
            'main'   => ['temp'=>300+mt_rand(-8,12), 'humidity'=> mt_rand(35,70)],
            'clouds' => ['all' => mt_rand(5,75)],
        ];
    }
} else {
    $weatherData = json_decode($raw, true);
}

$engine    = new Forecast($db, $userId, $user);
$forecasts = $engine->generateForecasts($weatherData['list'] ?? []);

echo json_encode([
    'forecasts' => $forecasts,
    'simulated' => $weatherData['simulated'] ?? false,
    'city'      => $user['city'],
]);
