<?php
// includes/header.php – Sidebar + Top Header
// Requires: $pageTitle, $pageSubtitle, $activePage, $user
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Helios – Smart Solar & Grid Optimizer Dashboard">
    <title><?= htmlspecialchars($pageTitle ?? 'Helios') ?> – Helios Solar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" as="script">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <script>window.APP_URL = '<?= APP_URL ?>';</script>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<div class="app-shell">

<!-- ══ SIDEBAR ═══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="sun-icon">☀️</span>
        <div>
            <h2>Helios</h2>
            <span>Solar Optimizer v1.0</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= ($activePage==='dashboard') ? 'active' : '' ?>">
            <span class="nav-icon">⚡</span> Dashboard
            <?php if ($activePage==='dashboard'): ?>
            <span class="nav-badge">Live</span>
            <?php endif; ?>
        </a>
        <a href="<?= APP_URL ?>/analytics.php" class="nav-item <?= ($activePage==='analytics') ? 'active' : '' ?>">
            <span class="nav-icon">📈</span> Analytics
        </a>

        <div class="nav-section-label">Intelligence</div>
        <a href="<?= APP_URL ?>/forecast.php" class="nav-item <?= ($activePage==='forecast') ? 'active' : '' ?>">
            <span class="nav-icon">🤖</span> AI Forecast
        </a>
        <a href="<?= APP_URL ?>/devices.php" class="nav-item <?= ($activePage==='devices') ? 'active' : '' ?>">
            <span class="nav-icon">🔌</span> IoT Devices
        </a>

        <div class="nav-section-label">Finance & Impact</div>
        <a href="<?= APP_URL ?>/net_metering.php" class="nav-item <?= ($activePage==='net_metering') ? 'active' : '' ?>">
            <span class="nav-icon">⚖️</span> Net Metering
        </a>
        <a href="<?= APP_URL ?>/carbon.php" class="nav-item <?= ($activePage==='carbon') ? 'active' : '' ?>">
            <span class="nav-icon">🌿</span> Carbon Tracker
        </a>

        <div class="nav-section-label">Account</div>
        <a href="<?= APP_URL ?>/settings.php" class="nav-item <?= ($activePage==='settings') ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span> Settings
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= htmlspecialchars($user['avatar'] ?? '☀️') ?></div>
            <div class="user-info">
                <h4><?= htmlspecialchars($user['name'] ?? 'User') ?></h4>
                <p><?= htmlspecialchars($user['city'] ?? 'N/A') ?></p>
            </div>
            <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Logout">
                <i class="fas fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

<!-- ══ MAIN CONTENT ══════════════════════════════════════════ -->
<div class="main-content">

    <!-- Top Header -->
    <header class="top-header">
        <button class="header-btn" id="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none">
            <i class="fas fa-bars"></i>
        </button>
        <div class="page-title">
            <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
            <p><?= htmlspecialchars($pageSubtitle ?? '') ?></p>
        </div>
        <div class="header-right">
            <div class="live-indicator" id="live-status">
                <div class="live-dot"></div>
                Live
            </div>
            <a href="<?= APP_URL ?>/settings.php" class="header-btn" title="Settings">
                <i class="fas fa-gear"></i>
            </a>
            <a href="<?= APP_URL ?>/logout.php" class="header-btn" title="Logout">
                <i class="fas fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <!-- Page Content starts here -->
    <div class="page-content">
