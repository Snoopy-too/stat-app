<?php
session_start();
require_once 'config/database.php';
require_once 'includes/SecurityUtils.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    // Initialize security utils
    $security = new SecurityUtils($pdo);

    // Check if rate limit exceeded
    if (!$security->checkLoginAttempts($email, $ipAddress)) {
        $_SESSION['error'] = "Too many failed login attempts. Please try again in 30 minutes.";
        header("Location: login.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT club_id, club_name, admin_username, password_hash FROM clubs WHERE admin_email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Log successful login
            $security->logLoginAttempt($email, $ipAddress, true);

            $_SESSION['club_id'] = $user['club_id'];
            $_SESSION['club_name'] = $user['club_name'];
            $_SESSION['admin_username'] = $user['admin_username'];
            $_SESSION['logged_in'] = true;

            header("Location: dashboard.php");
            exit();
        } else {
            // Log failed login attempt
            $security->logLoginAttempt($email, $ipAddress, false);

            $_SESSION['error'] = "Invalid email or password";
            header("Location: login.php");
            exit();
        }

    } catch(PDOException $e) {
        // Log failed attempt on database error
        $security->logLoginAttempt($email, $ipAddress, false);

        $_SESSION['error'] = "Login failed. Please try again.";
        header("Location: login.php");
        exit();
    }
}

// Handle unauthorized access attempts
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
?>