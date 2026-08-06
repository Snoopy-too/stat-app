<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/NavigationHelper.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

require_once '../includes/SecurityUtils.php';
$security = new SecurityUtils($pdo);
$csrf_token = $security->generateCSRFToken();

// Get club_id and game_id from URL
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : null;

// Validate club_id and game_id exist
if (!$club_id || !$game_id) {
    $_SESSION['error'] = "Invalid club ID or game ID provided.";
    header("Location: dashboard.php");
    exit();
}

// Get game details
$stmt = $pdo->prepare("SELECT g.*, c.club_name FROM games g JOIN clubs c ON g.club_id = c.club_id WHERE g.game_id = ? AND g.club_id = ?");
$stmt->execute([$game_id, $club_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    $_SESSION['error'] = "Game not found.";
    header("Location: manage_games.php?club_id=" . $club_id);
    exit();
}

ensure_results_tables_exist($pdo);

$results = [];
try {
    // Get individual, team, and cooperative game results
    $stmt = $pdo->prepare("
        (SELECT
            gr.result_id,
            gr.played_at,
            CAST(COALESCE(NULLIF(m.nickname, ''), m.member_name, 'Unknown Member') AS CHAR CHARACTER SET utf8mb4) as winner_name,
            'individual' as game_type,
            gr.duration,
            gr.notes
        FROM game_results gr
        LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id
        WHERE gr.game_id = ?)
        UNION ALL
        (SELECT
            tgr.result_id,
            tgr.played_at,
            CAST(CONCAT(COALESCE(t.team_name, 'Unknown Team'), ' (Team)') AS CHAR CHARACTER SET utf8mb4) as winner_name,
            'team' as game_type,
            tgr.duration,
            tgr.notes
        FROM team_game_results tgr
        LEFT JOIN teams t ON tgr.winner = t.team_id
        WHERE tgr.game_id = ?)
        UNION ALL
        (SELECT
            cgr.result_id,
            cgr.played_at,
            CAST(CONCAT(UPPER(cgr.outcome), ' - Co-op') AS CHAR CHARACTER SET utf8mb4) as winner_name,
            'cooperative' as game_type,
            cgr.duration,
            cgr.notes
        FROM cooperative_game_results cgr
        WHERE cgr.game_id = ?)
        ORDER BY played_at DESC
    ");
    $stmt->execute([$game_id, $game_id, $game_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("UNION query failed in admin/results.php: " . $e->getMessage());
    $results = [];

    // Fallback: Query all three tables separately and merge
    try {
        $stmt = $pdo->prepare("SELECT gr.result_id, gr.played_at, COALESCE(NULLIF(m.nickname, ''), m.member_name, 'Unknown Member') as winner_name, 'individual' as game_type, gr.duration, gr.notes FROM game_results gr LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id WHERE gr.game_id = ?");
        $stmt->execute([$game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e1) {}

    try {
        $stmt = $pdo->prepare("SELECT tgr.result_id, tgr.played_at, CONCAT(COALESCE(t.team_name, 'Unknown Team'), ' (Team)') as winner_name, 'team' as game_type, tgr.duration, tgr.notes FROM team_game_results tgr LEFT JOIN teams t ON tgr.winner = t.team_id WHERE tgr.game_id = ?");
        $stmt->execute([$game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e2) {}

    try {
        $stmt = $pdo->prepare("SELECT cgr.result_id, cgr.played_at, CONCAT(UPPER(cgr.outcome), ' - Co-op') as winner_name, 'cooperative' as game_type, cgr.duration, cgr.notes FROM cooperative_game_results cgr WHERE cgr.game_id = ?");
        $stmt->execute([$game_id]);
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e3) {}

    usort($results, function($a, $b) {
        return strtotime($b['played_at']) <=> strtotime($a['played_at']);
    });
}

// Handle bulk action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['selected_results'])) {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: results.php?club_id=$club_id&game_id=$game_id");
        exit();
    }

    $selected_results = $_POST['selected_results'];
    $bulk_action = $_POST['bulk_action'];

    if ($bulk_action === 'bulk_delete') {
        $deleted_count = 0;
        foreach ($selected_results as $item) {
            $parts = explode(':', $item, 2);
            if (count($parts) === 2) {
                $type = $parts[0];
                $res_id = (int)$parts[1];
                if ($type === 'individual') {
                    $stmt = $pdo->prepare("DELETE FROM game_results WHERE result_id = ? AND game_id = ?");
                    if ($stmt->execute([$res_id, $game_id])) $deleted_count++;
                } elseif ($type === 'team') {
                    $stmt = $pdo->prepare("DELETE FROM team_game_results WHERE result_id = ? AND game_id = ?");
                    if ($stmt->execute([$res_id, $game_id])) $deleted_count++;
                } elseif ($type === 'cooperative') {
                    $stmt = $pdo->prepare("DELETE FROM cooperative_game_results WHERE result_id = ? AND game_id = ?");
                    if ($stmt->execute([$res_id, $game_id])) $deleted_count++;
                }
            }
        }
        $_SESSION['success'] = "$deleted_count result(s) deleted successfully!";
    }
    header("Location: results.php?club_id=$club_id&game_id=$game_id");
    exit();
}

// Handle sorting and searching
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'played_at';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'desc';

$allowed_sorts = ['played_at', 'winner_name', 'game_type'];
$sort = in_array($sort, $allowed_sorts) ? $sort : 'played_at';
$order = ($order === 'asc') ? 'asc' : 'desc';

usort($results, function($a, $b) use ($sort, $order) {
    $valA = $a[$sort] ?? '';
    $valB = $b[$sort] ?? '';
    if ($sort === 'played_at') {
        $cmp = strtotime($valA) <=> strtotime($valB);
    } else {
        $cmp = strcasecmp((string)$valA, (string)$valB);
    }
    return ($order === 'asc') ? $cmp : -$cmp;
});

// Aggregate analytics for charts
$winner_counts = [];
$monthly_plays = [];

foreach ($results as $r) {
    $w = $r['winner_name'] ?: 'Unknown';
    $winner_counts[$w] = ($winner_counts[$w] ?? 0) + 1;

    $month = date('Y-m', strtotime($r['played_at']));
    $monthly_plays[$month] = ($monthly_plays[$month] ?? 0) + 1;
}

arsort($winner_counts);
$top_winners = array_slice($winner_counts, 0, 7, true);

ksort($monthly_plays);
$monthly_labels = array_map(function($m) {
    return date('M Y', strtotime($m . '-01'));
}, array_keys($monthly_plays));
$monthly_data = array_values($monthly_plays);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Results - <?php echo htmlspecialchars($game['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $club_id, $game['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Games' . ($game['club_name'] ? ' (' . htmlspecialchars($game['club_name']) . ')' : '')); ?>
        <div class="header-actions">
            <a href="manage_games.php?club_id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small">← Back to Overview</a>
        </div>
    </div>
    
    <div class="container">
        <?php display_session_message('error'); ?>
        <?php display_session_message('success_message', 'success'); ?>

        <div class="card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                    <?php 
                    $img_url = get_game_image_url($game['game_image'] ?? null, '../');
                    if ($img_url): 
                    ?>
                        <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px;">
                    <?php endif; ?>
                    <h1 style="margin: 0; font-size: 1.75rem; font-weight: 700; color: var(--color-heading);"><?php echo htmlspecialchars($game['game_name']); ?> Results</h1>
                </div>
                <p style="margin: 0.25rem 0; color: var(--color-text-muted);">
                    <strong>Club:</strong> <?php echo htmlspecialchars($game['club_name']); ?> &bull; 
                    <strong>Players:</strong> <?php echo $game['min_players'] . '-' . $game['max_players']; ?> players &bull; 
                    <strong>Added:</strong> <?php echo date('M j, Y', strtotime($game['created_at'])); ?>
                </p>
            </div>
            <div class="btn-group" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="add_result.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>"
                   class="btn btn--primary">Add Result</a>
            </div>
        </div>

        <?php if (!empty($results)): ?>
        <div class="card">
            <h2>Analytics & Trends for <?php echo htmlspecialchars($game['game_name']); ?></h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                <div>
                    <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Top Winners</h3>
                    <div style="position: relative; height: 240px;">
                        <canvas id="winnersChart"></canvas>
                    </div>
                </div>
                <div>
                    <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Plays Over Time</h3>
                    <div style="position: relative; height: 240px;">
                        <canvas id="playsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-toolbar">
                <div class="toolbar-group">
                    <a href="add_result.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>" class="btn btn--primary">
                        <span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>Add Result
                    </a>
                </div>
                <form method="GET" class="toolbar-group toolbar-group--grow search-form" id="filter-form">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo strtolower($order); ?>">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="Search history..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <a href="?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>" class="btn btn--subtle btn--small">Reset</a>
                    </div>
                </form>
                <form method="POST" class="toolbar-group" id="bulk-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
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
                            <th><input type="checkbox" id="select-all" class="form-check-input"></th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>&sort=played_at&order=<?php echo ($sort === 'played_at' && $order === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>" class="table-sort-link sort-link">
                                    <span>Date Played</span>
                                    <?php if ($sort === 'played_at'): ?>
                                        <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>&sort=winner_name&order=<?php echo ($sort === 'winner_name' && $order === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>" class="table-sort-link sort-link">
                                    <span>Winner / Outcome</span>
                                    <?php if ($sort === 'winner_name'): ?>
                                        <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game_id; ?>&sort=game_type&order=<?php echo ($sort === 'game_type' && $order === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>" class="table-sort-link sort-link">
                                    <span>Type</span>
                                    <?php if ($sort === 'game_type'): ?>
                                        <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="noSearchMatch" style="display: none;">
                            <td colspan="5" class="text-center text-muted" style="padding: 1.5rem;">No game results match your search.</td>
                        </tr>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding: 1.5rem;">No game results recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_results[]" form="bulk-form"
                                           value="<?php echo $result['game_type'] . ':' . $result['result_id']; ?>" class="form-check-input result-checkbox">
                                </td>
                                <td data-label="Date Played"><?php echo date('M j, Y', strtotime($result['played_at'])); ?></td>
                                <td data-label="Winner / Outcome"><?php echo htmlspecialchars($result['winner_name']); ?></td>
                                <td data-label="Type"><span class="club-stat-pill" style="text-transform: capitalize; font-size: 0.8rem; background: var(--color-surface-muted); color: var(--color-text);"><?php echo htmlspecialchars($result['game_type']); ?></span></td>
                                <td data-label="Actions">
                                    <?php
                                    $view_url = match($result['game_type']) {
                                        'individual' => 'view_result.php',
                                        'team' => 'view_team_result.php',
                                        'cooperative' => 'view_cooperative_result.php',
                                        default => 'view_result.php'
                                    };
                                    $delete_url = match($result['game_type']) {
                                        'individual' => 'delete_result.php',
                                        'team' => 'delete_team_result.php',
                                        'cooperative' => 'delete_cooperative_result.php',
                                        default => 'delete_result.php'
                                    };
                                    ?>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <a href="<?php echo $view_url; ?>?result_id=<?php echo $result['result_id']; ?>"
                                           class="btn btn--small btn--secondary">View Details</a>
                                        <form id="delete-form-<?php echo $result['result_id']; ?>" action="<?php echo $delete_url; ?>" method="POST" style="display: inline;">
                                            <input type="hidden" name="result_id" value="<?php echo $result['result_id']; ?>">
                                            <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                                            <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
                                            <button type="button" class="btn btn--small btn--danger"
                                                    onclick="showConfirmDialog(event, {
                                                        title: 'Delete Game Result?',
                                                        message: 'Are you sure you want to delete this result? This action cannot be undone.',
                                                        confirmText: 'Delete Result',
                                                        onConfirm: () => document.getElementById('delete-form-<?php echo $result['result_id']; ?>').submit()
                                                    })">
                                                Delete
                                            </button>
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
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
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
                    message: `Are you sure you want to delete ${selectedCheckboxes.length} selected result(s)?`,
                    confirmText: 'Delete Results',
                    cancelText: 'Cancel',
                    type: 'danger',
                    warningMessage: 'Selected game results will be permanently removed.',
                    onConfirm: () => {
                        document.getElementById('bulk-form').submit();
                    },
                    onCancel: () => {
                        selectEl.value = '';
                    }
                });
            }
        }

        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            const filterResults = () => {
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
            searchInput.addEventListener('input', filterResults);
            if (searchInput.value) filterResults();
        }
    </script>
    <?php if (!empty($results)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function getThemeColors() {
                const computed = getComputedStyle(document.documentElement);
                let text = computed.getPropertyValue('--color-text').trim();
                let border = computed.getPropertyValue('--color-border').trim();
                
                if (!text || text === '') {
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark' ||
                                   (!document.documentElement.hasAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    text = isDark ? '#f1f5f9' : '#1e293b';
                    border = isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.08)';
                }
                return { text, border };
            }

            const colors = getThemeColors();

            // Winners Chart (Pie Chart)
            const winnerLabels = <?php echo json_encode(array_keys($top_winners)); ?>;
            const winnerData = <?php echo json_encode(array_values($top_winners)); ?>;
            let winnersChart = null;
            
            const winnersCtx = document.getElementById('winnersChart');
            if (winnersCtx && winnerLabels.length > 0) {
                winnersChart = new Chart(winnersCtx, {
                    type: 'pie',
                    data: {
                        labels: winnerLabels,
                        datasets: [{
                            label: 'Wins',
                            data: winnerData,
                            backgroundColor: [
                                '#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#f43f5e', '#14b8a6', '#6366f1'
                            ],
                            borderWidth: 2,
                            borderColor: getComputedStyle(document.body).getPropertyValue('--color-surface').trim() || '#ffffff'
                        }]
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
                        }
                    }
                });
            }

            // Plays Over Time Chart
            const monthLabels = <?php echo json_encode($monthly_labels); ?>;
            const monthData = <?php echo json_encode($monthly_data); ?>;
            let playsChart = null;

            const playsCtx = document.getElementById('playsChart');
            if (playsCtx && monthLabels.length > 0) {
                playsChart = new Chart(playsCtx, {
                    type: 'line',
                    data: {
                        labels: monthLabels,
                        datasets: [{
                            label: 'Games Played',
                            data: monthData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.15)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
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
                if (winnersChart && winnersChart.options.plugins.legend) {
                    winnersChart.options.plugins.legend.labels.color = c.text;
                    winnersChart.update();
                }
                if (playsChart && playsChart.options.scales) {
                    playsChart.options.scales.x.ticks.color = c.text;
                    playsChart.options.scales.x.grid.color = c.border;
                    playsChart.options.scales.y.ticks.color = c.text;
                    playsChart.options.scales.y.grid.color = c.border;
                    playsChart.update();
                }
            }

            window.addEventListener('themechange', updateChartTheme);
            const observer = new MutationObserver(updateChartTheme);
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        });
    </script>
    <?php endif; ?>
</body>
</html>
