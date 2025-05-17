<?php
session_start();
require_once '../config/database.php';

// Ensure user is logged in and has appropriate admin access
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

// Fetch clubs with statistics for the current admin
$query = "
    SELECT c.*,
           COUNT(DISTINCT m.member_id) as member_count,
           COUNT(DISTINCT g.game_id) as game_count,
           SUM(COALESCE(gr.total, 0) + COALESCE(tgr.total, 0)) as games_played
    FROM clubs c
    LEFT JOIN members m ON c.club_id = m.club_id
    LEFT JOIN games g ON c.club_id = g.club_id
    LEFT JOIN (
        SELECT game_id, COUNT(result_id) as total
        FROM game_results
        WHERE game_id IN (3001, 3005)  /* Ensure game_id type matches if it's INT */
        GROUP BY game_id
    ) gr ON g.game_id = gr.game_id
    LEFT JOIN (
        SELECT game_id, COUNT(result_id) as total
        FROM team_game_results
        WHERE game_id IN (3001, 3005)  /* Ensure game_id type matches if it's INT */
        GROUP BY game_id
    ) tgr ON g.game_id = tgr.game_id
    WHERE c.admin_id = ?
    GROUP BY c.club_id
    ORDER BY c.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['admin_id']]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total statistics
$total_members = array_sum(array_column($clubs, 'member_count'));
$total_games = array_sum(array_column($clubs, 'game_count'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Link to external CSS -->
</head>
<body>
    <div class="header">
        <h1>Admin Dashboard</h1>
        <div class="header-links dropdown"> <!-- Use classes from styles.css -->
            <button class="dropbtn login-link">Account ▼</button>
            <div class="dropdown-content">
                <a href="./change_password.php">Change Password</a>
                <a href="./logout.php">Logout</a>
            </div>
        </div>
    </div>

    <!-- Removed the inline <style> block for dropdowns -->

    <div class="container">
        <div class="stats-overview grid">
            <div class="card stat-card">
                <h3>Total Clubs</h3>
                <div class="stat-number"><?php echo count($clubs); ?></div>
            </div>
            <div class="card stat-card">
                <h3>Total Members</h3>
                <div class="stat-number"><?php echo $total_members; ?></div>
            </div>
            <div class="card stat-card">
                <h3>Total Games</h3>
                <div class="stat-number"><?php echo $total_games; ?></div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="manage_clubs.php" class="button">Manage Clubs</a>
        </div>

        <div class="card">
            <h2>Club Overview</h2>
            <div class="table-responsive"> <!-- Added for better mobile table handling -->
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Club Name</th>
                            <th>Members</th>
                            <th>Games</th>
                            <th>Games Played</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clubs)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">You haven't created any clubs yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clubs as $club): ?>
                                <tr>
                                    <td data-label="Club Name"><?php echo htmlspecialchars($club['club_name']); ?></td>
                                    <td data-label="Members"><?php echo $club['member_count']; ?></td>
                                    <td data-label="Games"><?php echo $club['game_count']; ?></td>
                                    <td data-label="Games Played"><?php echo $club['games_played']; ?></td>
                                    <td data-label="Created"><?php echo date('M j, Y', strtotime($club['created_at'])); ?></td>
                                    <td data-label="Actions">
                                        <a href="manage_members.php?club_id=<?php echo $club['club_id']; ?>" class="button">Members</a>
                                        <a href="manage_games.php?club_id=<?php echo $club['club_id']; ?>" class="button">Games</a>
                                        <a href="manage_champions.php?club_id=<?php echo $club['club_id']; ?>" class="button">Champions</a>
                                        <a href="club_teams.php?club_id=<?php echo $club['club_id']; ?>" class="button">Teams</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>