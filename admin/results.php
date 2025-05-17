<?php
session_start();
require_once '../config/database.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

// Get club_id and game_id from URL
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : null;

// Validate club_id and game_id exist
if (!$club_id || !$game_id) {
    $_SESSION['error'] = "Invalid club ID or game ID provided.";
    header("Location: dashboard.php");
    exit();
}

// Get game details
$stmt = $pdo->prepare("SELECT g.*, c.club_name FROM games g JOIN clubs c ON g.club_id = c.club_id WHERE g.game_id = ? AND g.club_id = ?");
$stmt->execute([$game_id, $club_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    $_SESSION['error'] = "Game not found.";
    header("Location: manage_games.php?club_id=" . $club_id);
    exit();
}

// Get both individual and team game results
$stmt = $pdo->prepare("
    (SELECT 
        gr.result_id,
        gr.played_at,
        m.nickname as winner_name,
        'individual' as game_type,
        gr.duration,
        gr.notes
    FROM game_results gr 
    LEFT JOIN members m ON gr.member_id = m.member_id 
    WHERE gr.game_id = ?)
    UNION ALL
    (SELECT 
        tgr.result_id,
        tgr.played_at,
        t.team_name as winner_name,
        'team' as game_type,
        tgr.duration,
        tgr.notes
    FROM team_game_results tgr
    LEFT JOIN teams t ON tgr.winner = t.team_id
    WHERE tgr.game_id = ?)
    ORDER BY played_at DESC
");
$stmt->execute([$game_id, $game_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Results - <?php echo htmlspecialchars($game['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Game Results - <?php echo htmlspecialchars($game['game_name']); ?></h1>
        <a href="manage_games.php?club_id=<?php echo $club_id; ?>" class="button">Back to Games</a>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success"><?php echo $_SESSION['success_message']; ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="card">
            <h2>Game Information</h2>
            <p><strong>Club:</strong> <?php echo htmlspecialchars($game['club_name']); ?></p>
            <p><strong>Players:</strong> <?php echo $game['min_players'] . '-' . $game['max_players']; ?> players</p>
            <p><strong>Added:</strong> <?php echo date('M j, Y', strtotime($game['created_at'])); ?></p>
        </div>

        <div class="card">
            <h2>Game History</h2>
            <?php if (empty($results)): ?>
                <p>No game results recorded yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date Played</th>
                            <th>Winner</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($result['played_at'])); ?></td>
                                <td><?php echo htmlspecialchars($result['winner_name']); ?></td>
                                <td>
                                    <a href="<?php echo $result['game_type'] === 'individual' ? 'view_result.php' : 'view_team_result.php'; ?>?result_id=<?php echo $result['result_id']; ?>" 
                                       class="button">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="actions" style="margin-top: 20px;">
                <a href="add_result.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>" 
                   class="button">Add New Result</a>
                <a href="add_team_result.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>" 
                   class="button">Add New Team Result</a>
            </div>
        </div>
    </div>
</body>
</html>