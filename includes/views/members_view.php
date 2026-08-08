<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = !empty($is_admin);
$prefix = $isAdmin ? '../' : '';

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/NavigationHelper.php';
require_once dirname(__DIR__) . '/SecurityUtils.php';
require_once dirname(__DIR__) . '/services/ClubService.php';

if ($isAdmin) {
    $demo = isset($_GET['demo']) || isset($_GET['preview']);
    if (!$demo && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
        header("Location: login.php");
        exit();
    }
}

$security = new SecurityUtils($pdo);
$csrf_token = $security->generateCSRFToken();

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$slug = $_GET['slug'] ?? '';

$clubService = new ClubService($pdo);
$club = null;

if ($club_id || !empty($slug)) {
    $club = $clubService->getClubDetails($club_id, $slug);
}

if (!$club && $isAdmin) {
    if (!empty($_SESSION['current_club_id'])) {
        $club_id = (int)$_SESSION['current_club_id'];
        $club = $clubService->getClubDetails($club_id);
    }
}

if (!$club) {
    $stmt = $pdo->query("SELECT club_id FROM clubs ORDER BY club_id ASC LIMIT 1");
    $firstId = $stmt ? (int)$stmt->fetchColumn() : 0;
    if ($firstId > 0) {
        $club_id = $firstId;
        $club = $clubService->getClubDetails($club_id);
    }
}

if (!$club) {
    header("Location: " . ($isAdmin ? "login.php" : "index.php"));
    exit();
}

$club_id = (int)$club['club_id'];
$club_name = $club['club_name'] ?? 'Club Members';

if ($isAdmin && $club_id > 0) {
    $_SESSION['current_club_id'] = $club_id;
    $_SESSION['club_id'] = $club_id;
}

// Handle member creation/deletion and bulk actions for Admin
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_members.php?club_id=" . $club_id);
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create' && !empty($_POST['member_name']) && !empty($_POST['email'])) {
        $target_club_id = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : (int)$club_id;
        try {
            $stmt = $pdo->prepare("INSERT INTO members (club_id, admin_id, member_name, nickname, email, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([
                $target_club_id,
                $_SESSION['admin_id'] ?? null,
                trim($_POST['member_name']),
                trim($_POST['nickname']),
                trim($_POST['email'])
            ]);
            $_SESSION['success'] = "Member added successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding member: " . $e->getMessage();
        }
        header("Location: manage_members.php?club_id=" . $club_id);
        exit();
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : ($isAdmin ? 'member_name' : 'nickname');
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';

