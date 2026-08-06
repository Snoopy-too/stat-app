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
        $cStmt = $pdo->query("SELECT c.club_id FROM clubs c ORDER BY c.club_name ASC LIMIT 1");
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

// Handle bulk action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['selected_teams'])) {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: club_teams.php?club_id=" . $club_id);
        exit();
    }

    $selected_teams = array_map('intval', $_POST['selected_teams']);
    $bulk_action = $_POST['bulk_action'];

    if ($bulk_action === 'bulk_delete') {
        try {
            $pdo->beginTransaction();
            $placeholders = str_repeat('?,', count($selected_teams) - 1) . '?';
            $stmt = $pdo->prepare("
                DELETE t FROM teams t 
                JOIN members m ON t.member1_id = m.member_id 
                WHERE t.team_id IN ($placeholders) AND m.club_id = ?
            ");
            $params = array_merge($selected_teams, [$club_id]);
            $stmt->execute($params);
            $deletedCount = $stmt->rowCount();
            $pdo->commit();

            $_SESSION['success'] = "$deletedCount team(s) deleted successfully!";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error'] = "Error deleting teams: " . $e->getMessage();
        }
    }
    header("Location: club_teams.php?club_id=" . $club_id);
    exit();
}

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_team'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: club_teams.php?club_id=" . $club_id);
        exit();
    }

    $team_name = trim($_POST['team_name']);
    $member1 = isset($_POST['member1']) ? (int)$_POST['member1'] : null;
    $member2 = isset($_POST['member2']) ? (int)$_POST['member2'] : null;
    $member3 = isset($_POST['member3']) ? (int)$_POST['member3'] : null;
    $member4 = isset($_POST['member4']) ? (int)$_POST['member4'] : null;

    if (!empty($team_name) && $member1) {
        try {
            $pdo->beginTransaction();
            
            // Build dynamic SQL query based on selected members
            $fields = ['team_name', 'club_id', 'member1_id', 'member2_id', 'member3_id', 'member4_id'];
            $values = ['?', '?', '?', '?', '?', '?'];
            $params = [
                $team_name,
                $club_id,
                $member1 ?: null,
                $member2 ?: null,
                $member3 ?: null,
                $member4 ?: null
            ];
            
            $sql = "INSERT INTO teams (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $pdo->commit();
            $_SESSION['success'] = "Team created successfully!";
            header("Location: club_teams.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error creating team: " . $e->getMessage();
            header("Location: club_teams.php?club_id=" . $club_id);
            exit();
        }
    } else {
        $_SESSION['error'] = "Team name and at least one member are required.";
        header("Location: club_teams.php?club_id=" . $club_id);
        exit();
    }
}

// Fetch all members of the club
$stmt = $pdo->prepare("SELECT * FROM members WHERE club_id = ? ORDER BY member_name");
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing teams
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$team_sql = "
    SELECT t.*,
           m1.nickname as member1_nickname,
           m2.nickname as member2_nickname,
           m3.nickname as member3_nickname,
           m4.nickname as member4_nickname
    FROM teams t
    LEFT JOIN members m1 ON t.member1_id = m1.member_id
    LEFT JOIN members m2 ON t.member2_id = m2.member_id
    LEFT JOIN members m3 ON t.member3_id = m3.member_id
    LEFT JOIN members m4 ON t.member4_id = m4.member_id
    WHERE m1.club_id = ?";
