<?php
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Sorting parameters
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'game_date';
$order = (isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC') ? 'ASC' : 'DESC';
$allowed_sorts = ['game_date', 'game_name', 'position', 'num_players'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'game_date';
}

// Get member details
$stmt = $pdo->prepare("SELECT m.nickname, m.club_id, c.club_name FROM members m JOIN clubs c ON m.club_id = c.club_id WHERE m.member_id = ? AND m.status = 'active'");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header("Location: index.php");
    exit();
}

$club_id = $member['club_id'];

// Calculate Average Finish and get game history for the member (individual, team, and cooperative games)
$stmt = $pdo->prepare("SELECT DISTINCT gr.played_at as game_date, g.game_name, CASE WHEN gr.winner = ? THEN 1 WHEN gr.place_2 = ? THEN 2 WHEN gr.place_3 = ? THEN 3 WHEN gr.place_4 = ? THEN 4 WHEN gr.place_5 = ? THEN 5 WHEN gr.place_6 = ? THEN 6 WHEN gr.place_7 = ? THEN 7 WHEN gr.place_8 = ? THEN 8 END as position, gr.num_players, gr.game_id, gr.result_id, 'individual' as game_type, NULL as coop_outcome FROM game_results gr JOIN games g ON gr.game_id = g.game_id WHERE gr.winner = ? OR gr.place_2 = ? OR gr.place_3 = ? OR gr.place_4 = ? OR gr.place_5 = ? OR gr.place_6 = ? OR gr.place_7 = ? OR gr.place_8 = ?
UNION ALL
SELECT DISTINCT tgr.played_at as game_date, g.game_name, CASE WHEN tgr.winner = t.team_id THEN 1 WHEN tgr.place_2 = t.team_id THEN 2 WHEN tgr.place_3 = t.team_id THEN 3 WHEN tgr.place_4 = t.team_id THEN 4 END as position, tgr.num_teams as num_players, tgr.game_id, tgr.result_id, 'team' as game_type, NULL as coop_outcome FROM teams t JOIN team_game_results tgr ON (t.team_id = tgr.winner OR t.team_id = tgr.place_2 OR t.team_id = tgr.place_3 OR t.team_id = tgr.place_4) JOIN games g ON tgr.game_id = g.game_id WHERE (t.member1_id = ? OR t.member2_id = ? OR t.member3_id = ? OR t.member4_id = ?)
UNION ALL
SELECT cgr.played_at as game_date, g.game_name, CASE cgr.outcome WHEN 'win' THEN 0 ELSE -1 END as position, cgr.num_participants as num_players, cgr.game_id, cgr.result_id, 'cooperative' as game_type, cgr.outcome as coop_outcome FROM cooperative_game_results cgr JOIN cooperative_result_participants crp ON cgr.result_id = crp.result_id JOIN games g ON cgr.game_id = g.game_id WHERE crp.participant_type = 'member' AND crp.member_id = ?
UNION ALL
SELECT cgr.played_at as game_date, g.game_name, CASE cgr.outcome WHEN 'win' THEN 0 ELSE -1 END as position, cgr.num_participants as num_players, cgr.game_id, cgr.result_id, 'cooperative' as game_type, cgr.outcome as coop_outcome FROM cooperative_game_results cgr JOIN cooperative_result_participants crp ON cgr.result_id = crp.result_id JOIN teams t ON crp.team_id = t.team_id JOIN games g ON cgr.game_id = g.game_id WHERE crp.participant_type = 'team' AND (t.member1_id = ? OR t.member2_id = ? OR t.member3_id = ? OR t.member4_id = ?)
ORDER BY $sort $order");

$params = array_fill(0, 16, $member_id); // For individual games
$params = array_merge($params, array_fill(0, 4, $member_id)); // For team games
$params[] = $member_id; // For cooperative games (direct member)
$params = array_merge($params, array_fill(0, 4, $member_id)); // For cooperative games (via team)
$stmt->execute($params);
$game_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Average Finish (excluding cooperative games)
$total_points = 0;
$competitive_games = 0;
$coop_wins = 0;
$coop_total = 0;

foreach ($game_history as $game) {
    if ($game['game_type'] === 'cooperative') {
        $coop_total++;
        if ($game['coop_outcome'] === 'win') {
            $coop_wins++;
        }
    } else {
        $total_points += $game['position'];
        $competitive_games++;
    }
}
$average_finish = $competitive_games > 0 ? number_format($total_points / $competitive_games, 2) : 0;
$coop_win_rate = $coop_total > 0 ? number_format(($coop_wins / $coop_total) * 100, 0) : null;
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
<body class="has-sidebar">
    <?php
    // Render sidebar navigation
    NavigationHelper::renderSidebar('club_stats', $club_id, $member['club_name']);
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader($member['nickname'] . "'s Game History"); ?>
    </div>

    <div class="history-container">
        
        <h1 class="member-name"><?php echo htmlspecialchars($member['nickname']); ?>'s Game History</h1>
        
        <div class="stats-summary">
            <p><strong><span class="tooltip-trigger" data-tooltip="Lower is better! 1.00 = 1st place in every game played.">Average Finish:</span></strong> <?php echo $average_finish; ?> <span style="font-size: 0.875em; color: var(--color-text-muted);">(<?php echo $competitive_games; ?> competitive games)</span></p>
            <?php if ($coop_win_rate !== null): ?>
            <p><strong>Co-op Win Rate:</strong> <?php echo $coop_win_rate; ?>% <span style="font-size: 0.875em; color: var(--color-text-muted);">(<?php echo $coop_wins; ?>/<?php echo $coop_total; ?> games)</span></p>
            <?php endif; ?>
        </div>
        
        <?php if (count($game_history) > 0): ?>
        <table class="game-history">
            <thead>
                <tr>
                    <th><a href="?id=<?php echo $member_id; ?>&sort=game_date&order=<?php echo ($sort === 'game_date' && $order === 'DESC') ? 'ASC' : 'DESC'; ?>" class="sort-link" onclick="saveScroll()">Date Played <?php if ($sort === 'game_date') echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                    <th><a href="?id=<?php echo $member_id; ?>&sort=game_name&order=<?php echo ($sort === 'game_name' && $order === 'DESC') ? 'ASC' : 'DESC'; ?>" class="sort-link" onclick="saveScroll()">Game <?php if ($sort === 'game_name') echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                    <th><a href="?id=<?php echo $member_id; ?>&sort=position&order=<?php echo ($sort === 'position' && $order === 'DESC') ? 'ASC' : 'DESC'; ?>" class="sort-link" onclick="saveScroll()">Place <?php if ($sort === 'position') echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                    <th><a href="?id=<?php echo $member_id; ?>&sort=num_players&order=<?php echo ($sort === 'num_players' && $order === 'DESC') ? 'ASC' : 'DESC'; ?>" class="sort-link" onclick="saveScroll()">Total Players <?php if ($sort === 'num_players') echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
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
                    if ($game['game_type'] === 'cooperative') {
                        if ($game['coop_outcome'] === 'win') {
                            echo '<span class="outcome-badge outcome-win">WIN</span>';
                        } else {
                            echo '<span class="outcome-badge outcome-loss">LOSS</span>';
                        }
                    } elseif ($game['position'] == 1) {
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
    <script src="js/sidebar.js"></script>
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