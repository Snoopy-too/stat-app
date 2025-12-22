<?php
/**
 * New Result Router
 * Redirects admins based on their club count:
 * - Single club: Go directly to club_new_results.php
 * - Multiple clubs: Go to club_list.php to select a club
 */

session_start();
require_once '../config/database.php';

// Ensure user is logged in
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

// Get clubs for this admin
$stmt = $pdo->prepare("
    SELECT c.club_id, c.club_name
    FROM clubs c
    JOIN club_admins ca ON c.club_id = ca.club_id
    WHERE ca.admin_id = ?
    ORDER BY c.club_name ASC
");
$stmt->execute([$_SESSION['admin_id']]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$club_count = count($clubs);

if ($club_count === 0) {
    // No clubs - redirect to dashboard with message
    $_SESSION['error'] = "You need to create a club first before adding game results.";
    header("Location: dashboard.php");
    exit();
} elseif ($club_count === 1) {
    // Single club - go directly to game selection
    header("Location: club_new_results.php?club_id=" . $clubs[0]['club_id']);
    exit();
} else {
    // Multiple clubs - go to club selection
    header("Location: club_list.php");
    exit();
}
?>
