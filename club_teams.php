<?php
declare(strict_types=1);
session_start();
require_once 'config/database.php';
require_once 'includes/helpers.php';
require_once 'includes/NavigationHelper.php';
require_once 'includes/services/ClubService.php';

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0);
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

$clubService = new ClubService($pdo);
$club = $clubService->getClubDetails($club_id, $slug);

if (!$club) {
    header("Location: index.php");
    exit();
}

$club_id = (int)$club['club_id'];
$club_name = $club['club_name'] ?? 'Club Teams';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "
    SELECT t.*,
           m1.nickname as member1_nickname,
           m2.nickname as member2_nickname,
           m3.nickname as member3_nickname,
           m4.nickname as member4_nickname,
           (SELECT COUNT(*) FROM team_game_results tgr WHERE tgr.winner = t.team_id) as wins,
           (SELECT COUNT(*) FROM team_game_results tgr WHERE tgr.winner = t.team_id OR tgr.place_2 = t.team_id OR tgr.place_3 = t.team_id OR tgr.place_4 = t.team_id) as total_plays
    FROM teams t
    LEFT JOIN members m1 ON t.member1_id = m1.member_id
    LEFT JOIN members m2 ON t.member2_id = m2.member_id
    LEFT JOIN members m3 ON t.member3_id = m3.member_id
    LEFT JOIN members m4 ON t.member4_id = m4.member_id
    WHERE t.club_id = ?
";
$params = [$club_id];
if ($search !== '') {
    $query .= " AND (t.team_name LIKE ? OR m1.nickname LIKE ? OR m2.nickname LIKE ? OR m3.nickname LIKE ? OR m4.nickname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY wins DESC, t.team_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderSidebar('teams', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Teams (' . count($teams) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php
        TableHelper::renderTeamsTable($teams, [
            'is_admin' => false,
            'base_url' => 'club_teams.php?' . $base_url_param,
            'search' => $search,
            'club_id' => $club_id
        ]);
        ?>
    </div>
</body>
</html>
