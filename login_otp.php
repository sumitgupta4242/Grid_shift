<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Phone Login - Helios</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        .auth-card { background: var(--card-bg); border:1px solid var(--border); border-radius:24px; padding:44px 40px; width:100%; max-width:420px; box-shadow:0 30px 80px rgba(0,0,0,0.5); }
        .auth-brand { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:28px; }
        .auth-brand .sun { font-size:2rem; filter:drop-shadow(0 0 12px #f59e0b); }
        .auth-brand h1 { font-size:1.6rem; font-weight:800; color:var(--text-primary); letter-spacing:-1px; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:0.82rem; font-weight:500; color:var(--text-secondary); margin-bottom:6px; }
        .form-group input { width:100%; padding:11px 16px; border-radius:10px; background:rgba(255,255,255,0.06); border:1px solid var(--border); color:var(--text-primary); font-size:0.9rem; outline:none; transition:border-color .2s; }
        .form-group input:focus { border-color:var(--accent); }
        .input-icon { position:relative; }
        .input-icon i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.85rem; }
        .input-icon input { padding-left:40px; }
        .btn-primary-full { width:100%; padding:13px; border:none; border-radius:10px; background:linear-gradient(135deg,var(--accent),#22c55e); color:#0a1a0e; font-size:.95rem; font-weight:700; cursor:pointer; font-family:inherit; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-brand">
        <span class="sun">☀️</span><h1>Helios</h1>
    </div>
    <h2 style="text-align:center; margin-bottom:10px;">Login with Phone</h2>
    <p style="text-align:center; color:var(--text-muted); font-size:0.9rem; margin-bottom:24px;">Enter your phone number to receive a 6-digit OTP code.</p>

    <div id="error-alert" style="display:none; background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.3); border-radius:8px; padding:10px; margin-bottom:16px; color:#f87171; font-size:0.85rem;"><i class="fas fa-circle-exclamation"></i> <span id="error-msg"></span></div>

    <!-- Step 1: Request OTP -->
    <form id="form-request" onsubmit="requestOTP(event)">
        <div class="form-group">
            <label>Phone Number</label>
            <div class="input-icon">
                <i class="fas fa-phone"></i>
                <input type="tel" id="phone" required placeholder="+1 234 567 8900">
            </div>
        </div>
        <button type="submit" class="btn-primary-full" id="btn-request">Send Code</button>
    </form>

    <!-- Step 2: Verify OTP -->
    <form id="form-verify" onsubmit="verifyOTP(event)" style="display:none;">
        <div class="form-group">
            <label>Enter 6-Digit OTP</label>
            <input type="text" id="otp" required placeholder="123456" maxlength="6" style="text-align:center; font-size:1.5rem; letter-spacing:4px; font-weight:700;">
        </div>
        <button type="submit" class="btn-primary-full" id="btn-verify">Verify & Login</button>
    </form>
    
    <div style="text-align:center; margin-top:20px; font-size:0.85rem;">
        <a href="login.php" style="color:var(--text-muted); text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Email Login</a>
    </div>
</div>

<script>
async function requestOTP(e) {
    e.preventDefault();
    const phone = document.getElementById('phone').value;
    const btn = document.getElementById('btn-request');
    const err = document.getElementById('error-alert');
    
    btn.disabled = true;
    btn.innerHTML = 'Sending...';
    err.style.display = 'none';

    try {
        const formData = new FormData();
        formData.append('phone', phone);
        const res = await fetch('api/send_otp.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            document.getElementById('form-request').style.display = 'none';
            document.getElementById('form-verify').style.display = 'block';
            
            // Reload page if simulated (to show the session toast)
            if (data.simulated) {
                setTimeout(() => window.location.reload(), 500); 
            }
        } else {
            document.getElementById('error-msg').innerText = data.error;
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = 'Send Code';
        }
    } catch(err) {
        btn.disabled = false;
        btn.innerHTML = 'Send Code';
    }
}

async function verifyOTP(e) {
    e.preventDefault();
    const otp = document.getElementById('otp').value;
    const phone = document.getElementById('phone').value; // from step 1
    const btn = document.getElementById('btn-verify');
    const err = document.getElementById('error-alert');

    btn.disabled = true;
    btn.innerHTML = 'Verifying...';
    err.style.display = 'none';

    try {
        const formData = new FormData();
        formData.append('phone', phone);
        formData.append('otp', otp);
        const res = await fetch('api/verify_otp.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            document.getElementById('error-msg').innerText = data.error;
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = 'Verify & Login';
        }
    } catch(err) {
        btn.disabled = false;
        btn.innerHTML = 'Verify & Login';
    }
}
</script>

<?php include __DIR__ . '/includes/mock_toast.php'; ?>
</body>
</html>
