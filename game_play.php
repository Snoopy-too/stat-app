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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($game['game_name']); ?> History - <?php echo htmlspecialchars($member['nickname']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .history-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .member-stats {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .member-name {
            font-size: 24px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .game-history {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .position-1 {
            color: #f1c40f;
            font-weight: bold;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .back-link:hover {
            background-color: #2980b9;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
        <a href="member_stathistory.php?id=<?php echo $member_id; ?>" class="button">&larr; Back to Member Stats</a>
    </div>

    <div class="history-container">
        <h1 class="member-name">
            <?php echo htmlspecialchars($member['nickname']); ?>'s
            <?php echo htmlspecialchars($game['game_name']); ?> History
        </h1>
        
        <?php if (count($game_history) > 0): ?>
        <table class="game-history">
            <thead>
                <tr>
                    <th>Date Played</th>
                    <th>Place</th>
                    <th>Number of Players</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($game_history as $game): ?>
                <tr>
                    <td><?php echo date('F j Y', strtotime($game['game_date'])); ?></td>
                    <td class="<?php echo $game['position'] == 1 ? 'winner' : ''; ?>">
                        <?php 
                        if ($game['position'] == 1) echo 'Winner';
                        elseif ($game['position'] == 2) echo 'Second Place';
                        elseif ($game['position'] == 3) echo 'Third Place';
                        elseif ($game['position'] == 4) echo 'Fourth Place';
                        else echo $game['position'] . 'th Place';
                        ?>
                    </td>
                    <td><?php echo $game['num_players']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No game history available for this game.</p>
        <?php endif; ?>
    </div>
</body>
</html>