$team_params = [$club_id];
$team_sql .= " ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($team_sql);
$stmt->execute($team_params);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($demo) {
    $teams = [
        [
            'team_id' => 1,
            'team_name' => 'The Catan Settlers',
            'member1_nickname' => 'Alex',
            'member2_nickname' => 'Sam',
            'member3_nickname' => null,
            'member4_nickname' => null,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'team_id' => 2,
            'team_name' => 'Ticket Runners',
            'member1_nickname' => 'Jordan',
            'member2_nickname' => 'Casey',
            'member3_nickname' => null,
            'member4_nickname' => null,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    $members = [
        ['member_id' => 1, 'member_name' => 'Alex Rivers', 'nickname' => 'Alex'],
        ['member_id' => 2, 'member_name' => 'Sam Taylor', 'nickname' => 'Sam'],
        ['member_id' => 3, 'member_name' => 'Jordan Lee', 'nickname' => 'Jordan'],
        ['member_id' => 4, 'member_name' => 'Casey Morgan', 'nickname' => 'Casey']
    ];
}

// Generate CSRF token for form
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
    <title>Teams - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/team-validation.js"></script>
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('teams', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Teams (' . $club['club_name'] . ')'); ?>
    </div>

    <div class="container container--wide">

        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <h2>Teams (<?php echo count($teams); ?>)</h2>
            </div>

            <div id="add-team-form-wrapper" style="<?php echo isset($_POST['create_team']) ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Create New Team</h3>
                <form method="POST" class="stack">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="grid grid--columns-2">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="team_name">Team Name <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <input type="text" id="team_name" name="team_name" required class="form-control" placeholder="Enter team name">
                        </div>
                        <div style="grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; margin-top: 0.25rem; margin-bottom: 0.25rem;">
                            <span style="font-weight: 600; color: var(--color-heading); font-size: 0.9rem;">Team Members</span>
                            <button type="button" id="add-member-field-btn" class="btn btn--secondary btn--small" onclick="addMemberField()">
                                <span style="font-weight: bold; margin-right: 0.25rem;">+</span>Add Member
                            </button>
                        </div>
                        <div class="form-group">
                            <label for="member1">Member 1 <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <select id="member1" name="member1" required class="form-control">
                                <option value="">Select Member</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo $member['member_id']; ?>">
                                        <?php echo htmlspecialchars($member['member_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="member2">Member 2</label>
                            <select id="member2" name="member2" class="form-control">
                                <option value="">Select Member</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo $member['member_id']; ?>">
                                        <?php echo htmlspecialchars($member['member_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="group-member3" style="display: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <label for="member3" style="margin-bottom: 0;">Member 3</label>
                                <button type="button" onclick="removeMemberField(3)" style="background: none; border: none; color: var(--color-danger); cursor: pointer; font-size: 0.8rem; font-weight: 500;">✕ Remove</button>
                            </div>
                            <select id="member3" name="member3" class="form-control">
                                <option value="">Select Member</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo $member['member_id']; ?>">
                                        <?php echo htmlspecialchars($member['member_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="group-member4" style="display: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <label for="member4" style="margin-bottom: 0;">Member 4</label>
                                <button type="button" onclick="removeMemberField(4)" style="background: none; border: none; color: var(--color-danger); cursor: pointer; font-size: 0.8rem; font-weight: 500;">✕ Remove</button>
                            </div>
                            <select id="member4" name="member4" class="form-control">
                                <option value="">Select Member</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo $member['member_id']; ?>">
                                        <?php echo htmlspecialchars($member['member_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; margin-top:1.25rem; margin-bottom:0;">
                        <input type="hidden" name="create_team" value="1">
                        <button type="submit" class="btn btn--primary">Save Team</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddTeamForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="card-toolbar">
                <button type="button" class="btn btn--primary" id="toggle-add-team-btn" onclick="toggleAddTeamForm()" style="<?php echo isset($_POST['create_team']) ? 'visibility:hidden;' : ''; ?>">
                    <span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>Add a Team
                </button>
                <form method="GET" class="toolbar-group toolbar-group--grow search-form" id="filter-form">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="Search teams..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <a href="club_teams.php?club_id=<?php echo $club_id; ?>" class="btn btn--subtle btn--small">Reset</a>
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
                        <th style="text-align:left;">Team Name</th>
                        <th style="text-align:left;">Members</th>
                        <th style="text-align:left;">Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="noSearchMatch" style="display: none;">
                        <td colspan="5" class="text-center text-muted" style="padding: 1.5rem;">No teams match your search.</td>
                    </tr>
                    <?php if (empty($teams)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding: 1.5rem;">No teams created yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($teams as $team): ?>
                        <?php
                        $team_members = array_filter([
                            $team['member1_nickname'],
                            $team['member2_nickname'],
                            $team['member3_nickname'],
                            $team['member4_nickname']
                        ]);
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_teams[]" form="bulk-form"
                                       value="<?php echo $team['team_id']; ?>" class="form-check-input team-checkbox">
                            </td>
                            <td data-label="Team Name"><?php echo htmlspecialchars($team['team_name']); ?></td>
                            <td data-label="Members">
                                <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                                    <?php foreach ($team_members as $nickname): ?>
                                        <span class="club-stat-pill" style="font-weight:500;font-size:0.8rem;background:var(--color-surface-muted);color:var(--color-text);"><?php echo htmlspecialchars($nickname); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td data-label="Created" style="font-size:0.85rem;color:var(--color-text-muted);"><?php echo date('M j, Y', strtotime($team['created_at'])); ?></td>
                            <td data-label="Actions">
                                <div style="display:flex; gap:0.5rem; align-items:center;">
                                    <a href="edit_team.php?team_id=<?php echo $team['team_id']; ?>&club_id=<?php echo $club_id; ?>" class="btn btn--small btn--secondary">Edit</a>
                                    <button type="button" class="btn btn--small btn--danger" onclick="confirmDeleteTeam(event, <?php echo $team['team_id']; ?>, '<?php echo addslashes($team['team_name']); ?>')">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <script>
    function confirmDeleteTeam(event, teamId, teamName) {
        if (event) event.preventDefault();
        showConfirmDialog(event, {
            title: '⚠️ Delete Team',
            message: `Are you sure you want to delete <strong>${teamName}</strong>?`,
            confirmText: 'Delete Team',
            cancelText: 'Cancel',
            type: 'danger',
            warningMessage: 'This action is permanent and cannot be undone. Team records and statistics will be removed.',
            onConfirm: () => {
                window.location.href = `delete_team.php?team_id=${teamId}&club_id=<?php echo $club_id; ?>`;
            }
        });
    }

    function toggleAddTeamForm() {
        const wrapper = document.getElementById('add-team-form-wrapper');
        const btn = document.getElementById('toggle-add-team-btn');
        if (!wrapper) return;
        if (wrapper.style.display === 'none' || wrapper.style.display === '') {
            wrapper.style.display = 'block';
            if (btn) btn.style.visibility = 'hidden';
            document.getElementById('team_name')?.focus();
        } else {
            wrapper.style.display = 'none';
            if (btn) btn.style.visibility = 'visible';
        }
    }

    function addMemberField() {
        const group3 = document.getElementById('group-member3');
        const group4 = document.getElementById('group-member4');
        const btn = document.getElementById('add-member-field-btn');

        if (group3 && group3.style.display === 'none') {
            group3.style.display = 'block';
        } else if (group4 && group4.style.display === 'none') {
            group4.style.display = 'block';
            if (btn) btn.style.display = 'none';
        }
    }

    function removeMemberField(num) {
        const group = document.getElementById('group-member' + num);
        const select = document.getElementById('member' + num);
        const btn = document.getElementById('add-member-field-btn');

        if (group) group.style.display = 'none';
        if (select) select.value = '';
        if (btn) btn.style.display = 'inline-flex';
    }

    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        const filterTeams = () => {
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
        searchInput.addEventListener('input', filterTeams);
        if (searchInput.value) filterTeams();
    }

    document.getElementById('select-all')?.addEventListener('change', function() {
        document.querySelectorAll('.team-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    function executeBulkAction(selectEl) {
        const action = selectEl.value;
        if (!action) return;

        const selectedCheckboxes = document.querySelectorAll('.team-checkbox:checked');

        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one team.');
            selectEl.value = '';
            return;
        }

        if (action === 'bulk_delete') {
            showConfirmDialog(null, {
                title: '⚠️ Delete Selected Teams?',
                message: `Are you sure you want to delete ${selectedCheckboxes.length} selected team(s)?`,
                confirmText: 'Delete Teams',
                cancelText: 'Cancel',
                type: 'danger',
                warningMessage: 'Selected teams will be permanently removed.',
                onConfirm: () => {
                    document.getElementById('bulk-form').submit();
                },
                onCancel: () => {
                    selectEl.value = '';
                }
            });
        }
    }
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
