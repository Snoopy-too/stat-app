<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

$demo = isset($_GET['demo']) || isset($_GET['preview']);
if (!$demo && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$admin_clubs = [];

try {
    $stmt = $pdo->query("SELECT c.* FROM clubs c ORDER BY c.club_name");
    $admin_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
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
    $club = ['club_id' => 1, 'club_name' => 'Meeple & Dice Club'];
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
                OR t.member3_id = m.member_id OR t.member4_id = m.member_id)) as member_teams
    FROM members m
    JOIN clubs c ON m.club_id = c.club_id
    WHERE m.club_id = ?
";

$params = [$club_id];

if ($status_filter !== 'all') {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
}

// Define valid sort columns
$valid_sort_columns = ['member_name', 'nickname', 'email', 'status'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'member_name';
$query .= " ORDER BY m." . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($demo) {
    $members = [
        ['member_id' => 1, 'member_name' => 'Alex Rivers', 'nickname' => 'Alex', 'email' => 'alex@example.com', 'status' => 'active', 'club_name' => 'Meeple & Dice Club'],
        ['member_id' => 2, 'member_name' => 'Sam Taylor', 'nickname' => 'Sam', 'email' => 'sam@example.com', 'status' => 'active', 'club_name' => 'Meeple & Dice Club'],
        ['member_id' => 3, 'member_name' => 'Jordan Lee', 'nickname' => 'Jordan', 'email' => 'jordan@example.com', 'status' => 'active', 'club_name' => 'Meeple & Dice Club'],
        ['member_id' => 4, 'member_name' => 'Casey Morgan', 'nickname' => 'Casey', 'email' => 'casey@example.com', 'status' => 'active', 'club_name' => 'Meeple & Dice Club']
    ];
}

// Aggregate member stats (individual wins & team wins) for multi-layered stacked bar chart
$chart_member_names = [];
$chart_individual_wins = [];
$chart_team_wins = [];

if (!$demo && $club_id) {
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
            WHERE m.club_id = ?
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
    } catch (Exception $e) {
        $chart_member_names = [];
    }
}

if (empty($chart_member_names)) {
    foreach ($members as $idx => $m) {
        $name = !empty($m['nickname']) ? $m['nickname'] : (!empty($m['member_name']) ? $m['member_name'] : 'Member ' . ($idx + 1));
        $chart_member_names[] = $name;
        $chart_individual_wins[] = max(1, 8 - ($idx * 2));
        $chart_team_wins[] = max(0, 4 - $idx);
    }
}

// Wins Over Time Per Member (multi-line chart)
$wot_labels = [];   // month labels
$wot_datasets = []; // [ ['name'=>..., 'data'=>[...]], ... ]

