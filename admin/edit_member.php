<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

// Fetch all clubs for the current admin
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE admin_id = ? ORDER BY club_name");
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
            $stmt = $pdo->prepare("SELECT club_id FROM clubs WHERE club_id = ? AND admin_id = ?");
            $stmt->execute([$_POST['club_id'], $_SESSION['admin_id']]);
            if (!$stmt->fetch()) {
                throw new Exception("Unauthorized club access");
            }
        }

        $stmt = $pdo->prepare("UPDATE members SET member_name = ?, nickname = ?, email = ?, status = ?, club_id = ? WHERE member_id = ? AND club_id = ? AND admin_id = ?");
        $stmt->execute([
            trim($_POST['member_name']),
            trim($_POST['nickname']),
            trim($_POST['email']),
            $_POST['status'],
            $_POST['club_id'],
            $member_id,
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
<body>
    <?php
    // Render breadcrumbs
    NavigationHelper::renderBreadcrumbs([
        ['label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['label' => 'Clubs', 'url' => 'manage_clubs.php'],
        ['label' => 'Members', 'url' => 'manage_members.php?club_id=' . $club_id],
        'Edit Member'
    ]);
    ?>
    
    <div class="header">
        <div class="header-title-group">
            <?php NavigationHelper::renderHeaderTitle('Edit Member', htmlspecialchars($member['member_name']), 'dashboard.php', false); ?>
        </div>
        <div class="header-actions">
            <a href="../member_stathistory.php?id=<?php echo $member_id; ?>" class="btn btn--ghost btn--small" target="_blank" title="View public profile">👁️ View Profile</a>
            <a href="dashboard.php" class="btn btn--secondary btn--small">🏠 Dashboard</a>
            <a href="manage_members.php?club_id=<?php echo $club_id; ?>" class="btn btn--secondary btn--small">← Back to Members</a>
        </div>
    </div>
    
    <?php
    // Render admin navigation
    NavigationHelper::renderAdminNav('members', $club_id);
    ?>

    <div class="container">
        <?php display_session_message('error'); ?>

        <div class="card">
            <form method="POST" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="member_name" value="<?php echo htmlspecialchars($member['member_name']); ?>" required class="form-control">
                    
                    <label>Nickname (for public display)</label>
                    <input type="text" name="nickname" value="<?php echo htmlspecialchars($member['nickname']); ?>" required class="form-control">
                    
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($member['email']); ?>" required class="form-control">
                    
                    <label>Club</label>
                    <select name="club_id" required class="form-control">
                        <?php foreach ($admin_clubs as $club): ?>
                            <option value="<?php echo $club['club_id']; ?>" <?php echo ($member['club_id'] == $club['club_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($club['club_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo $member['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $member['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>

                    <button type="submit" class="btn">Update Member</button>
                    <a href="manage_members.php?club_id=<?php echo $club_id; ?>" class="btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>