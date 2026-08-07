<?php
/**
 * Helper Functions
 *
 * Common utility functions used throughout the application
 */

/**
 * Safely escape HTML output
 *
 * @param string $string The string to escape
 * @return string The escaped string
 */
function esc_html($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Display and clear a session message
 *
 * @param string $key Session key (e.g., 'success', 'error')
 * @param string $class CSS class for the message (default: uses key as class name)
 * @return void
 */
function display_session_message($key, $class = null) {
    if (isset($_SESSION[$key])) {
        $class = $class ?? $key; // Use key as class if not provided
        echo '<div class="message message--' . esc_html($class) . '">' . esc_html($_SESSION[$key]) . '</div>';
        unset($_SESSION[$key]);
    }
}

/**
 * Get and clear a session message (returns the message instead of echoing)
 *
 * @param string $key Session key
 * @return string|null The message or null if not set
 */
function get_session_message($key) {
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

/**
 * Get full image URL for a game image
 *
 * @param string|null $image Game image filename or full URL
 * @param string $prefix Path prefix for local images (e.g., '' or '../')
 * @return string
 */
function get_game_image_url($image, $prefix = '') {
    if (empty($image)) {
        return '';
    }
    if (preg_match('~^https?://~i', $image)) {
        return $image;
    }
    return $prefix . 'images/game_images/' . $image;
}

/**
 * Ensures the 'game_image' column exists in the 'games' table
 *
 * @param PDO $pdo
 * @return void
 */
function ensure_game_image_column_exists($pdo) {
    static $checked = false;
    if ($checked || !$pdo) {
        return;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM games LIKE 'game_image'");
        if ($stmt && $stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE games ADD COLUMN game_image VARCHAR(500) DEFAULT NULL");
        }
        $checked = true;
    } catch (Throwable $e) {
        // Silently fail if table issue or lacking permissions
    }
}

/**
 * Ensures the 'game_type' column exists in the 'games' table
 *
 * @param PDO $pdo
 * @return void
 */
function ensure_game_type_column_exists($pdo) {
    static $checked = false;
    if ($checked || !$pdo) {
        return;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM games LIKE 'game_type'");
        if ($stmt && $stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE games ADD COLUMN game_type VARCHAR(50) NOT NULL DEFAULT 'winner_losers'");
        }
        $checked = true;
    } catch (Throwable $e) {
        // Silently fail if table issue or lacking permissions
    }
}

/**
 * Ensures all result-related tables exist in the database
 *
 * @param PDO $pdo
 * @return void
 */
function ensure_results_tables_exist($pdo) {
    static $checked = false;
    if ($checked || !$pdo) {
        return;
    }
    $checked = true;

    try {
        $pdo->query("SELECT 1 FROM game_result_losers LIMIT 1");
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS game_result_losers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                result_id INT NOT NULL,
                member_id INT NOT NULL,
                FOREIGN KEY (result_id) REFERENCES game_results(result_id) ON DELETE CASCADE,
                FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e2) {}
    }

    try {
        $pdo->query("SELECT 1 FROM team_game_results LIMIT 1");
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS team_game_results (
                result_id INT AUTO_INCREMENT PRIMARY KEY,
                game_id INT NOT NULL,
                session_id VARCHAR(255) DEFAULT NULL,
                team_id INT DEFAULT NULL,
                position INT DEFAULT NULL,
                played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                duration INT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                num_teams INT DEFAULT NULL,
                winner INT DEFAULT NULL,
                place_2 INT DEFAULT NULL,
                place_3 INT DEFAULT NULL,
                place_4 INT DEFAULT NULL,
                place_5 INT DEFAULT NULL,
                place_6 INT DEFAULT NULL,
                place_7 INT DEFAULT NULL,
                place_8 INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e2) {}
    }

    try {
        $pdo->query("SELECT 1 FROM cooperative_game_results LIMIT 1");
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cooperative_game_results (
                result_id INT AUTO_INCREMENT PRIMARY KEY,
                game_id INT NOT NULL,
                session_id VARCHAR(255) NOT NULL,
                outcome ENUM('win','loss') NOT NULL,
                score INT DEFAULT NULL,
                difficulty VARCHAR(100) DEFAULT NULL,
                scenario VARCHAR(255) DEFAULT NULL,
                num_participants INT DEFAULT NULL,
                played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                duration INT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS cooperative_result_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                result_id INT NOT NULL,
                participant_type ENUM('member','team') NOT NULL,
                member_id INT DEFAULT NULL,
                team_id INT DEFAULT NULL,
                FOREIGN KEY (result_id) REFERENCES cooperative_game_results(result_id) ON DELETE CASCADE,
                FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
                FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e2) {}
    }
}

/**
 * Verify logged in admin password for sensitive operations (e.g. bulk delete)
 *
 * @param string $password
 * @param PDO $pdo
 * @return bool
 */
function verify_admin_password($password, $pdo) {
    if (empty($password) || !$pdo) {
        return false;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $hash = $stmt->fetchColumn();
        return $hash && password_verify($password, $hash);
    } catch (Throwable $e) {
        return false;
    }
}

