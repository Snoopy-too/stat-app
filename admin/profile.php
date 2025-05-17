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
    <style>
        body {
            background-color: #f0f0f0;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .container {
            width: 80%;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .profile-form {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Profile Settings</h1>
    </div>

    <div class="container">
        <div class="profile-form">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required
                           value="<?php echo htmlspecialchars($admin['username']); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($admin['email']); ?>">
                </div>
                <button type="submit" class="button">Save Changes</button>
                <a href="change_password.php" class="button">Change Password</a>
            </form>
        </div>
        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>
</body>
</html>