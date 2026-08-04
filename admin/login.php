<?php
ob_start();
require_once '../config/security_headers.php'; // Set security headers first
require_once '../config/session.php';        // Configure secure sessions
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/helpers.php';

if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
    header("Location: new_result.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('Form submitted');
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    // Initialize security utils
    $security = new SecurityUtils($pdo);

    // Check if rate limit exceeded (use username as email for admin logins)
    if (!$security->checkLoginAttempts($username, $ipAddress)) {
        $_SESSION['error'] = "Too many failed login attempts. Please try again in 15 minutes.";
        error_log("Rate limit exceeded for username: $username from IP: $ipAddress");
    } else {
        error_log('Attempting login for username: ' . $username);
        try {
            $stmt = $pdo->prepare("SELECT admin_id, username, password_hash, is_deactivated, admin_type, default_club_id FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log('Found admin user: ' . ($admin ? 'yes' : 'no'));
            if ($admin) {
                if (!empty($admin['is_deactivated']) && $admin['is_deactivated'] == 1) {
                    $security->logLoginAttempt($username, $ipAddress, false);
                    $_SESSION['error'] = "Your account has been deactivated. Please contact a Super Administrator.";
                } else {
                    error_log('Verifying password...');
                    if (password_verify($password, $admin['password_hash'])) {
                        // Log successful login
                        $security->logLoginAttempt($username, $ipAddress, true);

                        // Regenerate session ID to prevent session fixation attacks
                        session_regenerate_id(true);

                        $_SESSION['is_super_admin'] = true;
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_type'] = $admin['admin_type'] ?? 'multi_club';
                        $_SESSION['login_time'] = time();

                        // Set active club if default club is configured
                        $hasDefaultClub = false;
                        if (!empty($admin['default_club_id'])) {
                            $defClubId = (int)$admin['default_club_id'];
                            $chkDef = $pdo->prepare("SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?");
                            $chkDef->execute([$defClubId, $admin['admin_id']]);
                            if ($chkDef->fetch()) {
                                $_SESSION['current_club_id'] = $defClubId;
                                $_SESSION['club_id'] = $defClubId;
                                $hasDefaultClub = true;
                            }
                        }

                        error_log("Login successful for user: " . $username);

                        // Check if single_club admin needs to create their first club
                        if ($_SESSION['admin_type'] === 'single_club') {
                            $clubStmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE admin_id = ?");
                            $clubStmt->execute([$admin['admin_id']]);
                            if ($clubStmt->fetchColumn() == 0) {
                                header("Location: create_first_club.php");
                                exit();
                            }
                        }

                        // For users without a default club, check club count
                        if (!$hasDefaultClub) {
                            $userClubsStmt = $pdo->prepare("SELECT c.club_id FROM clubs c JOIN club_admins ca ON c.club_id = ca.club_id WHERE ca.admin_id = ?");
                            $userClubsStmt->execute([$admin['admin_id']]);
                            $userClubs = $userClubsStmt->fetchAll(PDO::FETCH_COLUMN);

                            if (count($userClubs) > 1) {
                                header("Location: select_club.php");
                                exit();
                            } elseif (count($userClubs) === 1) {
                                $_SESSION['current_club_id'] = (int)$userClubs[0];
                                $_SESSION['club_id'] = (int)$userClubs[0];
                            }
                        }

                        header("Location: new_result.php");
                        exit();
                    }
                    // Log failed password verification
                    $security->logLoginAttempt($username, $ipAddress, false);
                    $_SESSION['error'] = "Invalid credentials";
                }
            } else {
                // Log failed attempt for non-existent user
                $security->logLoginAttempt($username, $ipAddress, false);
                $_SESSION['error'] = "Invalid credentials";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $security->logLoginAttempt($username, $ipAddress, false);
            $_SESSION['error'] = "Login failed. Please try again.";
        }
    }

}
?>

<?php
$pageTitle = 'Admin Login - Board Game Club StatApp';
$htmlAttributes = 'data-club-theme="light" data-theme="light" data-theme-locked="true"';
require_once '../includes/templates/header.php';
?>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Admin Login</p>
        </div>
        <a href="../index.php" class="btn btn--secondary">&larr; Back to Main Site</a>
    </div>

    <div class="container container--narrow auth-shell">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>
        <div class="card auth-card">
            <form action="login.php" method="POST" class="stack">
                <div class="form-group">
                    <label for="username">Admin Username:</label>
                    <input type="text" id="username" name="username" required class="form-control" autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required class="form-control">
                </div>
                <div style="display: flex; justify-content: center; margin-bottom: 1rem;">
                    <a href="forgot_password.php" style="font-size: 0.9rem; color: var(--color-primary); text-decoration: none;">Forgot Password?</a>
                </div>
                <div style="display: flex; gap: 0.75rem; width: 100%; margin-top: 0.5rem;">
                    <button type="submit" class="btn btn--primary" style="flex: 1; text-align: center;">Login</button>
                    <a href="../register.php" class="btn btn--secondary" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">Register</a>
                </div>
            </form>
        </div>
        </div>
    </div>
<?php
$extraScripts = '<script src="../js/form-loading.js"></script><script src="../js/form-validation.js"></script>';
require_once '../includes/templates/footer.php';
?>
