<?php
// admin/migrate_sharing.php
require_once '../config/database.php';

// CLI or Super admin check
if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
        die("Unauthorized access.");
    }
}

try {
    // 1. Create club_admins table
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

    $pdo->beginTransaction();
    // 2. Migrate existing admin_id from clubs table to club_admins
    $pdo->exec("
        INSERT IGNORE INTO club_admins (club_id, admin_id, role)
        SELECT club_id, admin_id, 'owner' FROM clubs WHERE admin_id IS NOT NULL;
    ");

    $pdo->commit();
    echo "Migration successful. club_admins table created and data migrated.";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage();
}
