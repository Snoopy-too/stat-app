<?php
ob_start();
require_once '../config/security_headers.php';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

// Load app config if available (gitignored - may not exist on all environments)
if (file_exists(__DIR__ . '/../config/app_config.php')) {
    require_once '../config/app_config.php';
}

// Session-based CSRF token (no database table required)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            $_SESSION['error'] = "Invalid security token.";
        } else {
            // Regenerate CSRF token after successful validation
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("SELECT admin_id, username FROM admin_users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Ensure reset columns exist before using them
                    try { $pdo->exec("ALTER TABLE admin_users ADD COLUMN reset_token VARCHAR(64) NULL"); } catch (PDOException $e) {}
                    try { $pdo->exec("ALTER TABLE admin_users ADD COLUMN reset_token_expiry DATETIME NULL"); } catch (PDOException $e) {}

                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $update = $pdo->prepare("UPDATE admin_users SET reset_token = ?, reset_token_expiry = ? WHERE admin_id = ?");
                    $update->execute([$token, $expiry, $user['admin_id']]);

                    // Build reset link using configured BASE_URL or derive from request
                    $baseUrl = defined('BASE_URL') ? BASE_URL : 'https://' . $_SERVER['HTTP_HOST'];
                    $resetLink = $baseUrl . "/admin/reset_password.php?token=" . $token;
                    $fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@' . $_SERVER['HTTP_HOST'];
                    $signature = defined('EMAIL_SIGNATURE') ? EMAIL_SIGNATURE : 'Board Game Club StatApp Team';

                    $subject = "Password Reset Request";
                    $message = "Hi " . $user['username'] . ",\n\n";
                    $message .= "Click the link below to reset your password:\n";
                    $message .= $resetLink . "\n\n";
                    $message .= "This link expires in 1 hour.\n\n";
                    $message .= $signature . "\n";
                    $headers = "From: " . $fromEmail;

                    if (@mail($email, $subject, $message, $headers)) {
                        $_SESSION['success'] = "Password reset instructions have been sent to your email.";
                    } else {
                        error_log("Password Reset Link for $email: $resetLink");
                        $_SESSION['success'] = "Password reset instructions have been sent to your email. (Check server logs for link in dev)";
                    }
                } else {
                    // Don't reveal if user exists
                    $_SESSION['success'] = "If an account exists with that email, instructions have been sent.";
                }
            } else {
                $_SESSION['error'] = "Invalid email address.";
            }
        }
    } catch (Throwable $e) {
        error_log("Forgot password error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        $_SESSION['error'] = "An error occurred. Please try again. [" . $e->getMessage() . "]";
    }
    header("Location: forgot_password.php");
    exit();
}
?>
<?php
$pageTitle = 'Forgot Password - Board Game Club StatApp';
$htmlAttributes = 'data-club-theme="light" data-theme="light" data-theme-locked';
require_once '../includes/templates/header.php';
?>

<div class="landing-hero">
    <!-- Background Wave & Grid Contour Overlays -->
    <div class="hero-contour-waves"></div>

    <div class="landing-hero-content landing-hero-content--compact">
        <h1>Forgot <span class="highlight">Password</span></h1>
        <p class="landing-hero-subtitle">Enter your email address and we'll send you a link to reset your password.</p>

        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="landing-card landing-card--compact">
            <!-- Corner Contour Brackets -->
            <div class="card-corner-bracket card-corner-bracket--tl"></div>
            <div class="card-corner-bracket card-corner-bracket--tr"></div>
            <div class="card-corner-bracket card-corner-bracket--bl"></div>
            <div class="card-corner-bracket card-corner-bracket--br"></div>

            <form method="POST" action="forgot_password.php" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="email" class="form-label form-label--required">Email Address</label>
                    <div class="input-with-icon">
                        <span class="input-icon">✉️</span>
                        <input type="email" id="email" name="email" required class="form-control" placeholder="Enter your registered email" autofocus autocomplete="email">
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem; width: 100%; margin-top: 1rem;">
                    <button type="submit" class="btn btn--primary" style="flex: 1; text-align: center; justify-content: center;">Send Reset Link</button>
                    <a href="../index.php" class="btn btn--secondary" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">Back to Home</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="../js/form-loading.js"></script>';
require_once '../includes/templates/footer.php';
?>
