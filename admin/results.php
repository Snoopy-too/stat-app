<?php
declare(strict_types=1);
$queryString = $_SERVER['QUERY_STRING'] ?? '';
header("Location: manage_results.php" . ($queryString ? '?' . $queryString : ''), true, 301);
exit();
