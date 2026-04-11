<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $city = trim($_POST['city'] ?? 'New Delhi');
    $lat = (float) ($_POST['lat'] ?? 28.6139);
    $lon = (float) ($_POST['lon'] ?? 77.2090);

    if (!$name || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $result = registerUser($name, $email, $password, $city, $lat, $lon);
        if ($result['success']) {
            $success = $result['message']; // DO NOT Redirect. Let them click verification in the Toast
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
    <meta name="description" content="Create your Helios account and start optimizing solar energy today.">
    <title>Register – Helios Solar Optimizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .auth-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 44px 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
        }

        .auth-brand .sun {
            font-size: 2rem;
            filter: drop-shadow(0 0 12px #f59e0b);
        }

        .auth-brand h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -1px;
        }

        .auth-card h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .auth-card p {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin-bottom: 24px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 11px 16px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border);
            color: var(--text-primary);
            font-size: 0.9rem;
            font-family: inherit;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(80, 200, 120, .15);
        }

        .form-group .input-icon {
            position: relative;
        }

        .form-group .input-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .85rem;
        }

        .form-group .input-icon input {
            padding-left: 40px;
        }

        .btn-primary-full {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), #22c55e);
            color: #0a1a0e;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
            font-family: inherit;
        }

        .btn-primary-full:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(80, 200, 120, .35);
        }

        .auth-switch {
            text-align: center;
            margin-top: 18px;
            font-size: .85rem;
            color: var(--text-muted);
        }

        .auth-switch a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .alert-error {
            background: rgba(239, 68, 68, .12);
            border: 1px solid rgba(239, 68, 68, .3);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: .85rem;
            color: #f87171;
        }

        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 20px 0;
        }

        .city-hint {
            font-size: .78rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <div class="auth-card">
        <div class="auth-brand">
            <span class="sun">☀️</span>
            <h1>Helios</h1>
        </div>
        <h2>Create your account</h2>
        <p>Start optimizing your solar energy today</p>

        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert-success"
                style="background:rgba(80,200,120,.12); border:1px solid rgba(80,200,120,.3); border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:.85rem; color:#86efac;">
                <i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name <span style="color:#f87171">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="name" placeholder="Arjun Sharma"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>City</label>
                    <div class="input-icon">
                        <i class="fas fa-city"></i>
                        <input type="text" name="city" id="city" placeholder="New Delhi"
                            value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Email Address <span style="color:#f87171">*</span></label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="you@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Password <span style="color:#f87171">*</span></label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Minimum 6 characters" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Latitude</label>
                    <input type="number" name="lat" id="lat" step="0.0001" placeholder="28.6139"
                        value="<?= $_POST['lat'] ?? '28.6139' ?>">
                    <div class="city-hint">Auto-filled when you click below</div>
                </div>
                <div class="form-group">
                    <label>Longitude</label>
                    <input type="number" name="lon" id="lon" step="0.0001" placeholder="77.2090"
                        value="<?= $_POST['lon'] ?? '77.2090' ?>">
                </div>
            </div>
            <button type="button" id="btn-location"
                style="width:100%;padding:9px;border:1px solid var(--border);border-radius:8px;background:rgba(255,255,255,0.04);color:var(--text-secondary);font-size:.82rem;cursor:pointer;margin-bottom:14px;font-family:inherit;">
                <i class="fas fa-location-crosshairs"></i> &nbsp;Use My Current Location
            </button>
            <button type="submit" class="btn-primary-full">
                <i class="fas fa-solar-panel"></i> &nbsp;Create Account
            </button>
        </form>
        <div class="auth-switch">Already have an account? <a href="login.php">Sign in</a></div>
    </div>
    <script>
        document.getElementById('btn-location').addEventListener('click', function () {
            if (!navigator.geolocation) { alert('Geolocation not supported'); return; }
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detecting...';
            const btn = this;
            navigator.geolocation.getCurrentPosition(pos => {
                document.getElementById('lat').value = pos.coords.latitude.toFixed(4);
                document.getElementById('lon').value = pos.coords.longitude.toFixed(4);
                btn.innerHTML = '<i class="fas fa-check"></i> Location detected!';
                btn.style.borderColor = 'var(--accent)'; btn.style.color = 'var(--accent)';
            }, () => { btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Use My Current Location'; });
        });
    </script>
    <?php include __DIR__ . '/includes/mock_toast.php'; ?>
</body>

</html>