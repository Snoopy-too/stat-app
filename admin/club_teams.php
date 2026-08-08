<?php
// Forwarding endpoint for backward compatibility -> manage_teams.php
session_start();
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl = 'manage_teams.php' . ($queryString !== '' ? '?' . $queryString : '');
header("Location: " . $targetUrl, true, 302);
exit();
