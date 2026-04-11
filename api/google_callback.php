<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
session_start();

$code = $_GET['code'] ?? '';

if (!$code) {
    die("Error: No authorization code received from Google.");
}

$googleUser = null;

if (GOOGLE_OAUTH_CLIENT_ID === 'test_client_id_placeholder') {
    // Demo Mode
    $googleUser = $_SESSION['mock_google_profile'] ?? null;
} else {
    // Real Execution
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_OAUTH_CLIENT_ID,
        'client_secret' => GOOGLE_OAUTH_SECRET,
        'redirect_uri' => GOOGLE_OAUTH_REDIRECT,
        'grant_type' => 'authorization_code'
    ]));
    $response = curl_exec($ch);
    $tokenData = json_decode($response, true);
    curl_close($ch);

    if (empty($tokenData['access_token'])) {
        die("Error exchanging code for access token.");
    }

    $ch2 = curl_init("https://www.googleapis.com/oauth2/v2/userinfo");
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$tokenData['access_token']}"]);
    $profileRes = curl_exec($ch2);
    $googleUser = json_decode($profileRes, true);
    curl_close($ch2);
}

if (!$googleUser || empty($googleUser['email'])) {
    die("Error fetching Google profile.");
}

$db = getDB();
$stmt = $db->prepare('SELECT id, name, email FROM users WHERE email = ?');
$stmt->execute([$googleUser['email']]);
$user = $stmt->fetch();

if ($user) {
    // User exists, update google_id and log them in
    $db->prepare('UPDATE users SET google_id = ?, is_verified = 1 WHERE id = ?')->execute([$googleUser['id'], $user['id']]);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email']= $user['email'];
} else {
    // Auto-create user via Google OAuth
    $hash = password_hash(bin2hex(random_bytes(10)), PASSWORD_BCRYPT); // Dummy password
    $ins = $db->prepare('INSERT INTO users (name, email, password_hash, google_id, is_verified, location_lat, location_lon, city) VALUES (?,?,?,?,1,0,0,"Unknown")');
    $ins->execute([$googleUser['name'], $googleUser['email'], $hash, $googleUser['id']]);
    $userId = (int)$db->lastInsertId();
    
    // Seed default devices for new user
    $defaultDevices = [
        ['Refrigerator', 'Kitchen', 1, 150, 1, '🧊'],
        ['Wi-Fi Router',  'Network', 2, 20,  1, '📡'],
        ['LED Lights',    'Lighting',3, 60,  1, '💡']
    ];
    $stmtDev = $db->prepare('INSERT INTO devices (user_id, name, category, priority, power_watts, is_essential, icon) VALUES (?,?,?,?,?,?,?)');
    foreach ($defaultDevices as $d) {
        $stmtDev->execute(array_merge([$userId], $d));
    }

    $_SESSION['user_id']   = $userId;
    $_SESSION['user_name'] = $googleUser['name'];
    $_SESSION['user_email']= $googleUser['email'];
}

header('Location: ' . APP_URL . '/dashboard.php');
exit;
