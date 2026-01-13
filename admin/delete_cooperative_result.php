<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';

// Ensure user is logged in and has appropriate admin access
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

// Get result_id and club/game info for redirection
$result_id = isset($_POST['result_id']) ? (int)$_POST['result_id'] : (isset($_GET['result_id']) ? (int)$_GET['result_id'] : null);
$club_id = isset($_REQUEST['club_id']) ? (int)$_REQUEST['club_id'] : null;
$game_id = isset($_REQUEST['game_id']) ? (int)$_REQUEST['game_id'] : null;

if (!$result_id) {
    $_SESSION['error'] = "Invalid result ID provided.";
    header("Location: dashboard.php");
    exit();
}

try {
    // We need to fetch club_id and game_id if not provided, to ensure proper redirection
    if (!$club_id || !$game_id) {
        $check_stmt = $pdo->prepare("SELECT game_id FROM cooperative_game_results WHERE result_id = ?");
        $check_stmt->execute([$result_id]);
        $game_info = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($game_info) {
            $game_id = $game_info['game_id'];
            $club_stmt = $pdo->prepare("SELECT club_id FROM games WHERE game_id = ?");
            $club_stmt->execute([$game_id]);
            $club_id = $club_stmt->fetchColumn();
        }
    }

    $pdo->beginTransaction();

    // Delete the cooperative result (participants will be deleted by CASCADE)
    $stmt = $pdo->prepare("DELETE FROM cooperative_game_results WHERE result_id = ?");
    $stmt->execute([$result_id]);

    $pdo->commit();
    $_SESSION['success_message'] = "Cooperative game result deleted successfully!";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Error deleting result: " . $e->getMessage();
}

if ($club_id && $game_id) {
    header("Location: results.php?club_id=$club_id&game_id=$game_id");
} else {
    header("Location: dashboard.php");
}
exit();
