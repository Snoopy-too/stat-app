<?php
// admin/process_share_club.php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$security = new SecurityUtils($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$club_id = (int)($_POST['club_id'] ?? 0);
$target_admin_id = (int)($_POST['admin_id'] ?? 0);

if ($club_id <= 0 || $target_admin_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

// Security: Verify current admin has access to this club
try {
    $stmt = $pdo->prepare("SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?");
    $stmt->execute([$club_id, $_SESSION['admin_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to share this club.']);
        exit();
    }

    // Insert new admin
    $stmt = $pdo->prepare("INSERT IGNORE INTO club_admins (club_id, admin_id, role) VALUES (?, ?, 'admin')");
    $stmt->execute([$club_id, $target_admin_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Club shared successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'This club is already shared with that administrator.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
