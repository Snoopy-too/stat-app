<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get member details
$stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ? AND club_id = ?");
$stmt->execute([$member_id, $_SESSION['club_id']]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header("Location: members.php");
    exit();
}

// Get member's game statistics
$stmt = $pdo->prepare("
    SELECT g.game_name,
           COUNT(*) as total_plays,
           SUM(CASE WHEN gr.placement = 1 THEN 1 ELSE 0 END) as wins,
           AVG(gr.placement) as avg_placement
    FROM game_results gr
    JOIN game_history gh ON gr.history_id = gh.history_id
    JOIN games g ON gh.game_id = g.game_id
    WHERE gr.member_id = ?
    GROUP BY g.game_id
    ORDER BY wins DESC, total_plays DESC
");
$stmt->execute([$member_id]);
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Stats - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Player Statistics</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($member['full_name']); ?></p>
        </div>
        <a href="members.php" class="btn btn--secondary">Back to Members</a>
    </div>

    <div class="container container--narrow">
        <div class="stats-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Total Plays</th>
                        <th>Wins</th>
                        <th>Win Rate</th>
                        <th>Avg Placement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $game): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($game['game_name']); ?></td>
                        <td><?php echo $game['total_plays']; ?></td>
                        <td><?php echo $game['wins']; ?></td>
                        <td><?php echo round(($game['wins'] / $game['total_plays']) * 100, 1); ?>%</td>
                        <td><?php echo round($game['avg_placement'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats)): ?>
                    <tr>
                        <td colspan="5">No recorded plays yet for this member.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
