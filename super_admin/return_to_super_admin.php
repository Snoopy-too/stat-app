<?php
declare(strict_types=1);

session_start();
require_once '../config/database.php';

// ── Guard: only valid when currently impersonating ──────────────────────
if (
    empty($_SESSION['is_impersonating']) ||
    empty($_SESSION['original_super_admin_id'])
) {
    header("Location: ../admin/login.php");
    exit();
}

// ── Restore super admin identity ────────────────────────────────────────
$_SESSION['admin_id']       = (int) $_SESSION['original_super_admin_id'];
$_SESSION['admin_username'] = $_SESSION['original_super_admin_username'];
$_SESSION['is_super_admin'] = true;
$_SESSION['is_admin']       = false;
$_SESSION['is_impersonating'] = false;

// Clean up impersonation markers
unset(
    $_SESSION['original_super_admin_id'],
    $_SESSION['original_super_admin_username']
);

// Regenerate session ID for safety
session_regenerate_id(true);

header("Location: super_admin_cp.php");
exit();
