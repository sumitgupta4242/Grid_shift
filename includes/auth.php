<?php
// ============================================================
// Helios – Auth Middleware
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $db  = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function loginUser(string $email, string $password): array {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    if ((int)$user['is_verified'] === 0) {
        return ['success' => false, 'message' => 'Please verify your email address before logging in.'];
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email']= $user['email'];
    return ['success' => true];
}

function registerUser(string $name, string $email, string $password, string $city, float $lat, float $lon): array {
    require_once __DIR__ . '/../classes/Mailer.php';
    
    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }

    $hash  = password_hash($password, PASSWORD_BCRYPT);
    $token = bin2hex(random_bytes(32)); // Verification token
    
    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, city, location_lat, location_lon, is_verified, verification_token) VALUES (?,?,?,?,?,?,0,?)');
    $stmt->execute([$name, $email, $hash, $city, $lat, $lon, $token]);
    $userId = (int)$db->lastInsertId();

    // Seed default devices for new user
    $defaultDevices = [
        ['Refrigerator', 'Kitchen', 1, 150, 1, '🧊'],
        ['Wi-Fi Router',  'Network', 2, 20,  1, '📡'],
        ['LED Lights',    'Lighting',3, 60,  1, '💡'],
        ['Air Conditioner','HVAC',   4, 2000,0, '❄️'],
        ['Television',    'Multimedia',5,120,0, '📺'],
    ];
    $ins = $db->prepare('INSERT INTO devices (user_id, name, category, priority, power_watts, is_essential, icon) VALUES (?,?,?,?,?,?,?)');
    foreach ($defaultDevices as $d) {
        $ins->execute(array_merge([$userId], $d));
    }

    // Send Verification Email
    $verifyLink = APP_URL . "/verify_email.php?token={$token}";
    $msg = "Hello $name,\n\nPlease click the following link to verify your account:\n$verifyLink\n\nThanks,\nHelios Team";
    Mailer::sendEmail($email, "Verify Your Helios Account", $msg);

    // Notice we do NOT start the session here anymore!
    return ['success' => true, 'message' => 'Registration successful! Please check your email to verify your account.'];
}
