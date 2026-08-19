<?php
require_once __DIR__ . '/../config/session.php';

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to central home site
header('Location: https://theflyingdutchmen.games/logout');
exit();