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
$club_name = $club['club_name'] ?? 'Club Results';

// Optional game_id filter
$game_id = (isset($_GET['game_id']) && $_GET['game_id'] !== '') ? (int)$_GET['game_id'] : null;
$type_filter = isset($_GET['type']) ? $_GET['type'] : (isset($_GET['game_type']) ? $_GET['game_type'] : '');

$game = null;
if ($game_id) {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE game_id = ? AND club_id = ?");
    $stmt->execute([$game_id, $club_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$game) {
        $game_id = null;
    }
}

// Fetch all games for dropdown filter
$games_stmt = $pdo->prepare("SELECT game_id, game_name, game_type FROM games WHERE club_id = ? ORDER BY game_name ASC");
$games_stmt->execute([$club_id]);
$all_games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'played_at';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'desc';

$allowed_sorts = ['played_at', 'winner_name', 'game_name', 'game_type'];
$sort = in_array($sort, $allowed_sorts) ? $sort : 'played_at';
$order = ($order === 'asc') ? 'asc' : 'desc';

$fetch_indiv = empty($type_filter) || in_array($type_filter, ['winner_losers', 'ranked', 'win_lose', 'individual'], true);
$fetch_teams = empty($type_filter) || in_array($type_filter, ['teams', 'team'], true);
$fetch_coop  = empty($type_filter) || in_array($type_filter, ['cooperative', 'coop'], true);

// Build SQL query for results (nicknames only for public view)
$results = [];
try {
    if ($game_id) {
        if ($fetch_indiv) {
            $indiv_extra = ($type_filter === 'winner_losers' || $type_filter === 'win_lose') ? " AND gr.place_2 IS NULL" : ($type_filter === 'ranked' ? " AND gr.place_2 IS NOT NULL" : "");
            $stmt = $pdo->prepare("
                SELECT gr.result_id, gr.played_at, COALESCE(NULLIF(m.nickname, ''), 'Member') as winner_name, 
                       CASE WHEN gr.place_2 IS NOT NULL THEN 'ranked' ELSE 'winner_losers' END as game_type, 
                       gr.duration, gr.notes, g.game_id, g.game_name, COALESCE(m.status, 'active') as member_status
                FROM game_results gr
                JOIN games g ON gr.game_id = g.game_id
                LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id
                WHERE g.club_id = ? AND g.game_id = ?" . $indiv_extra . "
            ");
            $stmt->execute([$club_id, $game_id]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($fetch_teams) {
            $stmt = $pdo->prepare("
                SELECT tgr.result_id, tgr.played_at, CONCAT(COALESCE(t.team_name, 'Unknown Team'), ' (Team)') as winner_name, 
                       'teams' as game_type, 
                       tgr.duration, tgr.notes, g.game_id, g.game_name, 'active' as member_status
                FROM team_game_results tgr
                JOIN games g ON tgr.game_id = g.game_id
                LEFT JOIN teams t ON tgr.winner = t.team_id
                WHERE g.club_id = ? AND g.game_id = ?
            ");
            $stmt->execute([$club_id, $game_id]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($fetch_coop) {
            $stmt = $pdo->prepare("
                SELECT cgr.result_id, cgr.played_at, CONCAT(UPPER(cgr.outcome), ' - Co-op') as winner_name, 
                       'cooperative' as game_type, 
                       cgr.duration, cgr.notes, g.game_id, g.game_name, 'active' as member_status
                FROM cooperative_game_results cgr
                JOIN games g ON cgr.game_id = g.game_id
                WHERE g.club_id = ? AND g.game_id = ?
            ");
            $stmt->execute([$club_id, $game_id]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    } else {
        if ($fetch_indiv) {
            $indiv_extra = ($type_filter === 'winner_losers' || $type_filter === 'win_lose') ? " AND gr.place_2 IS NULL" : ($type_filter === 'ranked' ? " AND gr.place_2 IS NOT NULL" : "");
            $stmt = $pdo->prepare("
                SELECT gr.result_id, gr.played_at, COALESCE(NULLIF(m.nickname, ''), 'Member') as winner_name, 
                       CASE WHEN gr.place_2 IS NOT NULL THEN 'ranked' ELSE 'winner_losers' END as game_type, 
                       gr.duration, gr.notes, g.game_id, g.game_name, COALESCE(m.status, 'active') as member_status
                FROM game_results gr
                JOIN games g ON gr.game_id = g.game_id
                LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id
                WHERE g.club_id = ?" . $indiv_extra . "
            ");
            $stmt->execute([$club_id]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($fetch_teams) {
            $stmt = $pdo->prepare("
                SELECT tgr.result_id, tgr.played_at, CONCAT(COALESCE(t.team_name, 'Unknown Team'), ' (Team)') as winner_name, 
                       'teams' as game_type, 
                       tgr.duration, tgr.notes, g.game_id, g.game_name, 'active' as member_status
                FROM team_game_results tgr
                JOIN games g ON tgr.game_id = g.game_id
                LEFT JOIN teams t ON tgr.winner = t.team_id
                WHERE g.club_id = ?
            ");
            $stmt->execute([$club_id]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($fetch_coop) {
            $stmt = $pdo->prepare("
                SELECT cgr.result_id, cgr.played_at, CONCAT(UPPER(cgr.outcome), ' - Co-op') as winner_name, 
                       'cooperative' as game_type, 
                       cgr.duration, cgr.notes, g.game_id, g.game_name, 'active' as member_status
                FROM cooperative_game_results cgr
                JOIN games g ON cgr.game_id = g.game_id
                WHERE g.club_id = ?
            ");
            $stmt->execute([$club_id]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    }
} catch (Throwable $e) {
    error_log("Results fetch failed: " . $e->getMessage());
    $results = [];
}

// Sort results array
usort($results, function($a, $b) use ($sort, $order) {
    $valA = $a[$sort] ?? '';
    $valB = $b[$sort] ?? '';
    if ($sort === 'played_at') {
        $cmp = strtotime((string)$valA) <=> strtotime((string)$valB);
    } else {
        $cmp = strcasecmp((string)$valA, (string)$valB);
    }
    return ($order === 'asc') ? $cmp : -$cmp;
});

$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
    <script src="js/i18n.js"></script>
    <script src="js/sidebar.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderSidebar('results', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Results (' . count($results) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php TableHelper::renderResultsAnalytics($results, $all_games, $game_id, ['is_admin' => false, 'type_filter' => $type_filter]); ?>

        <?php
        TableHelper::renderResultsTable($results, $all_games, [
            'is_admin' => false,
            'base_url' => 'club_game_results.php?' . $base_url_param,
            'sort' => $sort,
            'order' => $order,
            'game_id' => $game_id,
            'type_filter' => $type_filter,
            'club_id' => $club_id
        ]);
        ?>
    </div>
</body>
</html>
