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

$target_club = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
if (!$target_club && !empty($_SESSION['current_club_id'])) {
    $target_club = (int)$_SESSION['current_club_id'];
}
if (!$target_club && !empty($_SESSION['club_id'])) {
    $target_club = (int)$_SESSION['club_id'];
}
if (!$target_club && !empty($clubs)) {
    $target_club = (int)$clubs[0]['club_id'];
}

if ($target_club > 0) {
    $_SESSION['current_club_id'] = $target_club;
    $_SESSION['club_id'] = $target_club;
    header("Location: club_new_results.php?club_id=" . $target_club);
    exit();
} else {
    $_SESSION['error'] = "You need to create a club first before adding game results.";
    header("Location: dashboard.php");
    exit();
}
?>
