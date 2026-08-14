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

// Ensure admin_type is in session
if (!isset($_SESSION['admin_type']) && isset($_SESSION['admin_id'])) {
    $typeStmt = $pdo->prepare("SELECT admin_type FROM admin_users WHERE admin_id = ?");
    $typeStmt->execute([$_SESSION['admin_id']]);
    $_SESSION['admin_type'] = $typeStmt->fetchColumn() ?: 'multi_club';
}

$security = new SecurityUtils($pdo);

try {
    $pdo->exec("ALTER TABLE admin_users ADD COLUMN default_club_id INT DEFAULT NULL");
} catch (Throwable $e) {}

// Fetch current admin data
$stmt = $pdo->prepare("SELECT username, email, created_at, default_club_id FROM admin_users WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$current_default_club_id = (int)($admin['default_club_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: account.php");
        exit();
    }

    if ($action === 'set_default_club') {
        $new_default = isset($_POST['default_club_id']) ? (int)$_POST['default_club_id'] : 0;
        
        if ($new_default > 0) {
            $chk = $pdo->prepare("SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?");
            $chk->execute([$new_default, $_SESSION['admin_id']]);
            if ($chk->fetch()) {
                $upd = $pdo->prepare("UPDATE admin_users SET default_club_id = ? WHERE admin_id = ?");
                $upd->execute([$new_default, $_SESSION['admin_id']]);
                $_SESSION['success'] = "Default club updated.";
            }
        } else {
            $upd = $pdo->prepare("UPDATE admin_users SET default_club_id = NULL WHERE admin_id = ?");
            $upd->execute([$_SESSION['admin_id']]);
            $_SESSION['success'] = "Default club preference cleared.";
        }
        
        $active_ref = !empty($_POST['active_club_id']) ? (int)$_POST['active_club_id'] : (!empty($_GET['club_id']) ? (int)$_GET['club_id'] : 0);
        $redirect_url = "account.php" . ($active_ref > 0 ? "?club_id=" . $active_ref : "");
        header("Location: " . $redirect_url);
        exit();
    }
    
    if ($action === 'update_club_theme' && !empty($_POST['club_id']) && !empty($_POST['theme'])) {
        $target_club_id = (int)$_POST['club_id'];
        $new_theme = trim($_POST['theme']);
        $allowed_themes = ['midnight', 'tabletop', 'arcade', 'light'];
        
        if (in_array($new_theme, $allowed_themes)) {
            try {
                $pdo->exec("ALTER TABLE clubs ADD COLUMN theme VARCHAR(50) DEFAULT 'midnight'");
            } catch (Throwable $e) {}

            $stmt = $pdo->prepare("UPDATE clubs SET theme = ? WHERE club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)");
            $stmt->execute([$new_theme, $target_club_id, $target_club_id, $_SESSION['admin_id']]);
            
            $_SESSION['current_club_id'] = $target_club_id;
        }
        header("Location: account.php?club_id=" . $target_club_id);
        exit();
    }

    if ($action === 'create' && !empty($_POST['club_name'])) {
        $club_name = trim($_POST['club_name']);
        $slug = trim($_POST['slug'] ?? '');
        $slug = $slug === '' ? null : $slug;
        
        if (!preg_match('/^[a-zA-Z0-9\s_-]+$/', $club_name)) {
            $_SESSION['error'] = "Club name can only contain letters, numbers, spaces, dashes and underscores.";
        } elseif ($slug !== null && !preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
            $_SESSION['error'] = "Slug can only contain letters, numbers, and hyphens.";
        } elseif ($slug !== null && in_array(strtolower($slug), ['admin', 'api', 'index', 'login', 'logout', 'dashboard', 'config', 'includes', 'css', 'js', 'images', 'uploads'])) {
            $_SESSION['error'] = "This slug is reserved and cannot be used.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO clubs (club_name, slug, admin_id) VALUES (?, ?, ?)");
                $stmt->execute([$club_name, $slug, $_SESSION['admin_id']]);
                $new_club_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO club_admins (club_id, admin_id, role) VALUES (?, ?, 'owner')");
                $stmt->execute([$new_club_id, $_SESSION['admin_id']]);
                
                $_SESSION['success'] = "Club created successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['error'] = "This slug is already in use. Please choose a different one.";
                } else {
                    error_log("Failed to create club: " . $e->getMessage());
                    $_SESSION['error'] = "Failed to create club. Please try again.";
                }
            }
        }
        header("Location: account.php");
        exit();
    }

    if ($action === 'leave' && !empty($_POST['club_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE club_id = ?");
        $stmt->execute([$_POST['club_id']]);
        $admin_count = $stmt->fetchColumn();

        try {
            $stmt = $pdo->prepare("DELETE FROM club_admins WHERE club_id = ? AND admin_id = ? AND role != 'owner'");
            $stmt->execute([$_POST['club_id'], $_SESSION['admin_id']]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Club removed from your account.";
            } else {
                $_SESSION['error'] = "Failed to remove club. Owners cannot remove themselves via this option.";
            }
        } catch (PDOException $e) {
            error_log("Failed to remove admin from club: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while removing the club.";
        }
        header("Location: account.php");
        exit();
    }

    if ($action === 'delete' && !empty($_POST['club_id']) && !empty($_POST['password'])) {
        $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin_user = $stmt->fetch();
        
        if (!$admin_user || !password_verify($_POST['password'], $admin_user['password_hash'])) {
            $_SESSION['error'] = "Incorrect password. Deletion cancelled.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE club_id = ?");
            $stmt->execute([$_POST['club_id']]);
            if ($stmt->fetchColumn() > 1) {
                $_SESSION['error'] = "Cannot delete a shared club. Please remove other admins first.";
            } else {
                try {
                    $pdo->beginTransaction();
                    $club_id = $_POST['club_id'];

                    $stmt = $pdo->prepare("SELECT game_id FROM games WHERE club_id = ?");
                    $stmt->execute([$club_id]);
                    $game_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($game_ids)) {
                        $placeholders = implode(',', array_fill(0, count($game_ids), '?'));
                        $pdo->prepare("DELETE FROM game_result_losers WHERE result_id IN (SELECT result_id FROM game_results WHERE game_id IN ($placeholders))")->execute($game_ids);
                        $pdo->prepare("DELETE FROM game_results WHERE game_id IN ($placeholders)")->execute($game_ids);
                        $pdo->prepare("DELETE FROM team_game_results WHERE game_id IN ($placeholders)")->execute($game_ids);
                        $pdo->prepare("DELETE FROM games WHERE club_id = ?")->execute([$club_id]);
                    }

                    $pdo->prepare("DELETE FROM champions WHERE club_id = ?")->execute([$club_id]);
                    $pdo->prepare("DELETE FROM teams WHERE club_id = ?")->execute([$club_id]);
                    $pdo->prepare("DELETE FROM members WHERE club_id = ?")->execute([$club_id]);
                    $pdo->prepare("DELETE FROM club_admins WHERE club_id = ?")->execute([$club_id]);
                    $pdo->prepare("DELETE FROM clubs WHERE club_id = ?")->execute([$club_id]);

                    $pdo->commit();
                    $_SESSION['success'] = "Club deleted successfully.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "Deletion failed: " . $e->getMessage();
                }
            }
        }
        header("Location: account.php");
        exit();
    }
    
    if ($action === 'update_profile') {
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Invalid email format";
        } else {
            $stmt = $pdo->prepare("SELECT admin_id FROM admin_users WHERE username = ? AND admin_id != ?");
            $stmt->execute([$new_username, $_SESSION['admin_id']]);
            
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Username is already taken";
            } else {
                $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, email = ? WHERE admin_id = ?");
                $stmt->execute([$new_username, $new_email, $_SESSION['admin_id']]);
                
                $_SESSION['admin_username'] = $new_username;
                $_SESSION['success'] = "Profile updated successfully";
                
                $admin['username'] = $new_username;
                $admin['email'] = $new_email;
            }
        }
        header("Location: account.php");
        exit();
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($new_password) < 8) {
            $_SESSION['error'] = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match";
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin_pass = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $admin_pass['password_hash'])) {
                $hashed_password = $security->hashPassword($new_password);
                
                $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE admin_id = ?");
                $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
                
                $_SESSION['success'] = "Password changed successfully";
            } else {
                $_SESSION['error'] = "Current password is incorrect";
            }
        }
        header("Location: account.php");
        exit();
    }
}

