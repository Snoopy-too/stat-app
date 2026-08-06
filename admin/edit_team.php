<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : (isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0);

// Get team info
$stmt = $pdo->prepare("SELECT * FROM teams WHERE team_id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : (isset($_POST['club_id']) ? (int)$_POST['club_id'] : (int)($team['club_id'] ?? 0));

if (!$team) {
    header("Location: club_teams.php" . ($club_id ? "?club_id=" . $club_id : ""));
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

    $team_name = trim($_POST['team_name'] ?? '');
    $member1 = !empty($_POST['member1']) ? (int)$_POST['member1'] : null;
    $member2 = !empty($_POST['member2']) ? (int)$_POST['member2'] : null;
    $member3 = !empty($_POST['member3']) ? (int)$_POST['member3'] : null;
    $member4 = !empty($_POST['member4']) ? (int)$_POST['member4'] : null;

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
            
            $pdo->commit();
            $_SESSION['success'] = "Team updated successfully!";
            header("Location: club_teams.php?club_id=" . $club_id);
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
                <h2>Edit Team</h2>
            </div>
            <form method="POST" action="edit_team.php?team_id=<?php echo $team_id; ?>&club_id=<?php echo $club_id; ?>" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
                <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                <div class="grid grid--columns-2">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="team_name">Team Name</label>
                        <input type="text" id="team_name" name="team_name" value="<?php echo htmlspecialchars($team['team_name']); ?>" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="member1">Member 1 (Required)</label>
                        <select id="member1" name="member1" required class="form-control">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $team['member1_id'] ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $team['member2_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php $has_m3 = !empty($team['member3_id']); ?>
                    <div class="form-group" id="group-member3" style="<?php echo $has_m3 ? '' : 'display: none;'; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <label for="member3" style="margin-bottom: 0;">Member 3</label>
                            <button type="button" onclick="removeMemberField(3)" style="background: none; border: none; color: var(--color-danger); cursor: pointer; font-size: 0.8rem; font-weight: 500;">✕ Remove</button>
                        </div>
                        <select id="member3" name="member3" class="form-control">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $team['member3_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php $has_m4 = !empty($team['member4_id']); ?>
                    <div class="form-group" id="group-member4" style="<?php echo $has_m4 ? '' : 'display: none;'; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <label for="member4" style="margin-bottom: 0;">Member 4</label>
                            <button type="button" onclick="removeMemberField(4)" style="background: none; border: none; color: var(--color-danger); cursor: pointer; font-size: 0.8rem; font-weight: 500;">✕ Remove</button>
                        </div>
                        <select id="member4" name="member4" class="form-control">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $team['member4_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 0.75rem;">
                    <button type="button" id="add-member-btn" class="btn btn--secondary btn--small" onclick="addMemberField()" style="<?php echo ($has_m3 && $has_m4) ? 'display: none;' : ''; ?>">
                        ➕ Add Member
                    </button>
                </div>

                <div class="form-actions" style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-start;">
                    <input type="hidden" name="update_team" value="1">
                    <button type="submit" class="btn btn--primary">Update Team</button>
                    <a href="club_teams.php?club_id=<?php echo $club_id; ?>" class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>
        <script>
        function addMemberField() {
            const group3 = document.getElementById('group-member3');
            const group4 = document.getElementById('group-member4');
            const btn = document.getElementById('add-member-btn');

            if (group3.style.display === 'none') {
                group3.style.display = 'block';
            } else if (group4.style.display === 'none') {
                group4.style.display = 'block';
                btn.style.display = 'none';
            }
            updateMemberDropdowns();
        }

        function removeMemberField(num) {
            const group = document.getElementById('group-member' + num);
            const select = document.getElementById('member' + num);
            const btn = document.getElementById('add-member-btn');

            if (group) {
                group.style.display = 'none';
                if (select) select.value = '';
            }
            btn.style.display = 'inline-flex';
            updateMemberDropdowns();
        }

        function updateMemberDropdowns() {
            const selects = [
                document.getElementById('member1'),
                document.getElementById('member2'),
                document.getElementById('member3'),
                document.getElementById('member4')
            ];
            const selected = selects.map(sel => sel.value).filter(v => v !== '');
            selects.forEach(sel => {
                Array.from(sel.options).forEach(opt => {
                    if (opt.value === '') {
                        opt.disabled = false;
                    } else {
                        opt.disabled = selected.includes(opt.value) && sel.value !== opt.value;
                    }
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            const selects = [
                document.getElementById('member1'),
                document.getElementById('member2'),
                document.getElementById('member3'),
                document.getElementById('member4')
            ];
            selects.forEach(sel => {
                sel.addEventListener('change', updateMemberDropdowns);
            });
            updateMemberDropdowns();
        });
        </script>
    </div>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
