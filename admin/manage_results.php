<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/NavigationHelper.php';
require_once '../includes/SecurityUtils.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$csrf_token = $security->generateCSRFToken();

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
if (!$club_id && !empty($_SESSION['current_club_id'])) {
    $club_id = (int)$_SESSION['current_club_id'];
}
if (!$club_id && !empty($_SESSION['club_id'])) {
    $club_id = (int)$_SESSION['club_id'];
}

if (!$club_id && isset($_SESSION['admin_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT c.club_id FROM clubs c JOIN club_admins ca ON c.club_id = ca.club_id WHERE ca.admin_id = ? ORDER BY c.club_name ASC LIMIT 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $club_id = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}
}
if (!$club_id) {
    try {
        $club_id = (int)$pdo->query("SELECT club_id FROM clubs ORDER BY club_id ASC LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {}
}
if ($club_id > 0) {
    $_SESSION['current_club_id'] = $club_id;
    $_SESSION['club_id'] = $club_id;
}

$club = null;
if ($club_id) {
    $stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
}
$club_name = $club['club_name'] ?? 'StatApp Admin';

// Optional game_id filter
$game_id = (isset($_GET['game_id']) && $_GET['game_id'] !== '') ? (int)$_GET['game_id'] : null;
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

ensure_results_tables_exist($pdo);

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['selected_results'])) {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_results.php?club_id=$club_id" . ($game_id ? "&game_id=$game_id" : ""));
        exit();
    }

    $selected_results = $_POST['selected_results'];
    $bulk_action = $_POST['bulk_action'];

    if ($bulk_action === 'bulk_delete') {
        $password = $_POST['password'] ?? '';
        if (!verify_admin_password($password, $pdo)) {
            $_SESSION['error'] = "Incorrect password. Bulk delete cancelled.";
            header("Location: manage_results.php?club_id=$club_id" . ($game_id ? "&game_id=$game_id" : ""));
            exit();
        }
        $deleted_count = 0;
        foreach ($selected_results as $item) {
            $parts = explode(':', $item, 2);
            if (count($parts) === 2) {
                $type = $parts[0];
                $res_id = (int)$parts[1];
                if ($type === 'individual') {
                    $stmt = $pdo->prepare("DELETE gr FROM game_results gr JOIN games g ON gr.game_id = g.game_id WHERE gr.result_id = ? AND g.club_id = ?");
                    if ($stmt->execute([$res_id, $club_id])) $deleted_count++;
                } elseif ($type === 'team') {
                    $stmt = $pdo->prepare("DELETE tgr FROM team_game_results tgr JOIN games g ON tgr.game_id = g.game_id WHERE tgr.result_id = ? AND g.club_id = ?");
                    if ($stmt->execute([$res_id, $club_id])) $deleted_count++;
                } elseif ($type === 'cooperative') {
                    $stmt = $pdo->prepare("DELETE cgr FROM cooperative_game_results cgr JOIN games g ON cgr.game_id = g.game_id WHERE cgr.result_id = ? AND g.club_id = ?");
                    if ($stmt->execute([$res_id, $club_id])) $deleted_count++;
                }
            }
        }
        $_SESSION['success'] = "$deleted_count result(s) deleted successfully!";
    }
    header("Location: manage_results.php?club_id=$club_id" . ($game_id ? "&game_id=$game_id" : ""));
    exit();
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'played_at';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'desc';

$allowed_sorts = ['played_at', 'winner_name', 'game_name', 'game_type'];
$sort = in_array($sort, $allowed_sorts) ? $sort : 'played_at';
$order = ($order === 'asc') ? 'asc' : 'desc';

// Build SQL query for results
$results = [];
try {
    if ($game_id) {
        // Individual results
        $stmt = $pdo->prepare("
            SELECT gr.result_id, gr.played_at, COALESCE(NULLIF(m.nickname, ''), m.member_name, 'Unknown Member') as winner_name, COALESCE(g.game_type, 'winner_losers') as game_type, gr.duration, gr.notes, g.game_id, g.game_name, COALESCE(m.status, 'active') as member_status
            FROM game_results gr
            JOIN games g ON gr.game_id = g.game_id
            LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id
            WHERE g.club_id = ? AND g.game_id = ?
        ");
        $stmt->execute([$club_id, $game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Team results
        $stmt = $pdo->prepare("
            SELECT tgr.result_id, tgr.played_at, CONCAT(COALESCE(t.team_name, 'Unknown Team'), ' (Team)') as winner_name, COALESCE(g.game_type, 'teams') as game_type, tgr.duration, tgr.notes, g.game_id, g.game_name, 'active' as member_status
            FROM team_game_results tgr
            JOIN games g ON tgr.game_id = g.game_id
            LEFT JOIN teams t ON tgr.winner = t.team_id
            WHERE g.club_id = ? AND g.game_id = ?
        ");
        $stmt->execute([$club_id, $game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Cooperative results
        $stmt = $pdo->prepare("
            SELECT cgr.result_id, cgr.played_at, CONCAT(UPPER(cgr.outcome), ' - Co-op') as winner_name, COALESCE(g.game_type, 'cooperative') as game_type, cgr.duration, cgr.notes, g.game_id, g.game_name, 'active' as member_status
            FROM cooperative_game_results cgr
            JOIN games g ON cgr.game_id = g.game_id
            WHERE g.club_id = ? AND g.game_id = ?
        ");
        $stmt->execute([$club_id, $game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        // Individual results
        $stmt = $pdo->prepare("
            SELECT gr.result_id, gr.played_at, COALESCE(NULLIF(m.nickname, ''), m.member_name, 'Unknown Member') as winner_name, COALESCE(g.game_type, 'winner_losers') as game_type, gr.duration, gr.notes, g.game_id, g.game_name, COALESCE(m.status, 'active') as member_status
            FROM game_results gr
            JOIN games g ON gr.game_id = g.game_id
            LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id
            WHERE g.club_id = ?
        ");
        $stmt->execute([$club_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Team results
        $stmt = $pdo->prepare("
            SELECT tgr.result_id, tgr.played_at, CONCAT(COALESCE(t.team_name, 'Unknown Team'), ' (Team)') as winner_name, COALESCE(g.game_type, 'teams') as game_type, tgr.duration, tgr.notes, g.game_id, g.game_name, 'active' as member_status
            FROM team_game_results tgr
            JOIN games g ON tgr.game_id = g.game_id
            LEFT JOIN teams t ON tgr.winner = t.team_id
            WHERE g.club_id = ?
        ");
        $stmt->execute([$club_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Cooperative results
        $stmt = $pdo->prepare("
            SELECT cgr.result_id, cgr.played_at, CONCAT(UPPER(cgr.outcome), ' - Co-op') as winner_name, COALESCE(g.game_type, 'cooperative') as game_type, cgr.duration, cgr.notes, g.game_id, g.game_name, 'active' as member_status
            FROM cooperative_game_results cgr
            JOIN games g ON cgr.game_id = g.game_id
            WHERE g.club_id = ?
        ");
        $stmt->execute([$club_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
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

$baseUrl = 'manage_results.php?club_id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Results - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('results', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Results (' . count($results) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <?php TableHelper::renderResultsAnalytics($results, $all_games, $game_id, ['is_admin' => true]); ?>

        <?php
        TableHelper::renderResultsTable($results, $all_games, [
            'is_admin' => true,
            'base_url' => $baseUrl,
            'sort' => $sort,
            'order' => $order,
            'game_id' => $game_id,
            'club_id' => $club_id,
            'csrf_token' => $csrf_token
        ]);
        ?>
    </div>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
</body>
</html>
