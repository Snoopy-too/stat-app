<?php
require_once __DIR__ . '/../config/session.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: ../index.php");
    exit();
}

$security = new SecurityUtils($pdo);
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : (isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0);

// Get team info
$stmt = $pdo->prepare("SELECT t.* FROM teams t JOIN club_admins ca ON t.club_id = ca.club_id WHERE t.team_id = ? AND ca.admin_id = ?");
$stmt->execute([$team_id, $_SESSION['admin_id']]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : (isset($_POST['club_id']) ? (int)$_POST['club_id'] : (int)($team['club_id'] ?? 0));

if (!$team) {
    header("Location: manage_teams.php" . ($club_id ? "?club_id=" . $club_id : ""));
    exit();
}

// Fetch all members of the club
$stmt = $pdo->prepare("SELECT * FROM members WHERE club_id = ? ORDER BY member_name");
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: edit_team.php?club_id=" . $club_id . "&team_id=" . $team_id);
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM teams WHERE team_id = ? AND club_id = ?");
        $stmt->execute([$team_id, $club_id]);
        $_SESSION['success'] = "Team deleted successfully!";
        header("Location: manage_teams.php?club_id=" . $club_id);
        exit();
    }

    $team_name = trim($_POST['team_name'] ?? '');
    $selected_members = isset($_POST['team_members']) ? array_values(array_filter(array_map('intval', $_POST['team_members']))) : [];
    if (empty($selected_members)) {
        $m1 = !empty($_POST['member1']) ? (int)$_POST['member1'] : null;
        $m2 = !empty($_POST['member2']) ? (int)$_POST['member2'] : null;
        $m3 = !empty($_POST['member3']) ? (int)$_POST['member3'] : null;
        $m4 = !empty($_POST['member4']) ? (int)$_POST['member4'] : null;
        $selected_members = array_values(array_filter([$m1, $m2, $m3, $m4]));
    }

    $member1 = $selected_members[0] ?? null;
    $member2 = $selected_members[1] ?? null;
    $member3 = $selected_members[2] ?? null;
    $member4 = $selected_members[3] ?? null;

    if (!empty($team_name) && $member1) {
        try {
            $pdo->beginTransaction();
            
            // Verify all selected members belong to the same club
            $memberIds = array_values(array_filter([$member1, $member2, $member3, $member4]));
            if (!empty($memberIds)) {
                $placeholders = str_repeat('?,', count($memberIds) - 1) . '?';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE member_id IN ($placeholders) AND club_id = ?");
                $params = $memberIds;
                $params[] = $club_id;
                $stmt->execute($params);
                $validMembers = $stmt->fetchColumn();
                
                if ((int)$validMembers !== count($memberIds)) {
                    throw new PDOException('Invalid member selection. All members must belong to the same club.');
                }
            }
            
            $sql = "UPDATE teams SET team_name = ?, member1_id = ?, member2_id = ?, member3_id = ?, member4_id = ? WHERE team_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$team_name, $member1, $member2, $member3, $member4, $team_id]);
            
            save_team_members($pdo, $team_id, $selected_members);
            
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $_SESSION['success'] = "Team updated successfully!";
            header("Location: manage_teams.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error'] = "Error updating team: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Team name and at least one member are required.";
    }
}

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Team</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <script src="../js/i18n.js"></script>
    <style>
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: var(--spacing-3, 0.75rem);
            padding: var(--spacing-3, 0.75rem);
            border: 1.5px solid var(--color-border-strong);
            border-radius: var(--radius-sm, 4px);
            background: var(--color-surface-muted);
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-2, 0.5rem);
            padding: var(--spacing-2, 0.5rem);
            border-radius: var(--radius-sm, 4px);
            transition: background-color var(--transition-fast, 0.15s);
            cursor: pointer;
            user-select: none;
        }
        .checkbox-item:hover {
            background-color: var(--color-surface);
        }
    </style>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('teams', $club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Edit Team', htmlspecialchars($team['team_name'])); ?>
    </div>

    <div class="container container--narrow">
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <h2 data-i18n="admin.teamDetails">Edit Team</h2>
            </div>
            <form method="POST" action="edit_team.php?team_id=<?php echo $team_id; ?>&club_id=<?php echo $club_id; ?>" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
                <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                <div class="grid grid--columns-2">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="team_name" data-i18n="teams.teamName">Team Name</label>
                        <input type="text" id="team_name" name="team_name" value="<?php echo htmlspecialchars($team['team_name']); ?>" required class="form-control">
                    </div>

                        <?php
                        $current_team_members = get_team_member_ids($pdo, (int)$team_id);
                        ?>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                                <label class="form-label" style="margin:0;"><span data-i18n="teams.selectTeamMembers">Select Team Members:</span> <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                                <div style="display:flex; gap:0.5rem;">
                                    <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.team-member-checkbox', true)" data-i18n="common.selectAll">Select All</button>
                                    <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.team-member-checkbox', false)" data-i18n="common.uncheckAll">Uncheck All</button>
                                </div>
                            </div>
                            <div id="team-members-checkbox-list" class="checkbox-grid">
                                <?php foreach ($members as $member): ?>
                                    <label for="team_member_<?php echo $member['member_id']; ?>" class="form-check checkbox-item">
                                        <input type="checkbox" name="team_members[]" id="team_member_<?php echo $member['member_id']; ?>" value="<?php echo $member['member_id']; ?>" class="form-check-input team-member-checkbox" <?php echo in_array((int)$member['member_id'], $current_team_members) ? 'checked' : ''; ?>>
                                        <span class="form-check-label"><?php echo htmlspecialchars($member['nickname'] ?? $member['member_name'] ?? ''); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                </div>

                <div class="form-actions" style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-start; flex-wrap: wrap;">
                    <input type="hidden" name="update_team" value="1">
                    <button type="submit" class="btn btn--primary" data-i18n="admin.updateTeam">Update Team</button>
                    <a href="manage_teams.php?club_id=<?php echo $club_id; ?>" class="btn btn--subtle" data-i18n="common.cancel">Cancel</a>
                    <button type="button" class="btn btn--danger" style="margin-left: auto;" data-i18n="admin.deleteTeam"
                            onclick="showConfirmDialog(event, {
                                title: '⚠️ Delete Team',
                                message: 'Are you sure you want to delete <strong><?php echo addslashes(htmlspecialchars($team['team_name'])); ?></strong>?',
                                confirmText: 'Delete Team',
                                cancelText: 'Cancel',
                                type: 'danger',
                                warningMessage: 'This action is permanent and cannot be undone. Team records and statistics will be removed.',
                                onConfirm: () => document.getElementById('delete-team-form').submit()
                            })">Delete Team</button>
                </div>
            </form>

            <form id="delete-team-form" action="edit_team.php?team_id=<?php echo $team_id; ?>&club_id=<?php echo $club_id; ?>" method="POST" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="delete">
            </form>
        </div>

        <script>
        function toggleAllCheckboxes(selector, checkedState) {
            document.querySelectorAll(selector).forEach(cb => {
                const item = cb.closest('.checkbox-item');
                if (!item || item.style.display !== 'none') {
                    if (cb.checked !== checkedState) {
                        cb.checked = checkedState;
                        cb.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        }
        </script>
    </div>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
