<?php
header('Content-Type: application/json');
require_once 'config/database.php';

// Get and sanitize search term
$search_term = isset($_GET['term']) ? trim($_GET['term']) : '';

// Prepare response array
$response = [];

if (strlen($search_term) >= 2) {
    try {
        // Create search pattern with wildcards
        $search_pattern = "%{$search_term}%";
        
        // Prepare SQL query using LIKE for partial matches and SOUNDEX for fuzzy matching
        // Order results by match priority (exact matches first, then SOUNDEX matches)
        $stmt = $pdo->prepare("SELECT club_id, club_name,
            CASE 
                WHEN LOWER(club_name) LIKE LOWER(?) THEN 1
                WHEN SOUNDEX(club_name) = SOUNDEX(?) THEN 2
                ELSE 3
            END as match_priority
            FROM clubs 
            WHERE LOWER(club_name) LIKE LOWER(?)
            OR SOUNDEX(club_name) = SOUNDEX(?)
            ORDER BY match_priority, club_name
            LIMIT 10");
        
        $stmt->execute([$search_pattern, $search_term, $search_pattern, $search_term]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remove match_priority from results
        foreach ($response as &$result) {
            unset($result['match_priority']);
        }
        
    } catch (PDOException $e) {
        // Log error but don't expose details to client
        error_log('Search clubs error: ' . $e->getMessage());
    }
}

// Set JSON response headers
header('Content-Type: application/json');
echo json_encode($response);