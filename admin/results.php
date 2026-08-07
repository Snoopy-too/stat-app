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

// Get club_id from URL or session
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
if (!$club_id && !empty($_SESSION['current_club_id'])) {
    $club_id = (int)$_SESSION['current_club_id'];
}
if (!$club_id && !empty($_SESSION['club_id'])) {
    $club_id = (int)$_SESSION['club_id'];
}

// Auto-resolve club_id if missing
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

// Get club details
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
$games_stmt = $pdo->prepare("SELECT game_id, game_name FROM games WHERE club_id = ? ORDER BY game_name ASC");
$games_stmt->execute([$club_id]);
$all_games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

ensure_results_tables_exist($pdo);

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['selected_results'])) {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: results.php?club_id=$club_id" . ($game_id ? "&game_id=$game_id" : ""));
        exit();
    }

    $selected_results = $_POST['selected_results'];
    $bulk_action = $_POST['bulk_action'];

    if ($bulk_action === 'bulk_delete') {
        $password = $_POST['password'] ?? '';
        if (!verify_admin_password($password, $pdo)) {
            $_SESSION['error'] = "Incorrect password. Bulk delete cancelled.";
            header("Location: results.php?club_id=$club_id" . ($game_id ? "&game_id=$game_id" : ""));
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
    header("Location: results.php?club_id=$club_id" . ($game_id ? "&game_id=$game_id" : ""));
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
            SELECT gr.result_id, gr.played_at, COALESCE(NULLIF(m.nickname, ''), m.member_name, 'Unknown Member') as winner_name, COALESCE(g.game_type, 'winner_losers') as game_type, gr.duration, gr.notes, g.game_id, g.game_name
            FROM game_results gr
            JOIN games g ON gr.game_id = g.game_id
            LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id
            WHERE g.club_id = ? AND g.game_id = ?
        ");
        $stmt->execute([$club_id, $game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Team results
        $stmt = $pdo->prepare("
            SELECT tgr.result_id, tgr.played_at, CONCAT(COALESCE(t.team_name, 'Unknown Team'), ' (Team)') as winner_name, COALESCE(g.game_type, 'teams') as game_type, tgr.duration, tgr.notes, g.game_id, g.game_name
            FROM team_game_results tgr
            JOIN games g ON tgr.game_id = g.game_id
            LEFT JOIN teams t ON tgr.winner = t.team_id
            WHERE g.club_id = ? AND g.game_id = ?
        ");
        $stmt->execute([$club_id, $game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Cooperative results
        $stmt = $pdo->prepare("
            SELECT cgr.result_id, cgr.played_at, CONCAT(UPPER(cgr.outcome), ' - Co-op') as winner_name, COALESCE(g.game_type, 'cooperative') as game_type, cgr.duration, cgr.notes, g.game_id, g.game_name
            FROM cooperative_game_results cgr
            JOIN games g ON cgr.game_id = g.game_id
            WHERE g.club_id = ? AND g.game_id = ?
        ");
        $stmt->execute([$club_id, $game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        // Individual results
        $stmt = $pdo->prepare("
            SELECT gr.result_id, gr.played_at, COALESCE(NULLIF(m.nickname, ''), m.member_name, 'Unknown Member') as winner_name, COALESCE(g.game_type, 'winner_losers') as game_type, gr.duration, gr.notes, g.game_id, g.game_name
            FROM game_results gr
            JOIN games g ON gr.game_id = g.game_id
            LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id
            WHERE g.club_id = ?
        ");
        $stmt->execute([$club_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Team results
        $stmt = $pdo->prepare("
            SELECT tgr.result_id, tgr.played_at, CONCAT(COALESCE(t.team_name, 'Unknown Team'), ' (Team)') as winner_name, COALESCE(g.game_type, 'teams') as game_type, tgr.duration, tgr.notes, g.game_id, g.game_name
            FROM team_game_results tgr
            JOIN games g ON tgr.game_id = g.game_id
            LEFT JOIN teams t ON tgr.winner = t.team_id
            WHERE g.club_id = ?
        ");
        $stmt->execute([$club_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Cooperative results
        $stmt = $pdo->prepare("
            SELECT cgr.result_id, cgr.played_at, CONCAT(UPPER(cgr.outcome), ' - Co-op') as winner_name, COALESCE(g.game_type, 'cooperative') as game_type, cgr.duration, cgr.notes, g.game_id, g.game_name
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('results', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Results' . ($club_name ? ' (' . $club_name . ')' : '')); ?>
    </div>

    <div class="container">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <h2>Results (<?php echo count($results); ?>)</h2>
            </div>

            <div class="card-toolbar">
                <a href="club_new_results.php?club_id=<?php echo $club_id; ?>" class="btn btn--primary">
                    <span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>New Result
                </a>
                <form method="GET" class="toolbar-group" id="filter-form">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo strtolower($order); ?>">
                    <select name="game_id" class="form-control form-control--sm" onchange="this.form.submit()" style="max-width: 220px;">
                        <option value="">All Games</option>
                        <?php foreach ($all_games as $g_opt): ?>
                            <option value="<?php echo $g_opt['game_id']; ?>" <?php echo ($game_id == $g_opt['game_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g_opt['game_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <form method="POST" class="toolbar-group" id="bulk-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <?php if ($game_id): ?><input type="hidden" name="game_id" value="<?php echo $game_id; ?>"><?php endif; ?>
                    <select name="bulk_action" id="bulk-action-select" class="form-control form-control--sm" onchange="executeBulkAction(this)">
                        <option value="">Bulk Actions</option>
                        <option value="bulk_delete">Delete Selected</option>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="select-all" class="form-check-input"></th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>&sort=played_at&order=<?php echo ($sort === 'played_at' && $order === 'asc') ? 'desc' : 'asc'; ?>" class="table-sort-link sort-link">
                                    <span>Date Played</span>
                                    <?php if ($sort === 'played_at'): ?>
                                        <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <?php if (!$game_id): ?>
                                <th>
                                    <a href="?club_id=<?php echo $club_id; ?>&sort=game_name&order=<?php echo ($sort === 'game_name' && $order === 'asc') ? 'desc' : 'asc'; ?>" class="table-sort-link sort-link">
                                        <span>Game</span>
                                        <?php if ($sort === 'game_name'): ?>
                                            <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endif; ?>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>&sort=winner_name&order=<?php echo ($sort === 'winner_name' && $order === 'asc') ? 'desc' : 'asc'; ?>" class="table-sort-link sort-link">
                                    <span>Winner / Outcome</span>
                                    <?php if ($sort === 'winner_name'): ?>
                                        <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>&sort=game_type&order=<?php echo ($sort === 'game_type' && $order === 'asc') ? 'desc' : 'asc'; ?>" class="table-sort-link sort-link">
                                    <span>Type</span>
                                    <?php if ($sort === 'game_type'): ?>
                                        <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="<?php echo $game_id ? '6' : '7'; ?>" class="text-center text-muted" style="padding: 1.5rem;">No results found.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_results[]" form="bulk-form"
                                           value="<?php echo $result['game_type'] . ':' . $result['result_id']; ?>" class="form-check-input result-checkbox">
                                </td>
                                <td data-label="Date Played"><?php echo date('M j, Y', strtotime($result['played_at'])); ?></td>
                                <?php if (!$game_id): ?>
                                    <td data-label="Game">
                                        <a href="results.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $result['game_id']; ?>" style="font-weight: 600; text-decoration: none; color: var(--color-primary);">
                                            <?php echo htmlspecialchars($result['game_name']); ?>
                                        </a>
                                    </td>
                                <?php endif; ?>
                                <td data-label="Winner / Outcome"><?php echo htmlspecialchars($result['winner_name']); ?></td>
                                <td data-label="Type">
                                    <span class="club-stat-pill" style="text-transform: capitalize; font-size: 0.8rem; background: var(--color-surface-muted); color: var(--color-text);">
                                        <?php
                                        $gt_label = match($result['game_type']) {
                                            'winner_losers' => 'Winner/Losers',
                                            'ranked' => 'Ranked',
                                            'teams', 'team' => 'Teams',
                                            'cooperative' => 'Cooperative',
                                            default => ucfirst(str_replace('_', ' ', $result['game_type']))
                                        };
                                        echo htmlspecialchars($gt_label);
                                        ?>
                                    </span>
                                </td>
                                <td data-label="Duration"><?php echo !empty($result['duration']) ? (int)$result['duration'] . ' mins' : '-'; ?></td>
                                <td data-label="Actions">
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <a href="edit_result.php?result_id=<?php echo $result['result_id']; ?>&type=<?php echo $result['game_type']; ?>" class="btn btn--small btn--secondary">View/Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>

    <script>
        document.getElementById('select-all')?.addEventListener('change', function() {
            document.querySelectorAll('.result-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        function executeBulkAction(selectEl) {
            const action = selectEl.value;
            if (!action) return;

            const selectedCheckboxes = document.querySelectorAll('.result-checkbox:checked');

            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one result.');
                selectEl.value = '';
                return;
            }

            if (action === 'bulk_delete') {
                showConfirmDialog(null, {
                    title: '⚠️ Delete Selected Results?',
                    message: `Are you sure you want to delete ${selectedCheckboxes.length} selected result record(s)?`,
                    confirmText: 'Delete Results',
                    cancelText: 'Cancel',
                    type: 'danger',
                    warningMessage: 'Selected result records will be permanently removed.',
                    requirePassword: true,
                    onConfirm: (password) => {
                        const bulkForm = document.getElementById('bulk-form');
                        let passInput = bulkForm.querySelector('input[name="password"]');
                        if (!passInput) {
                            passInput = document.createElement('input');
                            passInput.type = 'hidden';
                            passInput.name = 'password';
                            bulkForm.appendChild(passInput);
                        }
                        passInput.value = password;
                        bulkForm.submit();
                    },
                    onCancel: () => {
                        selectEl.value = '';
                    }
                });
            }
        }
    </script>
</body>
</html>
