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
<body>
    <?php
    // Render breadcrumbs
    if ($game) {
        NavigationHelper::renderBreadcrumbs([
            ['label' => 'Home', 'url' => 'index.php'],
            ['label' => $game['club_name'], 'url' => 'club_stats.php?id=' . $club_id],
            ['label' => 'Games', 'url' => 'club_game_list.php?id=' . $club_id],
            $game['game_name']
        ]);
    }
    ?>
    
    <div class="header">
        <?php NavigationHelper::renderHeaderTitle('Board Game Club StatApp', $game ? $game['game_name'] : 'Game Details', 'index.php'); ?>
        <div class="header-actions">
            <a href="club_game_list.php?id=<?php echo $club_id; ?>" class="btn btn--secondary btn--small">← Games List</a>
            <a href="club_stats.php?id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small">Club Stats</a>
            <a href="index.php" class="btn btn--ghost btn--small">🏠 Home</a>
        </div>
    </div>
    
    <?php
    // Render navigation and context bar
    if ($game) {
        NavigationHelper::renderMobileCardNav('games', $club_id);
        NavigationHelper::renderPublicNav('games', $club_id);
        NavigationHelper::renderContextBar('Game', $game['game_name'], 'View all games', 'club_game_list.php?id=' . $club_id);
    }
    ?>

    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($game): ?>
            <div class="card">
                <h2><?php echo htmlspecialchars($game['game_name']); ?></h2>
                <p><strong>Club:</strong> <?php echo htmlspecialchars($game['club_name']); ?></p>

                <?php if (count($results) > 0): ?>
                    <table class="results-table">
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
                    <div class="no-results">
                        <p>No results have been recorded for this game yet.</p>
                    </div>
                <?php endif; ?>
            </div>
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
</body>
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
</html>
