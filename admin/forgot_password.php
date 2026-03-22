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
                    try {
                        $pdo->query("SELECT reset_token FROM admin_users LIMIT 0");
                    } catch (PDOException $colErr) {
                        $pdo->exec("ALTER TABLE admin_users
                                    ADD COLUMN reset_token VARCHAR(64) NULL,
                                    ADD COLUMN reset_token_expiry DATETIME NULL");
                    }

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Password Recovery</p>
        </div>
        <a href="login.php" class="btn btn--secondary">&larr; Back to Login</a>
    </div>

    <div class="container container--narrow auth-shell">
        <div class="card auth-card">
            <?php display_session_message('success'); ?>
            <?php display_session_message('error'); ?>

            <p class="text-muted mb-4">Enter your email address and we'll send you a link to reset your password.</p>

            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" required class="form-control" autofocus>
                </div>
                <button type="submit" class="btn btn--block">Send Reset Link</button>
            </form>
        </div>
    </div>
</body>
</html>
