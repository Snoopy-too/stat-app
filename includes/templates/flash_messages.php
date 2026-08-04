<?php
/**
 * Flash Messages Partial Template
 * Renders success, error, info, and warning messages stored in session.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flashTypes = [
    'flash_success' => 'message--success',
    'flash_error'   => 'message--error',
    'flash_warning' => 'message--warning',
    'flash_info'    => 'message--info',
    'success'       => 'message--success',
    'error'         => 'message--error'
];

foreach ($flashTypes as $sessionKey => $cssClass) {
    if (!empty($_SESSION[$sessionKey])) {
        $message = $_SESSION[$sessionKey];
        unset($_SESSION[$sessionKey]);
        ?>
        <div class="message <?php echo $cssClass; ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
    }
}
?>
