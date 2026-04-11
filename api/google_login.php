<?php
// api/google_login.php
require_once __DIR__ . '/../config/config.php';
session_start();

if (GOOGLE_OAUTH_CLIENT_ID === 'test_client_id_placeholder') {
    // Demo Mode: Mock the Google Authentication
    $_SESSION['mock_google_profile'] = [
        'id' => 'g123456789',
        'email' => 'google_demo_user@helios.com',
        'name' => 'Google Demo User',
        'picture' => 'https://ui-avatars.com/api/?name=Google+Demo'
    ];
    header('Location: ' . APP_URL . '/api/google_callback.php?code=mock_code_123');
    exit;
}

// Real OAuth Redirect
$oauthParams = [
    'client_id' => GOOGLE_OAUTH_CLIENT_ID,
    'redirect_uri' => GOOGLE_OAUTH_REDIRECT,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online'
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($oauthParams);
header('Location: ' . $authUrl);
exit;
