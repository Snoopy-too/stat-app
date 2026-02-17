<?php
declare(strict_types=1);

session_start();
require_once '../config/database.php';

// ── Guard: only super admins may impersonate ────────────────────────────
if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['admin_id'])) {
    header("Location: super_admin_cp.php");
    exit();
}

$target_admin_id = intval($_POST['admin_id']);

try {
    $stmt = $pdo->prepare(
        "SELECT admin_id, username, is_deactivated, is_super_admin
         FROM admin_users
         WHERE admin_id = ?
         LIMIT 1"
    );
    $stmt->execute([$target_admin_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        $_SESSION['sa_error'] = "Admin user not found.";
        header("Location: super_admin_cp.php");
        exit();
    }

    if (!empty($target['is_deactivated'])) {
        $_SESSION['sa_error'] = "Cannot impersonate a deactivated admin.";
        header("Location: super_admin_cp.php");
        exit();
    }

    // ── Preserve the super admin's identity for later restoration ────────
    $_SESSION['original_super_admin_id']       = $_SESSION['admin_id'];
    $_SESSION['original_super_admin_username']  = $_SESSION['admin_username'];

    // ── Switch session to the target admin ───────────────────────────────
    $_SESSION['admin_id']       = (int) $target['admin_id'];
    $_SESSION['admin_username'] = $target['username'];
    $_SESSION['admin_type']     = 'multi_club';   // safe default
    $_SESSION['is_super_admin'] = false;
    $_SESSION['is_admin']       = true;
    $_SESSION['is_impersonating'] = true;

    // Regenerate session ID to avoid session‑fixation risks
    session_regenerate_id(true);

    header("Location: ../admin/dashboard.php");
    exit();
} catch (PDOException $e) {
    error_log("Impersonation error: " . $e->getMessage());
    $_SESSION['sa_error'] = "An error occurred. Please try again.";
    header("Location: super_admin_cp.php");
    exit();
}
