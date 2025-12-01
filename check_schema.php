<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("DESCRIBE clubs");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in clubs table:\n";
    foreach ($columns as $col) {
        echo "- " . $col . "\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
