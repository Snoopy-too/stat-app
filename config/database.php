<?php
require_once __DIR__ . '/env.php';

try {
    // Add charset=utf8mb4 to prevent encoding-based injection attacks
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Fetch as associative array by default
        ]
    );
} catch(PDOException $e) {
    // Log error securely without exposing details to users
    error_log("Database connection failed: " . $e->getMessage());

    // Show generic error to users (never expose database details)
    http_response_code(503);
    die("Service temporarily unavailable. Please try again later.");
}
?>