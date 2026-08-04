<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "New password must be at least 8 characters long";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match";
    } else {
        $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($current_password, $admin['password_hash'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE admin_id = ?");
            $stmt->execute([$hashed_password, $_SESSION['admin_id']]);

            $_SESSION['success'] = "Password updated successfully";
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Current password is incorrect";
        }
    }
}

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Super Admin</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Change Password</h1>
            <p class="header-subtitle">Update your administrator credentials</p>
        </div>
        <a href="dashboard.php" class="btn btn--secondary">Back to Dashboard</a>
    </div>
    <div class="container container--narrow">
        <div class="card">
            <div class="card-header">
                <h2>Change Password</h2>
                <p class="card-subtitle">Ensure your account stays secure</p>
            </div>

            <?php display_session_message('success'); ?>
            <?php display_session_message('error'); ?>
            
            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">
                        Minimum 8 characters
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Change Password</button>
                    <a href="dashboard.php" class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>
