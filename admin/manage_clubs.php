<?php
// Deprecated endpoint - club management integrated into account.php
session_start();
header("Location: account.php", true, 302);
exit();
