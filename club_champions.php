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
$club_name = $club['club_name'] ?? 'Club Champions';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'desc';

$query = "
    SELECT c.*, m.member_name, m.nickname
    FROM champions c
    JOIN members m ON c.member_id = m.member_id
    WHERE m.club_id = ?
";
$params = [$club_id];

$valid_sort_columns = ['nickname', 'member_name', 'date', 'champ_comments'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'date';
$order = ($order === 'asc') ? 'ASC' : 'DESC';
if ($sort === 'nickname' || $sort === 'member_name') {
    $query .= " ORDER BY COALESCE(NULLIF(m.nickname, ''), m.member_name) " . $order;
} else {
    $query .= " ORDER BY c." . $sort . " " . $order;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$champions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Champions - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
    <script src="js/sidebar.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderSidebar('champions', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Champions (' . count($champions) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php
        TableHelper::renderChampionsTable($champions, [
            'is_admin' => false,
            'base_url' => 'club_champions.php?' . $base_url_param,
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'club_id' => $club_id
        ]);
        ?>
    </div>
</body>
</html>
