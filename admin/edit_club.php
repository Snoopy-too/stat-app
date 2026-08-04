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
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get club details and check permissions
$stmt = $pdo->prepare("
    SELECT c.*, ca.role as admin_role
    FROM clubs c
    JOIN club_admins ca ON c.club_id = ca.club_id
    WHERE c.club_id = ? AND ca.admin_id = ?
");
$stmt->execute([$club_id, $_SESSION['admin_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club || $club['admin_role'] !== 'owner') {
    $_SESSION['error'] = "Only the club owner can edit this club.";
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from_param = isset($_GET['from']) ? '&from=' . urlencode($_GET['from']) : '';

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: edit_club.php?id=" . $club_id . $from_param);
        exit();
    }

    $action = $_POST['action'] ?? 'update';

    if ($action === 'add_admin') {
        if ($club['admin_role'] !== 'owner') {
            $_SESSION['error'] = "Only the club owner can add administrators.";
        } else {
            $email = trim($_POST['share_email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Please enter a valid email address.";
            } else {
                $stmt = $pdo->prepare("SELECT admin_id, username FROM admin_users WHERE email = ?");
                $stmt->execute([$email]);
                $target_admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$target_admin) {
                    $_SESSION['error'] = "No administrator account found with that email address.";
                } elseif ($target_admin['admin_id'] == $_SESSION['admin_id']) {
                    $_SESSION['error'] = "You are already the owner of this club.";
                } else {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO club_admins (club_id, admin_id, role) VALUES (?, ?, 'admin')");
                    $stmt->execute([$club_id, $target_admin['admin_id']]);

                    if ($stmt->rowCount() > 0) {
                        $_SESSION['success'] = "Administrator '" . htmlspecialchars($target_admin['username']) . "' added successfully!";
                    } else {
                        $_SESSION['error'] = "'" . htmlspecialchars($target_admin['username']) . "' is already an administrator of this club.";
                    }
                }
            }
        }
        header("Location: edit_club.php?id=" . $club_id . $from_param);
        exit();
    } elseif ($action === 'remove_admin') {
        if ($club['admin_role'] !== 'owner') {
            $_SESSION['error'] = "Only the club owner can remove administrators.";
        } else {
            $target_admin_id = (int)($_POST['target_admin_id'] ?? 0);
            if ($target_admin_id === (int)$_SESSION['admin_id']) {
                $_SESSION['error'] = "You cannot remove yourself as owner.";
            } else {
                $delStmt = $pdo->prepare("DELETE FROM club_admins WHERE club_id = ? AND admin_id = ? AND role != 'owner'");
                $delStmt->execute([$club_id, $target_admin_id]);
                if ($delStmt->rowCount() > 0) {
                    $_SESSION['success'] = "Administrator removed from club.";
                } else {
                    $_SESSION['error'] = "Could not remove administrator.";
                }
            }
        }
        header("Location: edit_club.php?id=" . $club_id . $from_param);
        exit();
    } elseif ($action === 'delete') {
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin_user = $stmt->fetch();

        if (!$admin_user || !password_verify($password, $admin_user['password_hash'])) {
            $_SESSION['error'] = "Incorrect password. Deletion cancelled.";
            header("Location: edit_club.php?id=" . $club_id . $from_param);
            exit();
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE club_id = ?");
        $stmt->execute([$club_id]);
        if ($stmt->fetchColumn() > 1) {
            $_SESSION['error'] = "Cannot delete a shared club. Please remove other admins first.";
            header("Location: edit_club.php?id=" . $club_id . $from_param);
            exit();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT game_id FROM games WHERE club_id = ?");
            $stmt->execute([$club_id]);
            $game_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($game_ids)) {
                $placeholders = implode(',', array_fill(0, count($game_ids), '?'));
                $pdo->prepare("DELETE FROM game_result_losers WHERE result_id IN (SELECT result_id FROM game_results WHERE game_id IN ($placeholders))")->execute($game_ids);
                $pdo->prepare("DELETE FROM game_results WHERE game_id IN ($placeholders)")->execute($game_ids);
                $pdo->prepare("DELETE FROM team_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = ?)")->execute([$club_id]);
                $pdo->prepare("DELETE FROM games WHERE club_id = ?")->execute([$club_id]);
            }

            $pdo->prepare("DELETE FROM champions WHERE club_id = ?")->execute([$club_id]);
            $pdo->prepare("DELETE FROM teams WHERE club_id = ?")->execute([$club_id]);
            $pdo->prepare("DELETE FROM members WHERE club_id = ?")->execute([$club_id]);
            $pdo->prepare("DELETE FROM club_admins WHERE club_id = ?")->execute([$club_id]);
            $pdo->prepare("DELETE FROM clubs WHERE club_id = ?")->execute([$club_id]);

            $pdo->commit();

            if (isset($_SESSION['current_club_id']) && $_SESSION['current_club_id'] == $club_id) {
                unset($_SESSION['current_club_id']);
                unset($_SESSION['club_id']);
            }

            $_SESSION['success'] = "Club deleted successfully.";
            $from = $_GET['from'] ?? '';
            $redirect_target = ($from === 'account') ? "account.php" : (($from === 'manage_clubs') ? "manage_clubs.php" : "dashboard.php");
            header("Location: " . $redirect_target);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to delete club: " . $e->getMessage());
            $_SESSION['error'] = "Failed to delete club. Please try again.";
            header("Location: edit_club.php?id=" . $club_id . $from_param);
            exit();
        }
    } else {
        $club_name = trim($_POST['club_name']);
        $slug = trim($_POST['slug']);
        $slug = $slug === '' ? null : $slug;
        $status = $_POST['status'] ?? 'active';
        
        if (empty($club_name)) {
            $_SESSION['error'] = "Club name cannot be empty.";
        } elseif ($slug !== null && !preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
            $_SESSION['error'] = "Slug can only contain letters, numbers, and hyphens.";
        } elseif ($slug !== null && in_array(strtolower($slug), ['admin', 'api', 'index', 'login', 'logout', 'dashboard', 'config', 'includes', 'css', 'js', 'images', 'uploads'])) {
            $_SESSION['error'] = "This slug is reserved and cannot be used.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE clubs 
                    SET club_name = ?, slug = ?, status = ?
                    WHERE club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)
                ");
                $stmt->execute([
                    $club_name, $slug, $status, $club_id, $club_id, $_SESSION['admin_id']
                ]);
                
                $_SESSION['success'] = "Club updated successfully!";
                $from = $_GET['from'] ?? '';
                $redirect_target = "view_club.php?id=" . $club_id;
                if ($from === 'account') {
                    $redirect_target = "account.php";
                } elseif ($from === 'manage_clubs') {
                    $redirect_target = "manage_clubs.php";
                }
                header("Location: " . $redirect_target);
                exit();
                
            } catch(PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['error'] = "This slug is already in use. Please choose a different one.";
                } else {
                    $_SESSION['error'] = "Failed to update club. Please try again.";
                }
            }
        }
    }
}

