<?php
/**
 * Club New Results - Select a game to add results for
 * Shows game cards for a specific club
 */

session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/NavigationHelper.php';

// Ensure user is logged in
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

// Get club_id from URL
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;

if (!$club_id) {
    $_SESSION['error'] = "No club selected.";
    header("Location: new_result.php");
    exit();
}

// Verify admin has access to this club
$stmt = $pdo->prepare("
    SELECT c.* FROM clubs c
    JOIN club_admins ca ON c.club_id = ca.club_id
    WHERE c.club_id = ? AND ca.admin_id = ?
");
$stmt->execute([$club_id, $_SESSION['admin_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    $_SESSION['error'] = "Club not found or access denied.";
    header("Location: dashboard.php");
    exit();
}

// Get games for this club
$stmt = $pdo->prepare("
    SELECT g.*,
           (SELECT COUNT(DISTINCT session_id) FROM game_results WHERE game_id = g.game_id) +
           (SELECT COUNT(DISTINCT session_id) FROM team_game_results WHERE game_id = g.game_id) as play_count
    FROM games g
    WHERE g.club_id = ?
    ORDER BY g.game_name ASC
");
$stmt->execute([$club_id]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Game - <?php echo htmlspecialchars($club['club_name']); ?> - StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <style>
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.25rem;
            padding: 1rem 0;
        }
        .game-card {
            background: var(--card-bg, #fff);
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .game-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .game-card__image {
            width: 100%;
            height: 140px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .game-card__image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .game-card__placeholder {
            font-size: 3rem;
            opacity: 0.4;
        }
        .game-card__content {
            padding: 1rem;
        }
        .game-card__name {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.25rem;
            color: var(--text-primary, #1e293b);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .game-card__meta {
            font-size: 0.75rem;
            color: var(--text-secondary, #64748b);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .game-card__plays {
            background: var(--bg-tertiary, #f1f5f9);
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-weight: 500;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary, #64748b);
            text-decoration: none;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .back-link:hover {
            color: var(--text-primary, #1e293b);
        }
        .page-intro {
            margin-bottom: 1.5rem;
        }
        .page-intro p {
            color: var(--text-secondary, #64748b);
            margin: 0;
        }
    </style>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('new_result', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Add Game Result', $club['club_name']); ?>
    </div>

    <div class="container">
        <?php display_session_message('error'); ?>
        <?php display_session_message('success'); ?>

        <?php
        // Check if admin has multiple clubs - show back link if so
        $clubCountStmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE admin_id = ?");
        $clubCountStmt->execute([$_SESSION['admin_id']]);
        $hasMultipleClubs = $clubCountStmt->fetchColumn() > 1;
        ?>

        <?php if ($hasMultipleClubs): ?>
            <a href="club_list.php" class="back-link">
                <span>←</span>
                <span>Back to club selection</span>
            </a>
        <?php endif; ?>

        <div class="page-intro">
            <p>Select a game to record a new play result.</p>
        </div>

        <?php if (empty($games)): ?>
            <div class="card">
                <div class="empty-state" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🎲</div>
                    <h3 style="margin: 0 0 0.5rem;">No Games Yet</h3>
                    <p style="color: var(--text-secondary); margin: 0 0 1.5rem;">Add some games to your club before recording results.</p>
                    <a href="manage_games.php?club_id=<?php echo $club_id; ?>" class="btn">Add Games</a>
                </div>
            </div>
        <?php else: ?>
            <div class="game-grid">
                <?php foreach ($games as $game): ?>
                    <a href="results.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game['game_id']; ?>" class="game-card">
                        <div class="game-card__image">
                            <?php if (!empty($game['game_image'])): ?>
                                <img src="../images/game_images/<?php echo htmlspecialchars($game['game_image']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>" loading="lazy">
                            <?php else: ?>
                                <span class="game-card__placeholder">🎲</span>
                            <?php endif; ?>
                        </div>
                        <div class="game-card__content">
                            <h3 class="game-card__name" title="<?php echo htmlspecialchars($game['game_name']); ?>">
                                <?php echo htmlspecialchars($game['game_name']); ?>
                            </h3>
                            <div class="game-card__meta">
                                <span><?php echo $game['min_players']; ?>-<?php echo $game['max_players']; ?> players</span>
                                <span class="game-card__plays"><?php echo $game['play_count']; ?> plays</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="../js/sidebar.js"></script>
</body>
</html>
