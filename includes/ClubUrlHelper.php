<?php
/**
 * Helper function to generate club URL based on slug availability
 * @param array $club Club data array with 'slug' and 'club_id' keys
 * @return string URL to club stats page
 */
function getClubUrl($club) {
    if (!empty($club['slug'])) {
        return $club['slug'];
    }
    return 'club_stats.php?id=' . $club['club_id'];
}

/**
 * Helper function to fetch club data with slug support
 * @param PDO $pdo Database connection
 * @param int $club_id Club ID (if provided)
 * @param string $slug Club slug (if provided)
 * @return array|false Club data or false if not found
 */
function fetchClubByIdOrSlug($pdo, $club_id = 0, $slug = '') {
    if ($club_id > 0 || !empty($slug)) {
        $sql = "SELECT * FROM clubs WHERE ";
        $params = [];
        
        if ($club_id > 0) {
            $sql .= "club_id = ?";
            $params[] = $club_id;
        } else {
            $sql .= "slug = ?";
            $params[] = $slug;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return false;
}
?>
