<?php
session_start();
require_once 'config/database.php';
require_once 'includes/SecurityUtils.php';

// Initialize SecurityUtils
$security = new SecurityUtils($pdo);

// Generate CSRF token
$csrfToken = $security->generateCSRFToken();

// Clean up expired tokens
$security->cleanExpiredTokens();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Board Game Club StatApp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
        <h2>Register</h2>
    </div>
    <div class="container container--narrow auth-shell">
    <div class="card auth-card">
        <form id="registrationForm" action="process_registration.php" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required
                       class="form-control"
                       minlength="2" maxlength="50">
                <div class="username-requirements">
                    <div class="requirement" data-requirement="length">
                        <i class="fas fa-times"></i> Between 2 and 50 characters
                    </div>
                    <div class="requirement" data-requirement="alphanumeric">
                        <i class="fas fa-times"></i> Only letters, numbers, and underscores
                    </div>
                    <div class="requirement" data-requirement="no-spaces">
                        <i class="fas fa-times"></i> No spaces allowed
                    </div>
                </div>
                <div class="error-message" id="usernameError"></div>
            </div>

            <div class="form-group">
                <label for="email">Club Admin Email:</label>
                <input type="email" id="email" name="email" required class="form-control">
                <!-- This is where the server-side error will appear -->
                <div class="error-message" id="emailError"></div>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required
                        minlength="8" class="form-control">
                    <i class="password-toggle fas fa-eye"></i>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar"></div>
                </div>
                <div class="password-requirements">
                    <div class="requirement" data-requirement="length">
                        <i class="fas fa-times"></i> At least 8 characters
                    </div>
                    <div class="requirement" data-requirement="uppercase">
                        <i class="fas fa-times"></i> One uppercase letter
                    </div>
                    <div class="requirement" data-requirement="lowercase">
                        <i class="fas fa-times"></i> One lowercase letter
                    </div>
                    <div class="requirement" data-requirement="number">
                        <i class="fas fa-times"></i> One number
                    </div>
                    <div class="requirement" data-requirement="special">
                        <i class="fas fa-times"></i> One special character
                    </div>
                </div>
                <div class="error-message" id="passwordError"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" required class="form-control">
                    <i class="password-toggle fas fa-eye"></i>
                </div>
                <div class="error-message" id="confirmPasswordError"></div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn" disabled>Register</button>
                <a href="admin/login.php" class="btn btn--secondary">Back to Login</a>
            </div>
        </form>
    </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email'); // Get email input
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const submitButton = form.querySelector('button[type="submit"]');
            const emailError = document.getElementById('emailError'); // Get email error div
            const usernameError = document.getElementById('usernameError'); // Get username error div (if needed)
            const passwordError = document.getElementById('passwordError'); // Get password error div
            const confirmPasswordError = document.getElementById('confirmPasswordError'); // Get confirm password error div

            // --- Display Server-Side Error ---
            <?php if (isset($_SESSION['registration_error'])): ?>
                const errorMessage = <?php echo json_encode($_SESSION['registration_error']); ?>;
                // Display error near the relevant field (e.g., email)
                // You might want more sophisticated logic here if the error could apply
                // to username or password too, but for 'Email already registered',
                // showing it by the email field makes sense.
                if (emailError) {
                    emailError.textContent = errorMessage;
                    if (emailInput) {
                       emailInput.classList.add('is-invalid'); // Optional: Add error style
                    }
                } else {
                    // Fallback if emailError div doesn't exist for some reason
                    alert('Registration Error: ' + errorMessage);
                }
                <?php unset($_SESSION['registration_error']); // Clear the message after preparing to display ?>
            <?php endif; ?>

            // --- Validation Functions (Keep these as they are) ---
            function validateUsername() {
                // ... (your existing username validation logic) ...
                 const username = usernameInput.value.trim();
                let allValid = true;
                const requirements = {
                    length: username.length >= 2 && username.length <= 50,
                    alphanumeric: /^[a-zA-Z0-9_]+$/.test(username),
                    'no-spaces': !/\s/.test(username)
                };
                Object.entries(requirements).forEach(([req, valid]) => {
                    const reqElement = document.querySelector(`.username-requirements [data-requirement="${req}"]`);
                    if (reqElement) {
                        reqElement.classList.toggle('valid', valid);
                        reqElement.classList.toggle('invalid', !valid);
                        reqElement.querySelector('i').className = valid ? 'fas fa-check' : 'fas fa-times';
                    }
                    if (!valid) allValid = false;
                });
                if (username.length === 0) { /* handle empty UI */
                    const lengthReqElement = document.querySelector('.username-requirements [data-requirement="length"]');
                    if(lengthReqElement) { lengthReqElement.classList.remove('valid'); lengthReqElement.classList.add('invalid'); lengthReqElement.querySelector('i').className = 'fas fa-times'; }
                    const alphaReqElement = document.querySelector('.username-requirements [data-requirement="alphanumeric"]');
                    if(alphaReqElement) { alphaReqElement.classList.remove('valid'); alphaReqElement.classList.add('invalid'); alphaReqElement.querySelector('i').className = 'fas fa-times'; }
                    allValid = false;
                }
                return allValid;
            }

            function validatePassword() {
                // ... (your existing password validation logic) ...
                 const password = passwordInput.value;
                let allValid = true;
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };
                Object.entries(requirements).forEach(([req, valid]) => {
                    const reqElement = document.querySelector(`.password-requirements [data-requirement="${req}"]`);
                    if (reqElement) {
                        reqElement.classList.toggle('valid', valid);
                        reqElement.classList.toggle('invalid', !valid);
                        reqElement.querySelector('i').className = valid ? 'fas fa-check' : 'fas fa-times';
                    }
                    if (!valid) allValid = false;
                });
                return allValid;
            }

             // --- Simplified Check Form Validity (Only enables/disables button) ---
             function checkFormValidity() {
                const emailValue = emailInput.value;
                const isUsernameValid = validateUsername(); // Re-run validation logic
                const isPasswordValid = validatePassword(); // Re-run validation logic
                const doPasswordsMatch = passwordInput.value === confirmPasswordInput.value && passwordInput.value.length > 0;
                const isEmailFormatValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue); // Basic client check

                // Clear confirm password error if passwords now match or fields change
                if (confirmPasswordError) {
                     if (passwordInput.value === confirmPasswordInput.value) {
                         confirmPasswordError.textContent = '';
                         confirmPasswordInput.classList.remove('is-invalid');
                     } else if (confirmPasswordInput.value.length > 0) {
                         // Only show mismatch error if confirm field has content
                         confirmPasswordError.textContent = 'Passwords do not match';
                         confirmPasswordInput.classList.add('is-invalid');
                     } else {
                         // Clear error if confirm field is empty
                          confirmPasswordError.textContent = '';
                         confirmPasswordInput.classList.remove('is-invalid');
                     }
                }


                submitButton.disabled = !(
                    isUsernameValid &&
                    emailValue.length > 0 &&
                    isEmailFormatValid &&
                    isPasswordValid &&
                    doPasswordsMatch
                );
            }

            // --- Event Listeners ---

            // Username validation on input
            usernameInput.addEventListener('input', () => {
                validateUsername(); // Update visual indicators
                checkFormValidity(); // Check overall form validity for button state
            });

            // Email: Clear server error on input, check validity
            emailInput.addEventListener('input', () => {
                if (emailError && emailError.textContent) {
                    emailError.textContent = ''; // Clear server-side error now
                    emailInput.classList.remove('is-invalid');
                }
                checkFormValidity(); // Check overall form validity
            });

            // Password validation on input
            passwordInput.addEventListener('input', () => {
                validatePassword(); // Update visual indicators
                 // Also check confirm password whenever password changes
                 if (confirmPasswordInput.value.length > 0) {
                      checkFormValidity(); // This will re-evaluate password match
                 } else {
                      checkFormValidity(); // Still check validity for button state
                 }

            });

            // Confirm Password validation on input
            confirmPasswordInput.addEventListener('input', () => {
                checkFormValidity(); // This will check the match and update button state
            });


            // Password visibility toggles (keep as is)
            document.querySelectorAll('.password-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetInput = this.previousElementSibling;
                    if (targetInput && targetInput.matches('input[type="password"], input[type="text"]')) {
                         const type = targetInput.getAttribute('type');
                         targetInput.setAttribute('type', type === 'password' ? 'text' : 'password');
                         this.className = `password-toggle fas ${type === 'password' ? 'fa-eye-slash' : 'fa-eye'}`;
                    }
                });
            });

            // Form submission handler (keep as is)
            form.addEventListener('submit', function(e) {
                checkFormValidity(); // Final check before submitting
                if (submitButton.disabled) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });

            // --- Initial State Check ---
            // Run validations to set initial UI indicators and button state
            validateUsername();
            validatePassword();
            checkFormValidity(); // Call this last to set the button state correctly based on initial field values (if any)

        });
    </script>
</body>
</html>
