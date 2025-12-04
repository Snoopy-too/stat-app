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
            
            if (!preg_match('/^[a-zA-Z0-9\s_-]+$/', $club_name)) {
                $_SESSION['error'] = "Club name can only contain letters, numbers, spaces, dashes and underscores.";
            } elseif ($slug !== null && !preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
                $_SESSION['error'] = "Slug can only contain letters, numbers, and hyphens.";
            } elseif ($slug !== null && in_array(strtolower($slug), ['admin', 'api', 'index', 'login', 'logout', 'dashboard', 'config', 'includes', 'css', 'js', 'images', 'uploads'])) {
                $_SESSION['error'] = "This slug is reserved and cannot be used.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE clubs SET club_name = ?, slug = ? WHERE club_id = ? AND admin_id = ?");
                    $stmt->execute([$club_name, $slug, $_POST['club_id'], $_SESSION['admin_id']]);
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
        header("Location: manage_clubs.php");
        exit();
    }
}

// Fetch clubs for the current admin and count them
$query = "SELECT c.*, 
          SUM(COALESCE(gr.total_plays, 0)) as total_plays 
          FROM clubs c
          LEFT JOIN (
              SELECT g.club_id, (COALESCE(gr.total, 0) + COALESCE(tgr.total, 0)) as total_plays
              FROM games g
              LEFT JOIN (
                  SELECT game_id, COUNT(result_id) as total 
                  FROM game_results 
                  GROUP BY game_id
              ) gr ON g.game_id = gr.game_id
              LEFT JOIN (
                  SELECT game_id, COUNT(result_id) as total 
                  FROM team_game_results 
                  GROUP BY game_id
              ) tgr ON g.game_id = tgr.game_id
          ) gr ON c.club_id = gr.club_id
          WHERE c.admin_id = ?
          GROUP BY c.club_id
          ORDER BY c.club_name ASC"; // Adjusted query to sum total plays from games

$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['admin_id']]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$club_count = count($clubs);
$club_limit = 5; // Set maximum number of clubs allowed

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
<body>
    <?php
    // Render breadcrumbs
    NavigationHelper::renderBreadcrumbs([
        ['label' => 'Dashboard', 'url' => 'dashboard.php'],
        'Manage Clubs'
    ]);
    ?>
    
    <div class="header">
        <div class="header-title-group">
            <?php NavigationHelper::renderHeaderTitle('Manage Clubs', 'Create and edit your clubs', 'dashboard.php', false); ?>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn--secondary btn--small">← Back to Dashboard</a>
        </div>
    </div>
    
    <?php NavigationHelper::renderAdminNav('clubs'); ?>
    
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
                                    <img src="../images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="Club Logo" class="club-logo-thumbnail">
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($club['club_name']); ?></span>
                            </td>
                            <td class="hide-on-mobile" data-label="Created"><?php echo date('M j, Y', strtotime($club['created_at'])); ?></td>
                            <td class="hide-on-mobile" data-label="Total Plays"><?php echo $club['total_plays'] ?: 0; ?></td>
                            <td class="actions-cell table-col--primary" data-label="Actions">
                                <div class="table-actions">
                                    <button type="button" class="btn btn--xsmall"
                                            onclick="editClub(<?php echo $club['club_id']; ?>, '<?php echo addslashes($club['club_name']); ?>', '<?php echo addslashes($club['slug'] ?? ''); ?>')">
                                        Edit
                                    </button>
                                    <a href="manage_members.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--xsmall">Members</a>
                                    <a href="manage_games.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--xsmall">Games</a>
                                    <a href="club_teams.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--xsmall">Teams</a>
                                    <a href="manage_logo.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--xsmall">Logo</a>
                                    <button type="button" class="btn btn--xsmall btn--secondary" onclick="confirmApiGeneration(<?php echo $club['club_id']; ?>, '<?php echo addslashes($club['club_name']); ?>')">Create API</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Edit Club Modal -->
    <div id="editClubModal" class="modal">
        <div class="modal__dialog">
            <form id="editClubForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="club_id" id="edit_club_id">
                <div class="form-group">
                    <label>Club Name:</label>
                    <input type="text" name="club_name" id="edit_club_name" required class="form-control" pattern="[a-zA-Z0-9 _\-]+" title="Only letters, numbers, spaces, dashes and underscores are allowed">
                </div>

                <div class="form-group">
                    <label for="edit_club_slug">Club URL Slug (optional):</label>
                    <input type="text" name="slug" id="edit_club_slug" class="form-control" pattern="[a-zA-Z0-9\-]+" title="Only letters, numbers, and hyphens allowed">
                    <small style="display:block; margin-top:0.5rem; color:var(--text-light);">
                        Leave empty to use ID-based URL. If set, club will be accessible at domain.com/slug
                    </small>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">Save Changes</button>
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
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

    <script>
        const modal = document.getElementById('editClubModal');
        const modalDialog = modal.querySelector('.modal__dialog');

        function editClub(clubId, clubName, clubSlug = '') {
            document.getElementById('edit_club_id').value = clubId;
            document.getElementById('edit_club_name').value = clubName;
            document.getElementById('edit_club_slug').value = clubSlug || '';
            modal.classList.add('is-open');
        }

        function closeEditModal() {
            modal.classList.remove('is-open');
        }

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

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target === modal) {
                closeEditModal();
            }
            if (event.target === apiModal) {
                closeApiModal();
            }
        };

        // Prevent event propagation from modal content
        modalDialog.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        apiModalDialog.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>
