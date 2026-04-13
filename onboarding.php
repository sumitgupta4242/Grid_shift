<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
session_start();

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$user = getCurrentUser();

// If profile is already complete, redirect to dashboard
if ($user['name'] !== 'Phone User') {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - Grid shift</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        .onboarding-card { 
            background: var(--card-bg); 
            border:1px solid var(--border); 
            border-radius:24px; 
            padding:44px 40px; 
            width:100%; 
            max-width:500px; 
            box-shadow:0 30px 80px rgba(0,0,0,0.5); 
            animation: fadeIn 0.6s ease;
        }
        .onboarding-brand { display:flex; align-items:center; gap:10px; margin-bottom:28px; }
        .onboarding-brand .sun { font-size:2rem; filter:drop-shadow(0 0 12px #f59e0b); }
        .onboarding-brand h1 { font-size:1.6rem; font-weight:800; color:var(--text-primary); letter-spacing:-1px; }
        
        .progress-steps { display:flex; gap:8px; margin-bottom:32px; }
        .step { flex:1; height:4px; border-radius:10px; background:rgba(255,255,255,0.06); position:relative; }
        .step.active { background: var(--accent); box-shadow: 0 0 10px var(--accent-glow); }
        
        h2 { font-size:1.5rem; font-weight:700; color:var(--text-primary); margin-bottom:8px; }
        p.subtitle { color:var(--text-muted); font-size:0.9rem; margin-bottom:28px; }
        
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-size:0.82rem; font-weight:500; color:var(--text-secondary); margin-bottom:8px; }
        .form-group input { 
            width:100%; padding:12px 16px; border-radius:12px; 
            background:rgba(255,255,255,0.04); border:1px solid var(--border); 
            color:var(--text-primary); font-size:0.95rem; outline:none; transition:all .2s;
        }
        .form-group input:focus { border-color:var(--accent); background:rgba(80,200,120,0.03); }
        
        .optional-tag { float:right; font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-top:2px; }
        
        .location-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        
        .btn-submit { 
            width:100%; padding:14px; border:none; border-radius:12px; 
            background:linear-gradient(135deg, var(--accent), #22c55e); 
            color:#0a1a0e; font-size:1rem; font-weight:700; cursor:pointer; 
            transition:all .3s; margin-top:10px; font-family:inherit;
        }
        .btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(80,200,120,0.3); }
        .btn-submit:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

        .btn-location {
            width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;
            background:rgba(255,255,255,0.03); color:var(--text-secondary);
            font-size:0.85rem; cursor:pointer; transition:all .2s; margin-bottom:20px;
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .btn-location:hover { border-color:var(--text-muted); color:var(--text-primary); }
    </style>
</head>
<body>
<div class="onboarding-card">
    <div class="onboarding-brand">
        <span class="sun">☀️</span><h1>Grid shift</h1>
    </div>
    
    <div class="progress-steps">
        <div class="step active"></div>
        <div class="step"></div>
    </div>

    <h2>One last step!</h2>
    <p class="subtitle">Welcome to Grid shift. Let's personalize your experience by completing your profile details.</p>

    <div id="error-alert" style="display:none;" class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i> <span id="error-msg"></span>
    </div>

    <form id="onboarding-form" onsubmit="completeOnboarding(event)">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" id="name" required placeholder="Enter your full name" autofocus>
        </div>

        <div class="form-group">
            <label>Email Address <span class="optional-tag">Optional</span></label>
            <input type="email" id="email" placeholder="email@example.com">
        </div>

        <div class="form-group">
            <label>City <span class="optional-tag">Optional</span></label>
            <input type="text" id="city" placeholder="e.g. New Delhi">
        </div>

        <div class="location-row">
            <div class="form-group">
                <label>Latitude</label>
                <input type="number" id="lat" step="0.0001" placeholder="28.6139">
            </div>
            <div class="form-group">
                <label>Longitude</label>
                <input type="number" id="lon" step="0.0001" placeholder="77.2090">
            </div>
        </div>

        <button type="button" class="btn-location" id="btn-location">
            <i class="fas fa-location-crosshairs"></i> Get Current Location
        </button>

        <button type="submit" class="btn-submit" id="btn-submit">Complete Setup <i class="fas fa-arrow-right"></i></button>
    </form>
</div>

<script>
document.getElementById('btn-location').addEventListener('click', function() {
    if (!navigator.geolocation) { alert('Geolocation not supported'); return; }
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';
    btn.disabled = true;

    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('lat').value = pos.coords.latitude.toFixed(4);
        document.getElementById('lon').value = pos.coords.longitude.toFixed(4);
        btn.innerHTML = '<i class="fas fa-check"></i> Location Found';
        btn.style.borderColor = 'var(--accent)';
        btn.style.color = 'var(--accent)';
        btn.disabled = false;
    }, () => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        alert('Could not detect location. Please enter manually.');
    });
});

async function completeOnboarding(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit');
    const err = document.getElementById('error-alert');
    const msg = document.getElementById('error-msg');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finalizing...';
    err.style.display = 'none';

    const formData = new FormData();
    formData.append('name', document.getElementById('name').value);
    formData.append('email', document.getElementById('email').value);
    formData.append('city', document.getElementById('city').value);
    formData.append('lat', document.getElementById('lat').value);
    formData.append('lon', document.getElementById('lon').value);

    try {
        const res = await fetch('api/complete_onboarding.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            msg.innerText = data.error;
            err.style.display = 'flex';
            btn.disabled = false;
            btn.innerHTML = 'Complete Setup <i class="fas fa-arrow-right"></i>';
        }
    } catch (e) {
        msg.innerText = 'An unexpected error occurred.';
        err.style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = 'Complete Setup <i class="fas fa-arrow-right"></i>';
    }
}
</script>
</body>
</html>
