<?php
// admin/migrate_extra_fields.php
require_once '../config/database.php';

try {
    // Add missing columns to clubs table
    $pdo->exec("
        ALTER TABLE clubs 
        ADD COLUMN description TEXT AFTER slug,
        ADD COLUMN meeting_day VARCHAR(20) AFTER description,
        ADD COLUMN meeting_time TIME AFTER meeting_day,
        ADD COLUMN location VARCHAR(255) AFTER meeting_time,
        ADD COLUMN status ENUM('active', 'suspended', 'inactive') DEFAULT 'active' AFTER location;
    ");

    echo "Migration successful. Extra club fields added.";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
