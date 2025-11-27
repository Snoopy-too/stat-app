<?php
/**
 * Database Configuration Template
 *
 * INSTRUCTIONS:
 * 1. Copy this file to env.php in the same directory
 * 2. Update the values below with your database credentials
 * 3. Never commit env.php to version control!
 *
 * PRODUCTION SECURITY CHECKLIST:
 * [ ] Change DB_USER from 'root' to a dedicated application user
 * [ ] Set a strong DB_PASS (never leave empty!)
 * [ ] Verify DB_HOST matches your production database server
 * [ ] Ensure database user has ONLY the permissions needed
 * [ ] Use different credentials for dev/staging/production
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'statapp');
define('DB_USER', 'statapp_user'); // Create a dedicated user, don't use root!
define('DB_PASS', 'your_secure_password_here'); // Use a strong password!