// Fetch all administrators for this club
$adminsStmt = $pdo->prepare("
    SELECT u.admin_id, u.username, u.email, ca.role
    FROM club_admins ca
    JOIN admin_users u ON ca.admin_id = u.admin_id
    WHERE ca.club_id = ?
    ORDER BY CASE WHEN ca.role = 'owner' THEN 0 ELSE 1 END, u.username ASC
");
$adminsStmt->execute([$club_id]);
$club_administrators = $adminsStmt->fetchAll(PDO::FETCH_ASSOC);

$statuses = ['active', 'suspended', 'inactive'];
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Club - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('clubs', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Edit Club', $club['club_name']); ?>
    </div>

    <div class="container container--narrow">
        <div class="card">
            <?php display_session_message('error'); ?>
            <?php display_session_message('success'); ?>

            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="update">

                <div class="form-group">
                    <label for="club_name">Club Name:</label>
                    <input type="text" id="club_name" name="club_name" class="form-control" required
                           value="<?php echo htmlspecialchars($club['club_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="slug">Club URL Slug (optional):</label>
                    <input type="text" id="slug" name="slug" class="form-control"
                           pattern="[a-zA-Z0-9\-]+" title="Only letters, numbers, and hyphens allowed"
                           value="<?php echo htmlspecialchars($club['slug'] ?? ''); ?>">
                    <small style="display:block; margin-top:0.5rem; color:var(--text-light);">
                        Leave empty to use ID-based URL. If set, club will be accessible at domain.com/slug
                    </small>
                    <?php if (!empty($club['slug'])): ?>
                        <div style="margin-top:1rem; padding:1rem; background:var(--bg-secondary); border-radius:0.5rem;">
                            <strong style="display:block; margin-bottom:0.5rem;">Current Vanity URL:</strong>
                            <div style="display:flex; gap:0.5rem; align-items:center;">
                                <code id="vanity-url" style="flex:1; padding:0.5rem; background:var(--bg-primary); border-radius:0.25rem; font-size:0.9rem;">
                                    <?php
                                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                                    $host = $_SERVER['HTTP_HOST'];
                                    $base_path = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/');
                                    echo htmlspecialchars($protocol . '://' . $host . $base_path . '/' . $club['slug']);
                                    ?>
                                </code>
                                <button type="button" class="btn btn--small btn--subtle" onclick="copyVanityUrl(this)">Copy</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="status">Club Status:</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>"
                                <?php echo ($status == $club['status']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                    <button type="submit" class="btn btn--primary">Save Changes</button>
                    <button type="button" class="btn btn--danger" onclick="openDeleteModal()">Delete Club</button>
                    <a href="<?php echo (!empty($_GET['from']) && $_GET['from'] === 'account') ? 'account.php' : ((!empty($_GET['from']) && $_GET['from'] === 'manage_clubs') ? 'manage_clubs.php' : 'view_club.php?id=' . $club_id); ?>" class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Club Administrators Section -->
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h2>Administrators (<?php echo count($club_administrators); ?>)</h2>
            </div>

            <?php if ($club['admin_role'] === 'owner'): ?>
                <div id="add-admin-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'add_admin' && isset($_SESSION['error'])) ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                    <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add New Administrator</h3>
                    <form method="POST" class="form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="add_admin">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="share_email">Admin Email Address <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <input type="email" id="share_email" name="share_email" placeholder="admin@example.com" required class="form-control" style="max-width: 400px;">
                            <small style="color: var(--color-text-muted); font-size: 0.85rem; margin-top: 0.25rem; display: block;">Enter the registered email address of the administrator you want to add to this club.</small>
                        </div>
                        <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                            <button type="submit" class="btn btn--primary">Save Administrator</button>
                            <button type="button" class="btn btn--subtle" onclick="toggleAddAdminForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <div class="card-toolbar">
                    <button type="button" class="btn btn--primary" id="add-admin-btn" onclick="toggleAddAdminForm()" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'add_admin' && isset($_SESSION['error'])) ? 'visibility:hidden;' : ''; ?>">
                        <span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>Add an Administrator
                    </button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <?php if ($club['admin_role'] === 'owner'): ?>
                                <th style="text-align:right;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($club_administrators as $admin_item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($admin_item['username']); ?></strong>
                                    <?php if ($admin_item['admin_id'] == $_SESSION['admin_id']): ?>
                                        <small style="color: var(--color-text-muted);">(You)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($admin_item['email'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php echo $admin_item['role'] === 'owner' ? 'badge--primary' : 'badge--secondary'; ?>" style="text-transform: capitalize; font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($admin_item['role']); ?>
                                    </span>
                                </td>
                                <?php if ($club['admin_role'] === 'owner'): ?>
                                    <td style="text-align:right;">
                                        <?php if ($admin_item['role'] !== 'owner' && $admin_item['admin_id'] != $_SESSION['admin_id']): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove <?php echo addslashes($admin_item['username']); ?> from this club?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="remove_admin">
                                                <input type="hidden" name="target_admin_id" value="<?php echo (int)$admin_item['admin_id']; ?>">
                                                <button type="submit" class="btn btn--small btn--danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Club Modal -->
    <div id="deleteClubModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__content">
                <div class="modal__header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
                    <h3 class="modal__title" style="margin:0; color:var(--color-heading); font-size:1.25rem; font-weight:600;">⚠️ Delete Club</h3>
                    <button type="button" class="modal__close" onclick="closeDeleteModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--color-text-muted);">&times;</button>
                </div>
                <div class="modal__body">
                    <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($club['club_name']); ?></strong>?</p>
                    <div class="message message--error" style="margin: 1rem 0;">
                        <strong>Warning:</strong> This action is permanent and cannot be undone. All associated members, games, champions, teams, and match results will be permanently erased.
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="delete">
                        
                        <div class="form-group" style="margin-top: 1rem;">
                            <label for="admin_password">Confirm Password:</label>
                            <input type="password" id="admin_password" name="password" class="form-control" required placeholder="Enter your password to confirm">
                        </div>
                        
                        <div class="form-actions" style="margin-top: 1.5rem; display:flex; gap:0.5rem; justify-content:flex-start;">
                            <button type="submit" class="btn btn--danger">Permanently Delete Club</button>
                            <button type="button" class="btn btn--subtle" onclick="closeDeleteModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAddAdminForm() {
            const wrapper = document.getElementById('add-admin-form-wrapper');
            const btn = document.getElementById('add-admin-btn');
            if (!wrapper) return;
            if (wrapper.style.display === 'none') {
                wrapper.style.display = 'block';
                if (btn) btn.style.visibility = 'hidden';
                const emailInput = document.getElementById('share_email');
                if (emailInput) emailInput.focus();
            } else {
                wrapper.style.display = 'none';
                if (btn) btn.style.visibility = 'visible';
            }
        }

        function openDeleteModal() {
            document.getElementById('deleteClubModal').classList.add('is-open');
            document.getElementById('admin_password').focus();
        }

        function closeDeleteModal() {
            document.getElementById('deleteClubModal').classList.remove('is-open');
            document.getElementById('admin_password').value = '';
        }

        function copyVanityUrl(btn) {
            const urlElement = document.getElementById('vanity-url');
            if (!urlElement) return;
            const url = urlElement.textContent.trim();

            navigator.clipboard.writeText(url).then(() => {
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.classList.add('btn--success');

                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('btn--success');
                }, 2000);
            }).catch(err => {
                alert('Failed to copy URL: ' + err);
            });
        }
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
