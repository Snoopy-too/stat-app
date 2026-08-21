<?php
require_once __DIR__ . '/../config/session.php';

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
if (isset($_COOKIE['tfd_stat_session'])) {
    setcookie('tfd_stat_session', '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to central home site
$host = $_SERVER['HTTP_HOST'] ?? 'stats.theflyingdutchmen.games';
$domain = (strpos($host, 'theflyingdutchmen.com') !== false) ? 'theflyingdutchmen.com' : 'theflyingdutchmen.games';
header("Location: https://{$domain}/logout");
exit();
