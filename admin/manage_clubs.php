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
                    $stmt = $pdo->prepare("INSERT INTO clubs (club_name, slug) VALUES (?, ?)");
                    $stmt->execute([$club_name, $slug]);
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
$query = "SELECT c.*, 
          (COALESCE((SELECT COUNT(DISTINCT session_id) FROM game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0) +
           COALESCE((SELECT COUNT(DISTINCT session_id) FROM team_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0)) as total_plays,
          ca.role as admin_role,
          (SELECT COUNT(*) FROM club_admins WHERE club_id = c.club_id) as admin_count
          FROM clubs c
          JOIN club_admins ca ON c.club_id = ca.club_id
          WHERE ca.admin_id = ?
          GROUP BY c.club_id
          ORDER BY c.club_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['admin_id']]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$club_count = count($clubs);

// Determine club limit based on admin type
$admin_type = $_SESSION['admin_type'] ?? 'multi_club';
$club_limit = ($admin_type === 'single_club') ? 1 : 5;

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
    <?php NavigationHelper::renderAdminSidebar('clubs'); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Clubs', 'Create and edit your clubs'); ?>
    </div>
    
    <div class="container">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <?php if (isset($_SESSION['api_file'])): ?>
            <div class="message message--success">
                API File Created: <strong><?php echo htmlspecialchars($_SESSION['api_file']); ?></strong>
                <br>
                <a href="../<?php echo htmlspecialchars($_SESSION['api_file']); ?>" target="_blank" style="color: inherit; text-decoration: underline;">Open File</a>
            </div>
            <?php unset($_SESSION['api_file']); ?>
        <?php endif; ?>

        <?php
        // Hide create club section entirely for single_club admins who already have a club
        $hide_create_section = ($admin_type === 'single_club' && $club_count >= 1);
        ?>
        <?php if (!$hide_create_section): ?>
            <?php if ($club_count < $club_limit): ?>
            <div class="card">
                <h2>Create New Club</h2>
                <form method="POST" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <label for="club_name">Club Name:</label>
                        <input type="text" id="club_name" name="club_name" placeholder="Club Name" required class="form-control" pattern="[a-zA-Z0-9 _\-]+" title="Only letters, numbers, spaces, dashes and underscores are allowed">
                    </div>
                    <div class="form-group">
                        <label for="slug">Club URL Slug (optional):</label>
                        <input type="text" id="slug" name="slug" placeholder="e.g., theflyingdutchmen" class="form-control" pattern="[a-zA-Z0-9\-]+" title="Only letters, numbers, and hyphens allowed">
                        <small style="display:block; margin-top:0.5rem; color:var(--text-light);">
                            Leave empty to use ID-based URL. If set, club will be accessible at domain.com/slug
                        </small>
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="action" value="create">
                        <button type="submit" class="btn">Create Club</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="message info">
                    Maximum number of clubs (<?php echo $club_limit; ?>) has been reached. You cannot create more clubs.
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card">
            <h2>Existing Clubs</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Club Name</th>
                        <th>Created</th>
                        <th>Total Plays</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clubs as $club): ?>
                        <tr>
                            <td class="club-name-cell" data-label="Club Name">
                                <?php if ($club['logo_image']): ?>
                                    <img src="../images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="Club Logo" class="club-logo-thumbnail" loading="lazy">
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($club['club_name']); ?></span>
                            </td>
                            <td class="hide-on-mobile" data-label="Created"><?php echo date('M j, Y', strtotime($club['created_at'])); ?></td>
                            <td class="hide-on-mobile" data-label="Total Plays"><?php echo $club['total_plays'] ?: 0; ?></td>
                            <td class="actions-cell table-col--primary" data-label="Actions">
                                <div class="dropdown">
                                    <button type="button" class="btn btn--small btn--secondary dropdown__toggle" onclick="toggleDropdown(this, event)">
                                        Manage <span style="margin-left: 0.5ch; font-size: 0.8em;">▼</span>
                                    </button>
                                    <div class="dropdown__menu">
                                        <a href="edit_club.php?id=<?php echo $club['club_id']; ?>">Edit Details</a>
                                        <a href="manage_members.php?club_id=<?php echo $club['club_id']; ?>">Manage Members</a>
                                        <a href="manage_games.php?club_id=<?php echo $club['club_id']; ?>">Manage Games</a>
                                        <a href="club_teams.php?club_id=<?php echo $club['club_id']; ?>">Manage Teams</a>
                                        <a href="manage_logo.php?club_id=<?php echo $club['club_id']; ?>">Update Logo</a>
                                        
                                        <div style="border-top: 1px solid var(--color-border); margin: 0.25rem 0;"></div>
                                        
                                        <button type="button" onclick="confirmApiGeneration(<?php echo $club['club_id']; ?>, '<?php echo addslashes($club['club_name']); ?>')">
                                            Generate API JSON
                                        </button>
                                        <button type="button" style="color: var(--color-danger);"
                                                onclick="confirmClubDeletion(<?php echo $club['club_id']; ?>, '<?php echo addslashes($club['club_name']); ?>', <?php echo $club['admin_count']; ?>)"
                                                <?php echo ($club['admin_count'] > 1) ? 'title="Shared clubs cannot be deleted"' : ''; ?>>
                                            Delete Club
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- API Generation Confirmation Modal -->
    <div id="apiConfirmModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__content">
                <h3>Generate API File</h3>
                <p>Are you sure you want to generate a new API file for <strong id="api_club_name"></strong>?</p>
                <p class="text-sm text-muted">This will create a new JSON file with the latest club data. Any existing API file for this club will remain accessible.</p>
                
                <form id="apiConfirmForm" method="POST" action="generate_api.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="club_id" id="api_club_id">
                    
                    <div class="form-group" style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" class="btn btn--ghost" onclick="closeApiModal()">Cancel</button>
                        <button type="submit" class="btn btn--primary">Generate API</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Delete Club Confirmation Modal -->
    <div id="deleteClubModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__content">
                <div class="modal__header">
                    <h3 class="modal__title text-danger">⚠️ Delete Club</h3>
                    <button type="button" class="modal__close" onclick="closeDeleteModal()">&times;</button>
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
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn--subtle" onclick="closeDeleteModal()">Cancel</button>
                            <button type="submit" class="btn btn--danger">Delete Club</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        // API Modal Logic
        const apiModal = document.getElementById('apiConfirmModal');
        const apiModalDialog = apiModal.querySelector('.modal__dialog');

        function confirmApiGeneration(clubId, clubName) {
            document.getElementById('api_club_id').value = clubId;
            document.getElementById('api_club_name').textContent = clubName;
            apiModal.classList.add('is-open');
        }

        function closeApiModal() {
            apiModal.classList.remove('is-open');
        }

        // Delete Modal Logic
        const deleteModal = document.getElementById('deleteClubModal');
        const deleteModalDialog = deleteModal.querySelector('.modal__dialog');

        function confirmClubDeletion(clubId, clubName, adminCount) {
            if (adminCount > 1) {
                alert("This club is shared with other administrators. You must remove other admins before deleting this club.");
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

        // Close modals when clicking outside
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

            // Close Modals
            if (event.target === apiModal) {
                closeApiModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        };

        // Prevent event propagation
        apiModalDialog.addEventListener('click', e => e.stopPropagation());
        deleteModalDialog.addEventListener('click', e => e.stopPropagation());
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
