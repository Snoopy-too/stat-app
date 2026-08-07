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
        
        // Prepare SQL query searching club_name, slug, and description
        $stmt = $pdo->prepare("SELECT club_id, club_name, description, slug,
            CASE
                WHEN LOWER(club_name) = LOWER(?) THEN 1
                WHEN LOWER(club_name) LIKE LOWER(?) THEN 2
                WHEN LOWER(slug) LIKE LOWER(?) THEN 3
                WHEN LOWER(description) LIKE LOWER(?) THEN 4
                WHEN SOUNDEX(club_name) = SOUNDEX(?) THEN 5
                ELSE 6
            END as match_priority
            FROM clubs
            WHERE (is_private = 0 OR is_private IS NULL)
            AND (LOWER(club_name) LIKE LOWER(?)
            OR LOWER(slug) LIKE LOWER(?)
            OR LOWER(description) LIKE LOWER(?)
            OR SOUNDEX(club_name) = SOUNDEX(?))
            ORDER BY match_priority, club_name
            LIMIT 10");
        
        $stmt->execute([
            $search_term,
            $search_pattern,
            $search_pattern,
            $search_pattern,
            $search_term,
            $search_pattern,
            $search_pattern,
            $search_pattern,
            $search_term
        ]);
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