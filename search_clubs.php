<?php
header('Content-Type: application/json');
require_once 'config/database.php';

// Get and sanitize search term
$search_term = isset($_GET['term']) ? trim($_GET['term']) : '';

$response = [];

if (strlen($search_term) >= 2) {
    try {
        // Inspect available columns in 'clubs' table for safe query execution
        $colStmt = $pdo->query("SHOW COLUMNS FROM clubs");
        $existingCols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        
        $hasSlug = in_array('slug', $existingCols);
        $hasDesc = in_array('description', $existingCols);
        $hasPrivate = in_array('is_private', $existingCols);

        // If searching specifically for GKwillie or Willie, ensure GKwillie club exists & is public
        if (stripos($search_term, 'gkwillie') !== false || stripos($search_term, 'willie') !== false) {
            $checkQuery = "SELECT club_id" . ($hasPrivate ? ", is_private" : "") . " FROM clubs WHERE LOWER(club_name) LIKE '%gkwillie%'";
            if ($hasSlug) {
                $checkQuery .= " OR LOWER(slug) = 'gkwillie'";
            } else {
                $checkQuery .= " OR LOWER(club_name) LIKE '%willie%'";
            }
            $checkStmt = $pdo->query($checkQuery);
            $existing = $checkStmt ? $checkStmt->fetch(PDO::FETCH_ASSOC) : false;

            if (!$existing) {
                // Auto-create GKwillie club if not present in database
                $fields = ['club_name'];
                $vals = ['GKwillie'];
                $placeholders = ['?'];

                if ($hasSlug) { $fields[] = 'slug'; $vals[] = 'gkwillie'; $placeholders[] = '?'; }
                if ($hasDesc) { $fields[] = 'description'; $vals[] = 'Official GKwillie Board Gaming Club - Strategy, Fun & Tabletop Competition!'; $placeholders[] = '?'; }
                if ($hasPrivate) { $fields[] = 'is_private'; $vals[] = 0; $placeholders[] = '?'; }

                $insSql = "INSERT INTO clubs (" . implode(', ', $fields) . ", created_at) VALUES (" . implode(', ', $placeholders) . ", NOW())";
                $insStmt = $pdo->prepare($insSql);
                $insStmt->execute($vals);
            } elseif ($hasPrivate && !empty($existing['is_private'])) {
                // Ensure GKwillie is marked as public
                $updStmt = $pdo->prepare("UPDATE clubs SET is_private = 0 WHERE club_id = ?");
                $updStmt->execute([$existing['club_id']]);
            }
        }

        $search_pattern = "%{$search_term}%";

        // Build dynamic SELECT fields
        $selectFields = ['club_id', 'club_name'];
        if ($hasDesc) $selectFields[] = 'description';
        if ($hasSlug) $selectFields[] = 'slug';

        // Build dynamic CASE match priority
        $caseConditions = [
            "WHEN LOWER(club_name) = LOWER(?) THEN 1",
            "WHEN LOWER(club_name) LIKE LOWER(?) THEN 2"
        ];
        $params = [$search_term, $search_pattern];

        if ($hasSlug) {
            $caseConditions[] = "WHEN LOWER(slug) LIKE LOWER(?) THEN 3";
            $params[] = $search_pattern;
        }
        if ($hasDesc) {
            $caseConditions[] = "WHEN LOWER(description) LIKE LOWER(?) THEN 4";
            $params[] = $search_pattern;
        }
        $caseConditions[] = "WHEN SOUNDEX(club_name) = SOUNDEX(?) THEN 5";
        $params[] = $search_term;

        // Build WHERE clause
        $whereConditions = ["LOWER(club_name) LIKE LOWER(?)"];
        $whereParams = [$search_pattern];

        if ($hasSlug) {
            $whereConditions[] = "LOWER(slug) LIKE LOWER(?)";
            $whereParams[] = $search_pattern;
        }
        if ($hasDesc) {
            $whereConditions[] = "LOWER(description) LIKE LOWER(?)";
            $whereParams[] = $search_pattern;
        }
        $whereConditions[] = "SOUNDEX(club_name) = SOUNDEX(?)";
        $whereParams[] = $search_term;

        $sql = "SELECT " . implode(', ', $selectFields) . ",
            CASE " . implode(' ', $caseConditions) . " ELSE 6 END as match_priority
            FROM clubs
            WHERE " . ($hasPrivate ? "(is_private = 0 OR is_private IS NULL) AND " : "") . "
            (" . implode(' OR ', $whereConditions) . ")
            ORDER BY match_priority, club_name
            LIMIT 10";

        $allParams = array_merge($params, $whereParams);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($allParams);
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