<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/SSOHelper.php';

$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    $_SESSION['error'] = "Authentication failed: " . htmlspecialchars($error);
    header("Location: admin/login.php");
    exit();
}

if (!$code) {
    header("Location: admin/login.php");
    exit();
}

$clientId = 'stats-app-756c55ba';
$clientSecret = '5138c552e18e228e34c8a4e977ba3bb417c123192bb1b7e19d4ac1b28b60357f';
$redirectUri = 'https://stats.theflyingdutchmen.games/auth_callback.php';
$tokenUrl = 'http://127.0.0.1:4000/oauth/token';
$userinfoUrl = 'http://127.0.0.1:4000/oauth/userinfo';

// Exchange code for token
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Host: theflyingdutchmen.games'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri
]));
$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? null;

if (!$accessToken) {
    $_SESSION['error'] = "Failed to retrieve access token from authorization server.";
    header("Location: admin/login.php");
    exit();
}

// Fetch user profile
$ch = curl_init($userinfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Host: theflyingdutchmen.games'
]);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);
$email = $userData['email'] ?? null;

if (!$email) {
    $_SESSION['error'] = "Failed to retrieve user email from authorization server.";
    header("Location: admin/login.php");
    exit();
}

if (SSOHelper::loginAdminByEmail($email)) {
    $clubId = $_SESSION['current_club_id'] ?? $_SESSION['club_id'] ?? null;
    if ($clubId) {
        header("Location: admin/club_new_results.php?club_id=" . (int)$clubId);
    } else {
        header("Location: admin/select_club.php");
    }
    exit();
} else {
    $_SESSION['error'] = "No StatApp administrator account found for " . htmlspecialchars($email);
    header("Location: admin/login.php");
    exit();
}
