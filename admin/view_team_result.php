<?php
session_start();
require_once '../config/database.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

// Get result_id from URL
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : null;

if (!$result_id) {
    $_SESSION['error'] = "Invalid result ID provided.";
    header("Location: dashboard.php");
    exit();
}

// Get team game result details with game, club, and team information
$stmt = $pdo->prepare("
    SELECT tgr.*, g.game_name, g.club_id, c.club_name,
           t1.team_name as winner_team_name,
           t2.team_name as place_2_team_name,
           t3.team_name as place_3_team_name,
           t4.team_name as place_4_team_name
    FROM team_game_results tgr
    JOIN games g ON tgr.game_id = g.game_id
    JOIN clubs c ON g.club_id = c.club_id
    LEFT JOIN teams t1 ON tgr.winner = t1.team_id
    LEFT JOIN teams t2 ON tgr.place_2 = t2.team_id
    LEFT JOIN teams t3 ON tgr.place_3 = t3.team_id
    LEFT JOIN teams t4 ON tgr.place_4 = t4.team_id
    WHERE tgr.result_id = ?");
$stmt->execute([$result_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error'] = "Result not found.";
    header("Location: dashboard.php");
    exit();
}

// Convert duration to hours and minutes
$hours = floor($result['duration'] / 60);
$minutes = $result['duration'] % 60;
$duration = '';
if ($hours > 0) {
    $duration .= $hours . ' hr ';
}
if ($minutes > 0 || $hours == 0) {
    $duration .= $minutes . ' min';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Game Result Details - <?php echo htmlspecialchars($result['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Team Game Result Details - <?php echo htmlspecialchars($result['game_name']); ?></h1>
        <div class="btn-group">
            <a href="edit_team_result.php?result_id=<?php echo $result_id; ?>" class="btn">Edit Result</a>
            <a href="results.php?club_id=<?php echo $result['club_id']; ?>&game_id=<?php echo $result['game_id']; ?>" class="btn">Back to Results</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Game Information</h2>
            <p><strong>Club:</strong> <?php echo htmlspecialchars($result['club_name']); ?></p>
            <p><strong>Date Played:</strong> <?php echo date('M j, Y', strtotime($result['played_at'])); ?></p>
            <p><strong>Duration:</strong> <?php echo $duration; ?></p>
            <?php if ($result['notes']): ?>
                <p><strong>Notes:</strong> <?php echo htmlspecialchars($result['notes']); ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Team Results</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Place</th>
                        <th>Team</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1st Place (Winner)</td>
                        <td><?php echo htmlspecialchars($result['winner_team_name']); ?></td>
                    </tr>
                    <?php if ($result['place_2_team_name']): ?>
                        <tr>
                            <td>2nd Place</td>
                            <td><?php echo htmlspecialchars($result['place_2_team_name']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($result['place_3_team_name']): ?>
                        <tr>
                            <td>3rd Place</td>
                            <td><?php echo htmlspecialchars($result['place_3_team_name']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($result['place_4_team_name']): ?>
                        <tr>
                            <td>4th Place</td>
                            <td><?php echo htmlspecialchars($result['place_4_team_name']); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>
