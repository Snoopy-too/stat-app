<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// Handle actions: deactivate, change password, change email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['admin_id'])) {
        $admin_id = intval($_POST['admin_id']);
        if ($_POST['action'] === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE admin_users SET is_deactivated = 1 WHERE admin_id = ?");
            $stmt->execute([$admin_id]);
        } elseif ($_POST['action'] === 'activate') {
            $stmt = $pdo->prepare("UPDATE admin_users SET is_deactivated = 0 WHERE admin_id = ?");
            $stmt->execute([$admin_id]);
        } elseif ($_POST['action'] === 'change_email' && isset($_POST['new_email'])) {
            $new_email = trim($_POST['new_email']);
            if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("UPDATE admin_users SET email = ? WHERE admin_id = ?");
                $stmt->execute([$new_email, $admin_id]);
            }
        } elseif ($_POST['action'] === 'change_password' && isset($_POST['new_password'])) {
            $new_password = $_POST['new_password'];
            if (strlen($new_password) >= 6) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE admin_id = ?");
                $stmt->execute([$hashed, $admin_id]);
            }
        }
    }
}

// Fetch all admin users
$stmt = $pdo->query("SELECT admin_id, username, email, is_super_admin, is_deactivated FROM admin_users");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Control Panel</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background: #3498db; color: #fff; }
        .deactivated { background: #f8d7da; }
        .action-form { display: inline-block; margin: 0 2px; }
        .btn { padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-success { background: #2ecc71; color: #fff; }
        .btn-warning { background: #f1c40f; color: #fff; }
        .btn-info { background: #3498db; color: #fff; }
        .input-inline { width: 120px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Super Admin Control Panel</h1>
        <a href="logout.php" class="button">Logout</a>
    </div>
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Super Admin</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($admins as $admin): ?>
        <tr class="<?php echo $admin['is_deactivated'] ? 'deactivated' : ''; ?>">
            <td><?php echo htmlspecialchars($admin['admin_id']); ?></td>
            <td><?php echo htmlspecialchars($admin['username']); ?></td>
            <td>
                <form class="action-form" method="POST" style="display:inline;">
                    <input type="hidden" name="admin_id" value="<?php echo $admin['admin_id']; ?>">
                    <input type="hidden" name="action" value="change_email">
                    <input class="input-inline" type="email" name="new_email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    <button class="btn btn-info" type="submit">Update</button>
                </form>
            </td>
            <td><?php echo $admin['is_super_admin'] ? 'Yes' : 'No'; ?></td>
            <td><?php echo $admin['is_deactivated'] ? 'Deactivated' : 'Active'; ?></td>
            <td>
                <?php if (!$admin['is_super_admin']): ?>
                    <form class="action-form" method="POST">
                        <input type="hidden" name="admin_id" value="<?php echo $admin['admin_id']; ?>">
                        <input type="hidden" name="action" value="<?php echo $admin['is_deactivated'] ? 'activate' : 'deactivate'; ?>">
                        <button class="btn <?php echo $admin['is_deactivated'] ? 'btn-success' : 'btn-danger'; ?>" type="submit"><?php echo $admin['is_deactivated'] ? 'Activate' : 'Deactivate'; ?></button>
                    </form>
                <?php endif; ?>
                <form class="action-form" method="POST">
                    <input type="hidden" name="admin_id" value="<?php echo $admin['admin_id']; ?>">
                    <input type="hidden" name="action" value="change_password">
                    <input class="input-inline" type="password" name="new_password" placeholder="New Password" minlength="6" required>
                    <button class="btn btn-warning" type="submit">Change Password</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>