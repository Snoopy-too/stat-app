<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// Get admin details
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE admin_users 
            SET username = ?, email = ?
            WHERE admin_id = ?
        ");
        $stmt->execute([$username, $email, $_SESSION['admin_id']]);
        
        $_SESSION['admin_username'] = $username;
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();
        
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            $_SESSION['error'] = "Username or email already exists.";
        } else {
            $_SESSION['error'] = "Failed to update profile. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Super Admin</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Profile Settings</h1>
            <p class="header-subtitle">Update your super admin account details</p>
        </div>
        <a href="dashboard.php" class="btn btn--secondary">Back to Dashboard</a>
    </div>

    <div class="container container--narrow">
        <div class="card">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="message message--success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form method="POST" class="stack">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" class="form-control" required
                           value="<?php echo htmlspecialchars($admin['username']); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" required
                           value="<?php echo htmlspecialchars($admin['email']); ?>">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Save Changes</button>
                    <a href="change_password.php" class="btn btn--subtle">Change Password</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>
