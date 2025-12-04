<?php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/helpers.php';

$security = new SecurityUtils($pdo);
$csrf_token = $security->generateCSRFToken();

// Auto-migration to ensure columns exist (Hack for no-CLI access)
try {
    $pdo->query("SELECT reset_token FROM admin_users LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE admin_users 
                    ADD COLUMN reset_token VARCHAR(64) NULL AFTER email_token_expiry,
                    ADD COLUMN reset_token_expiry DATETIME NULL AFTER reset_token");
    } catch (PDOException $e2) {
        // Ignore if already exists or other error, will fail later if critical
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token.";
    } else {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT admin_id, username FROM admin_users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $update = $pdo->prepare("UPDATE admin_users SET reset_token = ?, reset_token_expiry = ? WHERE admin_id = ?");
                $update->execute([$token, $expiry, $user['admin_id']]);

                // Send Email
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                $subject = "Password Reset Request";
                $message = "Hi " . $user['username'] . ",\n\n";
                $message .= "Click the link below to reset your password:\n";
                $message .= $resetLink . "\n\n";
                $message .= "This link expires in 1 hour.\n";
                $headers = "From: no-reply@stat-app.com";

                // In a real environment, use mail() or a library. 
                // For local dev, we might just log it or display it if mail() fails.
                if (mail($email, $subject, $message, $headers)) {
                    $_SESSION['success'] = "Password reset instructions have been sent to your email.";
                } else {
                    // Fallback for local dev without mail server
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
