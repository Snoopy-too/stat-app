<?php
session_start();

// If no success message in session, redirect to registration page
if (!isset($_SESSION['registration_success'])) {
    header('Location: register.php');
    exit;
}

// Get the message and clear it from session
$message = $_SESSION['registration_success'];
unset($_SESSION['registration_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Success - Board Game Club StatApp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
    </div>
    <div class="success-container">
        <i class="fas fa-check-circle success-icon"></i>
        <h2 class="success-title">Registration Successful!</h2>
        <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
        <a href="admin/login.php" class="btn">Go to Login</a>
        <p class="email-note">
            <i class="fas fa-envelope"></i>
            Please check your email for the verification link.
        </p>
    </div>
</body>
</html>