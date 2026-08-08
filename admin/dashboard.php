<?php
// Deprecated endpoint - redirect to account.php
session_start();
header("Location: account.php", true, 302);
exit();
