<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

// Get club ID or Slug from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Fetch club and game details
$club = null;
$games = [];
$error = '';

if ($club_id > 0 || !empty($slug)) {
    // First fetch club details to ensure it exists
    $sql = "SELECT club_id, club_name, slug FROM clubs WHERE ";
    $params = [];
    
    if ($club_id > 0) {
        $sql .= "club_id = ?";
        $params[] = $club_id;
    } else {
        $sql .= "slug = ?";
        $params[] = $slug;
    }
    
    $club_stmt = $pdo->prepare($sql);
    $club_stmt->execute($params);
    $club = $club_stmt->fetch(PDO::FETCH_ASSOC);

    if ($club) {
        $club_id = $club['club_id']; // Ensure club_id is set
        
        // Fetch all games associated with this club
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
    <?php
    // Render breadcrumbs
    if ($club) {
        $club_url = !empty($club['slug']) ? $club['slug'] : 'club_stats.php?id=' . $club_id;
        NavigationHelper::renderBreadcrumbs([
            ['label' => 'Home', 'url' => 'index.php'],
            ['label' => $club['club_name'], 'url' => $club_url],
            'Games'
        ]);
    }
    ?>
    
    <div class="header">
        <?php NavigationHelper::renderHeaderTitle('Board Game Club StatApp', 'Club Games', 'index.php'); ?>
        <div class="header-actions">
            <?php 
            $back_url = !empty($club['slug']) ? $club['slug'] : 'club_stats.php?id=' . $club_id;
            ?>
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn--secondary btn--small">← Back to Club Stats</a>
            <a href="index.php" class="btn btn--ghost btn--small">🏠 Home</a>
        </div>
    </div>
    
    <?php
    // Render navigation
    if ($club) {
        NavigationHelper::renderMobileCardNav('games', $club_id);
        NavigationHelper::renderPublicNav('games', $club_id);
    }
    ?>
    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($club): ?>
            <h2><?php echo htmlspecialchars($club['club_name']); ?>'s Games</h2>
            <?php if (count($games) > 0): ?>
                <div class="games-grid">
                    <?php foreach ($games as $game): ?>
                        <div class="game-card">
                            <a href="game_details.php?id=<?php echo $game['game_id']; ?>" class="game-link-wrapper">
                                <div class="game-card__image-container">
                                    <?php if ($game['game_image']): ?>
                                        <img src="images/game_images/<?php echo htmlspecialchars($game['game_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($game['game_name']); ?>" 
                                             class="game-card__image">
                                    <?php else: ?>
                                        <div class="game-card__image-placeholder">
                                            <span><?php echo mb_substr($game['game_name'], 0, 1); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="game-card__content">
                                    <div class="game-name"><?php echo htmlspecialchars($game['game_name']); ?></div>
                                    <div class="game-meta">
                                        <span class="game-players"><?php echo $game['min_players'] . '-' . $game['max_players']; ?> Players</span>
                                    </div>
                                </div>
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
