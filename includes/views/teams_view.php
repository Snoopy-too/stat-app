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
$club_name = $club['club_name'] ?? 'Club Teams';

if ($isAdmin && $club_id > 0) {
    $_SESSION['current_club_id'] = $club_id;
    $_SESSION['club_id'] = $club_id;
}

// Fetch active members for team creation (Admin)
$club_members = [];
if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT member_id, nickname, member_name FROM members WHERE club_id = ? AND (status IS NULL OR status = 'active') ORDER BY nickname ASC");
    $stmt->execute([$club_id]);
    $club_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle Team creation/deletion for Admin
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_teams.php?club_id=" . $club_id);
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_team') {
        $team_name = trim($_POST['team_name'] ?? '');
        $m1 = (int)($_POST['member1_id'] ?? 0);
        $m2 = (int)($_POST['member2_id'] ?? 0);
        $m3 = !empty($_POST['member3_id']) ? (int)$_POST['member3_id'] : null;
        $m4 = !empty($_POST['member4_id']) ? (int)$_POST['member4_id'] : null;

        if (!empty($team_name) && $m1 > 0 && $m2 > 0 && $m1 !== $m2) {
            try {
                $stmt = $pdo->prepare("INSERT INTO teams (club_id, team_name, member1_id, member2_id, member3_id, member4_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$club_id, $team_name, $m1, $m2, $m3, $m4]);
                $_SESSION['success'] = "Team created successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error creating team: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Team name and at least two distinct members are required.";
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
if ($search !== '') {
    $query .= " AND (t.team_name LIKE ? OR m1.nickname LIKE ? OR m2.nickname LIKE ? OR m3.nickname LIKE ? OR m4.nickname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY wins DESC, t.team_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = ($isAdmin ? 'manage_teams.php?club_id=' : 'club_teams.php?id=') . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="<?php echo $prefix; ?>css/styles.css">
    <script src="<?php echo $prefix; ?>js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php
    if ($isAdmin) {
        NavigationHelper::renderAdminSidebar('teams', $club_id, $club_name);
    } else {
        NavigationHelper::renderSidebar('teams', $club_id, $club_name);
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Teams (' . count($teams) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php if ($isAdmin): ?>
            <?php display_session_message('success'); ?>
            <?php display_session_message('error'); ?>

            <div id="add-team-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create_team') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add a Team</h3>
                <form method="POST" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="create_team">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="team_name">Team Name <span style="color:var(--color-error,#ef4444);">*</span></label>
                            <input type="text" id="team_name" name="team_name" placeholder="Team Name" required class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="member1_id">Member 1 <span style="color:var(--color-error,#ef4444);">*</span></label>
                            <select id="member1_id" name="member1_id" required class="form-control">
                                <option value="">Select Member</option>
                                <?php foreach ($club_members as $m): ?>
                                    <option value="<?php echo $m['member_id']; ?>"><?php echo htmlspecialchars($m['nickname'] ?: $m['member_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="member2_id">Member 2 <span style="color:var(--color-error,#ef4444);">*</span></label>
                            <select id="member2_id" name="member2_id" required class="form-control">
                                <option value="">Select Member</option>
                                <?php foreach ($club_members as $m): ?>
                                    <option value="<?php echo $m['member_id']; ?>"><?php echo htmlspecialchars($m['nickname'] ?: $m['member_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="member3_id">Member 3 (Optional)</label>
                            <select id="member3_id" name="member3_id" class="form-control">
                                <option value="">None</option>
                                <?php foreach ($club_members as $m): ?>
                                    <option value="<?php echo $m['member_id']; ?>"><?php echo htmlspecialchars($m['nickname'] ?: $m['member_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="member4_id">Member 4 (Optional)</label>
                            <select id="member4_id" name="member4_id" class="form-control">
                                <option value="">None</option>
                                <?php foreach ($club_members as $m): ?>
                                    <option value="<?php echo $m['member_id']; ?>"><?php echo htmlspecialchars($m['nickname'] ?: $m['member_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <button type="submit" class="btn btn--primary">Save Team</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddTeamForm()">Cancel</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php
            TableHelper::renderTeamsTable($teams, [
                'is_admin' => $isAdmin,
                'base_url' => $baseUrl,
                'search' => $search,
                'club_id' => $club_id
            ]);
            ?>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <script>
            function toggleAddTeamForm() {
                const wrapper = document.getElementById('add-team-form-wrapper');
                const btn = document.getElementById('toggle-add-team-btn');
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
