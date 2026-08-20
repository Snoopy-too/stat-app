<?php
session_start();

// If no success message in session, redirect to registration page
if (!isset($_SESSION['registration_success'])) {
    header('Location: register.php');
    exit;
}

// Get the message and email, then clear them from session
$message = $_SESSION['registration_success'];
$email = $_SESSION['registration_email'] ?? '';
unset($_SESSION['registration_success']);
unset($_SESSION['registration_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Success - Board Game Club StatApp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://theflyingdutchmen.games/stylesheets/tfd-nav.css">
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <!-- Shared Top Navigation Bar (Managed by tfd-navbar.js) -->
    <header id="tfd-navbar" class="tfd-navbar" data-active="stats"></header>

    <div class="success-container" style="margin-top: 2rem;">
        <i class="fas fa-check-circle success-icon"></i>
        <h2 class="success-title">Registration Successful!</h2>
        <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
        <?php if ($email): ?>
        <p class="email-note">
            <i class="fas fa-envelope"></i>
            A verification email has been sent to <strong><?php echo htmlspecialchars($email); ?></strong>. Please check your inbox (and spam folder) for the verification link.
        </p>
        <?php else: ?>
        <p class="email-note">
            <i class="fas fa-envelope"></i>
            Please check your email for the verification link.
        </p>
        <?php endif; ?>
        <a href="index.php" class="btn">Go to Home</a>
    </div>
    <script src="https://theflyingdutchmen.games/javascripts/tfd-navbar.js"></script>
</body>
</html>
