<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get club details with statistics
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(DISTINCT m.member_id) as member_count,
           COUNT(DISTINCT g.game_id) as game_count,
           COUNT(DISTINCT gh.history_id) as games_played
    FROM clubs c
    LEFT JOIN members m ON c.club_id = m.club_id
    LEFT JOIN games g ON c.club_id = g.club_id
    LEFT JOIN game_history gh ON c.club_id = gh.club_id
    WHERE c.club_id = ?
    GROUP BY c.club_id
");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

// Get recent activity
$stmt = $pdo->prepare("
    SELECT gh.*, g.game_name, COUNT(gr.result_id) as player_count
    FROM game_history gh
    JOIN games g ON gh.game_id = g.game_id
    LEFT JOIN game_results gr ON gh.history_id = gr.history_id
    WHERE gh.club_id = ?
    GROUP BY gh.history_id
    ORDER BY gh.played_at DESC
    LIMIT 10
");
$stmt->execute([$club_id]);
$recent_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top games
$stmt = $pdo->prepare("
    SELECT g.game_name, COUNT(gh.history_id) as play_count
    FROM games g
    LEFT JOIN game_history gh ON g.game_id = gh.game_id
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
    <title>View Club - Super Admin</title>
            padding: 15px;
            border-radius: 4px;
            text-align: center;
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
            <p><strong>Description:</strong> <?php echo htmlspecialchars($club['description']); ?></p>
            <p><strong>Meeting Day:</strong> <?php echo htmlspecialchars($club['meeting_day']); ?></p>
            <p><strong>Meeting Time:</strong> <?php echo htmlspecialchars($club['meeting_time']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($club['location']); ?></p>
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
                            <?php echo $game['player_count']; ?> players - 
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
            <a href="../delete_club.php?id=<?php echo $club_id; ?>" 
               class="btn btn--danger"
               onclick="return confirm('Are you sure you want to delete this club?')">
                Delete Club
            </a>
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