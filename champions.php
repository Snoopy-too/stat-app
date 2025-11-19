<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

// Fetch champions for each game
$stmt = $pdo->prepare("
    SELECT c.champion_id, c.game_id, c.member_id, c.start_date, c.end_date,
           g.game_name, m.full_name
    FROM champions c
    JOIN games g ON c.game_id = g.game_id
    JOIN members m ON c.member_id = m.member_id
    WHERE c.club_id = ?
    ORDER BY g.game_name, c.start_date DESC
");
$stmt->execute([$_SESSION['club_id']]);
$champions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group champions by game
$championsByGame = [];
foreach ($champions as $champion) {
    $championsByGame[$champion['game_name']][] = $champion;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Champions - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Champions</h1>
        <h2><?php echo htmlspecialchars($_SESSION['club_name']); ?></h2>
    </div>

    <div class="container">
        <div class="champions-list">
            <?php foreach ($championsByGame as $gameName => $gameChampions): ?>
            <div class="game-section">
                <div class="game-title"><?php echo htmlspecialchars($gameName); ?></div>
                <?php foreach ($gameChampions as $champion): ?>
                <div class="champion-item">
                    <div>
                        <div class="champion-name"><?php echo htmlspecialchars($champion['full_name']); ?></div>
                        <div class="champion-period">
                            <?php 
                            echo date('M j, Y', strtotime($champion['start_date']));
                            echo ' - ';
                            echo $champion['end_date'] ? date('M j, Y', strtotime($champion['end_date'])) : 'Present';
                            ?>
                        </div>
                    </div>
                    <a href="edit_champion.php?id=<?php echo $champion['champion_id']; ?>" 
                       class="btn btn--secondary">Edit</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="add_champion.php" class="btn">Add New Champion</a>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>