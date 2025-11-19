<?php
ob_start();
session_start();
require_once '../config/database.php';

if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('Form submitted');
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    error_log('Attempting login for username: ' . $username);
    try {
        $stmt = $pdo->prepare("SELECT admin_id, username, password_hash, is_deactivated FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log('Found admin user: ' . ($admin ? 'yes' : 'no'));
        if ($admin) {
            if (!empty($admin['is_deactivated']) && $admin['is_deactivated'] == 1) {
                $_SESSION['error'] = "Your account has been deactivated. Please contact a Super Administrator.";
            } else {
                error_log('Verifying password...');
                if (password_verify($password, $admin['password_hash'])) {
                    $_SESSION['is_super_admin'] = true;
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    error_log("Login successful for user: " . $username);
                    header("Location: dashboard.php");
                    exit();
                }
                $_SESSION['error'] = "Invalid credentials";
            }
        } else {
            $_SESSION['error'] = "Invalid credentials";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Login failed. Please try again.";
    }
    

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Admin Login</p>
        </div>
        <a href="../index.php" class="btn btn--secondary">&larr; Back to Main Site</a>
    </div>

    <div class="container container--narrow auth-shell">
        <div class="card auth-card">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST" class="stack">
                <div class="form-group">
                    <label for="username">Admin Username:</label>
                    <input type="text" id="username" name="username" required class="form-control" autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required class="form-control">
                </div>
                <button type="submit" class="btn btn--block">Login</button>
            </form>
            <div class="text-center mt-3">
                <a href="../register.php" class="btn btn--link">Register your club</a>
            </div>
        </div>
    </div>
</body>
</html>
