<?php
declare(strict_types=1);
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';
require_once 'includes/services/ClubService.php';
require_once 'includes/services/GameService.php';

// Get club ID or Slug from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Get sorting option from URL
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'alphabetical';
$allowed_sorts = ['alphabetical', 'most_played', 'recently_played'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'alphabetical';
}

$clubService = new ClubService($pdo);
$gameService = new GameService($pdo);

$club = $clubService->getClubDetails($club_id, $slug);
$games = [];
$error = '';

if ($club) {
    $club_id = (int)$club['club_id'];
    $games = $gameService->getGamesByClub($club_id, $sort === 'most_played' ? 'plays' : ($sort === 'recently_played' ? 'recent' : 'name'));
} else {
    $error = "Club not found.";
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
<body class="has-sidebar">
    <?php
    // Render sidebar navigation
    if ($club) {
        NavigationHelper::renderSidebar('games', $club_id, $club['club_name']);
    } else {
        NavigationHelper::renderSidebar('games');
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Games', $club ? $club['club_name'] : ''); ?>
    </div>
    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($club): ?>
            <div class="games-header-row">
                <h2><?php echo htmlspecialchars($club['club_name']); ?>'s Games</h2>
                <?php if (count($games) > 0): ?>
                    <div class="games-list-toolbar">
                        <form method="GET" action="" class="sort-form">
                            <?php if ($club_id > 0): ?>
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$club_id); ?>">
                            <?php endif; ?>
                            <?php if (!empty($slug)): ?>
                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                            <?php endif; ?>
                            
                            <div class="sort-group">
                                <label for="sort_by" class="sort-label">Sort by:</label>
                                <select name="sort" id="sort_by" class="form-control sort-select" onchange="this.form.submit()">
                                    <option value="alphabetical" <?php echo $sort === 'alphabetical' ? 'selected' : ''; ?>>Alphabetical</option>
                                    <option value="most_played" <?php echo $sort === 'most_played' ? 'selected' : ''; ?>>Most Played</option>
                                    <option value="recently_played" <?php echo $sort === 'recently_played' ? 'selected' : ''; ?>>Most Recently Played</option>
                                </select>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (count($games) > 0): ?>
                <div class="games-grid">
                    <?php foreach ($games as $game): ?>
                        <div class="game-card">
                            <a href="game_details.php?id=<?php echo (int)$game['game_id']; ?>" class="game-link-wrapper">
                                <div class="game-card__image-container">
                                    <?php if ($game['game_image']): ?>
                                        <img src="<?php echo htmlspecialchars(get_game_image_url($game['game_image'])); ?>" 
                                             alt="<?php echo htmlspecialchars($game['game_name']); ?>" 
                                             class="game-card__image" loading="lazy">
                                    <?php else: ?>
                                        <div class="game-card__image-placeholder"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="game-card__content">
                                    <div class="game-name"><?php echo htmlspecialchars($game['game_name']); ?></div>
                                    <div class="game-meta">
                                        <span class="game-players"><?php echo htmlspecialchars((string)$game['min_players']) . '-' . htmlspecialchars((string)$game['max_players']); ?> Players</span>
                                    </div>
                                    <div class="game-card__stats">
                                        <div class="game-card__stat-row">
                                            <span class="game-card__stat-label">Plays</span>
                                            <span class="game-card__stat-value"><?php echo (int)$game['plays']; ?></span>
                                        </div>
                                        <div class="game-card__stat-row">
                                            <span class="game-card__stat-label">Last Played</span>
                                            <span class="game-card__stat-value">
                                                <?php echo $game['last_played'] ? htmlspecialchars(date('M j, Y', strtotime($game['last_played']))) : 'Never'; ?>
                                            </span>
                                        </div>
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
    <script src="js/sidebar.js"></script>
    <script src="js/form-loading.js"></script>
    <script src="js/empty-states.js"></script>
</body>
</html>
