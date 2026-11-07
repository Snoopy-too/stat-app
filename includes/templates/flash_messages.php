<?php
/**
 * Flash Messages Partial Template
 * Renders success, error, info, and warning messages stored in session.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flashTypes = [
    'flash_success' => ['class' => 'message--success', 'role' => 'status'],
    'flash_error'   => ['class' => 'message--error',   'role' => 'alert'],
    'flash_warning' => ['class' => 'message--warning', 'role' => 'status'],
    'flash_info'    => ['class' => 'message--info',    'role' => 'status'],
    'success'       => ['class' => 'message--success', 'role' => 'status'],
    'error'         => ['class' => 'message--error',   'role' => 'alert']
];

foreach ($flashTypes as $sessionKey => $config) {
    if (!empty($_SESSION[$sessionKey])) {
        $message = $_SESSION[$sessionKey];
        unset($_SESSION[$sessionKey]);
        ?>
        <div class="message <?php echo $config['class']; ?>" role="<?php echo $config['role']; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
    }
}
?>
