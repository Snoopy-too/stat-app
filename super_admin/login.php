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
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .login-container { max-width: 400px; margin: 80px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .login-container h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn { width: 100%; padding: 10px; background: #3498db; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .btn:hover { background: #217dbb; }
        .error { color: #e74c3c; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Super Admin Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button class="btn" type="submit">Login</button>
        </form>
    </div>
</body>
</html>