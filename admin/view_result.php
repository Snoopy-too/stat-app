<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';

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

// Get game result details with game and club information
$stmt = $pdo->prepare("
    SELECT gr.*, g.game_name, g.club_id, c.club_name, 
           m1.nickname as winner_nickname,
           m2.nickname as place_2_nickname,
           m3.nickname as place_3_nickname,
           m4.nickname as place_4_nickname,
           m5.nickname as place_5_nickname,
           m6.nickname as place_6_nickname,
           m7.nickname as place_7_nickname,
           m8.nickname as place_8_nickname
    FROM game_results gr
    JOIN games g ON gr.game_id = g.game_id
    JOIN clubs c ON g.club_id = c.club_id
    LEFT JOIN members m1 ON gr.member_id = m1.member_id
    LEFT JOIN members m2 ON gr.place_2 = m2.member_id
    LEFT JOIN members m3 ON gr.place_3 = m3.member_id
    LEFT JOIN members m4 ON gr.place_4 = m4.member_id
    LEFT JOIN members m5 ON gr.place_5 = m5.member_id
    LEFT JOIN members m6 ON gr.place_6 = m6.member_id
    LEFT JOIN members m7 ON gr.place_7 = m7.member_id
    LEFT JOIN members m8 ON gr.place_8 = m8.member_id
    WHERE gr.result_id = ?");
$stmt->execute([$result_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error'] = "Result not found.";
    header("Location: dashboard.php");
    exit();
}

// Fetch losers if any
$loser_stmt = $pdo->prepare("SELECT m.nickname FROM game_result_losers grl JOIN members m ON grl.member_id = m.member_id WHERE grl.result_id = ? ORDER BY m.nickname");
$loser_stmt->execute([$result_id]);
$losers = $loser_stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title>Game Result Details - <?php echo htmlspecialchars($result['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Game Result Details - <?php echo htmlspecialchars($result['game_name']); ?></h1>
        <div class="header-buttons">
            <a href="edit_result.php?result_id=<?php echo $result_id; ?>" class="btn">Edit Result</a>
            <button type="button" class="btn btn--danger" onclick="confirmDeletion(<?php echo $result_id; ?>)">Delete Result</button>
            <a href="results.php?club_id=<?php echo $result['club_id']; ?>&game_id=<?php echo $result['game_id']; ?>" class="btn">Back to Results</a>
        </div>
    </div>
    
    <div class="container">
        <?php display_session_message('error'); ?>
        <?php display_session_message('success'); ?>

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
            <h2>Game Results</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Place</th>
                        <th>Player</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1st Place (Winner)</td>
                        <td><?php echo htmlspecialchars($result['winner_nickname']); ?></td>
                    </tr>
                    <?php if ($result['place_2_nickname']): ?>
                        <tr>
                            <td>2nd Place</td>
                            <td><?php echo htmlspecialchars($result['place_2_nickname']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($result['place_3_nickname']): ?>
                        <tr>
                            <td>3rd Place</td>
                            <td><?php echo htmlspecialchars($result['place_3_nickname']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php for ($i = 4; $i <= 8; $i++): ?>
                        <?php $place_key = "place_{$i}_nickname"; ?>
                        <?php if ($result[$place_key]): ?>
                            <tr>
                                <td><?php echo $i; ?>th Place</td>
                                <td><?php echo htmlspecialchars($result[$place_key]); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if (!empty($losers)): ?>
                        <tr>
                            <td>Losers</td>
                            <td>
                                <div class="losers-list">
                                    <?php echo htmlspecialchars(implode(', ', $losers)); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        function confirmDeletion(resultId) {
            if (confirm('Are you sure you want to delete this game result? This action cannot be undone.')) {
                window.location.href = 'delete_result.php?result_id=' + resultId;
            }
        }
    </script>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>