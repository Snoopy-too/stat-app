<?php
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get member details
$stmt = $pdo->prepare("SELECT m.nickname, m.club_id, c.club_name FROM members m JOIN clubs c ON m.club_id = c.club_id WHERE m.member_id = ? AND m.status = 'active'");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header("Location: index.php");
    exit();
}

$club_id = $member['club_id'];

// Calculate Average Finish and get game history for the member (both individual and team games)
$stmt = $pdo->prepare("SELECT DISTINCT gr.played_at as game_date, g.game_name, CASE WHEN gr.winner = ? THEN 1 WHEN gr.place_2 = ? THEN 2 WHEN gr.place_3 = ? THEN 3 WHEN gr.place_4 = ? THEN 4 WHEN gr.place_5 = ? THEN 5 WHEN gr.place_6 = ? THEN 6 WHEN gr.place_7 = ? THEN 7 WHEN gr.place_8 = ? THEN 8 END as position, gr.num_players, gr.game_id, 'individual' as game_type FROM game_results gr JOIN games g ON gr.game_id = g.game_id WHERE gr.winner = ? OR gr.place_2 = ? OR gr.place_3 = ? OR gr.place_4 = ? OR gr.place_5 = ? OR gr.place_6 = ? OR gr.place_7 = ? OR gr.place_8 = ? UNION ALL SELECT DISTINCT tgr.played_at as game_date, g.game_name, CASE WHEN tgr.winner = t.team_id THEN 1 WHEN tgr.place_2 = t.team_id THEN 2 WHEN tgr.place_3 = t.team_id THEN 3 WHEN tgr.place_4 = t.team_id THEN 4 END as position, tgr.num_teams as num_players, tgr.game_id, 'team' as game_type FROM teams t JOIN team_game_results tgr ON (t.team_id = tgr.winner OR t.team_id = tgr.place_2 OR t.team_id = tgr.place_3 OR t.team_id = tgr.place_4) JOIN games g ON tgr.game_id = g.game_id WHERE (t.member1_id = ? OR t.member2_id = ? OR t.member3_id = ? OR t.member4_id = ?) ORDER BY game_date DESC, position ASC");

$params = array_fill(0, 16, $member_id); // For individual games
$params = array_merge($params, array_fill(0, 4, $member_id)); // For team games
$stmt->execute($params);
$game_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Average Finish
$total_points = 0;
$total_games = count($game_history);
foreach ($game_history as $game) {
    $total_points += $game['position'];
}
$average_finish = $total_games > 0 ? number_format($total_points / $total_games, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game History - <?php echo htmlspecialchars($member['nickname']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
        
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <?php
    // Render breadcrumbs
    NavigationHelper::renderBreadcrumbs([
        ['label' => 'Home', 'url' => 'index.php'],
        ['label' => $member['club_name'], 'url' => 'club_stats.php?id=' . $club_id],
        $member['nickname'] . "'s History"
    ]);
    ?>
    
    <div class="header">
        <?php NavigationHelper::renderHeaderTitle('Board Game Club StatApp', $member['nickname'] . "'s Game History", 'index.php'); ?>
        <div class="header-actions">
            <a href="club_stats.php?id=<?php echo $club_id; ?>" class="btn btn--secondary btn--small">← Back to Club Stats</a>
            <a href="index.php" class="btn btn--ghost btn--small">🏠 Home</a>
        </div>
    </div>
    
    <?php
    NavigationHelper::renderPublicNav('', $club_id);
    NavigationHelper::renderContextBar('Member History', $member['nickname'], 'View all members', 'club_stats.php?id=' . $club_id);
    ?>

    <div class="history-container">
        
        <h1 class="member-name"><?php echo htmlspecialchars($member['nickname']); ?>'s Game History</h1>
        
        <div class="stats-summary">
            <p><strong>Average Finish:</strong> <?php echo $average_finish; ?></p>
        </div>
        
        <?php if (count($game_history) > 0): ?>
        <table class="game-history">
            <thead>
                <tr>
                    <th>Date Played</th>
                    <th>Game</th>
                    <th>Place</th>
                    <th>Total Players</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($game_history as $game): ?>
                <tr>
                    <td><?php echo date('F j Y', strtotime($game['game_date'])); ?></td>
                    <td>
                        <a href="game_details.php?id=<?php echo urlencode($game['game_id']); ?>" class="game-link">
                            <?php echo htmlspecialchars($game['game_name']); ?>
                        </a>
                    </td>
                    <td>
                    <?php
                    if ($game['position'] == 1) {
                        echo '<span class="button-gold-static">Winner</span>';
                    } elseif ($game['position'] == 2) {
                        echo 'Second Place';
                    } elseif ($game['position'] == 3) {
                        echo 'Third Place';
                    } elseif ($game['position'] == 4) {
                        echo 'Fourth Place';
                    } else {
                        echo $game['position'] . 'th Place';
                    }
                    ?>
                </td>
                    <td><?php echo htmlspecialchars($game['num_players']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No game history available for this member.</p>
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