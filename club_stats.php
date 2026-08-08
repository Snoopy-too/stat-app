<?php
declare(strict_types=1);

// club_stats.php is deprecated. Redirect all incoming traffic to club_game_results.php preserving query string parameters.
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$location = 'club_game_results.php' . ($queryString ? '?' . $queryString : '');

header("Location: " . $location, true, 301);
exit();
