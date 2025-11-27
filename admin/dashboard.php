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
        <div class="header-title-group">
            <h1>Admin Dashboard</h1>
            <p class="header-subtitle">Monitor membership and game activity</p>
        </div>
        <div class="header-actions">
            <a href="account.php" class="btn btn--secondary btn--small">⚙️ Account</a>
            <a href="../index.php" class="btn btn--secondary btn--small">View Site</a>
            <a href="logout.php" class="btn btn--secondary btn--small">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-stats">
            <div class="card stat-card stat-card--navy">
                <span class="stat-card__label">Total Clubs</span>
                <div class="stat-card__body">
                    <span class="stat-card__value"><?php echo count($clubs); ?></span>
                    <span class="stat-card__meta">Active clubs under your account</span>
                </div>
            </div>
            <div class="card stat-card stat-card--sky">
                <span class="stat-card__label">Total Members</span>
                <div class="stat-card__body">
                    <span class="stat-card__value"><?php echo $total_members; ?></span>
                    <span class="stat-card__meta">Across all managed clubs</span>
                </div>
            </div>
            <div class="card stat-card stat-card--neutral">
                <span class="stat-card__label">Total Games</span>
                <div class="stat-card__body">
                    <span class="stat-card__value"><?php echo $total_games; ?></span>
                    <span class="stat-card__meta">Available in your libraries</span>
                </div>
            </div>
        </div>

        <div class="dashboard-components">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2>Club Overview</h2>
                        <p class="card-subtitle card-subtitle--muted">Snapshot of club health and activity</p>
                    </div>
                    <a href="manage_clubs.php" class="btn btn--small btn--pill">Manage Clubs</a>
                </div>
                <div class="table-responsive">
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
                                    <td data-label="Games Played"><?php echo (int) ($club['games_played'] ?? 0); ?></td>
                                    <td data-label="Created"><?php echo date('M j, Y', strtotime($club['created_at'])); ?></td>
                                    <td data-label="Actions" class="table-col--primary">
                                        <div class="club-actions">
                                            <a href="manage_members.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--subtle btn--xsmall">Members</a>
                                            <a href="manage_games.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--subtle btn--xsmall">Games</a>
                                            <a href="manage_champions.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--subtle btn--xsmall">Champions</a>
                                            <a href="club_teams.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--subtle btn--xsmall">Teams</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/dark-mode.js"></script>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>
