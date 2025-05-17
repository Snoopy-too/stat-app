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
</head>
<body>
    <div class="header">
        <h1>Game Result Details - <?php echo htmlspecialchars($result['game_name']); ?></h1>
        <div class="header-buttons">
            <a href="edit_result.php?result_id=<?php echo $result_id; ?>" class="button">Edit Result</a>
            <a href="results.php?club_id=<?php echo $result['club_id']; ?>&game_id=<?php echo $result['game_id']; ?>" class="button">Back to Results</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
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
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>