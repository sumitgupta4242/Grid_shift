<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/Mailer.php';
session_start();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (!$email) {
        $error = "Please enter your email address.";
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $update = $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?');
            $update->execute([$token, $expires, $user['id']]);
            
            $resetLink = APP_URL . "/reset_password.php?token={$token}";
            $msg = "Hello {$user['name']},\n\nYou have requested a password reset. Please click the link below to set a new password. This link expires in 1 hour.\n$resetLink\n\nIf you did not request this, please ignore this email.";
            
            Mailer::sendEmail($email, "Password Reset - Helios", $msg);
        }
        
        // Always show success to prevent email enumeration
        $success = "If an account with that email exists, we have sent a password reset link.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Helios</title>
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
    <h2 style="text-align:center; margin-bottom:10px;">Forgot Password</h2>
    <p style="text-align:center; color:var(--text-muted); font-size:0.9rem; margin-bottom:24px;">Enter your email to receive a reset link.</p>

    <?php if ($error): ?>
        <div style="background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.3); border-radius:8px; padding:10px; margin-bottom:16px; color:#f87171; font-size:0.85rem;"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background:rgba(80,200,120,.12); border:1px solid rgba(80,200,120,.3); border-radius:8px; padding:10px; margin-bottom:16px; color:#86efac; font-size:0.85rem;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="you@example.com">
            </div>
            <button type="submit" class="btn-primary-full">Send Reset Link</button>
        </form>
    <?php endif; ?>
    
    <div style="text-align:center; margin-top:20px; font-size:0.85rem;">
        <a href="login.php" style="color:var(--text-muted); text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>

<?php include __DIR__ . '/includes/mock_toast.php'; ?>
</body>
</html>
