<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
session_start();

$token = $_GET['token'] ?? '';
$error = $success = '';
$validToken = false;

if (!$token) {
    $error = "No reset token provided.";
} else {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $validToken = true;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pass1 = $_POST['password'] ?? '';
            $pass2 = $_POST['confirm'] ?? '';
            
            if (strlen($pass1) < 6) {
                $error = "Password must be at least 6 characters.";
            } elseif ($pass1 !== $pass2) {
                $error = "Passwords do not match.";
            } else {
                $hash = password_hash($pass1, PASSWORD_BCRYPT);
                $update = $db->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
                if ($update->execute([$hash, $user['id']])) {
                    $success = "Your password has been successfully reset. You can now log in.";
                    $validToken = false; // Hide form
                } else {
                    $error = "Database error. Please try again.";
                }
            }
        }
    } else {
        $error = "This password reset token is invalid or has expired.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password - Helios</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        .auth-card { background: var(--card-bg); border:1px solid var(--border); border-radius:24px; padding:44px 40px; width:100%; max-width:420px; box-shadow:0 30px 80px rgba(0,0,0,0.5); }
        .auth-brand { display:flex; align-items:center; gap:10px; margin-bottom:28px; justify-content:center; }
        .auth-brand .sun { font-size:2rem; filter:drop-shadow(0 0 12px #f59e0b); }
        .auth-brand h1 { font-size:1.6rem; font-weight:800; color:var(--text-primary); letter-spacing:-1px; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:0.82rem; font-weight:500; color:var(--text-secondary); margin-bottom:6px; }
        .form-group input { width:100%; padding:11px 16px; border-radius:10px; background:rgba(255,255,255,0.06); border:1px solid var(--border); color:var(--text-primary); font-size:0.9rem; outline:none; transition:border-color .2s; }
        .form-group input:focus { border-color:var(--accent); }
        .btn-primary-full { width:100%; padding:13px; border:none; border-radius:10px; background:linear-gradient(135deg,var(--accent),#22c55e); color:#0a1a0e; font-size:.95rem; font-weight:700; cursor:pointer; font-family:inherit; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-brand">
        <span class="sun">☀️</span><h1>Helios</h1>
    </div>
    <h2 style="text-align:center; margin-bottom:10px;">Create New Password</h2>
    
    <?php if ($error): ?>
        <div style="background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.3); border-radius:8px; padding:10px; margin-bottom:16px; color:#f87171; font-size:0.85rem;"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background:rgba(80,200,120,.12); border:1px solid rgba(80,200,120,.3); border-radius:8px; padding:20px; text-align:center; color:#86efac; font-size:0.95rem;">
            <i class="fas fa-check-circle" style="font-size:2rem; margin-bottom:10px;"></i><br>
            <?= htmlspecialchars($success) ?><br><br>
            <a href="login.php" class="btn btn-primary" style="display:inline-block;">Go to Login</a>
        </div>
    <?php elseif ($validToken): ?>
        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" required placeholder="Minimum 6 characters">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm" required placeholder="Repeat your password">
            </div>
            <button type="submit" class="btn-primary-full">Save Password</button>
        </form>
    <?php else: ?>
        <div style="text-align:center; margin-top:20px;">
            <a href="forgot_password.php" style="color:var(--accent);">Request a new link</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
