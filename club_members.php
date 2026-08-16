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
$club_name = $club['club_name'] ?? 'Club Members';

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'nickname';
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


if ($status_filter !== 'all') {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
}

$valid_sort_columns = ['nickname', 'total_wins', 'championships_count', 'status'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'nickname';
if ($sort === 'total_wins' || $sort === 'championships_count') {
    $query .= " ORDER BY " . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');
} else {
    $query .= " ORDER BY m." . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aggregate member stats (individual wins & team wins) using nicknames for public display
$chart_member_names = [];
$chart_individual_wins = [];
$chart_team_wins = [];

try {
    $stmt_stats = $pdo->prepare("
        SELECT m.member_id,
               COALESCE(NULLIF(m.nickname, ''), 'Member') as name,
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
            SELECT COALESCE(NULLIF(m.nickname, ''), 'Member') as name,
                   DATE_FORMAT(gr.played_at, '%Y-%m') as month_key,
                   COUNT(*) as wins
            FROM game_results gr
            JOIN members m ON m.member_id = gr.member_id
            JOIN games g ON gr.game_id = g.game_id
            WHERE g.club_id = ?
              AND COALESCE(gr.winner, gr.member_id) = m.member_id
              AND (m.status IS NULL OR m.status != 'inactive')
              AND m.member_name NOT IN ('Unknown', 'Unknown Member') AND (m.nickname IS NULL OR m.nickname NOT IN ('Unknown', 'Unknown Member'))
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
} catch (Throwable $e) {}

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

$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
    <script src="js/sidebar.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderSidebar('members', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Members (' . count($members) . ')'); ?>
    </div>

    <div class="container container--medium">
        <?php TableHelper::renderMembersAnalytics($chart_member_names, $chart_individual_wins, $chart_team_wins, $wot_labels, $wot_datasets, ['is_admin' => false, 'all_months' => $wot_all_months, 'monthly_wins' => $wot_monthly_wins]); ?>

        <?php
        TableHelper::renderMembersTable($members, [
            'is_admin' => false,
            'base_url' => 'club_members.php?' . $base_url_param,
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'status_filter' => $status_filter,
            'club_id' => $club_id
        ]);
        ?>
    </div>
</body>
</html>
