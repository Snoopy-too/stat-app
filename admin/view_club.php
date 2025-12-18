<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get club details with statistics and check permissions
$stmt = $pdo->prepare("
    SELECT c.*, ca.role as admin_role,
           (SELECT COUNT(*) FROM members WHERE club_id = c.club_id) as member_count,
           (SELECT COUNT(*) FROM games WHERE club_id = c.club_id) as game_count,
           (COALESCE((SELECT COUNT(DISTINCT session_id) FROM game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0) +
            COALESCE((SELECT COUNT(DISTINCT session_id) FROM team_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0)) as games_played
    FROM clubs c
    JOIN club_admins ca ON c.club_id = ca.club_id
    WHERE c.club_id = ? AND ca.admin_id = ?
");
$stmt->execute([$club_id, $_SESSION['admin_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

// Get recent activity
$stmt = $pdo->prepare("
    SELECT s.session_id, s.game_id, s.played_at, g.game_name, s.type,
           (CASE 
               WHEN s.type = 'individual' THEN (SELECT COUNT(*) FROM game_results WHERE session_id = s.session_id)
               WHEN s.type = 'team' THEN (SELECT COUNT(*) FROM team_game_results WHERE session_id = s.session_id)
            END) as participant_count
    FROM (
        SELECT session_id, game_id, played_at, 'individual' as type
        FROM game_results
        WHERE game_id IN (SELECT game_id FROM games WHERE club_id = ?)
        GROUP BY session_id
        UNION ALL
        SELECT session_id, game_id, played_at, 'team' as type
        FROM team_game_results
        WHERE game_id IN (SELECT game_id FROM games WHERE club_id = ?)
        GROUP BY session_id
    ) s
    JOIN games g ON s.game_id = g.game_id
    ORDER BY s.played_at DESC
    LIMIT 10
");
$stmt->execute([$club_id, $club_id]);
$recent_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top games
$stmt = $pdo->prepare("
    SELECT g.game_name, COUNT(DISTINCT s.session_id) as play_count
    FROM games g
    LEFT JOIN (
        SELECT session_id, game_id FROM game_results
        UNION ALL
        SELECT session_id, game_id FROM team_game_results
    ) s ON g.game_id = s.game_id
    WHERE g.club_id = ?
    GROUP BY g.game_id
    ORDER BY play_count DESC
    LIMIT 5
");
$stmt->execute([$club_id]);
$top_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Club - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .club-info {
            background-color: var(--color-surface);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background-color: var(--bg-secondary);
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .recent-games, .top-games {
            background-color: var(--color-surface);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .button {
            background-color: #3498db;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .delete-button {
            background-color: #e74c3c;
        }
    </style>
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($club['club_name']); ?></h1>
    </div>

    <div class="container">
        <div class="club-info">
            <h2>Club Information</h2>
            <p><strong>Created:</strong> <?php echo date('F j, Y', strtotime($club['created_at'])); ?></p>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $club['member_count']; ?></div>
                    <div>Members</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $club['game_count']; ?></div>
                    <div>Games</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $club['games_played']; ?></div>
                    <div>Games Played</div>
                </div>
            </div>
        </div>

        <div class="recent-games">
            <h2>Recent Activity</h2>
            <?php if ($recent_games): ?>
                <ul>
                    <?php foreach ($recent_games as $game): ?>
                        <li>
                            <?php echo htmlspecialchars($game['game_name']); ?> - 
                            <?php echo $game['participant_count']; ?> <?php echo $game['type'] === 'individual' ? 'players' : 'teams'; ?> - 
                            <?php echo date('M j, Y', strtotime($game['played_at'])); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No recent activity</p>
            <?php endif; ?>
        </div>

        <div class="top-games">
            <h2>Most Played Games</h2>
            <?php if ($top_games): ?>
                <ul>
                    <?php foreach ($top_games as $game): ?>
                        <li>
                            <?php echo htmlspecialchars($game['game_name']); ?> - 
                            Played <?php echo $game['play_count']; ?> times
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No games played yet</p>
            <?php endif; ?>
        </div>

        <div>
            <a href="edit_club.php?id=<?php echo $club_id; ?>" class="btn">Edit Club</a>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
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