if (!$demo && $club_id) {
    try {
        // Get all months that have any wins for this club
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

            // Per-member cumulative wins per month
            $stmt_mwins = $pdo->prepare("
                SELECT COALESCE(NULLIF(m.nickname, ''), m.member_name) as name,
                       DATE_FORMAT(gr.played_at, '%Y-%m') as month_key,
                       COUNT(*) as wins
                FROM game_results gr
                JOIN members m ON m.member_id = gr.member_id
                JOIN games g ON gr.game_id = g.game_id
                WHERE g.club_id = ?
                  AND COALESCE(gr.winner, gr.member_id) = m.member_id
                GROUP BY m.member_id, month_key
                ORDER BY name, month_key
            ");
            $stmt_mwins->execute([$club_id]);
            $raw = $stmt_mwins->fetchAll(PDO::FETCH_ASSOC);

            // Build lookup: name -> month -> wins
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
    } catch (Exception $e) {
        $wot_labels = [];
        $wot_datasets = [];
    }
}

if (empty($wot_labels)) {
    // Demo fallback
    $wot_labels = ['Apr', 'May', 'Jun', 'Jul', 'Aug'];
    $demo_wins = [[1,2,2,3,4],[0,1,2,2,3],[0,0,1,2,2],[0,1,1,1,2]];
    foreach ($members as $idx => $m) {
        $name = !empty($m['nickname']) ? $m['nickname'] : ($m['member_name'] ?? 'Member ' . ($idx+1));
        $wot_datasets[] = ['name' => $name, 'data' => $demo_wins[$idx] ?? array_fill(0, 5, 0)];
        if ($idx >= 3) break;
    }
}

// Handle member creation/deletion and bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_members.php?club_id=" . $club_id);
        exit();
    }

    if (isset($_POST['bulk_action']) && !empty($_POST['selected_members'])) {
        $selected_members = $_POST['selected_members'];
        $bulk_action = $_POST['bulk_action'];
        
        try {
            switch ($bulk_action) {
                case 'bulk_activate':
                    $stmt = $pdo->prepare("UPDATE members SET status = 'active' WHERE member_id = ? AND club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)");
                    foreach ($selected_members as $member_id) {
                        $stmt->execute([$member_id, $club_id, $club_id, $_SESSION['admin_id']]);
                    }
                    $_SESSION['success'] = "Selected members activated successfully!";
                    break;
                    
                case 'bulk_deactivate':
                    $stmt = $pdo->prepare("UPDATE members SET status = 'inactive' WHERE member_id = ? AND club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)");
                    foreach ($selected_members as $member_id) {
                        $stmt->execute([$member_id, $club_id, $club_id, $_SESSION['admin_id']]);
                    }
                    $_SESSION['success'] = "Selected members deactivated successfully!";
                    break;
                    
                case 'bulk_delete':
                    $stmt = $pdo->prepare("DELETE FROM members WHERE member_id = ? AND club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)");
                    foreach ($selected_members as $member_id) {
                        $stmt->execute([$member_id, $club_id, $club_id, $_SESSION['admin_id']]);
                    }
                    $_SESSION['success'] = "Selected members deleted successfully!";
                    break;

                case 'bulk_email':
                    if (empty($_POST['email_subject']) || empty($_POST['email_message'])) {
                        $_SESSION['error'] = "Email subject and message are required.";
                        header("Location: manage_members.php?club_id=" . $club_id);
                        exit();
                    }

                    $subject = trim($_POST['email_subject']);
                    $message = trim($_POST['email_message']);

                    // Get member emails
                    $placeholders = str_repeat('?,', count($selected_members) - 1) . '?';
                    $stmt = $pdo->prepare("SELECT member_name, email FROM members WHERE member_id IN ($placeholders) AND club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)");
                    $params = array_merge($selected_members, [$club_id, $club_id, $_SESSION['admin_id']]);
                    $stmt->execute($params);
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $successCount = 0;
                    $failCount = 0;

                    foreach ($recipients as $recipient) {
                        // Prepare email
                        $to = $recipient['email'];
                        $memberName = $recipient['member_name'];

                        // Personalize message
                        $personalizedMessage = "Hello " . $memberName . ",\n\n" . $message . "\n\n---\nThis email sent from your board game club's administrator via The Flying Dutchmen StatApp";

                        // Email headers
                        $headers = "From: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'no-reply@' . $_SERVER['HTTP_HOST']) . "\r\n";
                        $headers .= "Reply-To: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'no-reply@' . $_SERVER['HTTP_HOST']) . "\r\n";
                        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                        $headers .= "X-Mailer: PHP/" . phpversion();

                        // Send email
                        if (mail($to, $subject, $personalizedMessage, $headers)) {
                            $successCount++;
                        } else {
                            $failCount++;
                            error_log("Failed to send email to: " . $to);
                        }
                    }

                    if ($successCount > 0 && $failCount === 0) {
                        $_SESSION['success'] = "Email sent successfully to {$successCount} member" . ($successCount !== 1 ? 's' : '') . "!";
                    } elseif ($successCount > 0 && $failCount > 0) {
                        $_SESSION['success'] = "Email sent to {$successCount} member" . ($successCount !== 1 ? 's' : '') . ", but failed for {$failCount}.";
                    } else {
                        $_SESSION['error'] = "Failed to send emails. Please check your server mail configuration.";
                    }
                    break;
            }
            
            header("Location: manage_members.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to perform bulk action: " . $e->getMessage();
            header("Location: manage_members.php?club_id=" . $club_id);
            exit();
        }
    } else if (isset($_POST['action'])) {
        // Update the INSERT query in the POST handling section
        if ($_POST['action'] === 'create' && !empty($_POST['member_name']) && !empty($_POST['email']) && !empty($_POST['club_id'])) {
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?");
                $stmt->execute([$_POST['club_id'], $_SESSION['admin_id']]);
                if (!$stmt->fetch()) {
                    throw new Exception("Unauthorized club access");
                }
                
                $stmt = $pdo->prepare("INSERT INTO members (club_id, admin_id, member_name, nickname, email, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([
                    $_POST['club_id'],
                    $_SESSION['admin_id'],
                    trim($_POST['member_name']),
                    trim($_POST['nickname']),
                    trim($_POST['email'])
                ]);
                $club_id = $_POST['club_id'];
                $_SESSION['success'] = "Member added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to add member: " . $e->getMessage();
            }
            header("Location: manage_members.php?club_id=" . $club_id);
            exit();

        } elseif ($_POST['action'] === 'delete' && !empty($_POST['member_id'])) {
            try {
                $del_member_id = (int)$_POST['member_id'];
                $stmt = $pdo->prepare("DELETE FROM members WHERE member_id = ? AND club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)");
                $stmt->execute([$del_member_id, $club_id, $club_id, $_SESSION['admin_id']]);
                $_SESSION['success'] = "Member deleted successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to delete member: " . $e->getMessage();
            }
            header("Location: manage_members.php?club_id=" . $club_id);
            exit();
        }
    }
}

