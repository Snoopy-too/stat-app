<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// Get overall statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT c.club_id) as total_clubs,
        COUNT(DISTINCT m.member_id) as total_members,
        COUNT(DISTINCT g.game_id) as total_games,
        COUNT(DISTINCT gh.history_id) as total_plays
    FROM clubs c
    LEFT JOIN members m ON c.club_id = m.club_id
    LEFT JOIN games g ON c.club_id = g.club_id
    LEFT JOIN game_history gh ON c.club_id = gh.club_id
");
$overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get most active clubs
$stmt = $pdo->query("
    SELECT c.club_name, 
           COUNT(DISTINCT gh.history_id) as game_sessions,
           COUNT(DISTINCT m.member_id) as member_count
    FROM clubs c
    LEFT JOIN game_history gh ON c.club_id = gh.club_id
    LEFT JOIN members m ON c.club_id = m.club_id
    GROUP BY c.club_id
    ORDER BY game_sessions DESC
    LIMIT 5
");
$active_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get most popular games
$stmt = $pdo->query("
    SELECT g.game_name, 
           COUNT(DISTINCT gh.history_id) as play_count,
           COUNT(DISTINCT gh.club_id) as club_count
    FROM games g
    JOIN game_history gh ON g.game_id = gh.game_id
    GROUP BY g.game_id
    ORDER BY play_count DESC
    LIMIT 5
");
$popular_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly growth data
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as new_clubs
    FROM clubs
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$monthly_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Super Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f0f0f0;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .container {
            width: 95%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        .chart-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .data-table th {
            background-color: #34495e;
            color: white;
        }
        .button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Platform Statistics</h1>
    </div>

    <div class="container">
        <div class="export-buttons" style="margin-bottom: 20px;">
            <a href="export_stats.php?type=all" class="button">Export Overview</a>
            <a href="export_stats.php?type=clubs" class="button">Export Club Data</a>
            <a href="export_stats.php?type=games" class="button">Export Game Data</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Clubs</h3>
                <div class="stat-number"><?php echo $overall_stats['total_clubs']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Members</h3>
                <div class="stat-number"><?php echo $overall_stats['total_members']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Games</h3>
                <div class="stat-number"><?php echo $overall_stats['total_games']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Game Sessions</h3>
                <div class="stat-number"><?php echo $overall_stats['total_plays']; ?></div>
            </div>
        </div>

        <div class="chart-container">
            <canvas id="growthChart"></canvas>
        </div>

        <div class="chart-container">
            <h2>Most Active Clubs</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Club Name</th>
                        <th>Game Sessions</th>
                        <th>Members</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_clubs as $club): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                        <td><?php echo $club['game_sessions']; ?></td>
                        <td><?php echo $club['member_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="chart-container">
            <h2>Most Popular Games</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Game Name</th>
                        <th>Times Played</th>
                        <th>Clubs Playing</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($popular_games as $game): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($game['game_name']); ?></td>
                        <td><?php echo $game['play_count']; ?></td>
                        <td><?php echo $game['club_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>

    <script>
        // Growth chart
        const growthData = <?php echo json_encode($monthly_growth); ?>;
        const months = growthData.map(item => item.month);
        const newClubs = growthData.map(item => item.new_clubs);

        new Chart(document.getElementById('growthChart'), {
            type: 'line',
            data: {
                labels: months.reverse(),
                datasets: [{
                    label: 'New Clubs per Month',
                    data: newClubs.reverse(),
                    borderColor: '#3498db',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Club Growth Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>