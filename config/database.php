<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'statapp');
define('DB_USER', 'Fidel');
define('DB_PASS', 'r149327jjj');
// Why is this being pushed instead of ignored?

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>