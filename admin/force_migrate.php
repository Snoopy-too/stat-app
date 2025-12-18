<?php
// admin/force_migrate.php
// TEMPORARY FILE - DELETE AFTER RUNNING
require_once '../config/database.php';

try {
    echo "<h1>Database Migration Tool</h1>";
    
    // 1. Create club_admins table
    echo "Step 1: Creating club_admins table... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS club_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            admin_id INT NOT NULL,
            role VARCHAR(20) DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (club_id, admin_id),
            FOREIGN KEY (club_id) REFERENCES clubs(club_id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Success<br>";

    // 2. Migrate existing owner data
    echo "Step 2: Migrating existing club owners... ";
    $pdo->exec("
        INSERT IGNORE INTO club_admins (club_id, admin_id, role)
        SELECT club_id, admin_id, 'owner' FROM clubs WHERE admin_id IS NOT NULL;
    ");
    echo "✅ Success<br>";

    // 3. Ensure login_attempts exists (just in case)
    echo "Step 3: Checking security tables... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            attempt_id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(255) NOT NULL,
            attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_successful TINYINT(1) DEFAULT 0,
            INDEX idx_ip_email (ip_address, email),
            INDEX idx_attempt_time (attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Success<br>";

    echo "<h3>Migration Complete! Please delete this file from your server now.</h3>";
    echo "<a href='login.php'>Go to Login</a>";

} catch (Exception $e) {
    echo "❌ <strong>Migration Failed:</strong> " . $e->getMessage();
}
?>
