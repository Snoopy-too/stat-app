<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

// Fetch all games with their play count
$stmt = $pdo->prepare("
    SELECT g.*, COUNT(gh.history_id) as play_count
    FROM games g
    LEFT JOIN game_history gh ON g.game_id = gh.game_id
    WHERE g.club_id = ?
    GROUP BY g.game_id
    ORDER BY g.game_name
");
$stmt->execute([$_SESSION['club_id']]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Games Collection</h1>
        <h2><?php echo htmlspecialchars($_SESSION['club_name']); ?></h2>
    </div>

    <div class="container">
        <div class="games-grid">
            <?php foreach ($games as $game): ?>
            <div class="game-card">
                <div class="game-name"><?php echo htmlspecialchars($game['game_name']); ?></div>
                <div class="game-details">
                    <strong>Min Players:</strong> <?php echo $game['min_players']; ?><br>
                    <strong>Max Players:</strong> <?php echo $game['max_players']; ?><br>
                    <strong>Play Time:</strong> <?php echo $game['play_time']; ?> minutes
                </div>
                <div class="game-stats">
                    Played <?php echo $game['play_count']; ?> times
                </div>
                <div>
                    <a href="edit_game.php?id=<?php echo $game['game_id']; ?>" class="btn btn--secondary">Edit</a>
                    <a href="delete_game.php?id=<?php echo $game['game_id']; ?>" 
                       class="btn btn--danger"
                       onclick="return confirm('Are you sure you want to delete this game?')">Delete</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="add_game.php" class="btn">Add New Game</a>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>