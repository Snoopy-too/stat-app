<?php
declare(strict_types=1);
session_start();
require_once 'config/database.php';
require_once 'includes/helpers.php';
require_once 'includes/NavigationHelper.php';
require_once 'includes/services/ClubService.php';

ensure_game_type_column_exists($pdo);

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0);
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

$clubService = new ClubService($pdo);
$club = $clubService->getClubDetails($club_id, $slug);

if (!$club) {
    header("Location: index.php");
    exit();
}

$club_id = (int)$club['club_id'];
$club_name = $club['club_name'] ?? 'Club Games';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'game_name';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';

$valid_sort_columns = ['game_name', 'game_type', 'total_plays', 'last_played'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'game_name';
$order = ($order === 'desc') ? 'desc' : 'asc';

$query = "SELECT g.*, c.club_name, 
          (COALESCE(gr.total, 0) + COALESCE(tgr.total, 0) + COALESCE(cgr.total, 0)) as total_plays,
          (
              SELECT MAX(played_at) FROM (
                  SELECT game_id, played_at FROM game_results
                  UNION ALL
                  SELECT game_id, played_at FROM team_game_results
                  UNION ALL
                  SELECT game_id, played_at FROM cooperative_game_results
              ) all_res WHERE all_res.game_id = g.game_id
          ) as last_played
          FROM games g 
          JOIN clubs c ON g.club_id = c.club_id
          LEFT JOIN (
              SELECT game_id, COUNT(result_id) as total 
              FROM game_results 
              GROUP BY game_id
          ) gr ON g.game_id = gr.game_id
          LEFT JOIN (
              SELECT game_id, COUNT(result_id) as total 
              FROM team_game_results 
              GROUP BY game_id
          ) tgr ON g.game_id = tgr.game_id
          LEFT JOIN (
              SELECT game_id, COUNT(result_id) as total 
              FROM cooperative_game_results 
              GROUP BY game_id
          ) cgr ON g.game_id = cgr.game_id
          WHERE g.club_id = ?";

$params = [$club_id];

$query .= " GROUP BY g.game_id, c.club_id, c.club_name, g.game_name, g.min_players, g.max_players, g.game_type";

if ($sort === 'total_plays') {
    $query .= " ORDER BY total_plays $order, g.game_name ASC";
} elseif ($sort === 'last_played') {
    $query .= " ORDER BY last_played $order, g.game_name ASC";
} else {
    $query .= " ORDER BY g.$sort $order";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
    <script src="js/i18n.js"></script>
    <script src="js/sidebar.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderSidebar('games', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Games (' . count($games) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php
        TableHelper::renderGamesTable($games, [
            'is_admin' => false,
            'base_url' => 'club_game_list.php?' . $base_url_param,
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'club_id' => $club_id
        ]);
        ?>
    </div>
</body>
</html>
