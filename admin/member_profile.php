<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get member details with club info
$stmt = $pdo->prepare("
    SELECT m.*, c.club_name,
           COUNT(DISTINCT gr.result_id) as games_played,
           COUNT(DISTINCT CASE WHEN gr.position = 1 THEN gr.result_id END) as wins,
           AVG(gr.position) as avg_position
    FROM members m
    JOIN clubs c ON m.club_id = c.club_id
    LEFT JOIN game_results gr ON m.member_id = gr.member_id
    WHERE m.member_id = ?
    GROUP BY m.member_id
");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header("Location: dashboard.php");
    exit();
}

// Get recent game history
$stmt = $pdo->prepare("
    SELECT g.game_name, gh.played_date, gr.position,
           COUNT(DISTINCT gr2.result_id) as total_players
    FROM game_results gr
    JOIN game_history gh ON gr.history_id = gh.history_id
    JOIN games g ON gh.game_id = g.game_id
    LEFT JOIN game_results gr2 ON gr.history_id = gr2.history_id
    WHERE gr.member_id = ?
    GROUP BY gr.result_id
    ORDER BY gh.played_date DESC
    LIMIT 10
");
$stmt->execute([$member_id]);
$recent_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE members 
            SET member_name = ?, 
                email = ?,
                notes = ?
            WHERE member_id = ?
        ");
        
        $stmt->execute([
            $_POST['member_name'],
            $_POST['email'],
            $_POST['notes'],
            $member_id
        ]);
        
        $_SESSION['success'] = "Profile updated successfully.";
        header("Location: member_profile.php?id=" . $member_id);
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update profile.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Profile - <?php echo htmlspecialchars($member['member_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Member Profile - <?php echo htmlspecialchars($member['member_name']); ?></h1>
    </div>
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message--success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <div class="action-buttons">
            <a href="club_leaderboard.php?club_id=<?php echo $member['club_id']; ?>" class="btn">View Club Leaderboard</a>
        </div>
        <div class="profile-grid">
            <div class="stats-card">
                <h2>Statistics</h2>
                <p><strong>Club:</strong> <?php echo htmlspecialchars($member['club_name']); ?></p>
                <p><strong>Games Played:</strong> <?php echo $member['games_played']; ?></p>
                <p><strong>Wins:</strong> <?php echo $member['wins']; ?></p>
                <p><strong>Win Rate:</strong> <?php echo $member['games_played'] > 0 ? round(($member['wins'] / $member['games_played']) * 100, 1) . '%': '0%'; ?></p>
                <p><strong>Average Position:</strong> <?php echo $member['avg_position'] ? round($member['avg_position'], 2) : 'N/A'; ?></p>
                <p><strong>Status:</strong> <span class="status-badge status-<?php echo $member['status']; ?>"><?php echo ucfirst($member['status']); ?></span></p>
            </div>
            <div class="stats-card">
                <h2>Edit Profile</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="member_name">Name</label>
                        <input type="text" id="member_name" name="member_name" value="<?php echo htmlspecialchars($member['member_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($member['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($member['notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>
        </div>
        <div class="game-history">
            <h2>Recent Games</h2>
            <table class="members-table">
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Date</th>
                        <th>Position</th>
                        <th>Players</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_games as $game): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($game['game_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($game['played_date'])); ?></td>
                            <td class="position-<?php echo $game['position']; ?>"><?php echo $game['position']; ?><?php echo getOrdinalSuffix($game['position']); ?></td>
                            <td><?php echo $game['total_players']; ?></td>
                        </tr>
                    <?php endforeach; ?>
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