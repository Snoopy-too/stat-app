<?php
ob_start();
session_start();
require_once '../config/database.php';

// Redirect if already logged in as super admin
if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
    header("Location: super_admin_cp.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        $stmt = $pdo->prepare("SELECT admin_id, username, password_hash, is_super_admin FROM admin_users WHERE username = ? AND is_deactivated = 0 LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && $admin['is_super_admin'] && password_verify($password, $admin['password_hash'])) {
            $_SESSION['is_super_admin'] = true;
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            header("Location: super_admin_cp.php");
            exit();
        } else {
            $error = "Invalid credentials or not a super admin.";
        }
    } catch (PDOException $e) {
        $error = "Login failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Super Admin Login</p>
        </div>
    </div>

    <div class="container container--narrow auth-shell">
        <div class="card auth-card">
            <h2 class="text-center">Sign In</h2>
            <?php if ($error): ?>
                <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off" class="stack">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn--block" type="submit">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
