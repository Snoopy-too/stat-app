<?php
// File: includes/RegistrationHandler.php

require_once 'SecurityUtils.php';
// Assuming config/database.php defines $pdo or connection details globally is not ideal,
// but keeping consistent with likely existing setup. Better would be dependency injection for config.
require_once __DIR__ . '/../config/database.php';

class RegistrationHandler {
    private $pdo;
    private $security;
    private $errors = []; // Keep track of validation errors

    public function __construct(PDO $pdo, SecurityUtils $security = null) {
        $this->pdo = $pdo;
        // Provide a default SecurityUtils instance if none is passed
        $this->security = $security ?? new SecurityUtils($pdo);
    }

    /**
     * Registers a new club administrator.
     * Performs validation, checks for existing email, hashes password, and inserts into DB.
     *
     * @param array $data Associative array containing form data.
     * @return array ['success' => bool, 'message' => string, 'admin_id' => int|null, 'errors' => array|null]
     */
    public function registerClubAdmin(array $data): array {
        $this->errors = []; // Reset errors for each call
        $clientIP = null; // Initialize client IP

        try {
            // Get Client IP early for rate limiting/logging
            $clientIP = $this->security->getClientIP();

            // 1. Check registration rate limiting
            if (!$this->security->checkRegistrationAttempts($clientIP)) {
                // Return specific error, don't throw exception here to keep format consistent
                return [
                    'success' => false,
                    'message' => 'Too many registration attempts. Please try again later.',
                    'errors' => ['rate_limit' => 'Too many registration attempts.']
                ];
            }

            // 2. Validate CSRF token
            if (!isset($data['csrf_token']) || !$this->security->verifyCSRFToken($data['csrf_token'])) {
                // Return specific error
                 return [
                    'success' => false,
                    'message' => 'Invalid security token. Please refresh the page and try again.',
                    'errors' => ['csrf' => 'Invalid security token.']
                 ];
            }

            // 3. Validate and sanitize input
            $email = filter_var(
                $this->security->sanitizeInput($data['email'] ?? ''),
                FILTER_VALIDATE_EMAIL
            );
            $fullName = $this->security->sanitizeInput($data['full_name'] ?? ''); // Corresponds to 'username' from form
            $password = $data['password'] ?? '';
            $confirmPassword = $data['confirm_password'] ?? '';

            // --- Specific Validation Checks ---

            // Basic Presence Checks
            if (empty($email)) {
                $this->errors['email'] = 'Email address is required.';
            } elseif (!$email) { // Check format only if not empty
                $this->errors['email'] = 'Invalid email address format.';
            }
            if (empty($fullName)) {
                 $this->errors['full_name'] = 'Username is required.';
            } elseif (strlen($fullName) < 2) { // Example length check
                $this->errors['full_name'] = 'Username must be at least 2 characters.';
            }
            if (empty($password)) {
                $this->errors['password'] = 'Password is required.';
            }
             if (empty($confirmPassword)) {
                $this->errors['confirm_password'] = 'Password confirmation is required.';
            }

             // --- Email Uniqueness Check (Perform *after* basic format check) ---
             $emailAlreadyRegistered = false;
             if ($email && !isset($this->errors['email'])) { // Only check if email format is valid
                try {
                     $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE email = ?');
                     $stmt->execute([$email]);
                     if ($stmt->fetchColumn() > 0) {
                         // ** Set the specific error message directly **
                         $this->errors['email'] = 'Email address is already registered.';
                         $emailAlreadyRegistered = true; // Flag this specific error
                     }
                } catch (PDOException $e) {
                    error_log("Database error checking email uniqueness: " . $e->getMessage());
                     // Add a generic error, don't expose DB details
                     $this->errors['database'] = 'Could not verify email uniqueness.';
                }
             }

             // --- Other Validations (Password, etc.) ---
             // Only check password rules if password fields are present and passwords match (initially)
             if (!empty($password) && !empty($confirmPassword)) {
                 if ($password !== $confirmPassword) {
                     $this->errors['confirm_password'] = 'Passwords do not match.';
                 } else {
                     // Check password complexity only if they match and are not empty
                     if (strlen($password) < 8) { $this->errors['password_length'] = 'Password must be at least 8 characters long.'; }
                     if (!preg_match('/[A-Z]/', $password)) { $this->errors['password_uppercase'] = 'Password must contain at least one uppercase letter.'; }
                     if (!preg_match('/[a-z]/', $password)) { $this->errors['password_lowercase'] = 'Password must contain at least one lowercase letter.'; }
                     if (!preg_match('/[0-9]/', $password)) { $this->errors['password_number'] = 'Password must contain at least one number.'; }
                     if (!preg_match('/[^A-Za-z0-9]/', $password)) { $this->errors['password_special'] = 'Password must contain at least one special character.'; }
                 }
             }


            // 4. Check collected errors and return specific message if needed
            if (!empty($this->errors)) {
                // ** Prioritize the duplicate email message **
                if ($emailAlreadyRegistered) {
                     return [
                        'success' => false,
                        'message' => 'Email address is already registered.', // Specific message for caller
                        'errors' => $this->errors // Return all errors found
                     ];
                } else {
                    // If other errors exist, return a general validation message
                    // You could refine this to return the *first* error message found if preferred
                    $firstErrorMessage = reset($this->errors); // Get the first error message
                    return [
                        'success' => false,
                        // Use the first specific error found, or a general fallback
                        'message' => $firstErrorMessage ?: 'Validation failed. Please check the fields.',
                        'errors' => $this->errors
                    ];
                }
            }

            // --- If all validations passed ---

            // 5. Generate verification token and hash password
            $verificationData = $this->security->generateEmailVerificationToken();
            $passwordHash = $this->security->hashPassword($password);
             if ($passwordHash === false) { // Check if hashing failed
                 throw new Exception('Failed to process password.');
             }


            // 6. Insert new administrator
            // Use explicit column names matching your table structure
            $stmt = $this->pdo->prepare(
                'INSERT INTO admin_users
                 (username, email, password_hash, email_verification_token, email_token_expiry,
                 account_status, is_super_admin, is_email_verified, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())' // Added created_at assuming it exists
            );
            $stmt->execute([
                $fullName, // Assuming 'username' column stores the full name/display name
                $email,
                $passwordHash,
                $verificationData['token'],
                $verificationData['expiry'],
                "pending", // account_status
                0,         // is_super_admin (default to false)
                0          // is_email_verified (default to false)
            ]);

            if ($stmt->rowCount() === 0) { // Check if insert actually happened
                throw new Exception('Failed to create administrator account due to database issue.');
            }

            $adminId = $this->pdo->lastInsertId();

            // 7. Send verification email
            $this->sendVerificationEmail($email, $fullName, $verificationData['token']);

            // 8. Log successful registration attempt - moved after all critical operations
            $this->security->logRegistrationAttempt($clientIP, true);

            // 9. Return success
            return [
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
                'admin_id' => $adminId
            ];

        } catch (Exception $e) {
            // Log failed registration attempt in case of unexpected exceptions
            if ($clientIP) { // Ensure clientIP was obtained
                 try {
                    $this->security->logRegistrationAttempt($clientIP, false);
                 } catch (Exception $logException) {
                     error_log('Failed to log registration attempt after exception: ' . $logException->getMessage());
                 }
            }

             // Log the actual exception
            error_log("Registration Error: " . $e->getMessage());

            // Return a generic error message for unexpected exceptions
            // Include specific errors if they were collected before the exception occurred
            return [
                'success' => false,
                // Provide a user-friendly message for unexpected errors
                'message' => 'An unexpected error occurred during registration. Please try again later.',
                // Include validation errors if they were populated before the exception
                'errors' => !empty($this->errors) ? $this->errors : ['general' => $e->getMessage()]
            ];
        }
    } // End of registerClubAdmin method

