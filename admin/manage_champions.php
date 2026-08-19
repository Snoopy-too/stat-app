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

$demoClub = function_exists('get_demo_data') ? get_demo_data('club') : [];
$club_name = $demoClub['club_name'] ?? 'Meeple Mosh';
if ($club_id && !$demo) {
    try {
        $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
        $stmt->execute([$club_id]);
        $club_name = $stmt->fetchColumn() ?: $club_name;
    } catch (Exception $e) {}
}

// Fetch active members for dropdown
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

$valid_sort_columns = ['member_name', 'nickname', 'date', 'champ_comments'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'date';
$order = ($order === 'asc') ? 'ASC' : 'DESC';
if ($sort === 'member_name' || $sort === 'nickname') {
    $query .= " ORDER BY COALESCE(NULLIF(m.nickname, ''), m.member_name) " . $order;
} else {
    $query .= " ORDER BY c." . $sort . " " . $order;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$champions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($demo) {
    $champions = get_demo_data('champions');
}

$baseUrl = 'manage_champions.php?club_id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Champions - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <script src="../js/i18n.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('champions', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Champions (' . count($champions) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div id="add-champion-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
            <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);" data-i18n="admin.crownChampion">Add a Champion</h3>
            <form method="POST" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="create">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="member_id"><span data-i18n="members.name">Member</span> <span style="color:var(--color-error,#ef4444);">*</span></label>
                        <select id="member_id" name="member_id" required class="form-control">
                            <option value="" data-i18n="admin.selectMember">Select Member</option>
                            <?php foreach ($club_members as $m): ?>
                                <option value="<?php echo $m['member_id']; ?>"><?php echo htmlspecialchars($m['nickname'] ?: $m['member_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="champ_date"><span data-i18n="champions.awarded">Date Awarded</span> <span style="color:var(--color-error,#ef4444);">*</span></label>
                        <input type="date" id="champ_date" name="champ_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="champ_comments" data-i18n="champions.seasonTitle">Title / Award Comments</label>
                        <input type="text" id="champ_comments" name="champ_comments" placeholder="e.g. 2026 Club Champion" class="form-control">
                    </div>
                </div>
                <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                    <button type="submit" class="btn btn--primary" data-i18n="admin.saveChampion">Save Champion</button>
                    <button type="button" class="btn btn--subtle" onclick="toggleAddChampionForm()" data-i18n="common.cancel">Cancel</button>
                </div>
            </form>
        </div>

        <?php
        TableHelper::renderChampionsTable($champions, [
            'is_admin' => true,
            'base_url' => $baseUrl,
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'club_id' => $club_id
        ]);
        ?>
    </div>

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
