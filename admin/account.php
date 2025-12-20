<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);

// Fetch current admin data
$stmt = $pdo->prepare("SELECT username, email, created_at FROM admin_users WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if ($action === 'update_profile') {
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        
        // Validate email format
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Invalid email format";
        } else {
            // Check if username is already taken by another admin
            $stmt = $pdo->prepare("SELECT admin_id FROM admin_users WHERE username = ? AND admin_id != ?");
            $stmt->execute([$new_username, $_SESSION['admin_id']]);
            
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Username is already taken";
            } else {
                $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, email = ? WHERE admin_id = ?");
                $stmt->execute([$new_username, $new_email, $_SESSION['admin_id']]);
                
                $_SESSION['admin_username'] = $new_username;
                $_SESSION['success'] = "Profile updated successfully";
                
                // Refresh admin data
                $admin['username'] = $new_username;
                $admin['email'] = $new_email;
            }
        }
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($new_password) < 8) {
            $_SESSION['error'] = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match";
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin_pass = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $admin_pass['password_hash'])) {
                $hashed_password = $security->hashPassword($new_password);
                
                $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE admin_id = ?");
                $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
                
                $_SESSION['success'] = "Password changed successfully";
            } else {
                $_SESSION['error'] = "Current password is incorrect";
            }
        }
    }
}

// Generate CSRF token for forms
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('account'); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Account Settings', 'Manage your profile and security settings'); ?>
    </div>

    <div class="container container--narrow">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>
        
        <!-- Profile Information -->
        <div class="card">
            <div class="card-header">
                <h2>Profile Information</h2>
                <p class="card-subtitle">Update your username and email address</p>
            </div>
            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($admin['username']); ?>" 
                        required
                        minlength="3"
                    >
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" 
                        required
                    >
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">
                        Used for account recovery and notifications
                    </small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Update Profile</button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h2>Change Password</h2>
                <p class="card-subtitle">Ensure your account stays secure</p>
            </div>
            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        class="form-control" 
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        class="form-control" 
                        required
                        minlength="8"
                    >
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">
                        Minimum 8 characters
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-control" 
                        required
                        minlength="8"
                    >
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Change Password</button>
                </div>
            </form>
        </div>

        <!-- Account Information -->
        <div class="card card--flat">
            <div class="card-header">
                <h3>Account Information</h3>
            </div>
            <div style="display: grid; gap: var(--spacing-3);">
                <div>
                    <strong style="color: var(--color-text-muted); font-size: var(--font-size-sm);">Account Created</strong>
                    <p style="margin: 0.25rem 0 0 0;"><?php echo date('F j, Y', strtotime($admin['created_at'])); ?></p>
                </div>
                <div>
                    <strong style="color: var(--color-text-muted); font-size: var(--font-size-sm);">Account ID</strong>
                    <p style="margin: 0.25rem 0 0 0;"><?php echo $_SESSION['admin_id']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/dark-mode.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
