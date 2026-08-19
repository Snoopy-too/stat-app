<?php
ob_start();
require_once '../config/security_headers.php';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

// Session-based CSRF token (no database table required)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$token = $_GET['token'] ?? '';
$valid_token = false;
$user = null;

if ($token) {
    $stmt = $pdo->prepare("SELECT admin_id FROM admin_users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $valid_token = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token.";
    } elseif (!$valid_token) {
        $_SESSION['error'] = "Invalid or expired reset token.";
    } else {
        // Regenerate CSRF token after successful validation
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
<?php
$pageTitle = 'Reset Password - Board Game Club StatApp';
$htmlAttributes = 'data-club-theme="light" data-theme="light" data-theme-locked';
require_once '../includes/templates/header.php';
?>

<div class="landing-hero">
    <!-- Background Wave & Grid Contour Overlays -->
    <div class="hero-contour-waves"></div>

    <div class="landing-hero-content landing-hero-content--compact">
        <h1>Reset <span class="highlight">Password</span></h1>
        <p class="landing-hero-subtitle">Enter your new password below.</p>

        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="landing-card landing-card--compact">
            <!-- Corner Contour Brackets -->
            <div class="card-corner-bracket card-corner-bracket--tl"></div>
            <div class="card-corner-bracket card-corner-bracket--tr"></div>
            <div class="card-corner-bracket card-corner-bracket--bl"></div>
            <div class="card-corner-bracket card-corner-bracket--br"></div>

            <?php if ($valid_token): ?>
                <form method="POST" class="stack">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="form-group">
                        <label for="password" class="form-label form-label--required">New Password</label>
                        <div class="input-with-icon">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="password" name="password" required class="form-control" placeholder="Enter new password" minlength="8" autofocus autocomplete="new-password">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label form-label--required">Confirm Password</label>
                        <div class="input-with-icon">
                            <span class="input-icon">🔑</span>
                            <input type="password" id="confirm_password" name="confirm_password" required class="form-control" placeholder="Confirm new password" minlength="8" autocomplete="new-password">
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; width: 100%; margin-top: 1rem;">
                        <button type="submit" class="btn btn--primary" style="flex: 1; text-align: center; justify-content: center;">Reset Password</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="message message--error">
                    Invalid or expired reset link. <a href="forgot_password.php">Request a new one</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="../js/form-loading.js"></script>';
require_once '../includes/templates/footer.php';
?>
