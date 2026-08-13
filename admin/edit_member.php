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
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

// Fetch all clubs for the current admin
$stmt = $pdo->prepare("SELECT c.* FROM clubs c JOIN club_admins ca ON c.club_id = ca.club_id WHERE ca.admin_id = ? ORDER BY c.club_name");
$stmt->execute([$_SESSION['admin_id']]);
$admin_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get member info
$stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ? AND club_id = ?");
$stmt->execute([$member_id, $club_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header("Location: manage_members.php?club_id=" . $club_id);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: edit_member.php?club_id=" . $club_id . "&member_id=" . $member_id);
        exit();
    }

    try {
        // Verify the new club belongs to the admin
        if (isset($_POST['club_id'])) {
            $stmt = $pdo->prepare("SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?");
            $stmt->execute([$_POST['club_id'], $_SESSION['admin_id']]);
            if (!$stmt->fetch()) {
                throw new Exception("Unauthorized club access");
            }
        }

        $stmt = $pdo->prepare("UPDATE members SET member_name = ?, nickname = ?, email = ?, status = ?, club_id = ? WHERE member_id = ? AND club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)");
        $stmt->execute([
            trim($_POST['member_name']),
            trim($_POST['nickname']),
            trim($_POST['email']),
            $_POST['status'],
            $_POST['club_id'],
            $member_id,
            $club_id,
            $club_id,
            $_SESSION['admin_id']
        ]);
        $_SESSION['success'] = "Member updated successfully!";
        header("Location: manage_members.php?club_id=" . $club_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to update member: " . $e->getMessage();
    }
}

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();

// Fetch game history and statistics for this member
$game_history = [];
$competitive_games = 0;
$total_wins = 0;
$total_points = 0;
$coop_wins = 0;
$coop_total = 0;
$championship_count = 0;

if ($member_id) {
    try {
        // Championships count
        $champ_stmt = $pdo->prepare("SELECT COUNT(*) FROM champions WHERE member_id = ?");
        $champ_stmt->execute([$member_id]);
        $championship_count = (int)$champ_stmt->fetchColumn();

        // Game history query
        $stmt_history = $pdo->prepare("
            SELECT DISTINCT gr.played_at as game_date, g.game_name,
                   CASE WHEN gr.winner = ? THEN 1 WHEN gr.place_2 = ? THEN 2 WHEN gr.place_3 = ? THEN 3 WHEN gr.place_4 = ? THEN 4 WHEN gr.place_5 = ? THEN 5 WHEN gr.place_6 = ? THEN 6 WHEN gr.place_7 = ? THEN 7 WHEN gr.place_8 = ? THEN 8 END as position,
                   gr.num_players, gr.game_id, gr.result_id, 'individual' as game_type, NULL as coop_outcome
            FROM game_results gr
            JOIN games g ON gr.game_id = g.game_id
            WHERE gr.winner = ? OR gr.place_2 = ? OR gr.place_3 = ? OR gr.place_4 = ? OR gr.place_5 = ? OR gr.place_6 = ? OR gr.place_7 = ? OR gr.place_8 = ?

            UNION ALL

            SELECT DISTINCT tgr.played_at as game_date, g.game_name,
                   CASE WHEN tgr.winner = t.team_id THEN 1 WHEN tgr.place_2 = t.team_id THEN 2 WHEN tgr.place_3 = t.team_id THEN 3 WHEN tgr.place_4 = t.team_id THEN 4 END as position,
                   tgr.num_teams as num_players, tgr.game_id, tgr.result_id, 'team' as game_type, NULL as coop_outcome
            FROM teams t
            JOIN team_game_results tgr ON (t.team_id = tgr.winner OR t.team_id = tgr.place_2 OR t.team_id = tgr.place_3 OR t.team_id = tgr.place_4)
            JOIN games g ON tgr.game_id = g.game_id
            WHERE (t.member1_id = ? OR t.member2_id = ? OR t.member3_id = ? OR t.member4_id = ?)

            UNION ALL

            SELECT cgr.played_at as game_date, g.game_name,
                   CASE cgr.outcome WHEN 'win' THEN 0 ELSE -1 END as position,
                   cgr.num_participants as num_players, cgr.game_id, cgr.result_id, 'cooperative' as game_type, cgr.outcome as coop_outcome
            FROM cooperative_game_results cgr
            JOIN cooperative_result_participants crp ON cgr.result_id = crp.result_id
            JOIN games g ON cgr.game_id = g.game_id
            WHERE crp.participant_type = 'member' AND crp.member_id = ?

            UNION ALL

            SELECT cgr.played_at as game_date, g.game_name,
                   CASE cgr.outcome WHEN 'win' THEN 0 ELSE -1 END as position,
                   cgr.num_participants as num_players, cgr.game_id, cgr.result_id, 'cooperative' as game_type, cgr.outcome as coop_outcome
            FROM cooperative_game_results cgr
            JOIN cooperative_result_participants crp ON cgr.result_id = crp.result_id
            JOIN teams t ON crp.team_id = t.team_id
            JOIN games g ON cgr.game_id = g.game_id
            WHERE crp.participant_type = 'team' AND (t.member1_id = ? OR t.member2_id = ? OR t.member3_id = ? OR t.member4_id = ?)

            ORDER BY game_date DESC
        ");

        $params = array_fill(0, 16, $member_id);
        $params = array_merge($params, array_fill(0, 4, $member_id));
        $params[] = $member_id;
        $params = array_merge($params, array_fill(0, 4, $member_id));
        $stmt_history->execute($params);
        $game_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

        foreach ($game_history as $gh) {
            if ($gh['game_type'] === 'cooperative') {
                $coop_total++;
                if ($gh['coop_outcome'] === 'win') {
                    $coop_wins++;
                }
            } else {
                $competitive_games++;
                $pos = (int)$gh['position'];
                $total_points += $pos;
                if ($pos === 1) {
                    $total_wins++;
                }
            }
        }
    } catch (Exception $e) {}
}

$average_finish = $competitive_games > 0 ? number_format($total_points / $competitive_games, 2) : '—';
$coop_win_rate = $coop_total > 0 ? number_format(($coop_wins / $coop_total) * 100, 0) . '%' : '—';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View/Edit Member - <?php echo htmlspecialchars($member['member_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('members', $club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('View/Edit Member', htmlspecialchars($member['member_name'])); ?>
    </div>

    <div class="container container--narrow">
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <h2>Member Details</h2>
            </div>
            <form method="POST" class="stack" style="padding: var(--spacing-6, 1.5rem);">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="grid grid--columns-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="member_name" class="form-label">Full Name <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                        <input type="text" id="member_name" name="member_name" value="<?php echo htmlspecialchars($member['member_name']); ?>" required class="form-control">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="nickname" class="form-label">Nickname (for public display) <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                        <input type="text" id="nickname" name="nickname" value="<?php echo htmlspecialchars($member['nickname']); ?>" required class="form-control">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="email" class="form-label">Email Address <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($member['email']); ?>" required class="form-control">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="club_id" class="form-label">Club <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                        <select id="club_id" name="club_id" required class="form-control">
                            <?php foreach ($admin_clubs as $club_item): ?>
                                <option value="<?php echo $club_item['club_id']; ?>" <?php echo ($member['club_id'] == $club_item['club_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($club_item['club_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?php echo $member['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $member['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-start; flex-wrap: wrap;">
                    <button type="submit" class="btn btn--primary">Update Member</button>
                    <a href="manage_members.php?club_id=<?php echo $club_id; ?>" class="btn btn--subtle">Cancel</a>
                    <button type="button" class="btn btn--danger" style="margin-left: auto;"
                            onclick="showConfirmDialog(event, {
                                title: '⚠️ Delete Member',
                                message: 'Are you sure you want to delete <strong><?php echo addslashes(htmlspecialchars($member['member_name'])); ?></strong>?',
                                confirmText: 'Delete Member',
                                cancelText: 'Cancel',
                                type: 'danger',
                                warningMessage: 'This action is permanent and cannot be undone.',
                                onConfirm: () => document.getElementById('delete-member-form').submit()
                            })">Delete Member</button>
                </div>
            </form>

            <form method="POST" action="manage_members.php?club_id=<?php echo $club_id; ?>" style="display:none;" id="delete-member-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
            </form>
        </div>

        <!-- Player Statistics Summary -->
        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.75rem; color: var(--color-heading);">Player Statistics</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem;">
                <div class="card" style="padding: 1rem; text-align: center;">
                    <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Total Wins</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;"><?php echo $total_wins; ?></div>
                </div>
                <div class="card" style="padding: 1rem; text-align: center;">
                    <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Avg. Finish</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-heading); margin-top: 0.25rem;"><?php echo $average_finish; ?></div>
                </div>
                <div class="card" style="padding: 1rem; text-align: center;">
                    <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Games Played</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-heading); margin-top: 0.25rem;"><?php echo count($game_history); ?></div>
                </div>
                <div class="card" style="padding: 1rem; text-align: center;">
                    <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Co-op Win Rate</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-heading); margin-top: 0.25rem;"><?php echo $coop_win_rate; ?></div>
                </div>
                <div class="card" style="padding: 1rem; text-align: center;">
                    <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Championships</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #d97706; margin-top: 0.25rem;">🏆 <?php echo $championship_count; ?></div>
                </div>
            </div>
        </div>

        <!-- Game History -->
        <div class="card" style="margin-top: 1.5rem; margin-bottom: 2rem;">
            <div class="card-header">
                <h2>Game History</h2>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Game</th>
                            <th>Type</th>
                            <th>Place / Outcome</th>
                            <th>Players</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($game_history)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding: 1.5rem;">No game results recorded for this player yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($game_history as $gh): ?>
                                <tr>
                                    <td><?php echo date('Y/m/d', strtotime($gh['game_date'])); ?></td>
                                    <td>
                                        <a href="edit_game.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $gh['game_id']; ?>" style="font-weight: 600; text-decoration: none; color: var(--color-primary);">
                                            <?php echo htmlspecialchars($gh['game_name']); ?>
                                        </a>
                                    </td>
                                    <td><span style="font-size: 0.75rem; text-transform: uppercase; padding: 0.15rem 0.5rem; border-radius: 999px; background: var(--color-surface-muted); font-weight: 600;"><?php echo ucfirst($gh['game_type']); ?></span></td>
                                    <td>
                                        <?php
                                        if ($gh['game_type'] === 'cooperative') {
                                            if ($gh['coop_outcome'] === 'win') {
                                                echo '<span class="status-badge status-active">WIN</span>';
                                            } else {
                                                echo '<span class="status-badge status-inactive">LOSS</span>';
                                            }
                                        } else {
                                            $pos = (int)$gh['position'];
                                            if ($pos === 1) {
                                                echo '<span class="status-badge status-active">🥇 1st Place</span>';
                                            } elseif ($pos === 2) {
                                                echo '<span style="font-weight: 600;">🥈 2nd Place</span>';
                                            } elseif ($pos === 3) {
                                                echo '<span style="font-weight: 600;">🥉 3rd Place</span>';
                                            } elseif ($pos > 0) {
                                                echo $pos . 'th Place';
                                            } else {
                                                echo '—';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo (int)$gh['num_players']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
</body>
</html>