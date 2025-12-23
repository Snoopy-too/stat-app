<?php
session_start();
require_once '../config/database.php';
require_once '../includes/NavigationHelper.php';

// Ensure user is logged in and has appropriate admin access
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

// Redirect single_club admins without a club to create their first club
if (isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'single_club') {
    $clubCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE admin_id = ?");
    $clubCheckStmt->execute([$_SESSION['admin_id']]);
    if ($clubCheckStmt->fetchColumn() == 0) {
        header("Location: create_first_club.php");
        exit();
    }
}

// Fetch clubs with statistics for the current admin
$query = "
    SELECT c.*,
           (SELECT COUNT(*) FROM members WHERE club_id = c.club_id) as member_count,
           (SELECT COUNT(*) FROM games WHERE club_id = c.club_id) as game_count,
           (COALESCE((SELECT COUNT(DISTINCT session_id) FROM game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0) +
            COALESCE((SELECT COUNT(DISTINCT session_id) FROM team_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = c.club_id)), 0)) as games_played,
           ca.role as admin_role
    FROM clubs c
    JOIN club_admins ca ON c.club_id = ca.club_id
    WHERE ca.admin_id = ?
    GROUP BY c.club_id
    ORDER BY c.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['admin_id']]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total statistics
$total_members = array_sum(array_column($clubs, 'member_count'));
$total_games = array_sum(array_column($clubs, 'game_count'));

// Get total champions count
$club_ids = array_column($clubs, 'club_id');
if (!empty($club_ids)) {
    $placeholders = implode(',', array_fill(0, count($club_ids), '?'));
    $champStmt = $pdo->prepare("SELECT COUNT(*) FROM champions WHERE club_id IN ($placeholders)");
    $champStmt->execute($club_ids);
    $total_champions = $champStmt->fetchColumn();

    // Get total teams count
    $teamStmt = $pdo->prepare("
        SELECT COUNT(*) FROM teams t
        JOIN members m ON t.member1_id = m.member_id
        WHERE m.club_id IN ($placeholders)
    ");
    $teamStmt->execute($club_ids);
    $total_teams = $teamStmt->fetchColumn();

    // Get total results count (individual + team results)
    $resultsStmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM game_results gr
             JOIN games g ON gr.game_id = g.game_id
             WHERE g.club_id IN ($placeholders)) +
            (SELECT COUNT(*) FROM team_game_results tgr
             JOIN games g ON tgr.game_id = g.game_id
             WHERE g.club_id IN ($placeholders))
        AS total_results
    ");
    $resultsStmt->execute(array_merge($club_ids, $club_ids));
    $total_results = $resultsStmt->fetchColumn();
} else {
    $total_champions = 0;
    $total_teams = 0;
    $total_results = 0;
}

// Check admin type for conditional display
$admin_type = $_SESSION['admin_type'] ?? 'multi_club';
$is_single_club = ($admin_type === 'single_club');

