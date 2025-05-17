<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Verify team exists and belongs to the club
$stmt = $pdo->prepare("SELECT t.*, m.club_id FROM teams t JOIN members m ON t.member1_id = m.member_id WHERE t.team_id = ? AND m.club_id = ?");
$stmt->execute([$team_id, $club_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    $_SESSION['error'] = "Team not found or access denied.";
    header("Location: club_teams.php?club_id=" . $club_id);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Delete the team
    $stmt = $pdo->prepare("DELETE FROM teams WHERE team_id = ?");
    $stmt->execute([$team_id]);
    
    $pdo->commit();
    $_SESSION['success'] = "Team deleted successfully!";
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error deleting team: " . $e->getMessage();
}

header("Location: club_teams.php?club_id=" . $club_id);
exit();