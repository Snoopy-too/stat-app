<?php
require_once 'config/database.php';

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get club details
$stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: index.php");
    exit();
}

$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'played_at';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
// Updated allowed columns for sorting combined results
$allowed_columns = ['played_at', 'game_name', 'winner_identifier', 'participants', 'game_type'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'played_at';
}

// Get combined game results (individual and team) for the club
$sql = "
    -- Individual Games
    SELECT
        gr.played_at,
        g.game_name,
        m.nickname as winner_identifier,
        gr.num_players as participants,
        gr.game_id,
        'Individual' as game_type,
        gr.result_id as record_id
    FROM game_results gr
    JOIN games g ON gr.game_id = g.game_id
    JOIN members m ON gr.winner = m.member_id
    WHERE m.club_id = :club_id_individual

    UNION ALL

    -- Team Games
    SELECT
        tgr.played_at,
        g.game_name,
        t.team_name as winner_identifier,
        tgr.num_teams as participants, -- Representing number of teams
        tgr.game_id,
        'Team' as game_type,
        tgr.result_id as record_id
    FROM team_game_results tgr
    JOIN games g ON tgr.game_id = g.game_id
    JOIN teams t ON tgr.winner = t.team_id -- <<< CORRECTED THIS LINE
    WHERE t.club_id = :club_id_team

    ORDER BY $sort_column $order
";

// Prepare and execute the statement (Line 60 where the error originally occurred)
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['club_id_individual' => $club_id, 'club_id_team' => $club_id]);
    $game_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // It's good practice to catch potential errors
    die("Database query failed: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<!-- Rest of your HTML code remains the same -->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Results - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Club Game Results</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($club['club_name']); ?></p>
        </div>
        <div class="header-actions">
            <a href="club_stats.php?id=<?php echo $club_id; ?>" class="btn btn--secondary btn--small">Back to Club Stats</a>
        </div>
    </div>

    <div class="container container--wide">
        <div class="card">
            <div class="card-header card-header--stack">
                <div>
                    <h2>Game History</h2>
                    <p class="card-subtitle card-subtitle--muted">Sorted chronologically across individual and team results.</p>
                </div>
            </div>

            <?php if (!empty($game_results)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><a href="?id=<?php echo $club_id; ?>&sort=played_at&order=<?php echo ($sort_column === 'played_at' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Date Played</span><?php if ($sort_column === 'played_at'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?id=<?php echo $club_id; ?>&sort=game_name&order=<?php echo ($sort_column === 'game_name' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Game</span><?php if ($sort_column === 'game_name'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?id=<?php echo $club_id; ?>&sort=game_type&order=<?php echo ($sort_column === 'game_type' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Type</span><?php if ($sort_column === 'game_type'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?id=<?php echo $club_id; ?>&sort=winner_identifier&order=<?php echo ($sort_column === 'winner_identifier' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Winner / Team</span><?php if ($sort_column === 'winner_identifier'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?id=<?php echo $club_id; ?>&sort=participants&order=<?php echo ($sort_column === 'participants' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Participants</span><?php if ($sort_column === 'participants'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($game_results as $result): ?>
                        <tr>
                            <td data-label="Date Played"><?php echo date('F j, Y', strtotime($result['played_at'])); ?></td>
                            <td data-label="Game">
                                <a href="<?php echo $result['game_type'] === 'Team' ? 'team_game_play_details.php' : 'game_play_details.php'; ?>?result_id=<?php echo urlencode($result['record_id']); ?>" class="game-link">
                                    <?php echo htmlspecialchars($result['game_name']); ?>
                                </a>
                            </td>
                            <td data-label="Type"><?php echo htmlspecialchars($result['game_type']); ?></td>
                            <td data-label="Winner / Team"><span class="position-badge position-1"><?php echo htmlspecialchars($result['winner_identifier']); ?></span></td>
                            <td data-label="Participants"><?php echo htmlspecialchars($result['participants']); ?> <?php echo ($result['game_type'] === 'Individual') ? 'Players' : 'Teams'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p>No game results available for this club.</p>
            <?php endif; ?>
        </div>
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
