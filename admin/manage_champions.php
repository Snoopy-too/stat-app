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

// Get club info
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Get all members for the dropdown
$member_query = "SELECT m.* FROM members m WHERE m.club_id = ? ORDER BY m.member_name ASC";
$stmt = $pdo->prepare($member_query);
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build the query for champions
$query = "
    SELECT c.*, m.member_name, m.nickname
    FROM champions c
    JOIN members m ON c.member_id = m.member_id
    WHERE m.club_id = ?
";

$params = [$club_id];

if ($search) {
    $query .= " AND (m.member_name LIKE ? OR c.champ_comments LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY c.date " . ($order === 'desc' ? 'DESC' : 'ASC');

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$champions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle member creation/deletion and bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_champions.php?club_id=" . $club_id);
        exit();
    }

    if (isset($_POST['bulk_action']) && !empty($_POST['selected_champions'])) {
        $selected_champions = $_POST['selected_champions'];
        $bulk_action = $_POST['bulk_action'];
        
        try {
            if ($bulk_action === 'bulk_delete') {
                $stmt = $pdo->prepare("DELETE FROM champions WHERE ID = ?");
                foreach ($selected_champions as $champion_id) {
                    $stmt->execute([$champion_id]);
                }
                $_SESSION['success'] = "Selected champions deleted successfully!";
            }
            
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to perform bulk action: " . $e->getMessage();
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        }
    } else if (isset($_POST['action'])) {
        // Update the INSERT query in the POST handling section
        if ($_POST['action'] === 'create' && !empty($_POST['member_id']) && !empty($_POST['champ_date'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO champions (club_id, member_id, date, champ_comments) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $club_id,
                    trim($_POST['member_id']),
                    trim($_POST['champ_date']),
                    trim($_POST['champ_comments'])
                ]);
                $_SESSION['success'] = "Champion added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to add champion: " . $e->getMessage();
            }
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        } elseif ($_POST['action'] === 'edit' && !empty($_POST['champion_id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE champions SET member_id = ?, date = ?, champ_comments = ? WHERE id = ?");
                $stmt->execute([
                    trim($_POST['edit_member_id']),
                    trim($_POST['edit_date']),
                    trim($_POST['edit_comments']),
                    $_POST['champion_id']
                ]);
                $_SESSION['success'] = "Champion updated successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to update champion: " . $e->getMessage();
            }
            header("Location: manage_champions.php?club_id=" . $club_id);
            exit();
        }
    }
}

// Generate CSRF token for forms
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Champions - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('champions', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Champions', htmlspecialchars($club['club_name'])); ?>
        <div class="header-actions">
            <a href="manage_trophy.php?club_id=<?php echo $club_id; ?>" class="btn btn--subtle btn--small">Manage Trophy</a>
        </div>
    </div>

    <div class="container container--wide">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2>Add New Champion</h2>
                    <p class="card-subtitle card-subtitle--muted">Recognize the latest club champion and capture their story.</p>
                </div>
            </div>
            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="create">
                <div class="grid grid--columns-3">
                    <div class="form-group">
                        <label for="member_id">Champion</label>
                        <select name="member_id" id="member_id" required class="form-control">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>">
                                    <?php echo htmlspecialchars($member['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="champ_date">Date Awarded</label>
                        <input type="date" id="champ_date" name="champ_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="champ_comments">Champion Notes</label>
                        <textarea id="champ_comments" name="champ_comments" placeholder="Champion comments" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Add Champion</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header card-header--stack">
                <div>
                    <h2>Champion History</h2>
                    <p class="card-subtitle card-subtitle--muted"><?php echo count($champions); ?> recorded champion<?php echo count($champions) === 1 ? '' : 's'; ?>.</p>
                </div>
            </div>
            <div class="card-toolbar">
                <form method="GET" class="toolbar-group toolbar-group--grow search-form">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="Search champions..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <button type="submit" class="btn btn--subtle btn--small">Filter</button>
                        <a href="?club_id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small">Reset</a>
                    </div>
                </form>
                <form method="POST" class="toolbar-group" id="bulk-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <select name="bulk_action" class="form-control form-control--sm">
                        <option value="">Bulk Actions</option>
                        <option value="bulk_delete">Delete Selected</option>
                    </select>
                    <button type="submit" class="btn btn--subtle btn--small" onclick="return confirmBulkAction()">Apply</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>Member Name</th>
                        <th>Date</th>
                        <th>Comments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($champions)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No champions have been recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($champions as $champion): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_champions[]" form="bulk-form"
                                       value="<?php echo $champion['ID']; ?>" class="champion-checkbox">
                            </td>
                            <td data-label="Member Name"><?php echo htmlspecialchars($champion['member_name']); ?></td>
                            <td data-label="Date"><?php echo date('F j Y', strtotime($champion['date'])); ?></td>
                            <td data-label="Comments"><?php echo htmlspecialchars($champion['champ_comments']); ?></td>
                            <td data-label="Actions">
                                <div class="btn-group">
                                    <button type="button" class="btn btn--subtle btn--xsmall btn--pill" onclick="editChampion(<?php echo $champion['ID']; ?>, <?php echo $champion['member_id']; ?>, '<?php echo $champion['date']; ?>', '<?php echo addslashes($champion['champ_comments']); ?>')">
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Champion Modal -->
    <div id="editChampionModal" class="modal">
        <div class="modal__dialog">
            <form id="editChampionForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="champion_id" id="edit_champion_id">
                <div class="form-group">
                    <label>Member:</label>
                    <select name="edit_member_id" id="edit_member_id" required class="form-control">
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>">
                                <?php echo htmlspecialchars($member['member_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date:</label>
                    <input type="date" name="edit_date" id="edit_date" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Comments:</label>
                    <textarea name="edit_comments" id="edit_comments" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">Save Changes</button>
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.champion-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            if (!action) {
                alert('Please select an action');
                return false;
            }
            return confirm('Are you sure you want to perform this action on the selected members?');
        }

        const championModal = document.getElementById('editChampionModal');
        const championModalDialog = championModal.querySelector('.modal__dialog');

        function editChampion(championId, memberId, date, comments) {
            document.getElementById('edit_champion_id').value = championId;
            document.getElementById('edit_member_id').value = memberId;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_comments').value = comments;
            championModal.classList.add('is-open');
        }

        function closeEditModal() {
            championModal.classList.remove('is-open');
        }

        championModal.addEventListener('click', function(event) {
            if (event.target === championModal) {
                closeEditModal();
            }
        });

        championModalDialog.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
<script>
(function() {
    // Save scroll position before navigating away (sort/filter links)
    function saveScrollPosition() {
        try {
            sessionStorage.setItem('manage_champions_scroll', window.scrollY);
        } catch (e) {}
    }
    // Attach to all sort/filter links
    document.addEventListener('DOMContentLoaded', function() {
        var links = document.querySelectorAll('a.sort-link, .search-form button[type="submit"], .search-form .button');
        links.forEach(function(link) {
            link.addEventListener('click', saveScrollPosition);
        });
        // Restore scroll position
        var scroll = sessionStorage.getItem('manage_champions_scroll');
        if (scroll !== null) {
            window.scrollTo(0, parseInt(scroll, 10));
            sessionStorage.removeItem('manage_champions_scroll');
        }
    });
    // Also save on form submit (search/filter)
    var forms = document.querySelectorAll('.search-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', saveScrollPosition);
    });
})();
</script>
</html>
