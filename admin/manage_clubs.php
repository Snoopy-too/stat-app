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

// Ensure admin_type is in session (fallback for existing logged-in users after migration)
if (!isset($_SESSION['admin_type']) && isset($_SESSION['admin_id'])) {
    $typeStmt = $pdo->prepare("SELECT admin_type FROM admin_users WHERE admin_id = ?");
    $typeStmt->execute([$_SESSION['admin_id']]);
    $_SESSION['admin_type'] = $typeStmt->fetchColumn() ?: 'multi_club';
}

$security = new SecurityUtils($pdo);

// Handle club creation/deletion if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_clubs.php");
        exit();
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create' && !empty($_POST['club_name'])) {
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
                    
                    // Assign the creator as the owner
                    $stmt = $pdo->prepare("INSERT INTO club_admins (club_id, admin_id, role) VALUES (?, ?, 'owner')");
                    $stmt->execute([$new_club_id, $_SESSION['admin_id']]);
                    
                    $_SESSION['success'] = "Club created successfully!";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $_SESSION['error'] = "This slug is already in use. Please choose a different one.";
                    } else {
                        // Log the actual error for debugging
                        error_log("Failed to create club: " . $e->getMessage());
                        $_SESSION['error'] = "Failed to create club. Please try again.";
                    }
                }
            }
        } elseif ($_POST['action'] === 'edit' && !empty($_POST['club_id']) && !empty($_POST['club_name'])) {
            $club_name = trim($_POST['club_name']);
            $slug = trim($_POST['slug'] ?? '');
            $slug = $slug === '' ? null : $slug;
            $is_private = isset($_POST['is_private']) ? 1 : 0;

            if (!preg_match('/^[a-zA-Z0-9\s_-]+$/', $club_name)) {
                $_SESSION['error'] = "Club name can only contain letters, numbers, spaces, dashes and underscores.";
            } elseif ($slug !== null && !preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
                $_SESSION['error'] = "Slug can only contain letters, numbers, and hyphens.";
            } elseif ($slug !== null && in_array(strtolower($slug), ['admin', 'api', 'index', 'login', 'logout', 'dashboard', 'config', 'includes', 'css', 'js', 'images', 'uploads'])) {
                $_SESSION['error'] = "This slug is reserved and cannot be used.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE clubs SET club_name = ?, slug = ?, is_private = ? WHERE club_id = ? AND EXISTS (SELECT 1 FROM club_admins WHERE club_id = ? AND admin_id = ?)");
                    $stmt->execute([$club_name, $slug, $is_private, $_POST['club_id'], $_POST['club_id'], $_SESSION['admin_id']]);
                    $_SESSION['success'] = "Club updated successfully!";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $_SESSION['error'] = "This slug is already in use. Please choose a different one.";
                    } else {
                        // Log the actual error for debugging
                        error_log("Failed to update club: " . $e->getMessage());
                        $_SESSION['error'] = "Failed to update club. Please try again.";
                    }
                }
            }
        }
        elseif ($_POST['action'] === 'leave' && !empty($_POST['club_id'])) {
            // Verify Club is actually shared
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
        }
        elseif ($_POST['action'] === 'delete' && !empty($_POST['club_id']) && !empty($_POST['password'])) {
            // Verify Password first
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin_user = $stmt->fetch();
            
            if (!$admin_user || !password_verify($_POST['password'], $admin_user['password_hash'])) {
                $_SESSION['error'] = "Incorrect password. Deletion cancelled.";
            } else {
                // Verify Club is not shared
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE club_id = ?");
                $stmt->execute([$_POST['club_id']]);
                if ($stmt->fetchColumn() > 1) {
                    $_SESSION['error'] = "Cannot delete a shared club. Please remove other admins first.";
                } else {
                    // Perform Deletion
                    try {
                        $pdo->beginTransaction();
                        $club_id = $_POST['club_id'];

                        // 1. Get Game IDs for cleanup
                        $stmt = $pdo->prepare("SELECT game_id FROM games WHERE club_id = ?");
                        $stmt->execute([$club_id]);
                        $game_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        if (!empty($game_ids)) {
                            $placeholders = implode(',', array_fill(0, count($game_ids), '?'));
                            
                            // Delete game dependent tables
                            $pdo->prepare("DELETE FROM game_result_losers WHERE result_id IN (SELECT result_id FROM game_results WHERE game_id IN ($placeholders))")->execute($game_ids);
                            $pdo->prepare("DELETE FROM game_results WHERE game_id IN ($placeholders)")->execute($game_ids);
                            $pdo->prepare("DELETE FROM team_game_results WHERE game_id IN ($placeholders)")->execute($game_ids);
                            
                            // Delete games
                            $pdo->prepare("DELETE FROM games WHERE club_id = ?")->execute([$club_id]);
                        }

                        // 2. Delete Club dependents
                        $pdo->prepare("DELETE FROM champions WHERE club_id = ?")->execute([$club_id]);
                        $pdo->prepare("DELETE FROM teams WHERE club_id = ?")->execute([$club_id]);
                        $pdo->prepare("DELETE FROM members WHERE club_id = ?")->execute([$club_id]);
                        $pdo->prepare("DELETE FROM club_admins WHERE club_id = ?")->execute([$club_id]);
                        
                        // 3. Delete Club
                        $pdo->prepare("DELETE FROM clubs WHERE club_id = ?")->execute([$club_id]);

                        $pdo->commit();
                        $_SESSION['success'] = "Club deleted successfully.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $_SESSION['error'] = "Deletion failed: " . $e->getMessage();
                    }
                }
            }
        }
        header("Location: manage_clubs.php");
        exit();
    }
}