    /**
     * Sends the verification email.
     * Note: Ensure mail server is configured on the host.
     */
    private function sendVerificationEmail(string $email, string $fullName, string $token): void {
        // Ensure app_config is loaded. Consider making these constants or injecting config.
        require_once __DIR__ . '/../config/app_config.php';

        // Define constants if not already defined to prevent errors
        if (!defined('BASE_URL')) define('BASE_URL', 'http://yourdomain.com'); // Replace with actual base URL
        if (!defined('TOKEN_EXPIRATION_HOURS')) define('TOKEN_EXPIRATION_HOURS', 24);
        if (!defined('EMAIL_SIGNATURE')) define('EMAIL_SIGNATURE', 'The StatApp Team');
        if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'no-reply@yourdomain.com'); // Replace with actual sender

        $verificationLink = rtrim(BASE_URL, '/') . '/verify_email.php?token=' . urlencode($token);

        $to = $email;
        $subject = 'Verify Your Board Game Club Account';

        // Build email body
        $message = "Dear $fullName,\n\n";
        $message .= "Thank you for registering with the Board Game Club StatApp. ";
        $message .= "Please click the following link to verify your email address:\n\n";
        $message .= $verificationLink . "\n\n";
        $message .= "This link will expire in " . TOKEN_EXPIRATION_HOURS . " hours.\n\n";
        $message .= "If you did not create this account, please ignore this email.\n\n";
        $message .= "Best regards,\n" . EMAIL_SIGNATURE;

