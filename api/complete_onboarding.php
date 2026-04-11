<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
session_start();

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$city    = trim($_POST['city'] ?? '');
$lat     = $_POST['lat'] ?? null;
$lon     = $_POST['lon'] ?? null;

if (!$name) {
    echo json_encode(['success' => false, 'error' => 'Full Name is required']);
    exit;
}

$db = getDB();

// Validate email if provided
if ($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        exit;
    }
    
    // Check if email already exists for another user
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Email already in use by another account']);
        exit;
    }
}

try {
    $stmt = $db->prepare('UPDATE users SET name = ?, email = IF(?, ?, email), city = IF(?, ?, city), location_lat = IF(?, ?, location_lat), location_lon = IF(?, ?, location_lon) WHERE id = ?');
    $stmt->execute([
        $name, 
        $email, $email, 
        $city, $city, 
        $lat !== null, $lat, 
        $lon !== null, $lon, 
        $user_id
    ]);
    
    // Update session
    $_SESSION['user_name'] = $name;
    if ($email) $_SESSION['user_email'] = $email;

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
