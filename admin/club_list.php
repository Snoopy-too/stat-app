<?php
/**
 * Club List - Select a club to add new game results
 * Shows club cards for admins with multiple clubs
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

// Get clubs for this admin with member and game counts
$stmt = $pdo->prepare("
    SELECT c.*,
           ca.role as admin_role,
           (SELECT COUNT(*) FROM members WHERE club_id = c.club_id) as member_count,
           (SELECT COUNT(*) FROM games WHERE club_id = c.club_id) as game_count
    FROM clubs c
    JOIN club_admins ca ON c.club_id = ca.club_id
    WHERE ca.admin_id = ?
    ORDER BY c.club_name ASC
");
$stmt->execute([$_SESSION['admin_id']]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Club - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <style>
        .club-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 1rem 0;
        }
        .club-card {
            background: var(--card-bg, #fff);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .club-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .club-card__image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .club-card__image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .club-card__placeholder {
            font-size: 4rem;
            opacity: 0.8;
        }
        .club-card__content {
            padding: 1.25rem;
        }
        .club-card__name {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
            color: var(--text-primary, #1e293b);
        }
        .club-card__stats {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary, #64748b);
        }
        .club-card__stat {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .club-card__action {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color, #e2e8f0);
            font-size: 0.875rem;
            color: var(--color-primary, #6366f1);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('new_result'); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Select Club', 'Choose a club to add new game results'); ?>
    </div>

    <div class="container">
        <?php display_session_message('error'); ?>
        <?php display_session_message('success'); ?>

        <?php if (empty($clubs)): ?>
            <div class="card">
                <div class="empty-state">
                    <p>You don't have any clubs yet.</p>
                    <a href="manage_clubs.php" class="btn">Create a Club</a>
                </div>
            </div>
        <?php else: ?>
            <div class="club-grid">
                <?php foreach ($clubs as $club): ?>
                    <a href="club_new_results.php?club_id=<?php echo $club['club_id']; ?>" class="club-card">
                        <div class="club-card__image">
                            <?php if ($club['logo_image']): ?>
                                <img src="../images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="<?php echo htmlspecialchars($club['club_name']); ?>" loading="lazy">
                            <?php else: ?>
                                <span class="club-card__placeholder">🎲</span>
                            <?php endif; ?>
                        </div>
                        <div class="club-card__content">
                            <h3 class="club-card__name"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                            <div class="club-card__stats">
                                <span class="club-card__stat">
                                    <span>👥</span>
                                    <span><?php echo $club['member_count']; ?> members</span>
                                </span>
                                <span class="club-card__stat">
                                    <span>🎲</span>
                                    <span><?php echo $club['game_count']; ?> games</span>
                                </span>
                            </div>
                            <div class="club-card__action">
                                <span>Add Result</span>
                                <span>→</span>
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
