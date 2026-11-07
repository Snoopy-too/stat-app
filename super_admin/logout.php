<?php
session_start();
// Unset all session variables
$_SESSION = array();
if (isset($_COOKIE['tfd_stat_session'])) {
    setcookie('tfd_stat_session', '', time() - 3600, '/');
}
// Destroy the session
session_destroy();
// Redirect to login page
header("Location: login.php");
exit();