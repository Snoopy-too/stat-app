<?php
session_start();
require_once 'config/database.php';

// Get club ID from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch club and game details
$club = null;
$games = [];
$error = '';

if ($club_id > 0) {
    // First fetch club details to ensure it exists
    $club_stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
    $club_stmt->execute([$club_id]);
    $club = $club_stmt->fetch(PDO::FETCH_ASSOC);

    if ($club) {
        // Fetch all games associated with this club
        // Modify the games query to remove the play count subquery
        $games_stmt = $pdo->prepare("SELECT g.* 
            FROM games g 
            WHERE g.club_id = ? 
            ORDER BY g.game_name");
        $games_stmt->execute([$club_id]);
        $games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = 'Club not found';
    }
} else {
    $error = 'Invalid club ID';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Games - Board Game StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Club Games</p>
        </div>
        <a href="club_stats.php?id=<?php echo $club_id; ?>" class="btn btn--secondary">Back to Club Stats</a>
    </div>
    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($club): ?>
            <h2><?php echo htmlspecialchars($club['club_name']); ?>'s Games</h2>
            <?php if (count($games) > 0): ?>
                <div class="games-grid">
                    <?php foreach ($games as $game): ?>
                        <div class="game-card">
                            <a href="game_details.php?id=<?php echo $game['game_id']; ?>" class="game-link">
                                <div class="game-name"><?php echo htmlspecialchars($game['game_name']); ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-games">
                    <p>No games have been added to this club yet.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script src="js/mobile-menu.js"></script>
    <script src="js/form-loading.js"></script>
    <script src="js/confirmations.js"></script>
    <script src="js/form-validation.js"></script>
    <script src="js/empty-states.js"></script>
    <script src="js/multi-step-form.js"></script>
    <script src="js/breadcrumbs.js"></script>
</body>
</html>
