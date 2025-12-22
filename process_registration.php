<?php
session_start();
require_once 'config/database.php';
require_once 'includes/SecurityUtils.php';
require_once 'includes/RegistrationHandler.php';

try {
    $security = new SecurityUtils($pdo);
    $registrationHandler = new RegistrationHandler($pdo, $security);

    // Validate CSRF token
    if (!$security->verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid CSRF token. Please try again.');    
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Get and sanitize input data
    $postData = [
        'csrf_token' => $_POST['csrf_token'] ?? '',
        'email' => $_POST['email'] ?? '',
        'full_name' => $_POST['username'] ?? '', // Using username field as full_name
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];

    // Sanitize input
    foreach ($postData as $key => $value) {
        if ($key !== 'password' && $key !== 'confirm_password') {
            $postData[$key] = $security->sanitizeInput($value);
        }
    }

    // Validate required fields
    if (empty($postData['email']) || empty($postData['full_name']) || empty($postData['password'])) {
        throw new Exception('All fields are required.');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Register the club administrator
    $adminResult = $registrationHandler->registerClubAdmin($postData);

    if (!$adminResult['success']) {
        // Check if the error is about email already registered
        if (strpos($adminResult['message'], 'Email address is already registered') !== false) {
            throw new Exception('Email address is already registered');
        }
        throw new Exception($adminResult['message']);
    }

    // Note: Club creation removed - single_club admins will create their club on first login
    // This allows them to choose their club name instead of auto-generating one

    // Commit transaction
    $pdo->commit();

    // Store success message in session
    $_SESSION['registration_success'] = 'Registration successful! Please check your email to verify your account.';
    
    // Redirect to success page
    header('Location: success.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Store error message in session
    $_SESSION['registration_error'] = $e->getMessage();
    
    // Redirect back to registration page
    header('Location: register.php');
    exit;
}
?>