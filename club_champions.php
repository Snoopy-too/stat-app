<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

// Get club ID from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch club details
$club = null;
$error = '';

if ($club_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$club) {
        $error = 'Club not found';
    }
} else {
    $error = 'Invalid club ID';
}

// Fetch all champions (past and present) for this club
$champions = [];
if ($club) {
    $stmt = $pdo->prepare("
        SELECT 
            m.nickname,
            c.date,
            c.champ_comments
        FROM champions c
        INNER JOIN members m ON c.member_id = m.member_id
        WHERE c.club_id = ?
        ORDER BY c.date DESC
    ");
    $stmt->execute([$club_id]);
    $champions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark the first one (most recent) as current champion, others as false
    foreach ($champions as $index => &$champion) {
        $champion['is_current'] = ($index === 0);
    }
    unset($champion); // Break the reference
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Champions - <?php echo $club ? htmlspecialchars($club['club_name']) : 'Board Game Club'; ?> - StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
    <style>
        .trophy-header {
            text-align: center;
            margin: 2rem 0;
        }
        .trophy-image {
            max-width: 300px;
            height: auto;
            margin: 0 auto 1rem;
            display: block;
        }
        .champions-timeline {
            margin-top: 2rem;
        }
        .champion-entry {
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--accent-color);
            position: relative;
        }
        .champion-entry.current {
            border-left-color: var(--success-color);
            background: linear-gradient(90deg, var(--success-bg) 0%, var(--card-bg) 100%);
        }
        .champion-badge {
            display: inline-block;
            background: var(--success-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .champion-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .champion-date {
            color: var(--text-light);
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }
        .champion-comments {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius-small);
            font-style: italic;
            color: var(--text-secondary);
        }
        .no-champions {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light);
        }
    </style>
</head>
<body class="has-sidebar">
    <?php
    // Render sidebar navigation
    if ($club) {
        NavigationHelper::renderSidebar('champions', $club_id, $club['club_name'], $club['logo_image'] ?? null);
    } else {
        NavigationHelper::renderSidebar('champions');
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Champions', $club ? $club['club_name'] : ''); ?>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($club): ?>
            <div class="card">
                <?php if ($club['champ_image']): ?>
                    <div class="trophy-header">
                        <img src="<?php echo htmlspecialchars($club['champ_image']); ?>" alt="Championship Trophy" class="trophy-image">
                        <h2><?php echo htmlspecialchars($club['club_name']); ?> Champions</h2>
                    </div>
                <?php else: ?>
                    <div class="trophy-header">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🏆</div>
                        <h2><?php echo htmlspecialchars($club['club_name']); ?> Champions</h2>
                    </div>
                <?php endif; ?>

                <?php if (count($champions) > 0): ?>
                    <div class="champions-timeline">
                        <?php foreach ($champions as $champion): ?>
                            <div class="champion-entry <?php echo $champion['is_current'] ? 'current' : ''; ?>">
                                <div class="champion-name">
                                    <?php echo htmlspecialchars($champion['nickname']); ?>
                                    <?php if ($champion['is_current']): ?>
                                        <span class="champion-badge">Current Champion</span>
                                    <?php endif; ?>
                                </div>
                                <div class="champion-date">
                                    Crowned: <?php echo date('F j, Y', strtotime($champion['date'])); ?>
                                </div>
                                <?php if ($champion['champ_comments']): ?>
                                    <div class="champion-comments">
                                        <?php echo nl2br(htmlspecialchars($champion['champ_comments'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-champions">
                        <p><strong>No champions yet!</strong></p>
                        <p>This club hasn't crowned any champions yet. Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="js/sidebar.js"></script>
    <script src="js/empty-states.js"></script>
</body>
</html>
