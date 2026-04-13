<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $result = loginUser($email, $password);
        if ($result['success']) {
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Grid shift – Smart Solar & Grid Optimizer. Login to your energy dashboard.">
    <title>Login – Grid shift Solar Optimizer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .auth-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            max-width: 960px;
            width: 95%;
            min-height: 560px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,0.5);
        }
        .auth-hero {
            background: linear-gradient(145deg, #0d1f12 0%, #1a3a20 50%, #0f2d1a 100%);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .auth-hero::before {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(80,200,120,0.15) 0%, transparent 70%);
            top: -100px; right: -100px;
        }
        .hero-logo { display: flex; align-items: center; gap: 12px; }
        .hero-logo .sun-icon { font-size: 2.4rem; filter: drop-shadow(0 0 16px #f59e0b); }
        .hero-logo h1 { font-size: 2rem; font-weight: 800; color: #fff; letter-spacing: -1px; }
        .hero-tagline { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }
        .hero-stats { display: flex; flex-direction: column; gap: 16px; margin-top: 32px; }
        .hero-stat { display: flex; align-items: center; gap: 14px; }
        .hero-stat-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(80,200,120,0.15); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .hero-stat-text h4 { font-size: 0.9rem; color: #e2f5ea; font-weight: 600; }
        .hero-stat-text p  { font-size: 0.78rem; color: var(--text-muted); }
        .hero-footer { font-size: 0.75rem; color: rgba(255,255,255,0.35); }
        .auth-form-panel { padding: 48px 40px; display: flex; flex-direction: column; justify-content: center; }
        .auth-form-panel h2 { font-size: 1.6rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
        .auth-form-panel p  { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 28px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px; }
        .form-group input {
            width: 100%; padding: 12px 16px; border-radius: 10px;
            background: rgba(255,255,255,0.06); border: 1px solid var(--border);
            color: var(--text-primary); font-size: 0.9rem; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        .form-group input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(80,200,120,0.15); }
        .form-group .input-icon { position: relative; }
        .form-group .input-icon i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem; }
        .form-group .input-icon input { padding-left: 40px; }
        .btn-primary-full {
            width: 100%; padding: 13px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), #22c55e);
            color: #0a1a0e; font-size: 0.95rem; font-weight: 700; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s; letter-spacing: 0.3px;
            font-family: inherit;
        }
        .btn-primary-full:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(80,200,120,0.35); }
        .auth-switch { text-align: center; margin-top: 20px; font-size: 0.85rem; color: var(--text-muted); }
        .auth-switch a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .demo-badge {
            background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.3);
            border-radius: 8px; padding: 10px 14px; margin-bottom: 22px;
            font-size: 0.8rem; color: #f59e0b;
        }
        .demo-badge strong { display: block; margin-bottom: 2px; }
        .alert-error {
            background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3);
            border-radius: 8px; padding: 10px 14px; margin-bottom: 18px;
            font-size: 0.85rem; color: #f87171;
        }
        @media (max-width: 700px) {
            .auth-wrapper { grid-template-columns: 1fr; }
            .auth-hero { display: none; }
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <!-- Hero Panel -->
    <div class="auth-hero">
        <div>
            <div class="hero-logo">
                <span class="sun-icon">☀️</span>
                <div>
                    <h1>Grid shift</h1>
                    <div class="hero-tagline">Smart Solar & Grid Optimizer</div>
                </div>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-icon">⚡</div>
                    <div class="hero-stat-text">
                        <h4>Real-time Monitoring</h4>
                        <p>Live solar output, battery & consumption</p>
                    </div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-icon">🤖</div>
                    <div class="hero-stat-text">
                        <h4>AI-Powered Forecasts</h4>
                        <p>30-day predictive solar generation</p>
                    </div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-icon">🌿</div>
                    <div class="hero-stat-text">
                        <h4>Carbon Tracker</h4>
                        <p>Track your CO₂ offset in real time</p>
                    </div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-icon">🔌</div>
                    <div class="hero-stat-text">
                        <h4>IoT Device Control</h4>
                        <p>Smart appliance load balancing</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="hero-footer">© 2026 Grid shift – Final Year Project Demo</div>
    </div>

    <!-- Form Panel -->
    <div class="auth-form-panel">
        <h2>Welcome back 👋</h2>
        <p>Sign in to your energy dashboard</p>

        <div class="demo-badge">
            <strong>🎓 Demo Credentials</strong>
            Email: demo@helios.com &nbsp;|&nbsp; Password: password
        </div>

        <?php if ($error): ?>
        <div class="alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div style="background:rgba(80,200,120,.12); border:1px solid rgba(80,200,120,.3); border-radius:8px; padding:10px; margin-bottom:18px; color:#86efac; font-size:0.85rem;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="you@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <label for="password" style="margin-bottom:0;">Password</label>
                    <a href="forgot_password.php" style="font-size:0.82rem; color:var(--accent); text-decoration:none;">Forgot password?</a>
                </div>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="btn-primary-full" id="btn-login" style="margin-top:4px;">
                <i class="fas fa-bolt"></i> &nbsp;Sign In
            </button>
        </form>
        
        <div style="display:flex; align-items:center; margin:24px 0;">
            <div style="flex:1; height:1px; background:var(--border);"></div>
            <div style="padding:0 14px; font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">or continue with</div>
            <div style="flex:1; height:1px; background:var(--border);"></div>
        </div>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <a href="api/google_login.php" style="display:flex; align-items:center; justify-content:center; gap:8px; background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:10px; padding:10px; color:var(--text-primary); text-decoration:none; font-size:0.9rem; transition:background 0.2s;">
                <i class="fab fa-google" style="color:#ea4335;"></i> Google
            </a>
            <a href="login_otp.php" style="display:flex; align-items:center; justify-content:center; gap:8px; background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:10px; padding:10px; color:var(--text-primary); text-decoration:none; font-size:0.9rem; transition:background 0.2s;">
                <i class="fas fa-mobile-screen" style="color:#60a5fa;"></i> Phone OTP
            </a>
        </div>

        <div class="auth-switch">
            Don't have an account? <a href="register.php">Create one free</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/mock_toast.php'; ?>
</body>
</html>
