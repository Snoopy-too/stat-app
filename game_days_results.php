<?php
session_start();
require_once 'config/database.php';

// Get date and club ID from URL parameters
$selected_date = isset($_GET['date']) ? $_GET['date'] : null;
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$selected_date || !$club_id) {
    die('Date or Club ID not specified.');
}

// Format the date for display
$display_date = date('F j, Y', strtotime($selected_date));

// Fetch individual game results for the selected date and club
$individual_results = [];
try {
    $stmt = $pdo->prepare("
        SELECT gr.result_id, gr.played_at, g.game_name as game_name, m.nickname as winner_nickname, gr.num_players, gr.notes, g.game_id, g.game_image
        FROM game_results gr
        JOIN games g ON gr.game_id = g.game_id
        JOIN members m ON gr.winner = m.member_id
        WHERE g.club_id = ? AND DATE(gr.played_at) = ?
        ORDER BY gr.played_at DESC
    ");
    $stmt->execute([$club_id, $selected_date]);
    $individual_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_individual = 'Error fetching individual game results: ' . $e->getMessage();
}

// Fetch team game results for the selected date and club
$team_results = [];
try {
    $stmt = $pdo->prepare("
        SELECT tgr.result_id, tgr.played_at, g.game_name as game_name, t_winner.team_name as winner_team_name, tgr.notes, g.game_id, g.game_image
        FROM team_game_results tgr
        JOIN games g ON tgr.game_id = g.game_id
        JOIN teams t_winner ON tgr.winner = t_winner.team_id
        WHERE g.club_id = ? AND DATE(tgr.played_at) = ?
        ORDER BY tgr.played_at DESC
    ");
    $stmt->execute([$club_id, $selected_date]);
    $team_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_team = 'Error fetching team game results: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Results for <?php echo htmlspecialchars($display_date); ?> - Board Game StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
    <style>
        .game-thumbnail {
            width: 48px !important;
            height: 48px !important;
            min-width: 48px !important;
            flex-shrink: 0;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-border);
            display: block;
            background: var(--color-surface-muted);
            overflow: hidden;
            position: relative;
            max-width: none !important;
        }
        .col-image {
            width: 48px;
            padding-right: 0 !important;
        }
        .game-thumbnail--skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, 
                rgba(17, 24, 39, 0.03) 25%, 
                rgba(17, 24, 39, 0.06) 37%, 
                rgba(17, 24, 39, 0.03) 63%);
            background-size: 400% 100%;
            animation: skeleton-loading 2s ease infinite;
        }
        @keyframes skeleton-loading {
            0% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php
    require_once 'includes/NavigationHelper.php';

    // Fetch club name for sidebar
    $club_name = '';
    $club_stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
    $club_stmt->execute([$club_id]);
    $club_row = $club_stmt->fetch(PDO::FETCH_ASSOC);
    if ($club_row) {
        $club_name = $club_row['club_name'];
    }

    // Render sidebar navigation
    NavigationHelper::renderSidebar('game_days', $club_id, $club_name);
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Game Results - ' . $display_date); ?>
    </div>
    <div class="container">
        <h2>Game Results for <?php echo htmlspecialchars($display_date); ?></h2>

        <?php if (!empty($error_individual)): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error_individual); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_team)): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error_team); ?></div>
        <?php endif; ?>

        <?php if (empty($individual_results) && empty($team_results) && empty($error_individual) && empty($error_team)): ?>
            <div class="card">
                <p>No game results found for this date.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($individual_results)): ?>
            <div class="card">
                <h3>Individual Games</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-image"></th>
                            <th>Game</th>
                            <th>Winner</th>
                            <th>Players</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($individual_results as $result): ?>
                        <tr>
                            <td class="col-image">
                                <?php if ($result['game_image']): ?>
                                    <img src="images/game_images/<?php echo htmlspecialchars($result['game_image']); ?>" alt="" class="game-thumbnail" loading="lazy">
                                <?php else: ?>
                                    <div class="game-thumbnail game-thumbnail--skeleton" title="No image uploaded"></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Game">
                                <a href="game_play_details.php?result_id=<?php echo urlencode($result['result_id']); ?>" class="game-link game-link--button">
                                    <?php echo htmlspecialchars($result['game_name']); ?>
                                </a>
                            </td>
                            <td data-label="Winner"><span class="position-badge position-1"><?php echo htmlspecialchars($result['winner_nickname']); ?></span></td>
                            <td data-label="Players"><?php echo htmlspecialchars($result['num_players']); ?></td>
                            <td data-label="Notes"><?php echo htmlspecialchars($result['notes'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($team_results)): ?>
            <div class="card">
                <h3>Team Games</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-image"></th>
                            <th>Game</th>
                            <th>Winning Team</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_results as $result): ?>
                        <tr>
                            <td class="col-image">
                                <?php if ($result['game_image']): ?>
                                    <img src="images/game_images/<?php echo htmlspecialchars($result['game_image']); ?>" alt="" class="game-thumbnail" loading="lazy">
                                <?php else: ?>
                                    <div class="game-thumbnail game-thumbnail--skeleton" title="No image uploaded"></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Game">
                                <a href="team_game_play_details.php?result_id=<?php echo urlencode($result['result_id']); ?>" class="game-link game-link--button">
                                    <?php echo htmlspecialchars($result['game_name']); ?>
                                </a>
                            </td>
                            <td data-label="Winning Team"><span class="position-badge position-1"><?php echo htmlspecialchars($result['winner_team_name']); ?></span></td>
                            <td data-label="Notes"><?php echo htmlspecialchars($result['notes'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
    <script src="js/sidebar.js"></script>
    <script src="js/empty-states.js"></script>
</body>
</html>
