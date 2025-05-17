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
    </style>
</head>
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
        <a href="club_stats.php?id=<?php echo $club_id; ?>" class="button">Back to Club Stats</a>
    </div>

    <div class="container">
        <div class="game-results-container">
        <h2><?php echo htmlspecialchars($club['club_name']); ?>'s Game Results</h2>

        <?php if (isset($game_results) && count($game_results) > 0): ?>
        <table class="game-results">
            <thead>
                <tr>
                    <th><a href="?id=<?php echo $club_id; ?>&sort=played_at&order=<?php echo ($sort_column === 'played_at' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="sort-link" onclick="saveScroll()">Date Played<?php if ($sort_column === 'played_at') echo $order === 'ASC' ? ' ▲' : ' ▼'; ?></a></th>
                    <th><a href="?id=<?php echo $club_id; ?>&sort=game_name&order=<?php echo ($sort_column === 'game_name' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="sort-link" onclick="saveScroll()">Game<?php if ($sort_column === 'game_name') echo $order === 'ASC' ? ' ▲' : ' ▼'; ?></a></th>
                    <th><a href="?id=<?php echo $club_id; ?>&sort=game_type&order=<?php echo ($sort_column === 'game_type' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="sort-link" onclick="saveScroll()">Type<?php if ($sort_column === 'game_type') echo $order === 'ASC' ? ' ▲' : ' ▼'; ?></a></th>
                    <th><a href="?id=<?php echo $club_id; ?>&sort=winner_identifier&order=<?php echo ($sort_column === 'winner_identifier' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="sort-link" onclick="saveScroll()">Winner / Team<?php if ($sort_column === 'winner_identifier') echo $order === 'ASC' ? ' ▲' : ' ▼'; ?></a></th>
                    <th><a href="?id=<?php echo $club_id; ?>&sort=participants&order=<?php echo ($sort_column === 'participants' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="sort-link" onclick="saveScroll()">Participants<?php if ($sort_column === 'participants') echo $order === 'ASC' ? ' ▲' : ' ▼'; ?></a></th>
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
                    <td data-label="Winner / Team"><span class="position position-1 button-gold-static"><?php echo htmlspecialchars($result['winner_identifier']); ?></span></td>
                    <td data-label="Participants"><?php echo htmlspecialchars($result['participants']); ?> <?php echo ($result['game_type'] === 'Individual') ? 'Players' : 'Teams'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No game results available for this club.</p>
        <?php endif; ?>
        </div>
    </div>
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