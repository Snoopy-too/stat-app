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

// Fetch members of the club with win and championship stats (reusing admin table query)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'nickname';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';

$valid_sort_columns = ['nickname', 'total_wins', 'championships_count'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'nickname';

$query = "
    SELECT m.*, c.club_name,
           COALESCE(NULLIF(m.nickname, ''), 'Member') as display_name,
           (SELECT GROUP_CONCAT(t.team_name ORDER BY t.team_name SEPARATOR ', ')
            FROM teams t
            WHERE (t.member1_id = m.member_id OR t.member2_id = m.member_id
                OR t.member3_id = m.member_id OR t.member4_id = m.member_id)) as member_teams,
           (
                (SELECT COUNT(*) FROM game_results gr WHERE COALESCE(gr.winner, gr.member_id) = m.member_id)
                +
                (SELECT COUNT(*) FROM team_game_results tgr JOIN teams t ON tgr.winner = t.team_id WHERE (t.member1_id = m.member_id OR t.member2_id = m.member_id OR t.member3_id = m.member_id OR t.member4_id = m.member_id))
           ) as total_wins,
           (SELECT COUNT(*) FROM champions ch WHERE ch.member_id = m.member_id) as championships_count
    FROM members m
    JOIN clubs c ON m.club_id = c.club_id
    WHERE m.club_id = ?
";

$params = [$club_id];
if ($search !== '') {
    $query .= " AND (m.member_name LIKE ? OR m.nickname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($sort === 'total_wins' || $sort === 'championships_count') {
    $query .= " ORDER BY " . $sort . " " . $order;
} else {
    $query .= " ORDER BY m." . $sort . " " . $order;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderSidebar('members', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader($club['club_name'] . ' Members (' . count($members) . ')'); ?>
    </div>

    <div class="container container--wide">

            <?php if (!empty($members)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=nickname&order=<?php echo ($sort === 'nickname' && $order === 'ASC') ? 'desc' : 'asc'; ?>" class="table-sort-link">Nickname <?php if ($sort === 'nickname'): ?><span><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th>Teams</th>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=total_wins&order=<?php echo ($sort === 'total_wins' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link">Total Wins <?php if ($sort === 'total_wins'): ?><span><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=championships_count&order=<?php echo ($sort === 'championships_count' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link">Championships <?php if ($sort === 'championships_count'): ?><span><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td data-label="Nickname"><strong><?php echo htmlspecialchars($m['nickname'] ?: 'Member'); ?></strong></td>
                            <td data-label="Teams"><?php echo htmlspecialchars($m['member_teams'] ?? '—'); ?></td>
                            <td data-label="Total Wins"><span class="badge badge--success"><?php echo (int)$m['total_wins']; ?> wins</span></td>
                            <td data-label="Championships"><?php echo (int)$m['championships_count'] > 0 ? '🏆 ' . (int)$m['championships_count'] : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted" style="padding: 1.5rem 0;">No members found for this club.</p>
            <?php endif; ?>
    </div>
</body>
</html>
