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
    <style>
        .verification-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .verification-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .success .verification-icon {
            color: #28a745;
        }

        .error .verification-icon {
            color: #dc3545;
        }

        .verification-message {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .verification-link {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: #4285f4;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .verification-link:hover {
            background-color: #3367d6;
        }
    </style>
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

        <a href="admin/login.php" class="verification-link">
            <?php echo $result['success'] ? 'Proceed to Login' : 'Back to Login'; ?>
        </a>
    </div>
</body>
</html>