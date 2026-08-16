<?php
require_once __DIR__ . '/../config/session.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/NavigationHelper.php';
require_once '../includes/SecurityUtils.php';

$demo = isset($_GET['demo']) || isset($_GET['preview']);
if (!$demo && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$csrf_token = $security->generateCSRFToken();
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;

$admin_clubs = [];
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.club_id, c.club_name 
            FROM clubs c
            JOIN club_admins ca ON c.club_id = ca.club_id
            WHERE ca.admin_id = ?
            ORDER BY c.club_name ASC
        ");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

if (!$club_id && !empty($_SESSION['current_club_id'])) {
    $club_id = (int)$_SESSION['current_club_id'];
}
if (!$club_id && !empty($_SESSION['club_id'])) {
    $club_id = (int)$_SESSION['club_id'];
}
if (!$club_id && !empty($admin_clubs)) {
    $club_id = (int)$admin_clubs[0]['club_id'];
}
if ($club_id > 0) {
    $_SESSION['current_club_id'] = $club_id;
    $_SESSION['club_id'] = $club_id;
}

$club = null;
if ($club_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
        $stmt->execute([$club_id]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

if ($demo || !$club) {
    $club_id = 1;
    $demoClub = function_exists('get_demo_data') ? get_demo_data('club') : [];
    $club = ['club_id' => 1, 'club_name' => $demoClub['club_name'] ?? 'Meeple Mosh'];
    $admin_clubs = [$club];
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'member_name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

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


if ($status_filter !== 'all') {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
}

$valid_sort_columns = ['member_name', 'nickname', 'total_wins', 'championships_count', 'status'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'member_name';
if ($sort === 'total_wins' || $sort === 'championships_count') {
    $query .= " ORDER BY " . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');
} else {
    $query .= " ORDER BY m." . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($demo) {
    $members = get_demo_data('members');
}

// Aggregate member stats for multi-layered stacked bar chart
$chart_member_names = [];
$chart_individual_wins = [];
$chart_team_wins = [];

if ($club_id) {
    try {
        $stmt_stats = $pdo->prepare("
            SELECT m.member_id,
                   COALESCE(NULLIF(m.nickname, ''), m.member_name) as name,
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
            WHERE m.club_id = ? AND (m.status IS NULL OR m.status != 'inactive') AND m.member_name NOT IN ('Unknown', 'Unknown Member') AND (m.nickname IS NULL OR m.nickname NOT IN ('Unknown', 'Unknown Member'))
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
    } catch (Exception $e) {}
}

if ($demo && empty($chart_member_names)) {
    $demo_ind = [18, 15, 12, 10, 9, 8, 7, 6, 5, 4, 3, 2];
    $demo_team = [11, 9, 7, 6, 5, 4, 4, 3, 2, 2, 1, 1];
    foreach ($members as $idx => $m) {
        $name = !empty($m['nickname']) ? $m['nickname'] : (!empty($m['member_name']) ? $m['member_name'] : 'Member ' . ($idx + 1));
        $chart_member_names[] = $name;
        $chart_individual_wins[] = $m['total_wins'] ?? ($demo_ind[$idx] ?? max(1, 12 - $idx));
        $chart_team_wins[] = $demo_team[$idx] ?? max(0, 6 - (int)floor($idx / 2));
    }
}

// Fetch Wins Over Time Per Member for line chart
$wot_labels = [];
$wot_datasets = [];
$wot_all_months = [];
$wot_monthly_wins = [];
if ($club_id) {
    try {
        $stmt_months = $pdo->prepare("
            SELECT DISTINCT DATE_FORMAT(gr.played_at, '%Y-%m') as month_key
            FROM game_results gr
            JOIN games g ON gr.game_id = g.game_id
            WHERE g.club_id = ?
            ORDER BY month_key ASC
        ");
        $stmt_months->execute([$club_id]);
        $all_months = array_column($stmt_months->fetchAll(PDO::FETCH_ASSOC), 'month_key');

        if (!empty($all_months)) {
            foreach ($all_months as $mk) {
                $wot_all_months[] = [
                    'key' => $mk,
                    'label' => date('M Y', strtotime($mk . '-01'))
                ];
            }

            $stmt_mwins = $pdo->prepare("
                SELECT COALESCE(NULLIF(m.nickname, ''), m.member_name) as name,
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

            foreach ($raw as $row) {
                $wot_monthly_wins[$row['name']][$row['month_key']] = (int)$row['wins'];
            }

            $wot_labels = array_map(fn($m) => $m['label'], $wot_all_months);
            foreach ($wot_monthly_wins as $name => $month_wins) {
                $series = [];
                $cumulative = 0;
                foreach ($all_months as $mk) {
                    $cumulative += $month_wins[$mk] ?? 0;
                    $series[] = $cumulative;
                }
                $wot_datasets[] = ['name' => $name, 'data' => $series];
            }
        }
    } catch (Exception $e) {}
}

if ($demo && empty($wot_all_months)) {
    $demo_months_keys = [];
    $start_ts = strtotime('-17 months');
    for ($i = 0; $i < 18; $i++) {
        $ts = strtotime("+$i months", $start_ts);
        $mk = date('Y-m', $ts);
        $lbl = date('M Y', $ts);
        $wot_all_months[] = ['key' => $mk, 'label' => $lbl];
        $demo_months_keys[] = $mk;
    }

    $demo_member_names = [
        'ShadowKnight', 'StarGazer', 'CyberSamurai', 'Vortex',
        'PixelMaster', 'Kingslayer', 'NeonRider', 'PointGod'
    ];

    foreach ($demo_member_names as $m_idx => $name) {
        foreach ($demo_months_keys as $idx => $mk) {
            $wot_monthly_wins[$name][$mk] = (($m_idx + 1) * ($idx + 2)) % 4;
        }
    }

    $wot_labels = array_map(fn($m) => $m['label'], $wot_all_months);
    foreach ($wot_monthly_wins as $name => $month_wins) {
        $series = [];
        $cumulative = 0;
        foreach ($demo_months_keys as $mk) {
            $cumulative += $month_wins[$mk] ?? 0;
            $series[] = $cumulative;
        }
        $wot_datasets[] = ['name' => $name, 'data' => $series];
    }
}

// Handle member creation/deletion and bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_members.php?club_id=" . $club_id);
        exit();
    }

    // Single member deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['member_id'])) {
        $del_member_id = (int)$_POST['member_id'];
        $target_club_id = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : (int)$club_id;
        if (delete_member_by_id($pdo, $del_member_id, $target_club_id)) {
            $_SESSION['success'] = "Member deleted successfully!";
        } else {
            $_SESSION['error'] = "Member not found or already deleted.";
        }
        header("Location: manage_members.php?club_id=" . $target_club_id);
        exit();
    }

    // Bulk member actions
    if (isset($_POST['bulk_action']) && !empty($_POST['selected_members']) && is_array($_POST['selected_members'])) {
        $bulk_action = $_POST['bulk_action'];
        $selected_members = array_filter(array_map('intval', $_POST['selected_members']));
        $target_club_id = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : (int)$club_id;

        if (!empty($selected_members)) {
            $count = 0;
            if ($bulk_action === 'bulk_delete') {
                foreach ($selected_members as $m_id) {
                    if (delete_member_by_id($pdo, $m_id, $target_club_id)) {
                        $count++;
                    }
                }
                $_SESSION['success'] = "$count member(s) deleted successfully!";
            } elseif ($bulk_action === 'bulk_activate') {
                $stmt = $pdo->prepare("UPDATE members SET status = 'active' WHERE member_id = ? AND club_id = ?");
                foreach ($selected_members as $m_id) {
                    if ($stmt->execute([$m_id, $target_club_id]) && $stmt->rowCount() > 0) {
                        $count++;
                    }
                }
                $_SESSION['success'] = "$count member(s) activated successfully!";
            } elseif ($bulk_action === 'bulk_deactivate') {
                $stmt = $pdo->prepare("UPDATE members SET status = 'inactive' WHERE member_id = ? AND club_id = ?");
                foreach ($selected_members as $m_id) {
                    if ($stmt->execute([$m_id, $target_club_id]) && $stmt->rowCount() > 0) {
                        $count++;
                    }
                }
                $_SESSION['success'] = "$count member(s) deactivated successfully!";
            }
        }
        header("Location: manage_members.php?club_id=" . $target_club_id);
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

$baseUrl = 'manage_members.php?club_id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('members', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club['club_name'] . ' Members (' . count($members) . ')'); ?>
    </div>

    <div class="container container--medium">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <?php TableHelper::renderMembersAnalytics($chart_member_names, $chart_individual_wins, $chart_team_wins, $wot_labels, $wot_datasets, ['is_admin' => true, 'open' => true, 'all_months' => $wot_all_months, 'monthly_wins' => $wot_monthly_wins]); ?>

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

        <?php
        TableHelper::renderMembersTable($members, [
            'is_admin' => true,
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
    <?php if (!empty($demo)): ?>
    <style>
    html, body, body * {
        pointer-events: none !important;
        user-select: none !important;
        cursor: default !important;
    }
    img {
        display: none !important;
    }
    *:hover, *:active, *:focus, *:focus-within {
        background: inherit !important;
        background-color: inherit !important;
        color: inherit !important;
        border-color: inherit !important;
        box-shadow: none !important;
        transform: none !important;
        transition: none !important;
        animation: none !important;
        outline: none !important;
        opacity: inherit !important;
    }
    .sidebar__nav a:hover, .sidebar__nav a:active, .sidebar__nav a:focus,
    .data-table tr:hover, .data-table tr:active, .data-table td:hover,
    .btn:hover, .btn:active, .btn:focus, button:hover, button:active,
    .form-control:hover, .form-control:active, .form-control:focus,
    a:hover, a:active, a:focus {
        background: transparent !important;
        background-color: transparent !important;
        color: inherit !important;
        box-shadow: none !important;
        transform: none !important;
    }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('a, button, input, select, textarea, details, summary').forEach(el => {
            el.setAttribute('tabindex', '-1');
            if (el.tagName === 'BUTTON' || el.tagName === 'INPUT' || el.tagName === 'SELECT') {
                el.setAttribute('disabled', 'disabled');
            }
        });
    });
    document.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); }, true);
    document.addEventListener('mouseover', function(e) { e.stopPropagation(); }, true);
    document.addEventListener('mouseenter', function(e) { e.stopPropagation(); }, true);
    document.addEventListener('mouseleave', function(e) { e.stopPropagation(); }, true);
    </script>
    <?php endif; ?>
    <script src="../js/sidebar.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-loading.js"></script>
</body>
</html>