// Check if we should display in single-club mode (either strictly single_club type OR multi_club with exactly one club)
$single_club_mode = (count($clubs) === 1);
$direct_link_club_id = $single_club_mode ? $clubs[0]['club_id'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Link to external CSS -->
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('dashboard'); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Admin Dashboard', 'Monitor membership and game activity'); ?>
    </div>

    <div class="container">
        <div class="dashboard-stats">
            <?php if (!$is_single_club): ?>
            <a href="manage_clubs.php" class="card stat-card stat-card--navy">
                <span class="stat-card__label">Total Clubs</span>
                <div class="stat-card__body">
                    <span class="stat-card__value"><?php echo count($clubs); ?></span>
                    <span class="stat-card__meta">Active clubs under your account</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($single_club_mode): ?>
                <!-- Single club (or effective single club): direct links -->
                <a href="manage_members.php?club_id=<?php echo $direct_link_club_id; ?>" class="card stat-card stat-card--sky stat-card--clickable">
                    <span class="stat-card__label">Total Members</span>
                    <div class="stat-card__body">
                        <span class="stat-card__value"><?php echo $total_members; ?></span>
                        <span class="stat-card__meta">In your club</span>
                    </div>
                </a>
                <div class="stat-card-pair">
                    <a href="manage_games.php?club_id=<?php echo $direct_link_club_id; ?>" class="card stat-card stat-card--neutral stat-card--clickable stat-card--half">
                        <span class="stat-card__label">Games</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $total_games; ?></span>
                            <span class="stat-card__meta">In your library</span>
                        </div>
                    </a>
                    <a href="club_new_results.php?club_id=<?php echo $direct_link_club_id; ?>" class="card stat-card stat-card--emerald stat-card--clickable stat-card--half">
                        <span class="stat-card__label">Results</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $total_results; ?></span>
                            <span class="stat-card__meta">Games played</span>
                        </div>
                    </a>
                </div>
                <a href="manage_champions.php?club_id=<?php echo $direct_link_club_id; ?>" class="card stat-card stat-card--gold stat-card--clickable">
                    <span class="stat-card__label">Champions</span>
                    <div class="stat-card__body">
                        <span class="stat-card__value"><?php echo $total_champions; ?></span>
                        <span class="stat-card__meta">Total champions to date</span>
                    </div>
                </a>
                <a href="club_teams.php?club_id=<?php echo $direct_link_club_id; ?>" class="card stat-card stat-card--purple stat-card--clickable">
                    <span class="stat-card__label">Teams</span>
                    <div class="stat-card__body">
                        <span class="stat-card__value"><?php echo $total_teams; ?></span>
                        <span class="stat-card__meta">Active teams</span>
                    </div>
                </a>
            <?php else: ?>
                <!-- Multi club admin: show club selector on click -->
                <div class="card stat-card stat-card--sky stat-card--clickable" onclick="showClubSelector('members')" style="cursor:pointer;">
                    <span class="stat-card__label">Total Members</span>
                    <div class="stat-card__body">
                        <span class="stat-card__value"><?php echo $total_members; ?></span>
                        <span class="stat-card__meta">Across all managed clubs</span>
                    </div>
                </div>
                <div class="stat-card-pair">
                    <div class="card stat-card stat-card--neutral stat-card--clickable stat-card--half" onclick="showClubSelector('games')" style="cursor:pointer;">
                        <span class="stat-card__label">Games</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $total_games; ?></span>
                            <span class="stat-card__meta">In your libraries</span>
                        </div>
                    </div>
                    <div class="card stat-card stat-card--emerald stat-card--clickable stat-card--half" onclick="showClubSelector('results')" style="cursor:pointer;">
                        <span class="stat-card__label">Results</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $total_results; ?></span>
                            <span class="stat-card__meta">Games played</span>
                        </div>
                    </div>
                </div>
                <div class="card stat-card stat-card--gold stat-card--clickable" onclick="showClubSelector('champions')" style="cursor:pointer;">
                    <span class="stat-card__label">Champions</span>
                    <div class="stat-card__body">
                        <span class="stat-card__value"><?php echo $total_champions; ?></span>
                        <span class="stat-card__meta">Total champions to date</span>
                    </div>
                </div>
                <div class="card stat-card stat-card--purple stat-card--clickable" onclick="showClubSelector('teams')" style="cursor:pointer;">
                    <span class="stat-card__label">Teams</span>
                    <div class="stat-card__body">
                        <span class="stat-card__value"><?php echo $total_teams; ?></span>
                        <span class="stat-card__meta">Active teams</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Club Selector Modal for Multi-Club Admins -->
    <?php if (!$is_single_club): ?>
    <div id="clubSelectorModal" class="modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.5);z-index:2000;align-items:center;justify-content:center;">
        <div class="modal-content card" style="max-width:400px;width:90%;margin:auto;padding:1.5rem;">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3 style="margin:0;" id="modalTitle">Select a Club</h3>
                <button onclick="closeClubSelector()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b;">&times;</button>
            </div>
            <div class="modal-body">
                <div class="club-list" style="display:flex;flex-direction:column;gap:0.5rem;">
                    <?php foreach ($clubs as $club): ?>
                    <button class="club-select-btn btn btn--ghost"
                            data-club-id="<?php echo $club['club_id']; ?>"
                            data-club-name="<?php echo htmlspecialchars($club['club_name']); ?>"
                            style="text-align:left;padding:0.75rem 1rem;justify-content:flex-start;">
                        <?php echo htmlspecialchars($club['club_name']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../js/dark-mode.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>

    <?php if (!$is_single_club): ?>
    <script>
    let currentAction = '';

    function showClubSelector(action) {
        currentAction = action;
        const modal = document.getElementById('clubSelectorModal');
        const title = document.getElementById('modalTitle');

        const titles = {
            'members': 'Select Club for Members',
            'games': 'Select Club for Games',
            'results': 'Select Club for Results',
            'champions': 'Select Club for Champions',
            'teams': 'Select Club for Teams'
        };

        title.textContent = titles[action] || 'Select a Club';
        modal.style.display = 'flex';

        // Add click handlers to club buttons
        document.querySelectorAll('.club-select-btn').forEach(btn => {
            btn.onclick = function() {
                const clubId = this.dataset.clubId;
                navigateToPage(clubId, currentAction);
            };
        });
    }

    function closeClubSelector() {
        document.getElementById('clubSelectorModal').style.display = 'none';
        currentAction = '';
    }

    function navigateToPage(clubId, action) {
        const pages = {
            'members': 'manage_members.php?club_id=',
            'games': 'manage_games.php?club_id=',
            'results': 'club_new_results.php?club_id=',
            'champions': 'manage_champions.php?club_id=',
            'teams': 'club_teams.php?club_id='
        };

        if (pages[action]) {
            window.location.href = pages[action] + clubId;
        }
    }

    // Close modal when clicking outside
    document.getElementById('clubSelectorModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeClubSelector();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeClubSelector();
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
