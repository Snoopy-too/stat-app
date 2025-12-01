<?php
require_once 'config/database.php';

try {
    // Check if column exists first
    $stmt = $pdo->query("SHOW COLUMNS FROM clubs LIKE 'slug'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE clubs ADD COLUMN slug VARCHAR(255) UNIQUE DEFAULT NULL";
        $pdo->exec($sql);
        echo "Column 'slug' added successfully.";
    } else {
        echo "Column 'slug' already exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
