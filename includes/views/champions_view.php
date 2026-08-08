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
$club_name = $club['club_name'] ?? 'Club Champions';

if ($isAdmin && $club_id > 0) {
    $_SESSION['current_club_id'] = $club_id;
    $_SESSION['club_id'] = $club_id;
}

// Fetch active members for champion creation (Admin)
$club_members = [];
if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT member_id, nickname, member_name FROM members WHERE club_id = ? AND (status IS NULL OR status = 'active') ORDER BY nickname ASC");
    $stmt->execute([$club_id]);
    $club_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle Champion creation/deletion for Admin
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_champions.php?club_id=" . $club_id);
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create' && !empty($_POST['member_id']) && !empty($_POST['champ_date'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO champions (club_id, member_id, date, champ_comments) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $club_id,
                (int)$_POST['member_id'],
                $_POST['champ_date'],
                trim($_POST['champ_comments'] ?? '')
            ]);
            $_SESSION['success'] = "Champion added successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding champion: " . $e->getMessage();
        }
        header("Location: manage_champions.php?club_id=" . $club_id);
        exit();
    }
}

// Fetch Champions
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
if ($search !== '') {
    $query .= " AND (m.member_name LIKE ? OR m.nickname LIKE ? OR c.champ_comments LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$valid_sort_columns = ['member_name', 'date', 'champ_comments'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'date';
$query .= " ORDER BY " . ($sort === 'member_name' ? 'm.member_name' : 'c.' . $sort) . " " . ($order === 'asc' ? 'ASC' : 'DESC');

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$champions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = ($isAdmin ? 'manage_champions.php?club_id=' : 'club_champions.php?id=') . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Champions - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="<?php echo $prefix; ?>css/styles.css">
    <script src="<?php echo $prefix; ?>js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php
    if ($isAdmin) {
        NavigationHelper::renderAdminSidebar('champions', $club_id, $club_name);
    } else {
        NavigationHelper::renderSidebar('champions', $club_id, $club_name);
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Champions (' . count($champions) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php if ($isAdmin): ?>
            <?php display_session_message('success'); ?>
            <?php display_session_message('error'); ?>

            <div id="add-champion-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add a Champion</h3>
                <form method="POST" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="create">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="member_id">Member <span style="color:var(--color-error,#ef4444);">*</span></label>
                            <select id="member_id" name="member_id" required class="form-control">
                                <option value="">Select Member</option>
                                <?php foreach ($club_members as $m): ?>
                                    <option value="<?php echo $m['member_id']; ?>"><?php echo htmlspecialchars($m['nickname'] ?: $m['member_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="champ_date">Date Awarded <span style="color:var(--color-error,#ef4444);">*</span></label>
                            <input type="date" id="champ_date" name="champ_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="champ_comments">Title / Award Comments</label>
                            <input type="text" id="champ_comments" name="champ_comments" placeholder="e.g. 2026 Club Champion" class="form-control">
                        </div>
                    </div>
                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <button type="submit" class="btn btn--primary">Save Champion</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddChampionForm()">Cancel</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php
            TableHelper::renderChampionsTable($champions, [
                'is_admin' => $isAdmin,
                'base_url' => $baseUrl,
                'sort' => $sort,
                'order' => $order,
                'search' => $search,
                'club_id' => $club_id
            ]);
            ?>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <script>
            function toggleAddChampionForm() {
                const wrapper = document.getElementById('add-champion-form-wrapper');
                const btn = document.getElementById('add-champion-btn');
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