        // Set email headers
        $headers = 'From: ' . FROM_EMAIL . "\r\n" .
                   'Reply-To: ' . FROM_EMAIL . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();

        // Use error suppression cautiously, better to have proper error handling/logging
        if (!mail($to, $subject, $message, $headers)) {
             error_log("Failed to send verification email to: " . $email);
             // Decide if you want to throw an exception here or just log the error
             // throw new Exception("Could not send verification email.");
        }
    }

    /**
     * Verifies an email address using the provided token.
     */
    public function verifyEmail(string $token): array {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT admin_id, email
                 FROM admin_users
                 WHERE email_verification_token = ?
                 AND email_token_expiry > NOW()
                 AND is_email_verified = 0' // Use 0 for boolean false in SQL
            );
            $stmt->execute([$token]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                // Check if already verified or token simply not found/expired
                 $stmtCheckVerified = $this->pdo->prepare(
                     'SELECT admin_id FROM admin_users WHERE email_verification_token = ? AND is_email_verified = 1'
                 );
                 $stmtCheckVerified->execute([$token]);
                 if ($stmtCheckVerified->fetch()) {
                     // Token might be reused from URL, but account is already verified
                     return ['success' => true, 'message' => 'Email already verified. You can log in.'];
                 }
                throw new Exception('Invalid or expired verification token.');
            }

            // Use transaction for update
            $this->pdo->beginTransaction();

            $stmtUpdate = $this->pdo->prepare(
                'UPDATE admin_users
                 SET is_email_verified = 1,       -- Use 1 for boolean true
                     email_verification_token = NULL,
                     email_token_expiry = NULL,
                     account_status = "active"   -- Set status to active
                 WHERE admin_id = ? AND is_email_verified = 0' // Ensure we only update unverified
            );
            $updated = $stmtUpdate->execute([$admin['admin_id']]);

             if (!$updated || $stmtUpdate->rowCount() === 0) {
                 $this->pdo->rollBack();
                 // Possible race condition or issue, token was valid but update failed
                 throw new Exception('Failed to update verification status. Please try the link again.');
             }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Email verification successful! You can now log in to your account.'
            ];

        } catch (Exception $e) {
             if ($this->pdo->inTransaction()) {
                 $this->pdo->rollBack();
             }
             error_log("Email Verification Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage() // Return the specific error message
            ];
        }
    }
} // End of class RegistrationHandler