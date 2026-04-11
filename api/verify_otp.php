<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
session_start();

$phone = trim($_POST['phone'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if (!$phone || !$otp) {
    echo json_encode(['success' => false, 'error' => 'Phone and OTP required']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE (phone = ? OR email = ?) AND otp_code = ? AND otp_expires > NOW()');
$stmt->execute([$phone, $phone, $otp]);
$user = $stmt->fetch();

if ($user) {
    // Invalidate OTP
    $db->prepare('UPDATE users SET otp_code = NULL, otp_expires = NULL, is_verified = 1 WHERE id = ?')->execute([$user['id']]);
    
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email']= $user['email'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP code']);
}
