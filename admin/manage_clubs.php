<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// Handle club creation/deletion if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create' && !empty($_POST['club_name'])) {
            $club_name = trim($_POST['club_name']);
            if (!preg_match('/^[a-zA-Z0-9\s_-]+$/', $club_name)) {
                $_SESSION['error'] = "Club name can only contain letters, numbers, spaces, dashes and underscores.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO clubs (club_name, admin_id) VALUES (?, ?)");
                $stmt->execute([$club_name, $_SESSION['admin_id']]);
                $_SESSION['success'] = "Club created successfully!";
            }
        } elseif ($_POST['action'] === 'edit' && !empty($_POST['club_id']) && !empty($_POST['club_name'])) {
            $club_name = trim($_POST['club_name']);
            if (!preg_match('/^[a-zA-Z0-9\s_-]+$/', $club_name)) {
                $_SESSION['error'] = "Club name can only contain letters, numbers, spaces, dashes and underscores.";
            } else {
                $stmt = $pdo->prepare("UPDATE clubs SET club_name = ? WHERE club_id = ? AND admin_id = ?");
                $stmt->execute([$club_name, $_POST['club_id'], $_SESSION['admin_id']]);
                $_SESSION['success'] = "Club updated successfully!";
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
    <div class="header">
        <div class="header-title-group">
            <h1>Manage Clubs</h1>
            <p class="header-subtitle">Create and edit your clubs</p>
        </div>
        <a href="dashboard.php" class="btn btn--secondary">Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message--success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if ($club_count < $club_limit): ?>
        <div class="card">
            <h2>Create New Club</h2>
            <form method="POST" class="form">
                <div class="form-group">
                    <input type="text" name="club_name" placeholder="Club Name" required class="form-control" pattern="[a-zA-Z0-9\s_-]+" title="Only letters, numbers, spaces, dashes and underscores are allowed">
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
                                            onclick="editClub(<?php echo $club['club_id']; ?>, '<?php echo addslashes($club['club_name']); ?>')">
                                        Edit
                                    </button>
                                    <a href="manage_members.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--xsmall">Members</a>
                                    <a href="manage_games.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--xsmall">Games</a>
                                    <a href="club_teams.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--xsmall">Teams</a>
                                    <a href="manage_logo.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn--xsmall">Logo</a>
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
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="club_id" id="edit_club_id">
                <div class="form-group">
                    <label>Club Name:</label>
                    <input type="text" name="club_name" id="edit_club_name" required class="form-control" pattern="[a-zA-Z0-9\s_-]+" title="Only letters, numbers, spaces, dashes and underscores are allowed">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">Save Changes</button>
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('editClubModal');
        const modalDialog = modal.querySelector('.modal__dialog');

        function editClub(clubId, clubName) {
            document.getElementById('edit_club_id').value = clubId;
            document.getElementById('edit_club_name').value = clubName;
            modal.classList.add('is-open');
        }

        function closeEditModal() {
            modal.classList.remove('is-open');
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target === modal) {
                closeEditModal();
            }
        };

        // Prevent event propagation from modal content
        modalDialog.addEventListener('click', function(event) {
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
