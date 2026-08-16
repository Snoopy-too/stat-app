<?php
ob_start();
require_once '../config/security_headers.php'; // Set security headers first
require_once '../config/session.php';        // Configure secure sessions
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/helpers.php';

if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
    $clubId = $_SESSION['current_club_id'] ?? $_SESSION['club_id'] ?? null;
    if ($clubId) {
        header("Location: club_new_results.php?club_id=" . (int)$clubId);
    } else {
        header("Location: select_club.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['admin_id'])) {
    $redirectUrl = "https://theflyingdutchmen.games/oauth/authorize?response_type=code&client_id=stats-app-756c55ba&redirect_uri=" . urlencode("https://stats.theflyingdutchmen.games/auth_callback.php");
    header("Location: " . $redirectUrl);
    exit();
}

ensure_club_admins_is_default_column_exists($pdo);
$security = new SecurityUtils($pdo);
$csrfToken = $security->generateCSRFToken();
$security->cleanExpiredTokens();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    // Check if rate limit exceeded (use username/email for admin logins)
    if (!$security->checkLoginAttempts($username, $ipAddress)) {
        $_SESSION['error'] = "Too many failed login attempts. Please try again in 15 minutes.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT admin_id, username, password_hash, is_deactivated, admin_type FROM admin_users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                if (!empty($admin['is_deactivated']) && $admin['is_deactivated'] == 1) {
                    $security->logLoginAttempt($username, $ipAddress, false);
                    $_SESSION['error'] = "Your account has been deactivated. Please contact a Super Administrator.";
                } else {
                    if (password_verify($password, $admin['password_hash'])) {
                        // Log successful login
                        $security->logLoginAttempt($username, $ipAddress, true);

                        // Regenerate session ID to prevent session fixation attacks
                        session_regenerate_id(true);

                        $_SESSION['is_super_admin'] = true;
                        $_SESSION['is_admin'] = true;
                        $_SESSION['admin_id'] = (int)$admin['admin_id'];
                        $_SESSION['user_id'] = (int)$admin['admin_id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['username'] = $admin['username'];
                        $_SESSION['admin_type'] = $admin['admin_type'] ?? 'multi_club';
                        $_SESSION['login_time'] = time();

                        // Keep me logged in (30-day session cookie)
                        if (!empty($_POST['remember_me'])) {
                            $_SESSION['remember_me'] = true;
                            $cookieParams = session_get_cookie_params();
                            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                                       (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                                       (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
                                       (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
                            setcookie(session_name(), session_id(), [
                                'expires' => time() + (30 * 86400),
                                'path' => $cookieParams['path'] ?? '/',
                                'domain' => $cookieParams['domain'] ?? '',
                                'secure' => $isHttps || (!empty($cookieParams['secure'])),
                                'httponly' => true,
                                'samesite' => $cookieParams['samesite'] ?? 'Lax',
                            ]);
                        }

                        // Check if single_club admin needs to create their first club
                        if ($_SESSION['admin_type'] === 'single_club') {
                            $clubStmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE admin_id = ?");
                            $clubStmt->execute([$admin['admin_id']]);
                            if ($clubStmt->fetchColumn() == 0) {
                                session_write_close();
                                header("Location: create_first_club.php");
                                exit();
                            }
                        }

                        // Fetch user's clubs with default flags
                        $userClubsStmt = $pdo->prepare("SELECT c.club_id, ca.is_default FROM clubs c JOIN club_admins ca ON c.club_id = ca.club_id WHERE ca.admin_id = ? ORDER BY c.club_name ASC");
                        $userClubsStmt->execute([$admin['admin_id']]);
                        $userClubs = $userClubsStmt->fetchAll(PDO::FETCH_ASSOC);

                        $defaultClubs = array_values(array_filter($userClubs, function($c) { return !empty($c['is_default']); }));
                        $defaultCount = count($defaultClubs);

                        if ($defaultCount === 1) {
                            // If only one club is set as default, skip club selection view
                            $defClubId = (int)$defaultClubs[0]['club_id'];
                            $_SESSION['current_club_id'] = $defClubId;
                            $_SESSION['club_id'] = $defClubId;
                        } elseif ($defaultCount > 1) {
                            // If multiple defaults, display them in select_club.php
                            unset($_SESSION['current_club_id'], $_SESSION['club_id']);
                            session_write_close();
                            header("Location: select_club.php");
                            exit();
                        } else {
                            // If no clubs are set as default, all clubs will be shown
                            if (count($userClubs) > 1) {
                                unset($_SESSION['current_club_id'], $_SESSION['club_id']);
                                session_write_close();
                                header("Location: select_club.php");
                                exit();
                            } elseif (count($userClubs) === 1) {
                                $singleClubId = (int)$userClubs[0]['club_id'];
                                $_SESSION['current_club_id'] = $singleClubId;
                                $_SESSION['club_id'] = $singleClubId;
                            } else {
                                unset($_SESSION['current_club_id'], $_SESSION['club_id']);
                                session_write_close();
                                header("Location: account.php");
                                exit();
                            }
                        }

                        $targetClubId = $_SESSION['current_club_id'] ?? $_SESSION['club_id'] ?? null;
                        session_write_close();
                        header("Location: " . ($targetClubId ? "club_new_results.php?club_id=" . (int)$targetClubId : "select_club.php"));
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

$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'register') || isset($_SESSION['registration_error']) ? 'register' : 'login';
$pageTitle = ($activeTab === 'register' ? 'Register' : 'Admin Login') . ' - Board Game Club StatApp';
$htmlAttributes = 'data-club-theme="light" data-theme="light" data-theme-locked';
require_once '../includes/templates/header.php';
?>

<header class="landing-header">
    <a href="../index.php" class="logo-brand">
        <span>🎲</span> StatApp
    </a>
</header>

<div class="landing-hero">
    <!-- Background Wave & Grid Contour Overlays -->
    <div class="hero-contour-waves"></div>

    <div class="landing-hero-content">
        <h1 id="auth-heading"><?php echo $activeTab === 'register' ? 'Create <span class="highlight">Account</span>' : 'Admin <span class="highlight">Login</span>'; ?></h1>
        <p class="landing-hero-subtitle" id="auth-subtitle">
            <?php echo $activeTab === 'register' ? 'Create an administrator account for your Board Game Club(s).' : 'Sign in to manage your Board Game Club stats.'; ?>
        </p>

        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>
        <?php display_session_message('registration_error', 'error'); ?>

        <div class="landing-card landing-card--compact">
            <!-- Corner Contour Brackets -->
            <div class="card-corner-bracket card-corner-bracket--tl"></div>
            <div class="card-corner-bracket card-corner-bracket--tr"></div>
            <div class="card-corner-bracket card-corner-bracket--bl"></div>
            <div class="card-corner-bracket card-corner-bracket--br"></div>

            <!-- Tab Switcher -->
            <div class="auth-tabs" role="tablist">
                <button type="button" class="auth-tab-btn <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="tab-btn-login" data-tab="login" role="tab" aria-selected="<?php echo $activeTab === 'login' ? 'true' : 'false'; ?>">Sign In</button>
                <button type="button" class="auth-tab-btn <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="tab-btn-register" data-tab="register" role="tab" aria-selected="<?php echo $activeTab === 'register' ? 'true' : 'false'; ?>">Create Account</button>
            </div>

            <!-- Sign In Panel -->
            <div class="auth-panel <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="panel-login" role="tabpanel">
                <form action="login.php" method="POST" class="stack">
                    <div class="form-group">
                        <label for="username" class="form-label form-label--required">Username or Email</label>
                        <div class="input-with-icon">
                            <span class="input-icon">👤</span>
                            <input type="text" id="username" name="username" required class="form-control" placeholder="Enter your username or email" autofocus autocomplete="username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label form-label--required">Password</label>
                        <div class="input-with-icon">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="password" name="password" required class="form-control" placeholder="Enter your password" autocomplete="current-password">
                        </div>
                    </div>

                    <div class="auth-options-row">
                        <label>
                            <input type="checkbox" name="remember_me" value="1" class="form-check-input">
                            <span>Keep me logged in</span>
                        </label>
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block" style="width: 100%; justify-content: center;">Sign In</button>
                </form>

                <div style="margin: 1.25rem 0 0.5rem; text-align: center;">
                    <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <hr style="flex: 1; border: 0; border-top: 1px solid var(--card-border, rgba(255,255,255,0.15));">
                        <span style="padding: 0 10px; color: var(--text-muted, #8b949e); font-size: 0.85rem;">or</span>
                        <hr style="flex: 1; border: 0; border-top: 1px solid var(--card-border, rgba(255,255,255,0.15));">
                    </div>
                    <a href="https://theflyingdutchmen.games/oauth/authorize?response_type=code&client_id=stats-app-756c55ba&response_type=code&redirect_uri=https://stats.theflyingdutchmen.games/auth_callback.php&scope=openid%20profile%20email" class="btn btn--secondary btn--block" style="width: 100%; justify-content: center; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                        <span>🎮</span> Sign in with The Flying Dutchmen
                    </a>
                </div>

                <div style="margin: 1.25rem 0 0.5rem; text-align: center;">
                    <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <hr style="flex: 1; border: 0; border-top: 1px solid var(--card-border, rgba(255,255,255,0.15));">
                        <span style="padding: 0 10px; color: var(--text-muted, #8b949e); font-size: 0.85rem;">or</span>
                        <hr style="flex: 1; border: 0; border-top: 1px solid var(--card-border, rgba(255,255,255,0.15));">
                    </div>
                    <a href="https://theflyingdutchmen.games/oauth/authorize?response_type=code&client_id=stats-app-756c55ba&response_type=code&redirect_uri=https://stats.theflyingdutchmen.games/auth_callback.php&scope=openid%20profile%20email" class="btn btn--secondary btn--block" style="width: 100%; justify-content: center; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                        <span>🎮</span> Sign in with The Flying Dutchmen
                    </a>
                </div>

                <p class="auth-switch-text">
                    Don't have an account? <button type="button" class="btn-link-inline" onclick="setAuthTab('register')">Create one</button>
                </p>
            </div>

            <!-- Create Account Panel -->
            <div class="auth-panel <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="panel-register" role="tabpanel">
                <form id="registrationForm" action="../process_registration.php" method="POST" class="stack">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="form-group">
                        <label for="reg_username" class="form-label form-label--required">Username</label>
                        <div class="input-with-icon">
                            <span class="input-icon">👤</span>
                            <input type="text" id="reg_username" name="username" required class="form-control"
                                   placeholder="Choose a unique username" minlength="2" maxlength="50"
                                   pattern="^[a-zA-Z0-9_]+$" title="Only letters, numbers, and underscores allowed"
                                   autocomplete="username">
                        </div>
                        <small class="help-text">2 to 50 characters (letters, numbers, underscores).</small>
                    </div>

                    <div class="form-group">
                        <label for="reg_email" class="form-label form-label--required">Club Admin Email</label>
                        <div class="input-with-icon">
                            <span class="input-icon">✉️</span>
                            <input type="email" id="reg_email" name="email" required class="form-control"
                                   placeholder="admin@yourdomain.com" autocomplete="email">
                        </div>
                        <small class="help-text">Used for admin login and notifications.</small>
                    </div>

                    <div class="form-group">
                        <label for="reg_password" class="form-label form-label--required">Password</label>
                        <div class="input-with-icon">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="reg_password" name="password" required class="form-control"
                                   minlength="8" placeholder="Create a strong password" autocomplete="new-password">
                        </div>
                        <small class="help-text">Min 8 characters with uppercase, lowercase, number, and symbol.</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label form-label--required">Confirm Password</label>
                        <div class="input-with-icon">
                            <span class="input-icon">🔑</span>
                            <input type="password" id="confirm_password" name="confirm_password" required class="form-control"
                                   minlength="8" placeholder="Re-enter your password" autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block" style="width: 100%; justify-content: center; margin-top: 0.5rem;">Register Account</button>
                </form>

                <p class="auth-switch-text">
                    Already have an account? <button type="button" class="btn-link-inline" onclick="setAuthTab('login')">Sign In</button>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function setAuthTab(tabName) {
    const heading = document.getElementById('auth-heading');
    const subtitle = document.getElementById('auth-subtitle');

    document.querySelectorAll('.auth-tab-btn').forEach(btn => {
        const isActive = btn.getAttribute('data-tab') === tabName;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    document.querySelectorAll('.auth-panel').forEach(panel => {
        panel.classList.toggle('active', panel.id === 'panel-' + tabName);
    });

    if (heading && subtitle) {
        if (tabName === 'register') {
            heading.innerHTML = 'Create <span class="highlight">Account</span>';
            subtitle.textContent = 'Create an administrator account for your Board Game Club(s).';
            document.title = 'Register - Board Game Club StatApp';
        } else {
            heading.innerHTML = 'Admin <span class="highlight">Login</span>';
            subtitle.textContent = 'Sign in to manage your Board Game Club stats.';
            document.title = 'Admin Login - Board Game Club StatApp';
        }
    }

    // Update query string smoothly without reload
    const newUrl = tabName === 'register' ? 'login.php?tab=register' : 'login.php';
    window.history.replaceState(null, '', newUrl);

    // Focus first input in newly active tab
    const firstInput = document.querySelector('#panel-' + tabName + ' input:not([type="hidden"])');
    if (firstInput) {
        firstInput.focus();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Handle tab switching via click
    document.querySelectorAll('.auth-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            setAuthTab(this.getAttribute('data-tab'));
        });
    });

    // Check URL hash if present
    if (window.location.hash === '#register') {
        setAuthTab('register');
    }

    // Password confirmation match validation
    const pwInput = document.getElementById('reg_password');
    const confirmInput = document.getElementById('confirm_password');

    function checkMatch() {
        if (confirmInput && pwInput && confirmInput.value && confirmInput.value !== pwInput.value) {
            confirmInput.setCustomValidity('Passwords do not match');
        } else if (confirmInput) {
            confirmInput.setCustomValidity('');
        }
    }

    if (pwInput && confirmInput) {
        pwInput.addEventListener('input', checkMatch);
        confirmInput.addEventListener('input', checkMatch);
    }
});
</script>

<?php
$extraScripts = '<script src="../js/form-loading.js"></script>';
require_once '../includes/templates/footer.php';
?>
