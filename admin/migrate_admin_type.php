<?php
/**
 * Migration script to add admin_type column to admin_users table
 * Run this script once to add the column and set existing admins to 'multi_club'
 */

require_once '../config/database.php';

echo "<h2>Admin Type Migration</h2>";

try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'admin_type'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: orange;'>Column 'admin_type' already exists. Migration skipped.</p>";
        exit;
    }

    // Add the admin_type column
    $sql = "ALTER TABLE admin_users
            ADD COLUMN admin_type ENUM('single_club', 'multi_club') NOT NULL DEFAULT 'multi_club'
            AFTER is_email_verified";

    $pdo->exec($sql);

    echo "<p style='color: green;'>Successfully added 'admin_type' column to admin_users table.</p>";

    // Count how many admins were affected
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM admin_users");
    $count = $countStmt->fetch(PDO::FETCH_ASSOC);

    echo "<p>All {$count['total']} existing admin(s) have been set to 'multi_club' type (default).</p>";
    echo "<p style='color: green;'>Migration completed successfully!</p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>Migration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
