<?php
/**
 * Diagnostic and Migration Script for Slug Column
 * 
 * This script will:
 * 1. Check if the slug column exists in the clubs table
 * 2. If it doesn't exist, add it with the proper constraints
 * 3. Display the current schema of the clubs table
 * 
 * Run this on your Bluehost server to diagnose and fix the slug column issue.
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>\n";
echo "<html><head><title>Slug Column Diagnostic</title>";
echo "<style>body{font-family:system-ui;padding:20px;max-width:800px;margin:0 auto;}";
echo ".success{color:green;padding:10px;background:#e8f5e9;border-radius:4px;margin:10px 0;}";
echo ".error{color:red;padding:10px;background:#ffebee;border-radius:4px;margin:10px 0;}";
echo ".info{color:blue;padding:10px;background:#e3f2fd;border-radius:4px;margin:10px 0;}";
echo "pre{background:#f5f5f5;padding:10px;border-radius:4px;overflow-x:auto;}</style>";
echo "</head><body>";

echo "<h1>🔍 Slug Column Diagnostic & Fix</h1>";

try {
    // Step 1: Check if slug column exists
    echo "<h2>Step 1: Checking if 'slug' column exists...</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM clubs LIKE 'slug'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "<div class='success'>✅ Column 'slug' already exists!</div>";
        
        // Get column details
        $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<h3>Column Details:</h3>";
        echo "<pre>" . print_r($columnInfo, true) . "</pre>";
        
    } else {
        echo "<div class='error'>❌ Column 'slug' does NOT exist!</div>";
        echo "<div class='info'>Attempting to add the column...</div>";
        
        // Step 2: Add the column
        try {
            $sql = "ALTER TABLE clubs ADD COLUMN slug VARCHAR(255) UNIQUE DEFAULT NULL";
            $pdo->exec($sql);
            echo "<div class='success'>✅ Successfully added 'slug' column with UNIQUE constraint!</div>";
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Failed to add column: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<div class='info'>You may need to contact your hosting provider or check database permissions.</div>";
        }
    }
    
    // Step 3: Show current clubs table schema
    echo "<h2>Step 2: Current 'clubs' table schema:</h2>";
    $stmt = $pdo->query("DESCRIBE clubs");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    printf("%-20s %-30s %-10s %-10s %-20s %-10s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 100) . "\n";
    foreach ($schema as $column) {
        printf("%-20s %-30s %-10s %-10s %-20s %-10s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Key'],
            $column['Default'] ?? 'NULL',
            $column['Extra']
        );
    }
    echo "</pre>";
    
    // Step 4: Test query
    echo "<h2>Step 3: Testing UPDATE query...</h2>";
    try {
        // Try to prepare the update statement used in manage_clubs.php
        $testStmt = $pdo->prepare("UPDATE clubs SET club_name = ?, slug = ? WHERE club_id = ? AND admin_id = ?");
        echo "<div class='success'>✅ UPDATE query with 'slug' column can be prepared successfully!</div>";
        echo "<div class='info'>The query should work now. Try updating a club in manage_clubs.php</div>";
    } catch (PDOException $e) {
        echo "<div class='error'>❌ UPDATE query failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Step 5: Show indexes on clubs table
    echo "<h2>Step 4: Indexes on 'clubs' table:</h2>";
    $stmt = $pdo->query("SHOW INDEX FROM clubs");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    foreach ($indexes as $index) {
        echo "Index: " . $index['Key_name'] . 
             " | Column: " . $index['Column_name'] . 
             " | Unique: " . ($index['Non_unique'] ? 'No' : 'Yes') . "\n";
    }
    echo "</pre>";
    
    echo "<hr>";
    echo "<h2>✅ Diagnostic Complete!</h2>";
    echo "<div class='info'><strong>Next Steps:</strong><br>";
    echo "1. If the slug column was added, upload your updated files to Bluehost<br>";
    echo "2. Try editing a club in manage_clubs.php and adding a slug<br>";
    echo "3. If you still see errors, check your PHP error logs on Bluehost<br>";
    echo "4. For security, DELETE this diagnostic file after you're done</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'>Please check your database connection settings in config/database.php</div>";
}

echo "</body></html>";
?>
