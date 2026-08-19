<?php
require_once __DIR__ . '/../config/session.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$champion_id = isset($_GET['champion_id']) ? (int)$_GET['champion_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// Fetch champion details
$stmt = $pdo->prepare("SELECT c.*, m.member_name, m.nickname, m.club_id as member_club_id FROM champions c JOIN members m ON c.member_id = m.member_id WHERE c.ID = ?");
$stmt->execute([$champion_id]);
$champion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$champion) {
    $_SESSION['error'] = "Champion record not found.";
    header("Location: manage_champions.php" . ($club_id ? "?club_id=$club_id" : ""));
    exit();
}

if (!$club_id) {
    $club_id = (int)$champion['club_id'] ?: (int)$champion['member_club_id'];
}

// Determine date column name in champions table
$champ_cols = $pdo->query("DESCRIBE champions")->fetchAll(PDO::FETCH_ASSOC);
$c_field_names = array_column($champ_cols, 'Field');
$date_col = in_array('date', $c_field_names) ? 'date' : (in_array('start_date', $c_field_names) ? 'start_date' : 'created_at');

$saved_date_raw = $champion[$date_col] ?? $champion['date'] ?? $champion['start_date'] ?? '';
$saved_date = !empty($saved_date_raw) ? date('Y-m-d', strtotime($saved_date_raw)) : date('Y-m-d');

// Fetch members for the club dropdown
$stmt = $pdo->prepare("SELECT member_id, member_name, nickname FROM members WHERE club_id = ? ORDER BY member_name ASC");
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: edit_champion.php?champion_id=" . $champion_id . "&club_id=" . $club_id);
        exit();
    }

    $new_member_id = !empty($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
    $new_date = !empty($_POST['date']) ? trim($_POST['date']) : '';
    $new_comments = isset($_POST['champ_comments']) ? trim($_POST['champ_comments']) : '';

    if ($new_member_id && $new_date) {
        try {
            $stmt = $pdo->prepare("UPDATE champions SET member_id = ?, {$date_col} = ?, champ_comments = ? WHERE ID = ? AND club_id = ?");
            $stmt->execute([$new_member_id, $new_date, $new_comments, $champion_id, $club_id]);
            $_SESSION['success'] = "Champion updated successfully!";
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to update champion: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Member and date are required fields.";
    }
}

// Generate CSRF token
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View/Edit Champion</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <script src="../js/i18n.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('champions', $club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('View/Edit Champion', htmlspecialchars($champion['member_name'])); ?>
    </div>

    <div class="container container--narrow">
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <h2 data-i18n="admin.championDetails">Champion Details</h2>
            </div>
            <form method="POST" class="stack" style="padding: var(--spacing-6, 1.5rem);">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="member_id" class="form-label"><span data-i18n="members.name">Member</span> <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                    <select id="member_id" name="member_id" required class="form-control">
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['member_id']; ?>" <?php echo ($champion['member_id'] == $m['member_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['member_name']) . ($m['nickname'] ? ' (' . htmlspecialchars($m['nickname']) . ')' : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date" class="form-label"><span data-i18n="champions.awarded">Championship Date</span> <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($saved_date); ?>" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="champ_comments" class="form-label" data-i18n="champions.seasonTitle">Comments / Title Details</label>
                    <textarea id="champ_comments" name="champ_comments" class="form-control" rows="3"><?php echo htmlspecialchars($champion['champ_comments']); ?></textarea>
                </div>

                <div class="form-actions" style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-start; flex-wrap: wrap;">
                    <button type="submit" class="btn btn--primary" data-i18n="admin.updateChampion">Update Champion</button>
                    <a href="manage_champions.php?club_id=<?php echo $club_id; ?>" class="btn btn--subtle" data-i18n="common.cancel">Cancel</a>
                    
                    <button type="button" class="btn btn--danger" style="margin-left: auto;" data-i18n="admin.deleteChampion"
                            onclick="showConfirmDialog(event, {
                                title: '⚠️ Delete Champion',
                                message: 'Are you sure you want to delete this championship record for <strong><?php echo addslashes(htmlspecialchars($champion['member_name'])); ?></strong>?',
                                confirmText: 'Delete Champion',
                                cancelText: 'Cancel',
                                type: 'danger',
                                warningMessage: 'This action is permanent and cannot be undone.',
                                onConfirm: () => document.getElementById('delete-champion-form').submit()
                            })">Delete Champion</button>
                </div>
            </form>

            <form method="POST" action="manage_champions.php?club_id=<?php echo $club_id; ?>" id="delete-champion-form" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="champion_id" value="<?php echo $champion_id; ?>">
            </form>
        </div>
    </div>

    <script src="../js/sidebar.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/form-validation.js"></script>
</body>
</html>
