<?php
// admin/check_admin_email.php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$security = new SecurityUtils($pdo);
$email = $_GET['email'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT admin_id, username FROM admin_users WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        if ($admin['admin_id'] == $_SESSION['admin_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot share a club with yourself.']);
        } else {
            echo json_encode(['success' => true, 'username' => $admin['username'], 'admin_id' => $admin['admin_id']]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No such email address exists.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
