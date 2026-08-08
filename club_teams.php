<?php
declare(strict_types=1);
session_start();
require_once 'config/database.php';
require_once 'includes/helpers.php';
require_once 'includes/NavigationHelper.php';
require_once 'includes/services/ClubService.php';

ensure_results_tables_exist($pdo);

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0);
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

$clubService = new ClubService($pdo);
$club = $clubService->getClubDetails($club_id, $slug);

if (!$club) {
    header("Location: index.php");
    exit();
}

$club_id = (int)$club['club_id'];

// Fetch teams of the club (reusing admin table query structure)
$stmt = $pdo->prepare("
    SELECT t.*,
           m1.nickname as m1_nick,
           m2.nickname as m2_nick,
           m3.nickname as m3_nick,
           m4.nickname as m4_nick,
           (SELECT COUNT(*) FROM team_game_results tgr WHERE tgr.winner = t.team_id) as wins,
           (SELECT COUNT(*) FROM team_game_results tgr WHERE tgr.winner = t.team_id OR tgr.place_2 = t.team_id OR tgr.place_3 = t.team_id OR tgr.place_4 = t.team_id) as total_plays
    FROM teams t
    LEFT JOIN members m1 ON t.member1_id = m1.member_id
    LEFT JOIN members m2 ON t.member2_id = m2.member_id
    LEFT JOIN members m3 ON t.member3_id = m3.member_id
    LEFT JOIN members m4 ON t.member4_id = m4.member_id
    WHERE t.club_id = ?
    ORDER BY wins DESC, t.team_name ASC
");
$stmt->execute([$club_id]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderSidebar('teams', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Teams', $club['club_name']); ?>
    </div>

    <div class="container container--wide">
        <div class="card">
            <div class="card-header card-header--stack" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2>Club Teams</h2>
                    <p class="card-subtitle card-subtitle--muted">Registered team rosters and performance statistics for <?php echo htmlspecialchars($club['club_name']); ?>.</p>
                </div>
            </div>

            <?php if (!empty($teams)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Team Name</th>
                            <th>Roster</th>
                            <th>Total Plays</th>
                            <th>Wins</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $t): ?>
                        <?php
                        $roster = array_filter([
                            $t['m1_nick'],
                            $t['m2_nick'],
                            $t['m3_nick'],
                            $t['m4_nick']
                        ]);
                        ?>
                        <tr>
                            <td data-label="Team Name"><strong><?php echo htmlspecialchars($t['team_name']); ?></strong></td>
                            <td data-label="Roster"><?php echo htmlspecialchars(implode(', ', $roster) ?: '—'); ?></td>
                            <td data-label="Total Plays"><?php echo (int)$t['total_plays']; ?></td>
                            <td data-label="Wins"><span class="badge badge--success"><?php echo (int)$t['wins']; ?> wins</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted" style="padding: 1.5rem 0;">No teams registered for this club yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
