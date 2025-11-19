<?php
session_start();
require_once 'config/database.php';

// Get club ID from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch unique dates from both tables
date_default_timezone_set('UTC');
$dates = [];
try {
    $stmt = $pdo->prepare("
 SELECT DISTINCT DATE(gr.played_at) as play_date
 FROM game_results gr
 JOIN games g ON gr.game_id = g.game_id
 WHERE g.club_id = ?
 ORDER BY gr.played_at DESC
");
$stmt->execute([$club_id]);
$dates1 = $stmt->fetchAll(PDO::FETCH_COLUMN);
$stmt = $pdo->prepare("
 SELECT DISTINCT DATE(tgr.played_at) as play_date
 FROM team_game_results tgr
 JOIN games g ON tgr.game_id = g.game_id
 WHERE g.club_id = ?
 ORDER BY tgr.played_at DESC
");
$stmt->execute([$club_id]);
$dates2 = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $dates = array_unique(array_merge($dates1, $dates2));
    rsort($dates);
} catch (Exception $e) {
    $error = 'Error fetching game days: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Days - Board Game StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Game Days</p>
        </div>
        <a href="club_stats.php?id=<?php echo htmlspecialchars($club_id); ?>" class="btn btn--secondary">Back to Club Stats</a>
    </div>
    <div class="container">
        <h2>Game Days</h2>
        <?php if (!empty($error)): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (count($dates) > 0): ?>
            <div class="games-grid">
                <?php foreach ($dates as $date): ?>
                    <a href="game_days_results.php?date=<?php echo urlencode($date); ?>&id=<?php echo htmlspecialchars($club_id); ?>" class="game-card game-link">
                        <?php echo htmlspecialchars(date('F j, Y', strtotime($date))); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <p>No game days found.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
