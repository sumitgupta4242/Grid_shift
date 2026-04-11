<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
session_start();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if ($token) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, is_verified FROM users WHERE verification_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['is_verified']) {
            $success = "Email already verified. You can log in.";
        } else {
            $update = $db->prepare('UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?');
            if ($update->execute([$user['id']])) {
                $success = "Email successfully verified! You may now log in.";
            } else {
                $error = "Failed to verify email due to a database error.";
            }
        }
    } else {
        $error = "Invalid or expired verification link.";
    }
} else {
    $error = "No verification token provided.";
}

$pageTitle = 'Email Verification';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> - Helios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;">

<div class="card" style="max-width: 450px; text-align:center; padding: 40px 30px;">
    <?php if ($success): ?>
        <i class="fas fa-circle-check" style="font-size:4rem; color:var(--accent); margin-bottom: 20px;"></i>
        <h2>Verification Complete</h2>
        <p style="color:var(--text-muted); margin-top:10px; margin-bottom: 25px;"><?= htmlspecialchars($success) ?></p>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-primary" style="width:100%; justify-content:center;">Proceed to Login</a>
    <?php else: ?>
        <i class="fas fa-circle-xmark" style="font-size:4rem; color:var(--danger); margin-bottom: 20px;"></i>
        <h2>Verification Failed</h2>
        <p style="color:var(--text-muted); margin-top:10px; margin-bottom: 25px;"><?= htmlspecialchars($error) ?></p>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-outline" style="width:100%; justify-content:center;">Return to Login</a>
    <?php endif; ?>
</div>

</body>
</html>
