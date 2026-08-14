<?php
require_once 'config/database.php';
require_once 'config/app_config.php';
require_once 'includes/RegistrationHandler.php';

// Initialize error logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/logs/verification_errors.log');

// Validate token parameter
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token'], ENT_QUOTES, 'UTF-8') : '';
$result = ['success' => false, 'message' => ''];

try {
    if (!$token) {
        throw new Exception('Verification token is missing.');
    }
    
    $registrationHandler = new RegistrationHandler($pdo);
    $result = $registrationHandler->verifyEmail($token);
    
} catch (Exception $e) {
    error_log('Verification error: ' . $e->getMessage());
    $result['message'] = 'An error occurred during verification. Please try again or contact support.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
        <h2>Email Verification</h2>
    </div>

    <div class="verification-container <?php echo $result['success'] ? 'success' : 'error'; ?>">
        <div class="verification-icon">
            <?php if ($result['success']): ?>
                ✓
            <?php else: ?>
                ⚠
            <?php endif; ?>
        </div>

        <div class="verification-message">
            <?php echo htmlspecialchars($result['message']); ?>
        </div>

        <a href="admin/login.php" class="btn">
            <?php echo $result['success'] ? 'Proceed to Login' : 'Back to Login'; ?>
        </a>
    </div>
</body>
</html>
