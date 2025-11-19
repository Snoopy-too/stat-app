<?php
require_once 'config/database.php';

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;

// Get member details
$stmt = $pdo->prepare("SELECT nickname, club_id FROM members WHERE member_id = ? AND status = 'active'");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

// Get game details
$stmt = $pdo->prepare("SELECT game_name FROM games WHERE game_id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member || !$game) {
    header("Location: index.php");
    exit();
}

// Get game history for the member and specific game
$stmt = $pdo->prepare("
    SELECT DISTINCT
        gr.played_at as game_date,
        g.game_name,
        CASE
            WHEN gr.winner = ? THEN 1
            WHEN gr.place_2 = ? THEN 2
            WHEN gr.place_3 = ? THEN 3
            WHEN gr.place_4 = ? THEN 4
            WHEN gr.place_5 = ? THEN 5
            WHEN gr.place_6 = ? THEN 6
            WHEN gr.place_7 = ? THEN 7
            WHEN gr.place_8 = ? THEN 8
        END as position,
        gr.num_players
    FROM game_results gr
    JOIN games g ON gr.game_id = g.game_id
    WHERE gr.game_id = ?
        AND (gr.winner = ?
        OR gr.place_2 = ?
        OR gr.place_3 = ?
        OR gr.place_4 = ?
        OR gr.place_5 = ?
        OR gr.place_6 = ?
        OR gr.place_7 = ?
        OR gr.place_8 = ?)
    ORDER BY gr.played_at DESC, position ASC
");
$stmt->execute([$member_id, $member_id, $member_id, $member_id, $member_id, $member_id, $member_id, $member_id, $game_id, $member_id, $member_id, $member_id, $member_id, $member_id, $member_id, $member_id, $member_id]);
$game_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

function ordinal_suffix($number) {
    $n = (int) $number;
    if ($n <= 0) {
        return $n;
    }
    $suffix = 'th';
    if (!in_array(($n % 100), [11, 12, 13], true)) {
        switch ($n % 10) {
            case 1:
                $suffix = 'st';
                break;
            case 2:
                $suffix = 'nd';
                break;
            case 3:
                $suffix = 'rd';
                break;
        }
    }
    return $n . $suffix;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($game['game_name']); ?> History - <?php echo htmlspecialchars($member['nickname']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($member['nickname']); ?>'s <?php echo htmlspecialchars($game['game_name']); ?> history</p>
        </div>
        <a href="member_stathistory.php?id=<?php echo $member_id; ?>" class="btn btn--secondary">&larr; Back to Member Stats</a>
    </div>

    <div class="container container--narrow">
        <div class="card">
            <h2><?php echo htmlspecialchars($member['nickname']); ?>’s <?php echo htmlspecialchars($game['game_name']); ?> History</h2>
            <?php if (count($game_history) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date Played</th>
                        <th>Place</th>
                        <th>Number of Players</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($game_history as $entry): ?>
                    <tr>
                        <td><?php echo date('F j, Y', strtotime($entry['game_date'])); ?></td>
                        <td>
                            <?php if (!empty($entry['position'])): ?>
                                <?php $position = (int) $entry['position']; ?>
                                <span class="position-badge position-<?php echo $position >=1 && $position <=8 ? $position : 1; ?>">
                                    <?php echo $position === 1 ? 'Winner' : ordinal_suffix($position); ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo $entry['num_players']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No game history available for this game.</p>
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