// Fetch clubs for the current admin and count them
$total_clubs_stmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE admin_id = ?");
$total_clubs_stmt->execute([$_SESSION['admin_id']]);
$total_clubs_unfiltered = (int)$total_clubs_stmt->fetchColumn();

$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM members WHERE club_id = c.club_id) as member_count,
          (SELECT COUNT(*) FROM games WHERE club_id = c.club_id) as game_count,
          (COALESCE((SELECT COUNT(DISTINCT session_id) FROM game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0) +
           COALESCE((SELECT COUNT(DISTINCT session_id) FROM team_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0)) as total_plays,
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

// Aggregate overall statistics for total row
$total_members = array_sum(array_column($clubs, 'member_count'));
$total_games = array_sum(array_column($clubs, 'game_count'));
$total_plays = array_sum(array_column($clubs, 'total_plays'));
$total_champions = array_sum(array_column($clubs, 'champion_count'));
$total_teams = array_sum(array_column($clubs, 'team_count'));

// Determine club limit based on admin type
$admin_type = $_SESSION['admin_type'] ?? 'multi_club';
$club_limit = ($admin_type === 'single_club') ? 1 : 5;

// Determine active club ID
$active_club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : (isset($_SESSION['current_club_id']) ? (int)$_SESSION['current_club_id'] : 0);
if (!$active_club_id && !empty($clubs)) {
    $active_club_id = (int)$clubs[0]['club_id'];
}
if ($active_club_id > 0) {
    $_SESSION['current_club_id'] = $active_club_id;
}

// Generate CSRF token for forms
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clubs - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('clubs', $active_club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Clubs', 'Overview and management of your board game clubs'); ?>
    </div>
    
    <div class="container">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>


        <?php
        $hide_create_section = ($admin_type === 'single_club' && $total_clubs_unfiltered >= 1);
        $can_add_club = (!$hide_create_section && $total_clubs_unfiltered < $club_limit);
        ?>

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
                        <span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>Add a Club
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="clubs-table data-table">
                    <thead>
                        <tr>
                            <th>Club Name</th>
                            <th class="hide-on-mobile">Created</th>
                            <th style="text-align:center;">Members</th>
                            <th style="text-align:center;">Games</th>
                            <th style="text-align:center;">Plays</th>
                            <th style="text-align:center;">Champions</th>
                            <th style="text-align:center;">Teams</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clubs as $club): ?>
                            <?php $is_active = ($club['club_id'] == $active_club_id); ?>
                            <tr style="<?php echo $is_active ? 'background: rgba(99, 102, 241, 0.06);' : ''; ?>">
                                <td class="club-name-cell" data-label="Club Name">
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <?php if ($club['logo_image']): ?>
                                            <img src="../images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="Club Logo" class="club-logo-thumbnail" loading="lazy">
                                        <?php else: ?>
                                            <div class="club-logo-thumbnail" style="background:var(--color-primary-soft);color:var(--color-primary-strong);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">🎯</div>
                                        <?php endif; ?>
                                        <div style="display:flex;flex-direction:column;gap:0.15rem;">
                                            <span style="font-weight:600;color:var(--color-heading);"><?php echo htmlspecialchars($club['club_name']); ?></span>
                                            <span class="show-on-mobile-only text-xs text-muted" style="display:none;"><?php echo date('M j, Y', strtotime($club['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="hide-on-mobile" data-label="Created" style="font-size:0.85rem;color:var(--color-text-muted);"><?php echo date('M j, Y', strtotime($club['created_at'])); ?></td>
                                <td style="text-align:center;" data-label="Members">
                                    <span class="club-stat-pill"><?php echo $club['member_count']; ?></span>
                                </td>
                                <td style="text-align:center;" data-label="Games">
                                    <span class="club-stat-pill"><?php echo $club['game_count']; ?></span>
                                </td>
                                <td style="text-align:center;" data-label="Plays">
                                    <span class="club-stat-pill"><?php echo $club['total_plays'] ?: 0; ?></span>
                                </td>
                                <td style="text-align:center;" data-label="Champions">
                                    <span class="club-stat-pill"><?php echo $club['champion_count']; ?></span>
                                </td>
                                <td style="text-align:center;" data-label="Teams">
                                    <span class="club-stat-pill"><?php echo $club['team_count']; ?></span>
                                </td>
                                <td class="actions-cell" data-label="Actions" style="text-align:right;">
                                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.5rem;">
                                        <a href="../club_json.php?id=<?php echo $club['club_id']; ?>" target="_blank" class="btn btn--small btn--subtle" title="View Club JSON Data">JSON API</a>
                                        <?php if ($is_active): ?>
                                            <span class="badge badge--primary" style="font-weight:600;">Viewing</span>
                                        <?php else: ?>
                                            <a href="manage_clubs.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--small btn--secondary">Select</a>
                                        <?php endif; ?>
                                        <?php if ($club['admin_role'] === 'owner'): ?>
                                            <a href="edit_club.php?id=<?php echo $club['club_id']; ?>&from=manage_clubs" class="btn btn--small btn--secondary">Edit</a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn--small btn--danger"
                                                    onclick="confirmLeaveClub(<?php echo $club['club_id']; ?>, '<?php echo addslashes($club['club_name']); ?>')">
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($clubs)): ?>
                    <tfoot>
                        <tr>
                            <td>Total (<?php echo $club_count; ?> <?php echo $club_count === 1 ? 'Club' : 'Clubs'; ?>)</td>
                            <td class="hide-on-mobile"></td>
                            <td style="text-align:center;"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_members; ?></span></td>
                            <td style="text-align:center;"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_games; ?></span></td>
                            <td style="text-align:center;"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_plays; ?></span></td>
                            <td style="text-align:center;"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_champions; ?></span></td>
                            <td style="text-align:center;"><span class="club-stat-pill" style="background:var(--color-primary);color:white;"><?php echo $total_teams; ?></span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
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
        const leaveModalDialog = leaveModal.querySelector('.modal__dialog');

        function confirmLeaveClub(clubId, clubName) {
            document.getElementById('leave_club_id').value = clubId;
            document.getElementById('leave_club_name').textContent = clubName;
            leaveModal.classList.add('is-open');
        }

        function closeLeaveModal() {
            leaveModal.classList.remove('is-open');
        }

        // Delete Modal Logic
        const deleteModal = document.getElementById('deleteClubModal');
        const deleteModalDialog = deleteModal.querySelector('.modal__dialog');

        function confirmClubDeletion(clubId, clubName, adminCount) {
            if (adminCount > 1) {
                alert("This club is shared with other administrators. You cannot delete it directly. If you wish to remove it from your list, please use the 'Leave Club' option instead.");
                return;
            }
            document.getElementById('delete_club_id').value = clubId;
            document.getElementById('delete_club_name').textContent = clubName;
            deleteModal.classList.add('is-open');
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('is-open');
            document.getElementById('admin_password').value = '';
        }

        // Dropdown Logic
        function toggleDropdown(button, event) {
            event.preventDefault();
            event.stopPropagation();
            
            const currentDropdown = button.closest('.dropdown');
            const wasOpen = currentDropdown.classList.contains('is-open');

            // Close any other open dropdowns first
            document.querySelectorAll('.dropdown.is-open').forEach(dropdown => {
                if (dropdown !== currentDropdown) {
                    dropdown.classList.remove('is-open');
                    dropdown.classList.remove('dropdown--up'); // Reset position
                }
            });
            
            // Toggle current
            if (!wasOpen) {
                // Smart Positioning: Check if there's enough space below
                const rect = button.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                const minSpaceRequired = 320; // Approx height of menu + padding
                
                // If limited space below and more space above, go up
                if (spaceBelow < minSpaceRequired && rect.top > spaceBelow) {
                    currentDropdown.classList.add('dropdown--up');
                } else {
                    currentDropdown.classList.remove('dropdown--up');
                }
                currentDropdown.classList.add('is-open');
            } else {
                currentDropdown.classList.remove('is-open');
                // Optional: remove direction class on close, though not strictly necessary
                // currentDropdown.classList.remove('dropdown--up'); 
            }
        }

        // Close modals and dropdowns when clicking outside
        window.onclick = function(event) {
            // Close Dropdowns if clicking outside
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown.is-open').forEach(d => {
                    d.classList.remove('is-open');
                    d.classList.remove('dropdown--up');
                });
            }


            if (event.target === leaveModal) {
                closeLeaveModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        };

        // Prevent event propagation
        leaveModalDialog.addEventListener('click', e => e.stopPropagation());
        deleteModalDialog.addEventListener('click', e => e.stopPropagation());

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
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
