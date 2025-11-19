<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

// Fetch game history with related information
$stmt = $pdo->prepare("
    SELECT gh.history_id, gh.play_date, g.game_name,
           GROUP_CONCAT(
               CONCAT(m.full_name, ' (', gr.placement, ')')
               ORDER BY gr.placement ASC
               SEPARATOR ', '
           ) as results
    FROM game_history gh
    JOIN games g ON gh.game_id = g.game_id
    JOIN game_results gr ON gh.history_id = gr.history_id
    JOIN members m ON gr.member_id = m.member_id
    WHERE gh.club_id = ?
    GROUP BY gh.history_id
    ORDER BY gh.play_date DESC
");
$stmt->execute([$_SESSION['club_id']]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game History - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Game History</h1>
        <h2><?php echo htmlspecialchars($_SESSION['club_name']); ?></h2>
    </div>
    <div class="container">
        <div class="history-list">
            <?php foreach ($history as $game): ?>
            <div class="history-item">
                <a href="edit_history.php?id=<?php echo $game['history_id']; ?>" class="btn btn--secondary">Edit</a>
                <div class="game-date"><?php echo date('F j, Y', strtotime($game['play_date'])); ?></div>
                <div class="game-name"><?php echo htmlspecialchars($game['game_name']); ?></div>
                <div class="game-results">Results: <?php echo htmlspecialchars($game['results']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="add_history.php" class="btn">Add New Game Session</a>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>