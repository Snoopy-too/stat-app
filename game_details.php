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
    <style>
        .game-hero {
            display: flex;
            gap: var(--spacing-8);
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: var(--spacing-8);
            margin-bottom: var(--spacing-8);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-md);
            align-items: center;
        }
        .game-hero__image-container {
            flex-shrink: 0;
            width: 300px;
            height: 300px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 4px solid var(--color-background);
            background: var(--color-surface-muted);
        }
        .game-hero__image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .game-hero__image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-sm);
            color: var(--color-text-soft);
            background: linear-gradient(135deg, var(--color-surface-muted), var(--color-border));
        }
        .game-hero__content {
            flex-grow: 1;
        }
        .game-hero__title {
            font-size: var(--font-size-4xl);
            margin-bottom: var(--spacing-4);
            color: var(--color-heading);
        }
        .game-hero__stats {
            display: flex;
            gap: var(--spacing-8);
            flex-wrap: wrap;
        }
        .game-stat {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-1);
        }
        .game-stat__label {
            font-size: var(--font-size-xs);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--color-text-muted);
            font-weight: var(--font-weight-bold);
        }
        .game-stat__value {
            font-size: var(--font-size-2xl);
            color: var(--color-heading);
            font-weight: var(--font-weight-bold);
        }
        
        @media (max-width: 48rem) {
            .game-hero {
                flex-direction: column;
                padding: var(--spacing-6);
                text-align: center;
                gap: var(--spacing-6);
            }
            .game-hero__image-container {
                width: 200px;
                height: 200px;
            }
            .game-hero__stats {
                justify-content: center;
                gap: var(--spacing-4);
            }
            .game-hero__title {
                font-size: var(--font-size-2xl);
            }
        }
    </style>
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
                <div class="section-header" style="margin-bottom: var(--spacing-6); border-bottom: 2px solid var(--color-border); padding-bottom: var(--spacing-2);">
                    <h2 style="margin: 0;">Match History</h2>
                </div>

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
                    <div class="no-results" style="text-align: center; padding: var(--spacing-12); background: var(--color-surface-muted); border-radius: var(--radius-lg); border: 2px dashed var(--color-border);">
                        <p style="color: var(--color-text-soft);">No results have been recorded for this game yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="js/sidebar.js"></script>
    <script src="js/empty-states.js"></script>
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