// Fetch clubs for current admin
$total_clubs_stmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE admin_id = ?");
$total_clubs_stmt->execute([$_SESSION['admin_id']]);
$total_clubs_unfiltered = (int)$total_clubs_stmt->fetchColumn();

$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM members WHERE club_id = c.club_id) as member_count,
          (SELECT COUNT(*) FROM games WHERE club_id = c.club_id) as game_count,
          (COALESCE((SELECT COUNT(DISTINCT session_id) FROM game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0) +
           COALESCE((SELECT COUNT(DISTINCT session_id) FROM team_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0) +
           COALESCE((SELECT COUNT(DISTINCT session_id) FROM cooperative_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0)) as total_plays,
          (SELECT COUNT(*) FROM champions WHERE club_id = c.club_id) as champion_count,
          (SELECT COUNT(*) FROM teams t JOIN members m ON t.member1_id = m.member_id WHERE m.club_id = c.club_id) as team_count,
          ca.role as admin_role,
          (SELECT COUNT(*) FROM club_admins WHERE club_id = c.club_id) as admin_count
          FROM clubs c
          JOIN club_admins ca ON c.club_id = ca.club_id
          WHERE ca.admin_id = ?
          ORDER BY c.club_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['admin_id']]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$club_count = count($clubs);

$total_members = array_sum(array_column($clubs, 'member_count'));
$total_games = array_sum(array_column($clubs, 'game_count'));
$total_plays = array_sum(array_column($clubs, 'total_plays'));
$total_champions = array_sum(array_column($clubs, 'champion_count'));
$total_teams = array_sum(array_column($clubs, 'team_count'));

$admin_type = $_SESSION['admin_type'] ?? 'multi_club';
$club_limit = ($admin_type === 'single_club') ? 1 : 5;

$active_club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : (isset($_SESSION['current_club_id']) ? (int)$_SESSION['current_club_id'] : (isset($_SESSION['club_id']) ? (int)$_SESSION['club_id'] : 0));
if (!$active_club_id && !empty($clubs)) {
    $active_club_id = (int)$clubs[0]['club_id'];
}
if ($active_club_id > 0) {
    $_SESSION['current_club_id'] = $active_club_id;
    $_SESSION['club_id'] = $active_club_id;
}

$active_club_name = 'Your Club';
$active_club_theme = 'midnight';

if (!empty($clubs)) {
    foreach ($clubs as $c) {
        if ($c['club_id'] == $active_club_id) {
            $active_club_name = $c['club_name'];
            $active_club_theme = $c['theme'] ?? 'midnight';
            break;
        }
    }
}

$active_club_owner = '';
$active_club_admins = [];

if ($active_club_id > 0) {
    $activeAdminsStmt = $pdo->prepare("
        SELECT u.username, ca.role
        FROM club_admins ca
        JOIN admin_users u ON ca.admin_id = u.admin_id
        WHERE ca.club_id = ?
        ORDER BY CASE WHEN ca.role = 'owner' THEN 0 ELSE 1 END, u.username ASC
    ");
    $activeAdminsStmt->execute([$active_club_id]);
    $allActiveAdmins = $activeAdminsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allActiveAdmins as $adm) {
        if ($adm['role'] === 'owner') {
            $active_club_owner = $adm['username'];
        } else {
            $active_club_admins[] = $adm['username'];
        }
    }
}

$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('account', $active_club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Account Settings'); ?>
    </div>

    <div class="container">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <?php
        $hide_create_section = ($admin_type === 'single_club' && $total_clubs_unfiltered >= 1);
        $can_add_club = (!$hide_create_section && $total_clubs_unfiltered < $club_limit);
        ?>

        <!-- Your Clubs Section -->
        <div class="card">
            <div class="card-header">
                <h2>Your Clubs (<?php echo $club_count; ?>)</h2>
            </div>

            <?php if ($can_add_club): ?>
            <div id="add-club-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Create New Club</h3>
                <form method="POST" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="club_name">Club Name <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <input type="text" id="club_name" name="club_name" placeholder="e.g. Wednesday Game Night" required class="form-control" pattern="[a-zA-Z0-9 _\-]+" title="Only letters, numbers, spaces, dashes and underscores are allowed">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="slug">Club URL Slug <small class="text-muted">(optional)</small></label>
                            <input type="text" id="slug" name="slug" placeholder="e.g. wednesdaygamenight" class="form-control" pattern="[a-zA-Z0-9\-]+" title="Only letters, numbers, and hyphens allowed">
                        </div>
                    </div>
                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <input type="hidden" name="action" value="create">
                        <button type="submit" class="btn btn--primary">Save Club</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddClubForm()">Cancel</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="card-toolbar">
                <?php if ($can_add_club): ?>
                    <button type="button" class="btn btn--primary" id="add-club-btn" onclick="toggleAddClubForm()" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? 'visibility:hidden;' : ''; ?>">
                        Add a Club
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="clubs-table data-table">
                    <thead>
                        <tr>
                            <th>Club Name</th>

                            <th class="th-sideways"><span class="th-sideways-inner">Results</span></th>
                            <th class="th-sideways"><span class="th-sideways-inner">Members</span></th>
                            <th class="th-sideways"><span class="th-sideways-inner">Teams</span></th>
                            <th class="th-sideways"><span class="th-sideways-inner">Champs</span></th>
                            <th class="th-sideways"><span class="th-sideways-inner">Games</span></th>
                            <th class="actions-header"></th>
                            <th class="th-sideways"><span class="th-sideways-inner">Default</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clubs as $club): ?>
                            <?php 
                            $is_active = ($club['club_id'] == $active_club_id);
                            ?>
                            <tr class="club-row <?php echo $is_active ? 'club-row--active' : ''; ?>">
                                <td class="club-name-cell" data-label="Club Name">
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <?php if (!empty($club['logo_image'])): ?>
                                            <img src="<?php echo htmlspecialchars(get_club_logo_url($club['logo_image'], '../')); ?>" alt="Club Logo" class="club-logo-thumbnail" loading="lazy" onerror="this.onerror=null; this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                            <div class="club-logo-thumbnail" style="display:none;background:var(--color-primary-soft);color:var(--color-primary-strong);align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">🎯</div>
                                        <?php else: ?>
                                            <div class="club-logo-thumbnail" style="background:var(--color-primary-soft);color:var(--color-primary-strong);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">🎯</div>
                                        <?php endif; ?>
                                        <div style="display:flex;flex-direction:column;gap:0.15rem;">
                                            <span style="font-weight:600;color:var(--color-heading);"><?php echo htmlspecialchars($club['club_name']); ?></span>

                                        </div>
                                    </div>
                                </td>

                                <td style="text-align:center;" data-label="Results">
                                    <span class="club-stat-pill"><?php echo $club['total_plays'] ?: 0; ?></span>
                                </td>
                                <td style="text-align:center;" data-label="Member">
                                    <span class="club-stat-pill"><?php echo $club['member_count']; ?></span>
                                </td>
                                <td style="text-align:center;" data-label="Teams">
                                    <span class="club-stat-pill"><?php echo $club['team_count']; ?></span>
                                </td>
                                <td style="text-align:center;" data-label="Champions">
                                    <span class="club-stat-pill"><?php echo $club['champion_count']; ?></span>
                                </td>
                                <td style="text-align:center;" data-label="Games">
                                    <span class="club-stat-pill"><?php echo $club['game_count']; ?></span>
                                </td>
                                <td class="actions-cell" data-label="Actions" style="text-align:right;">
                                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.5rem;">
                                        <?php if ($is_active): ?>
                                            <span class="badge badge--primary" style="font-weight:600;">Managing</span>
                                        <?php else: ?>
                                            <a href="account.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--small btn--secondary">Manage</a>
                                        <?php endif; ?>
                                        <?php if ($club['admin_role'] === 'owner'): ?>
                                            <a href="edit_club.php?id=<?php echo $club['club_id']; ?>&from=account" class="btn btn--small btn--secondary">View/Edit</a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn--small btn--danger"
                                                    onclick="confirmLeaveClub(<?php echo $club['club_id']; ?>, '<?php echo addslashes($club['club_name']); ?>')">
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align:center;" data-label="Default">
                                    <?php $is_default = ((int)$club['club_id'] === $current_default_club_id); ?>
                                    <form method="POST" style="margin:0; display:inline-flex; align-items:center; justify-content:center;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="set_default_club">
                                        <input type="hidden" name="default_club_id" value="<?php echo $club['club_id']; ?>">
                                        <input type="hidden" name="active_club_id" value="<?php echo $active_club_id; ?>">
                                        <input type="checkbox" 
                                               class="default-club-checkbox"
                                               <?php echo $is_default ? 'checked' : ''; ?> 
                                               onchange="toggleDefaultClub(this, <?php echo (int)$club['club_id']; ?>)"
                                               title="<?php echo $is_default ? 'Uncheck to unset default' : 'Check to set as default'; ?>"
                                               style="cursor:pointer; width:16px; height:16px; accent-color: var(--color-primary);">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($clubs)): ?>
                    <tfoot>
                        <tr>
                            <td>Total (<?php echo $club_count; ?> <?php echo $club_count === 1 ? 'Club' : 'Clubs'; ?>)</td>
                            <td style="text-align:center;" data-label="Total Results"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_plays; ?></span></td>
                            <td style="text-align:center;" data-label="Total Members"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_members; ?></span></td>
                            <td style="text-align:center;" data-label="Total Teams"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_teams; ?></span></td>
                            <td style="text-align:center;" data-label="Total Champions"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_champions; ?></span></td>
                            <td style="text-align:center;" data-label="Total Games"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_games; ?></span></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Theme for [Club Name] Card -->
        <div class="card">
            <div class="card-header">
                <h2>Theme for <?php echo htmlspecialchars($active_club_name); ?></h2>
            </div>
            <div class="theme-grid">
                <?php
                $available_themes = [
                    'light'    => ['name' => 'Cards & Dice',      'icon' => '🃏', 'bg' => '#ffffff', 'primary' => '#4f46e5', 'accent' => '#7c3aed'],
                    'tabletop' => ['name' => 'Tabletop',          'icon' => '🎲', 'bg' => '#062c1b', 'primary' => '#10b981', 'accent' => '#f59e0b'],
                    'midnight' => ['name' => 'Midnight Marauder', 'icon' => '🗡️', 'bg' => '#140d07', 'primary' => '#d97706', 'accent' => '#c86414'],
                    'arcade'   => ['name' => 'Cyber Arcade',      'icon' => '🕹️', 'bg' => '#090d16', 'primary' => '#06b6d4', 'accent' => '#f43f5e'],
                ];
                $current_club_theme = $active_club_theme ?: 'midnight';
                ?>

                <?php foreach ($available_themes as $t_key => $t_info): ?>
                    <?php $is_selected = ($current_club_theme === $t_key); ?>
                    <form method="POST" class="theme-card-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="update_club_theme">
                        <input type="hidden" name="club_id" value="<?php echo $active_club_id; ?>">
                        <input type="hidden" name="theme" value="<?php echo $t_key; ?>">
                        
                        <button type="submit" class="theme-option-card" style="width:100%; text-align:left; background: var(--color-surface-muted); border: 2px solid <?php echo $is_selected ? 'var(--color-primary)' : 'var(--color-border)'; ?>; border-radius: var(--radius-lg, 0.75rem); padding: 1rem; cursor: pointer; transition: all 0.2s ease; position: relative; <?php echo $is_selected ? 'box-shadow: 0 0 0 3px rgba(99,102,241,0.25);' : ''; ?>">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 0.75rem;">
                                <span style="font-size: 1.5rem;"><?php echo $t_info['icon']; ?></span>
                                <div style="display:flex; gap:0.35rem; align-items:center;">
                                    <span title="Background" style="width:14px; height:14px; border-radius:50%; background:<?php echo $t_info['bg']; ?>; display:inline-block; border:1px solid rgba(255,255,255,0.4);"></span>
                                    <span title="Primary" style="width:14px; height:14px; border-radius:50%; background:<?php echo $t_info['primary']; ?>; display:inline-block; border:1px solid rgba(255,255,255,0.4);"></span>
                                    <span title="Accent" style="width:14px; height:14px; border-radius:50%; background:<?php echo $t_info['accent']; ?>; display:inline-block; border:1px solid rgba(255,255,255,0.4);"></span>
                                </div>
                            </div>
                            <div style="font-weight: 600; font-size: 0.95rem; color: var(--color-heading); margin-bottom: 0.25rem;">
                                <?php echo htmlspecialchars($t_info['name']); ?>
                            </div>
                            <?php if ($is_selected): ?>
                                <span style="display:inline-block; font-size:0.75rem; font-weight:700; color:var(--color-primary); background:var(--color-primary-soft); padding:0.15rem 0.5rem; border-radius:0.375rem;">Active Theme</span>
                            <?php else: ?>
                                <span style="display:inline-block; font-size:0.75rem; font-weight:500; color:var(--color-text-muted);">Click to apply</span>
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h2>Change Password</h2>
                <p class="card-subtitle">Ensure your account stays secure</p>
            </div>
            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        class="form-control" 
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        class="form-control" 
                        required
                        minlength="8"
                    >
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">
                        Minimum 8 characters
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-control" 
                        required
                        minlength="8"
                    >
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn--primary">Change Password</button>
                </div>
            </form>
        </div>


        <!-- Account Information -->
        <div class="card card--flat">
            <div class="card-header" style="margin-bottom: 1.25rem;">
                <h3 style="margin: 0; font-size: 1.25rem; color: var(--color-heading);">Account Information</h3>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                
                <!-- Profile Info Group -->
                <div style="background: var(--color-surface-muted); padding: 1.1rem; border-radius: var(--radius-md, 0.5rem); border: 1px solid var(--color-border);">
                    <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); margin-bottom: 0.75rem;">👤 Administrator Details</div>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.875rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Username</span>
                            <strong style="color: var(--color-heading);"><?php echo htmlspecialchars($admin['username'] ?? $_SESSION['admin_username'] ?? 'N/A'); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Email</span>
                            <strong style="color: var(--color-heading);"><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Account ID</span>
                            <span style="font-family: monospace; font-weight: 600; background: rgba(99,102,241,0.12); color: var(--color-primary); padding: 0.1rem 0.45rem; border-radius: 0.25rem;">#<?php echo (int)$_SESSION['admin_id']; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Account Type</span>
                            <strong style="color: var(--color-heading); text-transform: capitalize;"><?php echo str_replace('_', ' ', $admin_type); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Club Quota</span>
                            <strong style="color: var(--color-heading);"><?php echo $total_clubs_unfiltered; ?> / <?php echo $club_limit; ?> Clubs</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Member Since</span>
                            <strong style="color: var(--color-heading);"><?php echo date('Y/m/d', strtotime($admin['created_at'])); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Global Portfolio Statistics -->
                <div style="background: var(--color-surface-muted); padding: 1.1rem; border-radius: var(--radius-md, 0.5rem); border: 1px solid var(--color-border);">
                    <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); margin-bottom: 0.75rem;">📊 Portfolio Statistics</div>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.875rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Total Members</span>
                            <strong style="color: var(--color-heading);"><?php echo (int)$total_members; ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Total Games</span>
                            <strong style="color: var(--color-heading);"><?php echo (int)$total_games; ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Total Plays Logged</span>
                            <strong style="color: var(--color-heading);"><?php echo (int)$total_plays; ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Champions Crowned</span>
                            <strong style="color: var(--color-heading);"><?php echo (int)$total_champions; ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Teams Registered</span>
                            <strong style="color: var(--color-heading);"><?php echo (int)$total_teams; ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Active Context Group -->
                <div style="background: var(--color-surface-muted); padding: 1.1rem; border-radius: var(--radius-md, 0.5rem); border: 1px solid var(--color-border);">
                    <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); margin-bottom: 0.75rem;">🎯 Active Context</div>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.875rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Active Club</span>
                            <strong style="color: var(--color-heading);"><?php echo htmlspecialchars($active_club_name); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Club ID</span>
                            <span style="font-family: monospace; font-weight: 600; background: rgba(99,102,241,0.12); color: var(--color-primary); padding: 0.1rem 0.45rem; border-radius: 0.25rem;">#<?php echo (int)$active_club_id; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Club Owner</span>
                            <strong style="color: var(--color-heading);"><?php echo htmlspecialchars($active_club_owner ?: 'N/A'); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
                            <span style="color: var(--color-text-muted);">Administrators</span>
                            <span style="color: var(--color-heading); font-weight: 500; text-align: right; word-break: break-word;">
                                <?php 
                                if (!empty($active_club_admins)) {
                                    echo htmlspecialchars(implode(', ', $active_club_admins));
                                } else {
                                    echo '<em style="color: var(--color-text-muted);">None</em>';
                                }
                                ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-text-muted);">Active Theme</span>
                            <strong style="color: var(--color-heading); text-transform: capitalize;"><?php echo htmlspecialchars($active_club_theme ?: 'midnight'); ?></strong>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Leave Club Confirmation Modal -->
    <div id="leaveClubModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__content">
                <div class="modal__header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
                    <h3 class="modal__title" style="margin:0; color:var(--color-heading); font-size:1.25rem; font-weight:600;">⚠️ Leave Club</h3>
                    <button type="button" class="modal__close" onclick="closeLeaveModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--color-text-muted);">&times;</button>
                </div>
                <div class="modal__body">
                    <p>Are you sure you want to leave <strong id="leave_club_name"></strong>?</p>
                    <div class="message message--warning">
                        <strong>Important:</strong> You will lose access to this club and it will be removed from your list. 
                        The club and its data will remain accessible to other administrators.
                    </div>
                    
                    <form id="leaveClubForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="leave">
                        <input type="hidden" name="club_id" id="leave_club_id">
                        
                        <div class="form-actions" style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-start;">
                            <button type="submit" class="btn btn--warning">Leave Club</button>
                            <button type="button" class="btn btn--subtle" onclick="closeLeaveModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Club Confirmation Modal -->
    <div id="deleteClubModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__content">
                <div class="modal__header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
                    <h3 class="modal__title" style="margin:0; color:var(--color-heading); font-size:1.25rem; font-weight:600;">⚠️ Delete Club</h3>
                    <button type="button" class="modal__close" onclick="closeDeleteModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--color-text-muted);">&times;</button>
                </div>
                <div class="modal__body">
                    <p>Are you sure you want to delete <strong id="delete_club_name"></strong>?</p>
                    <div class="message message--error">
                        <strong>Warning:</strong> This action is permanent and cannot be undone. All associated data will be permanently erased, including:
                        <ul style="margin-top: 0.5rem; margin-left: 1.5rem; list-style-type: disc;">
                            <li>Members and Champions</li>
                            <li>Games and Statistics</li>
                            <li>Teams and Match Results</li>
                        </ul>
                    </div>
                    
                    <form id="deleteClubForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="club_id" id="delete_club_id">
                        
                        <div class="form-group">
                            <label for="admin_password">Confirm Password</label>
                            <input type="password" id="admin_password" name="password" class="form-control" required placeholder="Enter your password to confirm">
                        </div>
                        
                        <div class="form-actions" style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-start;">
                            <button type="submit" class="btn btn--danger">Delete Club</button>
                            <button type="button" class="btn btn--subtle" onclick="closeDeleteModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Leave Modal Logic
        const leaveModal = document.getElementById('leaveClubModal');
        const leaveModalDialog = leaveModal ? leaveModal.querySelector('.modal__dialog') : null;

        function confirmLeaveClub(clubId, clubName) {
            if (!leaveModal) return;
            document.getElementById('leave_club_id').value = clubId;
            document.getElementById('leave_club_name').textContent = clubName;
            leaveModal.classList.add('is-open');
        }

        function closeLeaveModal() {
            if (leaveModal) leaveModal.classList.remove('is-open');
        }

        // Delete Modal Logic
        const deleteModal = document.getElementById('deleteClubModal');
        const deleteModalDialog = deleteModal ? deleteModal.querySelector('.modal__dialog') : null;

        function confirmClubDeletion(clubId, clubName, adminCount) {
            if (adminCount > 1) {
                alert("This club is shared with other administrators. You cannot delete it directly. If you wish to remove it from your list, please use the 'Leave Club' option instead.");
                return;
            }
            if (!deleteModal) return;
            document.getElementById('delete_club_id').value = clubId;
            document.getElementById('delete_club_name').textContent = clubName;
            deleteModal.classList.add('is-open');
        }

        function closeDeleteModal() {
            if (deleteModal) {
                deleteModal.classList.remove('is-open');
                document.getElementById('admin_password').value = '';
            }
        }

        window.onclick = function(event) {
            if (event.target === leaveModal) {
                closeLeaveModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        };

        if (leaveModalDialog) leaveModalDialog.addEventListener('click', e => e.stopPropagation());
        if (deleteModalDialog) deleteModalDialog.addEventListener('click', e => e.stopPropagation());

        function toggleAddClubForm() {
            const wrapper = document.getElementById('add-club-form-wrapper');
            const btn = document.getElementById('add-club-btn');
            if (!wrapper) return;
            if (wrapper.style.display === 'none' || wrapper.style.display === '') {
                wrapper.style.display = 'block';
                if (btn) btn.style.visibility = 'hidden';
                document.getElementById('club_name')?.focus();
            } else {
                wrapper.style.display = 'none';
                if (btn) btn.style.visibility = 'visible';
            }
        }

        function toggleDefaultClub(checkbox, clubId) {
            if (checkbox.checked) {
                document.querySelectorAll('.default-club-checkbox').forEach(cb => {
                    if (cb !== checkbox) cb.checked = false;
                });
                checkbox.form.querySelector('input[name="default_club_id"]').value = clubId;
            } else {
                checkbox.form.querySelector('input[name="default_club_id"]').value = 0;
            }
            checkbox.form.submit();
        }
    </script>
    <script src="../js/dark-mode.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
