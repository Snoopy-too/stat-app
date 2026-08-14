<?php
session_start();
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
$user_clubs = [];

try {
    $stmt = $pdo->query("SELECT club_id, club_name FROM clubs ORDER BY club_name ASC");
    $user_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$club_id && !empty($_SESSION['current_club_id'])) {
    $club_id = (int)$_SESSION['current_club_id'];
}
if (!$club_id && !empty($_SESSION['club_id'])) {
    $club_id = (int)$_SESSION['club_id'];
}
if (!$club_id && !empty($user_clubs)) {
    $club_id = (int)$user_clubs[0]['club_id'];
}
if ($club_id > 0) {
    $_SESSION['current_club_id'] = $club_id;
    $_SESSION['club_id'] = $club_id;
}

$club_name = 'Meeple & Dice Club';
if ($club_id && !$demo) {
    try {
        $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
        $stmt->execute([$club_id]);
        $club_name = $stmt->fetchColumn() ?: 'Meeple & Dice Club';
    } catch (Exception $e) {}
}

// Fetch active members for dropdowns
$club_members = [];
try {
    $stmt = $pdo->prepare("SELECT member_id, nickname, member_name FROM members WHERE club_id = ? AND (status IS NULL OR status = 'active') ORDER BY nickname ASC");
    $stmt->execute([$club_id]);
    $club_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_teams.php?club_id=" . $club_id);
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_team') {
        $team_name = trim($_POST['team_name'] ?? '');
        $selected_members = [];
        if (!empty($_POST['team_members']) && is_array($_POST['team_members'])) {
            $selected_members = array_values(array_filter(array_map('intval', $_POST['team_members'])));
        } elseif (!empty($_POST['members']) && is_array($_POST['members'])) {
            $selected_members = array_values(array_filter(array_map('intval', $_POST['members'])));
        } else {
            $m1 = (int)($_POST['member1_id'] ?? 0);
            $m2 = (int)($_POST['member2_id'] ?? 0);
            $m3 = !empty($_POST['member3_id']) ? (int)$_POST['member3_id'] : null;
            $m4 = !empty($_POST['member4_id']) ? (int)$_POST['member4_id'] : null;
            $selected_members = array_values(array_filter([$m1, $m2, $m3, $m4]));
        }

        $m1 = $selected_members[0] ?? 0;
        $m2 = $selected_members[1] ?? 0;
        $m3 = $selected_members[2] ?? null;
        $m4 = $selected_members[3] ?? null;

        if (!empty($team_name) && !empty($selected_members)) {
            try {
                $m1 = $selected_members[0] ?? null;
                $m2 = $selected_members[1] ?? null;
                $m3 = $selected_members[2] ?? null;
                $m4 = $selected_members[3] ?? null;
                $stmt = $pdo->prepare("INSERT INTO teams (club_id, team_name, member1_id, member2_id, member3_id, member4_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$club_id, $team_name, $m1, $m2, $m3, $m4]);
                $team_id = (int)$pdo->lastInsertId();
                save_team_members($pdo, $team_id, $selected_members);
                $_SESSION['success'] = "Team created successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error creating team: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Team name and at least one member are required.";
        }
        header("Location: manage_teams.php?club_id=" . $club_id);
        exit();
    }
}

// Fetch Teams
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "
    SELECT t.*,
           m1.nickname as member1_nickname,
           m2.nickname as member2_nickname,
           m3.nickname as member3_nickname,
           m4.nickname as member4_nickname,
           (SELECT COUNT(*) FROM team_game_results tgr WHERE tgr.winner = t.team_id) as wins,
           (SELECT COUNT(*) FROM team_game_results tgr WHERE tgr.winner = t.team_id OR tgr.place_2 = t.team_id OR tgr.place_3 = t.team_id OR tgr.place_4 = t.team_id) as total_plays
    FROM teams t
    LEFT JOIN members m1 ON t.member1_id = m1.member_id
    LEFT JOIN members m2 ON t.member2_id = m2.member_id
    LEFT JOIN members m3 ON t.member3_id = m3.member_id
    LEFT JOIN members m4 ON t.member4_id = m4.member_id
    WHERE t.club_id = ?
";
$params = [$club_id];
$query .= " ORDER BY wins DESC, t.team_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($demo) {
    $teams = get_demo_data('teams');
}

if (!empty($teams)) {
    $teamMembersMap = get_team_members_map($pdo, $teams);
    foreach ($teams as &$t) {
        if (isset($teamMembersMap[$t['team_id']])) {
            $t['members_list'] = array_column($teamMembersMap[$t['team_id']], 'nickname');
        }
    }
    unset($t);
}

$baseUrl = 'manage_teams.php?club_id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teams - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('teams', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Teams (' . count($teams) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div id="add-team-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create_team') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
            <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add a Team</h3>
            <form method="POST" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="create_team">
                <div style="margin-bottom: 1.25rem;">
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="team_name">Team Name <span style="color:var(--color-error,#ef4444);">*</span></label>
                        <input type="text" id="team_name" name="team_name" placeholder="Team Name" required class="form-control" style="max-width: 400px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                            <label class="form-label" style="margin:0;">Select Team Members: <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <div style="display:flex; gap:0.5rem;">
                                <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.team-member-checkbox', true)">Select All</button>
                                <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.team-member-checkbox', false)">Uncheck All</button>
                            </div>
                        </div>
                        <div id="team-members-checkbox-list" class="checkbox-grid">
                            <?php foreach ($club_members as $m): ?>
                                <label for="team_member_<?php echo $m['member_id']; ?>" class="form-check checkbox-item">
                                    <input type="checkbox" name="team_members[]" id="team_member_<?php echo $m['member_id']; ?>" value="<?php echo $m['member_id']; ?>" class="form-check-input team-member-checkbox">
                                    <span class="form-check-label"><?php echo htmlspecialchars($m['nickname'] ?: $m['member_name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                    <button type="submit" class="btn btn--primary">Save Team</button>
                    <button type="button" class="btn btn--subtle" onclick="toggleAddTeamForm()">Cancel</button>
                </div>
            </form>
        </div>

        <?php
        TableHelper::renderTeamsTable($teams, [
            'is_admin' => true,
            'base_url' => $baseUrl,
            'search' => $search,
            'club_id' => $club_id
        ]);
        ?>
    </div>

    <script>
        function toggleAddTeamForm() {
            const wrapper = document.getElementById('add-team-form-wrapper');
            const btn = document.getElementById('toggle-add-team-btn');
            if (!wrapper) return;
            const isHidden = wrapper.style.display === 'none';
            wrapper.style.display = isHidden ? 'block' : 'none';
            if (btn) btn.style.visibility = isHidden ? 'hidden' : 'visible';
        }
        function toggleAllCheckboxes(selector, checkedState) {
            document.querySelectorAll(selector).forEach(cb => {
                cb.checked = checkedState;
            });
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
    <script src="../js/form-loading.js"></script>
</body>
</html>
