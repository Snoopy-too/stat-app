<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Super Admin</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Change Password</h1>
            <p class="header-subtitle">Update your administrator credentials</p>
        </div>
        <a href="dashboard.php" class="btn btn--secondary">Back to Dashboard</a>
    </div>
    <div class="container">
        <div class="card">
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="stack">
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Change Password</button>
                    <a href="dashboard.php" class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
