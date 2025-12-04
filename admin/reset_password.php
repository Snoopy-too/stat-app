<?php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/helpers.php';

$security = new SecurityUtils($pdo);
$csrf_token = $security->generateCSRFToken();

$token = $_GET['token'] ?? '';
$valid_token = false;

if ($token) {
    $stmt = $pdo->prepare("SELECT admin_id FROM admin_users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $valid_token = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token.";
    } elseif (!$valid_token) {
        $_SESSION['error'] = "Invalid or expired reset token.";
    } else {
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];

        if (strlen($password) < 8) {
            $_SESSION['error'] = "Password must be at least 8 characters.";
        } elseif ($password !== $confirm) {
            $_SESSION['error'] = "Passwords do not match.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE admin_users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE admin_id = ?");
            $update->execute([$hash, $user['admin_id']]);
            
            $_SESSION['success'] = "Password reset successfully. You can now login.";
            header("Location: login.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Reset Password</p>
        </div>
    </div>

    <div class="container container--narrow auth-shell">
        <div class="card auth-card">
            <?php display_session_message('error'); ?>

            <?php if ($valid_token): ?>
                <form method="POST" class="stack">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <label for="password">New Password:</label>
                        <input type="password" id="password" name="password" required class="form-control" minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="form-control" minlength="8">
                    </div>
                    <button type="submit" class="btn btn--block">Reset Password</button>
                </form>
            <?php else: ?>
                <div class="message message--error">
                    Invalid or expired reset link. <a href="forgot_password.php">Request a new one</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
