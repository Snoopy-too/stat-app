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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('members', $club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Edit Member', htmlspecialchars($member['member_name'])); ?>
        <div class="header-actions">
            <a href="../member_stathistory.php?id=<?php echo $member_id; ?>" class="btn btn--ghost btn--small" target="_blank" title="View public profile">👁️ View Profile</a>
        </div>
    </div>

    <div class="container container--narrow">
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <h2>Edit Member</h2>
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

                <div class="form-actions" style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-start;">
                    <button type="submit" class="btn btn--primary">Update Member</button>
                    <a href="manage_members.php?club_id=<?php echo $club_id; ?>" class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>