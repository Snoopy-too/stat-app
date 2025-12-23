<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

// Get game ID from URL parameter
$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch game and results details
$game = null;
$results = [];
$error = '';
$club_id = null;

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'played_at';
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

$allowed_sorts = ['nickname', 'game_type', 'played_at'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'played_at';
}

if ($game_id > 0) {
    // First fetch game details to ensure it exists
    $game_stmt = $pdo->prepare("SELECT g.*, c.club_name, c.club_id 
        FROM games g 
        JOIN clubs c ON g.club_id = c.club_id 
        WHERE g.game_id = ?");
    $game_stmt->execute([$game_id]);
    $game = $game_stmt->fetch(PDO::FETCH_ASSOC);

    if ($game) {
        $club_id = $game['club_id'];
        // Fetch both individual and team results for this game
        $results_stmt = $pdo->prepare("
            (SELECT 
                gr.result_id,
                m.nickname,
                gr.position,
                gr.played_at,
                'individual' as game_type
            FROM game_results gr 
            JOIN members m ON gr.member_id = m.member_id 
            WHERE gr.game_id = ?)
            UNION ALL
            (SELECT 
                tgr.result_id,
                t.team_name as nickname,
                tgr.position,
                tgr.played_at,
                'team' as game_type
            FROM team_game_results tgr
            JOIN teams t ON tgr.team_id = t.team_id
            WHERE tgr.game_id = ?)
            ORDER BY $sort $order");
        $results_stmt->execute([$game_id, $game_id]);
        $results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = 'Game not found';
    }
} else {
    $error = 'Invalid game ID';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $game ? htmlspecialchars($game['game_name']) : 'Game'; ?> Details - Board Game StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php
    // Render sidebar navigation
    if ($game) {
        NavigationHelper::renderSidebar('games', $club_id, $game['club_name']);
    } else {
        NavigationHelper::renderSidebar('games');
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader($game ? $game['game_name'] : 'Game Details'); ?>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($game): ?>
            <div class="game-hero">
                <div class="game-hero__image-container">
                    <?php if ($game['game_image']): ?>
                        <img src="images/game_images/<?php echo htmlspecialchars($game['game_image']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>" class="game-hero__image">
                    <?php else: ?>
                        <div class="game-hero__image-placeholder">No Image Uploaded</div>
                    <?php endif; ?>
                </div>
                <div class="game-hero__content">
                    <h1 class="game-hero__title"><?php echo htmlspecialchars($game['game_name']); ?></h1>
                    <div class="game-hero__stats">
                        <div class="game-stat">
                            <span class="game-stat__label">Recommended Players</span>
                            <span class="game-stat__value"><?php echo $game['min_players'] . '-' . $game['max_players']; ?></span>
                        </div>
                        <div class="game-stat">
                            <span class="game-stat__label">Total Recorded Matches</span>
                            <span class="game-stat__value"><?php echo count($results); ?></span>
                        </div>
                        <div class="game-stat">
                            <span class="game-stat__label">Club</span>
                            <span class="game-stat__value"><?php echo htmlspecialchars($game['club_name']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Match History</h2>
                </div>

                <?php if (count($results) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><a href="?id=<?php echo $game_id; ?>&sort=nickname&order=<?php echo ($sort === 'nickname' && $order === 'DESC') ? 'ASC' : 'DESC'; ?>" class="sort-link" onclick="saveScroll()">Winner <?php if ($sort === 'nickname') echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                                <th><a href="?id=<?php echo $game_id; ?>&sort=game_type&order=<?php echo ($sort === 'game_type' && $order === 'DESC') ? 'ASC' : 'DESC'; ?>" class="sort-link" onclick="saveScroll()">Type <?php if ($sort === 'game_type') echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                                <th><a href="?id=<?php echo $game_id; ?>&sort=played_at&order=<?php echo ($sort === 'played_at' && $order === 'DESC') ? 'ASC' : 'DESC'; ?>" class="sort-link" onclick="saveScroll()">Date Played <?php if ($sort === 'played_at') echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr onclick="window.location='<?php echo $result['game_type'] === 'team' ? 'team_game_play_details.php' : 'game_play_details.php'; ?>?result_id=<?php echo $result['result_id']; ?>'" class="table-row--link">
                                    <td>
                                        <?php $position = (int) $result['position']; ?>
                                        <span class="position-badge position-<?php echo ($position >= 1 && $position <= 8) ? $position : 1; ?>">
                                            <?php echo htmlspecialchars($result['nickname']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($result['game_type']); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($result['played_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state empty-state--no-results">
                        <div class="empty-state__icon"></div>
                        <p class="empty-state__description">No results have been recorded for this game yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/form-loading.js"></script>
    <script src="js/confirmations.js"></script>
    <script src="js/form-validation.js"></script>
    <script src="js/empty-states.js"></script>
    <script>
    function saveScroll() {
        sessionStorage.setItem('scrollPos', window.scrollY);
    }
    window.addEventListener('DOMContentLoaded', function() {
        var scrollPos = sessionStorage.getItem('scrollPos');
        if (scrollPos !== null) {
            window.scrollTo(0, parseInt(scrollPos));
            sessionStorage.removeItem('scrollPos');
        }
    });
    </script>
</body>
</html>
