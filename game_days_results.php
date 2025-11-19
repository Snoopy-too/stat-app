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
        SELECT gr.result_id, gr.played_at, g.game_name as game_name, m.nickname as winner_nickname, gr.num_players, gr.notes, g.game_id
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
        SELECT tgr.result_id, tgr.played_at, g.game_name as game_name, t_winner.team_name as winner_team_name, tgr.notes, g.game_id
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
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Game Results for <?php echo htmlspecialchars($display_date); ?></p>
        </div>
        <a href="game_days.php?id=<?php echo htmlspecialchars($club_id); ?>" class="btn btn--secondary">Back to Game Days</a>
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
                            <th>Game</th>
                            <th>Winner</th>
                            <th>Players</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($individual_results as $result): ?>
                        <tr>
                            <td>
                                <a href="game_play_details.php?result_id=<?php echo urlencode($result['result_id']); ?>" class="game-link game-link--button">
                                    <?php echo htmlspecialchars($result['game_name']); ?>
                                </a>
                            </td>
                            <td><span class="position-badge position-1"><?php echo htmlspecialchars($result['winner_nickname']); ?></span></td>
                            <td><?php echo htmlspecialchars($result['num_players']); ?></td>
                            <td><?php echo htmlspecialchars($result['notes'] ?? 'N/A'); ?></td>
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
                            <th>Game</th>
                            <th>Winning Team</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_results as $result): ?>
                        <tr>
                            <td>
                                <a href="team_game_play_details.php?result_id=<?php echo urlencode($result['result_id']); ?>" class="game-link game-link--button">
                                    <?php echo htmlspecialchars($result['game_name']); ?>
                                </a>
                            </td>
                            <td><span class="position-badge position-1"><?php echo htmlspecialchars($result['winner_team_name']); ?></span></td>
                            <td><?php echo htmlspecialchars($result['notes'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
</html>
