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
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
if (!$club_id && !empty($_SESSION['current_club_id'])) {
    $club_id = (int)$_SESSION['current_club_id'];
}
if (!$club_id && !empty($_SESSION['club_id'])) {
    $club_id = (int)$_SESSION['club_id'];
}

if (!$club_id) {
    try {
        $cStmt = $pdo->query("SELECT club_id FROM clubs ORDER BY club_name ASC LIMIT 1");
        $club_id = (int)$cStmt->fetchColumn();
    } catch (Exception $e) {}
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
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'desc';

$allowed_sorts = [
    'member_name' => 'm.member_name',
    'date' => 'c.date',
    'champ_comments' => 'c.champ_comments'
];
$sort_column = isset($allowed_sorts[$sort]) ? $allowed_sorts[$sort] : 'c.date';
$order_direction = ($order === 'asc') ? 'ASC' : 'DESC';

// Get all members for the dropdown
$member_query = "SELECT m.* FROM members m WHERE m.club_id = ? ORDER BY m.member_name ASC";
$stmt = $pdo->prepare($member_query);
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build the query for champions
$query = "
    SELECT c.*, m.member_name, m.nickname
    FROM champions c
    JOIN members m ON c.member_id = m.member_id
    WHERE m.club_id = ?
";

$params = [$club_id];

$query .= " ORDER BY {$sort_column} {$order_direction}";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$champions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($demo) {
    $champions = [
        [
            'champion_id' => 1,
            'member_name' => 'Alex Rivers',
            'nickname' => 'Alex',
            'start_date' => '2026-01-01',
            'end_date' => null,
            'champ_comments' => 'Current Catan Champion'
        ],
        [
            'champion_id' => 2,
            'member_name' => 'Sam Taylor',
            'nickname' => 'Sam',
            'start_date' => '2026-02-15',
            'end_date' => null,
            'champ_comments' => 'Wingspan Master'
        ]
    ];
}

// Handle member creation/deletion and bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_champions.php?club_id=" . $club_id);
        exit();
    }

    if (isset($_POST['bulk_action']) && !empty($_POST['selected_champions'])) {
        $selected_champions = $_POST['selected_champions'];
        $bulk_action = $_POST['bulk_action'];
        
        try {
            if ($bulk_action === 'bulk_delete') {
                $password = $_POST['password'] ?? '';
                if (!verify_admin_password($password, $pdo)) {
                    $_SESSION['error'] = "Incorrect password. Bulk delete cancelled.";
                    header("Location: manage_champions.php?club_id=" . $club_id);
                    exit();
                }
                $stmt = $pdo->prepare("DELETE FROM champions WHERE ID = ?");
                foreach ($selected_champions as $champion_id) {
                    $stmt->execute([$champion_id]);
                }
                $_SESSION['success'] = "Selected champions deleted successfully!";
            }
            
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to perform bulk action: " . $e->getMessage();
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        }
    } else if (isset($_POST['action'])) {
        // Update the INSERT query in the POST handling section
        if ($_POST['action'] === 'create' && !empty($_POST['member_id']) && !empty($_POST['champ_date'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO champions (club_id, member_id, date, champ_comments) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $club_id,
                    trim($_POST['member_id']),
                    trim($_POST['champ_date']),
                    trim($_POST['champ_comments'])
                ]);
                $_SESSION['success'] = "Champion added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to add champion: " . $e->getMessage();
            }
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        } elseif ($_POST['action'] === 'edit' && !empty($_POST['champion_id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE champions SET member_id = ?, date = ?, champ_comments = ? WHERE id = ?");
                $stmt->execute([
                    trim($_POST['edit_member_id']),
                    trim($_POST['edit_date']),
                    trim($_POST['edit_comments']),
                    $_POST['champion_id']
                ]);
                $_SESSION['success'] = "Champion updated successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to update champion: " . $e->getMessage();
            }
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        } elseif ($_POST['action'] === 'delete' && !empty($_POST['champion_id'])) {
            try {
                $stmt = $pdo->prepare("DELETE FROM champions WHERE ID = ? AND club_id = ?");
                $stmt->execute([$_POST['champion_id'], $club_id]);
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = "Champion deleted successfully!";
                } else {
                    $_SESSION['error'] = "Champion not found.";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to delete champion: " . $e->getMessage();
            }
            header("Location: manage_champions.php?club_id=" . $club_id);
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
    <title>Manage Champions - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('champions', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club['club_name'] . ' Champions (' . count($champions) . ')'); ?>

    </div>

    <div class="container container--wide">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

            <div id="add-champion-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add New Champion</h3>
                <form method="POST" class="stack">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="create">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="member_id">Champion <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <select name="member_id" id="member_id" required class="form-control">
                                <option value="">Select Member</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo $member['member_id']; ?>">
                                        <?php echo htmlspecialchars($member['member_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="champ_date">Date Awarded <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <input type="date" id="champ_date" name="champ_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom: 0; grid-column: 1 / -1;">
                            <label for="champ_comments">Champion Notes</label>
                            <textarea id="champ_comments" name="champ_comments" placeholder="Champion comments" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <button type="submit" class="btn btn--primary">Save Champion</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddChampionForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="card-toolbar">
                <button type="button" class="btn btn--primary" id="toggle-add-champion-btn" onclick="toggleAddChampionForm()" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? 'visibility:hidden;' : ''; ?>">
                    <span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>Add a Champion
                </button>
                <form method="GET" class="toolbar-group toolbar-group--grow search-form" id="filter-form">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo strtolower($order); ?>">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="Search champions..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <a href="?club_id=<?php echo $club_id; ?>" class="btn btn--subtle btn--small">Reset</a>
                    </div>
                </form>
                <form method="POST" class="toolbar-group" id="bulk-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
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
                            <a href="?club_id=<?php echo $club_id; ?>&sort=member_name&order=<?php echo ($sort === 'member_name' && strtolower($order) === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>" class="table-sort-link sort-link">
                                <span>Member Name</span>
                                <?php if ($sort === 'member_name'): ?>
                                    <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?club_id=<?php echo $club_id; ?>&sort=date&order=<?php echo ($sort === 'date' && strtolower($order) === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>" class="table-sort-link sort-link">
                                <span>Date</span>
                                <?php if ($sort === 'date'): ?>
                                    <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?club_id=<?php echo $club_id; ?>&sort=champ_comments&order=<?php echo ($sort === 'champ_comments' && strtolower($order) === 'asc') ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>" class="table-sort-link sort-link">
                                <span>Comments</span>
                                <?php if ($sort === 'champ_comments'): ?>
                                    <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="noSearchMatch" style="display: none;">
                        <td colspan="5" class="text-center text-muted" style="padding: 1.5rem;">No champions match your search.</td>
                    </tr>
                    <?php if (empty($champions)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding: 1.5rem;">No champions created yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($champions as $champion): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_champions[]" form="bulk-form"
                                       value="<?php echo $champion['ID']; ?>" class="form-check-input champion-checkbox">
                            </td>
                            <td data-label="Member Name"><?php echo htmlspecialchars($champion['member_name']); ?></td>
                            <td data-label="Date"><?php echo date('Y/m/d', strtotime($champion['date'])); ?></td>
                            <td data-label="Comments"><?php echo htmlspecialchars($champion['champ_comments']); ?></td>
                            <td data-label="Actions">
                                <div style="display:flex; gap:0.5rem; align-items:center;">
                                    <a href="edit_champion.php?club_id=<?php echo $club_id; ?>&champion_id=<?php echo $champion['ID']; ?>" class="btn btn--small btn--secondary">View/Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </div>

        </div>
    </div>

    <script>
        document.getElementById('select-all')?.addEventListener('change', function() {
            document.querySelectorAll('.champion-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        function executeBulkAction(selectEl) {
            const action = selectEl.value;
            if (!action) return;

            const selectedCheckboxes = document.querySelectorAll('.champion-checkbox:checked');

            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one champion.');
                selectEl.value = '';
                return;
            }

            if (action === 'bulk_delete') {
                showConfirmDialog(null, {
                    title: '⚠️ Delete Selected Champions?',
                    message: `Are you sure you want to delete ${selectedCheckboxes.length} selected champion record(s)?`,
                    confirmText: 'Delete Champions',
                    cancelText: 'Cancel',
                    type: 'danger',
                    warningMessage: 'Selected champion records will be permanently removed.',
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

        function toggleAddChampionForm() {
            const wrapper = document.getElementById('add-champion-form-wrapper');
            const btn = document.getElementById('toggle-add-champion-btn');
            if (!wrapper) return;
            if (wrapper.style.display === 'none' || wrapper.style.display === '') {
                wrapper.style.display = 'block';
                if (btn) btn.style.visibility = 'hidden';
                document.getElementById('member_id')?.focus();
            } else {
                wrapper.style.display = 'none';
                if (btn) btn.style.visibility = 'visible';
            }
        }

        function confirmDeleteChampion(championId, memberName) {
            showConfirmDialog(null, {
                title: 'Delete Champion?',
                message: `Are you sure you want to delete the champion record for <strong>${memberName}</strong>?`,
                confirmText: 'Delete Champion',
                cancelText: 'Cancel',
                type: 'danger',
                warningMessage: 'This action is permanent and cannot be undone.',
                onConfirm: () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'manage_champions.php?club_id=<?php echo $club_id; ?>';
                    
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = '<?php echo $csrf_token; ?>';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete';
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'champion_id';
                    idInput.value = championId;
                    
                    form.appendChild(csrfInput);
                    form.appendChild(actionInput);
                    form.appendChild(idInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        championModal.addEventListener('click', function(event) {
            if (event.target === championModal) {
                closeEditModal();
            }
        });

        championModalDialog.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
<script>
(function() {
    // Save scroll position before navigating away (sort/filter links)
    function saveScrollPosition() {
        try {
            sessionStorage.setItem('manage_champions_scroll', window.scrollY);
        } catch (e) {}
    }
    // Attach to all sort/filter links
    document.addEventListener('DOMContentLoaded', function() {
        var links = document.querySelectorAll('a.sort-link, .search-form button[type="submit"], .search-form .button');
        links.forEach(function(link) {
            link.addEventListener('click', saveScrollPosition);
        });
        // Restore scroll position
        var scroll = sessionStorage.getItem('manage_champions_scroll');
        if (scroll !== null) {
            window.scrollTo(0, parseInt(scroll, 10));
            sessionStorage.removeItem('manage_champions_scroll');
        }
    });
    // Also save on form submit (search/filter)
    var forms = document.querySelectorAll('.search-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', saveScrollPosition);
    });

    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        const filterChampions = () => {
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
        searchInput.addEventListener('input', filterChampions);
        if (searchInput.value) filterChampions();
    }
})();
</script>
</html>
