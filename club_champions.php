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

// Fetch all champions for this club (reusing table query from admin/manage_champions.php)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

$query = "
    SELECT c.*, m.member_name, m.nickname
    FROM champions c
    JOIN members m ON c.member_id = m.member_id
    WHERE m.club_id = ?
";

$params = [$club_id];
if ($search !== '') {
    $query .= " AND (m.member_name LIKE ? OR m.nickname LIKE ? OR c.champ_comments LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY c.date " . $order;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$champions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark most recent as current champion
foreach ($champions as $index => &$champion) {
    $champion['is_current'] = ($index === 0);
}
unset($champion);

$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Champions - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderSidebar('champions', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader($club['club_name'] . ' Champions (' . count($champions) . ')'); ?>
    </div>

    <div class="container container--wide">

            <?php if (!empty($champions)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Member</th>
                            <th>Title / Award</th>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=date&order=<?php echo ($order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link">Date Awarded <?php echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($champions as $c): ?>
                        <tr>
                            <td data-label="Status">
                                <?php if (!empty($c['is_current'])): ?>
                                    <span class="badge badge--success">👑 Current Champion</span>
                                <?php else: ?>
                                    <span class="badge badge--neutral">Former Champion</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Member">
                                <strong><?php echo htmlspecialchars($c['nickname'] ?: 'Member'); ?></strong>
                            </td>
                            <td data-label="Title / Award"><?php echo htmlspecialchars($c['champ_comments'] ?: 'Club Champion'); ?></td>
                            <td data-label="Date Awarded"><?php echo date('Y/m/d', strtotime($c['date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted" style="padding: 1.5rem 0;">No champions recorded for this club yet.</p>
            <?php endif; ?>
    </div>
</body>
</html>
