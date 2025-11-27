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