$query = "
    SELECT m.*, c.club_name,
           (SELECT GROUP_CONCAT(t.team_name ORDER BY t.team_name SEPARATOR ', ')
            FROM teams t
            WHERE t.club_id = m.club_id
              AND (t.member1_id = m.member_id OR t.member2_id = m.member_id
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

if ($status_filter !== 'all') {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
}

$valid_sort_columns = ['member_name', 'nickname', 'total_wins', 'championships_count', 'status'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : ($isAdmin ? 'member_name' : 'nickname');
if ($sort === 'total_wins' || $sort === 'championships_count') {
    $query .= " ORDER BY " . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');
} else {
    $query .= " ORDER BY m." . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aggregate member stats for multi-layered stacked bar chart
$chart_member_names = [];
$chart_individual_wins = [];
$chart_team_wins = [];

try {
    $displayNameSql = $isAdmin 
        ? "COALESCE(NULLIF(m.nickname, ''), m.member_name) as name" 
        : "COALESCE(NULLIF(m.nickname, ''), 'Member') as name";

    $stmt_stats = $pdo->prepare("
        SELECT m.member_id,
               {$displayNameSql},
               (
                    SELECT COUNT(*)
                    FROM game_results gr
                    WHERE COALESCE(gr.winner, gr.member_id) = m.member_id
                ) as ind_wins,
                (
                    SELECT COUNT(*)
                    FROM team_game_results tgr
                    JOIN teams t ON tgr.winner = t.team_id
                    WHERE (t.member1_id = m.member_id OR t.member2_id = m.member_id OR t.member3_id = m.member_id OR t.member4_id = m.member_id)
                ) as tm_wins
        FROM members m
        WHERE m.club_id = ? AND (m.status IS NULL OR m.status != 'inactive')
        ORDER BY (ind_wins + tm_wins) DESC, name ASC
        LIMIT 12
    ");
    $stmt_stats->execute([$club_id]);
    $rows = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $chart_member_names[] = $row['name'];
        $chart_individual_wins[] = (int)$row['ind_wins'];
        $chart_team_wins[] = (int)$row['tm_wins'];
    }
} catch (Throwable $e) {}

// Fetch Wins Over Time Per Member for line chart
$wot_labels = [];
$wot_datasets = [];
try {
    $stmt_months = $pdo->prepare("
        SELECT DISTINCT DATE_FORMAT(gr.played_at, '%Y-%m') as month_key
        FROM game_results gr
        JOIN games g ON gr.game_id = g.game_id
        WHERE g.club_id = ?
        ORDER BY month_key ASC
        LIMIT 12
    ");
    $stmt_months->execute([$club_id]);
    $all_months = array_column($stmt_months->fetchAll(PDO::FETCH_ASSOC), 'month_key');

    if (!empty($all_months)) {
        $wot_labels = array_map(fn($m) => date('M Y', strtotime($m . '-01')), $all_months);

        $nameSql = $isAdmin 
            ? "COALESCE(NULLIF(m.nickname, ''), m.member_name)"
            : "COALESCE(NULLIF(m.nickname, ''), 'Member')";

        $stmt_mwins = $pdo->prepare("
            SELECT {$nameSql} as name,
                   DATE_FORMAT(gr.played_at, '%Y-%m') as month_key,
                   COUNT(*) as wins
            FROM game_results gr
            JOIN members m ON m.member_id = gr.member_id
            JOIN games g ON gr.game_id = g.game_id
            WHERE g.club_id = ?
              AND COALESCE(gr.winner, gr.member_id) = m.member_id
              AND (m.status IS NULL OR m.status != 'inactive')
            GROUP BY m.member_id, month_key
            ORDER BY name, month_key
        ");
        $stmt_mwins->execute([$club_id]);
        $raw = $stmt_mwins->fetchAll(PDO::FETCH_ASSOC);

        $lookup = [];
        foreach ($raw as $row) {
            $lookup[$row['name']][$row['month_key']] = (int)$row['wins'];
        }

        foreach ($lookup as $name => $month_wins) {
            $series = [];
            $cumulative = 0;
            foreach ($all_months as $mk) {
                $cumulative += $month_wins[$mk] ?? 0;
                $series[] = $cumulative;
            }
            $wot_datasets[] = ['name' => $name, 'data' => $series];
        }
    }
} catch (Throwable $e) {}

$baseUrl = ($isAdmin ? 'manage_members.php?club_id=' : 'club_members.php?id=') . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="<?php echo $prefix; ?>css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo $prefix; ?>js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php
    if ($isAdmin) {
        NavigationHelper::renderAdminSidebar('members', $club_id, $club_name);
    } else {
        NavigationHelper::renderSidebar('members', $club_id, $club_name);
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Members (' . count($members) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php if ($isAdmin): ?>
            <?php display_session_message('success'); ?>
            <?php display_session_message('error'); ?>
        <?php endif; ?>

        <?php TableHelper::renderMembersAnalytics($chart_member_names, $chart_individual_wins, $chart_team_wins, $wot_labels, $wot_datasets, ['is_admin' => $isAdmin]); ?>

        <?php if ($isAdmin): ?>
            <div id="add-member-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add New Member</h3>
                <form method="POST" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="member_name">Full Name <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <input type="text" id="member_name" name="member_name" placeholder="Full Name" required class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="nickname">Nickname <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <input type="text" id="nickname" name="nickname" placeholder="Nickname (for public display)" required class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="email">Email Address <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <input type="email" id="email" name="email" placeholder="Email Address" required class="form-control">
                        </div>
                    </div>
                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <button type="submit" class="btn btn--primary">Save Member</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddMemberForm()">Cancel</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php
            TableHelper::renderMembersTable($members, [
                'is_admin' => $isAdmin,
                'base_url' => $baseUrl,
                'sort' => $sort,
                'order' => $order,
                'search' => $search,
                'status_filter' => $status_filter,
                'club_id' => $club_id,
                'csrf_token' => $csrf_token
            ]);
            ?>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <script>
            function toggleAddMemberForm() {
                const wrapper = document.getElementById('add-member-form-wrapper');
                const btn = document.getElementById('add-member-btn');
                if (!wrapper) return;
                const isHidden = wrapper.style.display === 'none';
                wrapper.style.display = isHidden ? 'block' : 'none';
                if (btn) btn.style.visibility = isHidden ? 'hidden' : 'visible';
            }
        </script>
    <?php endif; ?>
    <script src="<?php echo $prefix; ?>js/sidebar.js"></script>
    <script src="<?php echo $prefix; ?>js/form-loading.js"></script>
</body>
</html>
