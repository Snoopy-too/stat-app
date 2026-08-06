<?php
session_start();
require_once 'config/database.php';
require_once 'includes/SecurityUtils.php';
require_once 'includes/helpers.php';

$security = new SecurityUtils($pdo);
$csrfToken = $security->generateCSRFToken();
$security->cleanExpiredTokens();

$pageTitle = 'Register Administrator - Board Game Club StatApp';
$htmlAttributes = 'data-club-theme="light" data-theme="light"';
require_once 'includes/templates/header.php';
?>

<header class="landing-header">
    <a href="index.php" class="logo-brand">
        <span>🎲</span> StatApp
    </a>
    <div class="header-actions">
        <a href="admin/login.php" class="btn btn--secondary btn--sm">Admin Login</a>
    </div>
</header>

<div class="landing-hero">
    <!-- Background Wave & Grid Contour Overlays -->
    <div class="hero-contour-waves"></div>

    <div class="landing-hero-content">
        <h1>Register as <span class="highlight">Admin</span></h1>
        <p class="landing-hero-subtitle">Create an administrator account for your Board Game Club(s).</p>

        <?php display_session_message('error'); ?>
        <?php display_session_message('registration_error', 'error'); ?>
        <?php display_session_message('success'); ?>

        <div class="landing-card">
            <!-- Corner Contour Brackets -->
            <div class="card-corner-bracket card-corner-bracket--tl"></div>
            <div class="card-corner-bracket card-corner-bracket--tr"></div>
            <div class="card-corner-bracket card-corner-bracket--bl"></div>
            <div class="card-corner-bracket card-corner-bracket--br"></div>

            <form id="registrationForm" action="process_registration.php" method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="form-group">
                    <label for="username" class="form-label form-label--required">Username</label>
                    <div class="input-with-icon">
                        <span class="input-icon">👤</span>
                        <input type="text" id="username" name="username" required class="form-control"
                               placeholder="Choose a unique username" minlength="2" maxlength="50"
                               pattern="^[a-zA-Z0-9_]+$" title="Only letters, numbers, and underscores allowed"
                               autocomplete="username" autofocus>
                    </div>
                    <small class="help-text">2 to 50 characters (letters, numbers, underscores).</small>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label form-label--required">Club Admin Email</label>
                    <div class="input-with-icon">
                        <span class="input-icon">✉️</span>
                        <input type="email" id="email" name="email" required class="form-control"
                               placeholder="admin@yourdomain.com" autocomplete="email">
                    </div>
                    <small class="help-text">Used for admin login and notifications.</small>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label form-label--required">Password</label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="password" name="password" required class="form-control"
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

                <div class="form-divider"></div>

                <div style="display: flex; gap: 0.75rem; width: 100%; margin-top: 1rem;">
                    <button type="submit" class="btn btn--primary" style="flex: 1; text-align: center; justify-content: center;">Register Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pwInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');

    function checkMatch() {
        if (confirmInput.value && confirmInput.value !== pwInput.value) {
            confirmInput.setCustomValidity('Passwords do not match');
        } else {
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
$extraScripts = '<script src="js/form-loading.js"></script>';
require_once 'includes/templates/footer.php';
?>