// Generate CSRF token for forms
$csrf_token = $security->generateCSRFToken();
?>

<?php
$themeParam = $_GET['theme'] ?? $_GET['club_theme'] ?? ($demo ? 'arcade' : '');
$htmlThemeAttrs = $themeParam ? 'data-club-theme="' . htmlspecialchars($themeParam) . '" data-theme="dark" data-theme-locked="true"' : '';
?>
<!DOCTYPE html>
<html lang="en" <?php echo $htmlThemeAttrs; ?>>
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
        <?php NavigationHelper::renderCompactHeader('Manage Members (' . $club['club_name'] . ')'); ?>
        <div class="header-actions">
            <a href="../club_stats.php?id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small" target="_blank" title="View on public site">👁️ Preview</a>
        </div>
    </div>

    <div class="container container--wide">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <?php if (!empty($chart_member_names)): ?>
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2>Analytics & Trends</h2>
            <div style="margin-top: 1rem;">
                <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Wins for Members and Their Teams</h3>
                <div style="position: relative; height: 260px;">
                    <canvas id="memberWinsChart"></canvas>
                </div>
            </div>
            <div style="margin-top: 2rem;">
                <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Cumulative Wins Over Time</h3>
                <div style="position: relative; height: 260px;">
                    <canvas id="winRatesChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>Members (<?php echo count($members); ?>)</h2>
            </div>

            <div id="add-member-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add New Member</h3>
                <form method="POST" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="create">
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
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="club_id">Club <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <select name="club_id" id="club_id" required class="form-control">
                                <option value="">Select Club</option>
                                <?php foreach ($admin_clubs as $club_option): ?>
                                    <option value="<?php echo $club_option['club_id']; ?>" <?php echo ($club_id == $club_option['club_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($club_option['club_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <button type="submit" class="btn btn--primary">Save Member</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddMemberForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="card-toolbar">
                <button type="button" class="btn btn--primary" id="add-member-btn" onclick="toggleAddMemberForm()" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? 'visibility:hidden;' : ''; ?>">
                    <span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>Add a Member
                </button>
                <form method="GET" class="toolbar-group toolbar-group--grow" id="filter-form">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo strtolower($order); ?>">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="Search members..."
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <select name="status" id="status-filter" class="form-control form-control--sm" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </form>
                <form method="POST" class="toolbar-group" id="bulk-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <select name="bulk_action" id="bulk-action-select" class="form-control form-control--sm" onchange="executeBulkAction(this)">
                        <option value="">Bulk Actions</option>
                        <option value="bulk_activate">Activate Selected</option>
                        <option value="bulk_deactivate">Deactivate Selected</option>
                        <option value="bulk_email">Send Email to</option>
                        <option value="bulk_delete">Delete Selected</option>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" class="form-check-input"></th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&sort=member_name&order=<?php echo ($sort === 'member_name' && strtolower($order) === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="table-sort-link sort-link">
                                    <span>Name</span>
                                    <?php if ($sort === 'member_name'): ?>
                                        <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&sort=nickname&order=<?php echo ($sort === 'nickname' && strtolower($order) === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="table-sort-link sort-link">
                                    <span>Nickname</span>
                                    <?php if ($sort === 'nickname'): ?>
                                        <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&sort=email&order=<?php echo ($sort === 'email' && strtolower($order) === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="table-sort-link sort-link">
                                    <span>Email</span>
                                    <?php if ($sort === 'email'): ?>
                                        <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&sort=status&order=<?php echo ($sort === 'status' && strtolower($order) === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="table-sort-link sort-link">
                                    <span>Status</span>
                                    <?php if ($sort === 'status'): ?>
                                        <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Teams</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <tr id="noSearchMatch" style="display: none;">
                        <td colspan="7" class="text-center text-muted" style="padding: 1.5rem;">No members match your search.</td>
                    </tr>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 1.5rem;">No members created yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_members[]" form="bulk-form"
                                       value="<?php echo $member['member_id']; ?>" class="form-check-input member-checkbox">
                            </td>
                            <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['nickname']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $member['status']; ?>">
                                    <?php echo ucfirst($member['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($member['member_teams'])): ?>
                                    <?php foreach (explode(', ', $member['member_teams']) as $team): ?>
                                        <span style="display:inline-block; font-size:0.75rem; padding:0.15rem 0.5rem; border-radius:999px; background:var(--color-surface-muted); border:1px solid var(--color-border); margin:0.1rem;"><?php echo htmlspecialchars($team); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <div class="btn-group" style="display:flex; gap:0.35rem; align-items:center;">
                                    <a href="edit_member.php?club_id=<?php echo $club_id; ?>&member_id=<?php echo $member['member_id']; ?>" 
                                       class="btn btn--small btn--secondary">Edit</a>
                                    <form method="POST" style="display:inline; margin:0;" id="delete-form-<?php echo $member['member_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                        <button type="button" class="btn btn--small btn--danger" onclick="confirmDeleteMember(event, <?php echo $member['member_id']; ?>, '<?php echo addslashes($member['member_name']); ?>')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        </div>

        <!-- Email Modal -->
        <div id="email-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Send Email to Selected Members</h2>
                    <button type="button" class="modal-close" onclick="closeEmailModal()">&times;</button>
                </div>
                <form method="POST" id="email-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="bulk_action" value="bulk_email">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <div id="email-recipients-container"></div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label for="email-subject">Subject *</label>
                            <input type="text" id="email-subject" name="email_subject" class="form-control" required placeholder="Email subject">
                        </div>
                        <div class="form-group">
                            <label for="email-message">Message *</label>
                            <textarea id="email-message" name="email_message" class="form-control" rows="10" required placeholder="Type your message here..."></textarea>
                            <p class="help-text">This message will be sent to all selected members.</p>
                        </div>
                    </div>
                    <div class="modal-footer" style="display:flex; gap:0.5rem; justify-content:flex-start;">
                        <button type="submit" class="btn btn--primary" id="send-email-btn">Send Email</button>
                        <button type="button" class="btn btn--subtle" onclick="closeEmailModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="email-loading-overlay" class="loading-overlay" style="display: none;">
            <div class="loading-message">
                <div class="loading-spinner"></div>
                <h3>Sending Emails...</h3>
                <p>Please wait. Do not refresh or leave this page.</p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.member-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Handle bulk action selection trigger
        function executeBulkAction(selectEl) {
            const action = selectEl.value;
            if (!action) return;

            const selectedCheckboxes = document.querySelectorAll('.member-checkbox:checked');

            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one member.');
                selectEl.value = '';
                return;
            }

            if (action === 'bulk_email') {
                openEmailModal(selectedCheckboxes);
                selectEl.value = '';
            } else {
                let title = 'Confirm Action';
                let message = `Are you sure you want to perform this action on ${selectedCheckboxes.length} selected member(s)?`;
                let confirmText = 'Confirm';
                let type = 'primary';
                let warningMessage = null;

                if (action === 'bulk_activate') {
                    title = 'Activate Selected Members?';
                    message = `Are you sure you want to activate ${selectedCheckboxes.length} selected member(s)?`;
                    confirmText = 'Activate Members';
                    type = 'primary';
                } else if (action === 'bulk_deactivate') {
                    title = '⚠️ Deactivate Selected Members?';
                    message = `Are you sure you want to deactivate ${selectedCheckboxes.length} selected member(s)?`;
                    confirmText = 'Deactivate Members';
                    type = 'warning';
                    warningMessage = 'Selected members will be marked inactive.';
                } else if (action === 'bulk_delete') {
                    title = '⚠️ Delete Selected Members?';
                    message = `Are you sure you want to delete ${selectedCheckboxes.length} selected member(s)?`;
                    confirmText = 'Delete Members';
                    type = 'danger';
                    warningMessage = 'Selected member records and statistics will be permanently removed.';
                }

                showConfirmDialog(null, {
                    title: title,
                    message: message,
                    confirmText: confirmText,
                    cancelText: 'Cancel',
                    type: type,
                    warningMessage: warningMessage,
                    onConfirm: () => {
                        document.getElementById('bulk-form').submit();
                    },
                    onCancel: () => {
                        selectEl.value = '';
                    }
                });
            }
        }

        function confirmDeleteMember(event, memberId, memberName) {
            if (event) event.preventDefault();
            showConfirmDialog(event, {
                title: '⚠️ Delete Member',
                message: `Are you sure you want to delete <strong>${memberName}</strong>?`,
                confirmText: 'Delete Member',
                cancelText: 'Cancel',
                type: 'danger',
                warningMessage: 'This action is permanent and cannot be undone. Member match history will be removed.',
                onConfirm: () => {
                    const form = document.getElementById('delete-form-' + memberId);
                    if (form) form.submit();
                }
            });
        }

        function openEmailModal(selectedCheckboxes) {
            const modal = document.getElementById('email-modal');
            const recipientsContainer = document.getElementById('email-recipients-container');

            // Clear previous recipients
            recipientsContainer.innerHTML = '';

            // Add hidden inputs for selected members
            selectedCheckboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_members[]';
                input.value = checkbox.value;
                recipientsContainer.appendChild(input);
            });

            // Show recipient count
            const count = selectedCheckboxes.length;
            const countText = document.createElement('p');
            countText.className = 'help-text';
            countText.textContent = `Sending to ${count} member${count !== 1 ? 's' : ''}`;
            recipientsContainer.appendChild(countText);

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeEmailModal() {
            const modal = document.getElementById('email-modal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
            document.getElementById('email-form').reset();
        }

        // Close modal when clicking outside
        document.getElementById('email-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEmailModal();
            }
        });

        // Handle email form submission
        document.getElementById('email-form').addEventListener('submit', function(e) {
            // Show loading overlay
            document.getElementById('email-loading-overlay').style.display = 'flex';
            document.getElementById('email-modal').style.display = 'none';

            // Disable the send button to prevent double submission
            document.getElementById('send-email-btn').disabled = true;
        });

        function toggleAddMemberForm() {
            const wrapper = document.getElementById('add-member-form-wrapper');
            const btn = document.getElementById('add-member-btn');
            if (!wrapper) return;
            if (wrapper.style.display === 'none' || wrapper.style.display === '') {
                wrapper.style.display = 'block';
                if (btn) btn.style.visibility = 'hidden';
                document.getElementById('member_name')?.focus();
            } else {
                wrapper.style.display = 'none';
                if (btn) btn.style.visibility = 'visible';
            }
        }

         // Handle sorting links with AJAX using event delegation on the body
         document.body.addEventListener('click', function(e) {
             if (e.target.matches('.sort-link')) {
                e.preventDefault();
                const url = e.target.href;
                const currentTable = document.querySelector('.data-table');
                const tableTopOffset = currentTable ? currentTable.getBoundingClientRect().top : 0; // Save table's top offset relative to viewport
                const currentScrollY = window.scrollY;

                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                         const parser = new DOMParser();
                         const doc = parser.parseFromString(html, 'text/html');
                         const newTableHeaderContent = doc.querySelector('.data-table thead').innerHTML;
                         const newTableRows = doc.querySelectorAll('.data-table tbody tr');
                         const currentTableHeader = document.querySelector('.data-table thead');
                         const currentTableBody = document.querySelector('.data-table tbody');

                         if (currentTableHeader && newTableHeaderContent) {
                             currentTableHeader.innerHTML = newTableHeaderContent; // Update header content
                         }
                         if (currentTableBody) {
                             currentTableBody.innerHTML = ''; // Clear existing rows
                             newTableRows.forEach(row => {
                                 currentTableBody.appendChild(row.cloneNode(true)); // Append new rows (clone to avoid issues)
                             });
                         }

                         window.history.pushState({}, '', url); // Update URL
                         // Use setTimeout to restore scroll after event loop turn
                         setTimeout(() => {
                             window.scrollTo(0, currentScrollY); // Restore original scroll position
                         }, 0);
                    })
                      .catch(error => console.error('Error:', error));
             }
         });

        const statusFilter = document.getElementById('status-filter');
        const filterForm = document.getElementById('filter-form');
        if (statusFilter && filterForm) {
            statusFilter.addEventListener('change', function() {
                filterForm.submit();
            });
        }

        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            const filterMembers = () => {
                const query = searchInput.value.toLowerCase().trim();
                const rows = document.querySelectorAll('.data-table tbody tr:not(#noSearchMatch)');
                let visibleCount = 0;
                let hasOriginalRows = false;
                rows.forEach(row => {
                    if (row.querySelector('.text-muted') && rows.length === 1) return;
                    hasOriginalRows = true;
                    const text = row.textContent.toLowerCase();
                    if (text.includes(query)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                const noMatch = document.getElementById('noSearchMatch');
                if (noMatch) {
                    noMatch.style.display = (visibleCount === 0 && query !== '' && hasOriginalRows) ? '' : 'none';
                }
            };
            searchInput.addEventListener('input', filterMembers);
            if (searchInput.value) filterMembers();
        }

        // Active Member Multi-Layered Stacked Bar Chart
        (function() {
            const memberNames = <?php echo json_encode($chart_member_names); ?>;
            const indWins = <?php echo json_encode($chart_individual_wins); ?>;
            const teamWins = <?php echo json_encode($chart_team_wins); ?>;
            let memberWinsChart = null;
            
            function getThemeColors() {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                let text = isDark ? '#f1f5f9' : '#1e293b';
                let border = isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.08)';
                const style = getComputedStyle(document.body);
                const cssText = style.getPropertyValue('--color-text').trim();
                const cssBorder = style.getPropertyValue('--color-border').trim();
                if (cssText) text = cssText;
                if (cssBorder) border = cssBorder;
                return { text, border };
            }

            const colors = getThemeColors();

            const winsCtx = document.getElementById('memberWinsChart');
            if (winsCtx && memberNames.length > 0) {
                memberWinsChart = new Chart(winsCtx, {
                    type: 'bar',
                    data: {
                        labels: memberNames,
                        datasets: [
                            {
                                label: 'Individual Wins',
                                data: indWins,
                                backgroundColor: '#3b82f6',
                                borderRadius: 4
                            },
                            {
                                label: 'Team Wins',
                                data: teamWins,
                                backgroundColor: '#8b5cf6',
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    color: colors.text,
                                    font: { family: 'inherit', size: 13, weight: '500' },
                                    padding: 14
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                ticks: { color: colors.text, font: { family: 'inherit', size: 12 } },
                                grid: { color: colors.border }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: { color: colors.text, stepSize: 1, precision: 0, font: { family: 'inherit', size: 12 } },
                                grid: { color: colors.border }
                            }
                        }
                    }
                });
            }

            // Wins Over Time Per Member (multi-line)
            const wotLabels = <?php echo json_encode($wot_labels); ?>;
            const wotDatasets = <?php echo json_encode($wot_datasets); ?>;
            let winRatesChart = null;

            const lineColors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#f43f5e','#6366f1'];

            const winrateCtx = document.getElementById('winRatesChart');
            if (winrateCtx && wotLabels.length > 0 && wotDatasets.length > 0) {
                winRatesChart = new Chart(winrateCtx, {
                    type: 'line',
                    data: {
                        labels: wotLabels,
                        datasets: wotDatasets.map((ds, i) => ({
                            label: ds.name,
                            data: ds.data,
                            borderColor: lineColors[i % lineColors.length],
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            borderWidth: 2
                        }))
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    color: colors.text,
                                    font: { family: 'inherit', size: 12 },
                                    padding: 12,
                                    boxWidth: 12
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: colors.text, font: { family: 'inherit', size: 12 } },
                                grid: { color: colors.border }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { color: colors.text, stepSize: 1, precision: 0, font: { family: 'inherit', size: 12 } },
                                grid: { color: colors.border }
                            }
                        }
                    }
                });
            }

            function updateChartTheme() {
                const c = getThemeColors();
                if (memberWinsChart && memberWinsChart.options.scales) {
                    if (memberWinsChart.options.plugins && memberWinsChart.options.plugins.legend) {
                        memberWinsChart.options.plugins.legend.labels.color = c.text;
                    }
                    memberWinsChart.options.scales.x.ticks.color = c.text;
                    memberWinsChart.options.scales.x.grid.color = c.border;
                    memberWinsChart.options.scales.y.ticks.color = c.text;
                    memberWinsChart.options.scales.y.grid.color = c.border;
                    memberWinsChart.update();
                }
                if (winRatesChart && winRatesChart.options.scales) {
                    winRatesChart.options.scales.x.ticks.color = c.text;
                    winRatesChart.options.scales.x.grid.color = c.border;
                    winRatesChart.options.scales.y.ticks.color = c.text;
                    winRatesChart.options.scales.y.grid.color = c.border;
                    winRatesChart.update();
                }
            }

            window.addEventListener('themechange', updateChartTheme);
            const observer = new MutationObserver(updateChartTheme);
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        })();
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
