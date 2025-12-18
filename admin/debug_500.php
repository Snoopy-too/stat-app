<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Debug Info</h1>";
echo "PHP Version: " . PHP_VERSION . "<br>";

echo "<h2>Checking Requirements</h2>";
$files = [
    '../config/database.php',
    '../config/security_headers.php',
    '../config/session.php',
    '../includes/SecurityUtils.php',
    '../includes/helpers.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ Found: $file<br>";
        try {
            require_once $file;
            echo "--- ✅ Required: $file<br>";
        } catch (Throwable $e) {
            echo "--- ❌ Error requiring $file: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Missing: $file<br>";
    }
}

echo "<h2>Checking Database Connection and Tables</h2>";
if (isset($pdo)) {
    echo "✅ PDO connection established.<br>";
    $tables = ['admin_users', 'clubs', 'club_admins', 'members', 'games', 'game_results', 'team_game_results', 'csrf_tokens'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            echo "✅ Table exists: $table<br>";
        } catch (PDOException $e) {
            echo "❌ Table missing or error on $table: " . $e->getMessage() . "<br>";
        }
    }
} else {
    echo "❌ PDO connection not found. Check config/database.php<br>";
}
?>
