<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

// Get result ID from URL parameter
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : 0;

// Fetch cooperative game result details
$result = null;
$error = '';
$club_id = null;
$game_id = null;
$participants = [];
$team_members = [];

if ($result_id > 0) {
    $stmt = $pdo->prepare("SELECT cgr.*, g.game_name, g.game_id, g.game_image, c.club_name, c.club_id
        FROM cooperative_game_results cgr
        JOIN games g ON cgr.game_id = g.game_id
        JOIN clubs c ON g.club_id = c.club_id
        WHERE cgr.result_id = ?");
    $stmt->execute([$result_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $club_id = $result['club_id'];
        $game_id = $result['game_id'];

        // Fetch participants
        $participant_stmt = $pdo->prepare("
            SELECT crp.*, m.nickname as member_name, t.team_name
            FROM cooperative_result_participants crp
            LEFT JOIN members m ON crp.member_id = m.member_id
            LEFT JOIN teams t ON crp.team_id = t.team_id
            WHERE crp.result_id = ?
            ORDER BY m.nickname, t.team_name");
        $participant_stmt->execute([$result_id]);
        $participants = $participant_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get team members if participant is a team
        if (!empty($participants) && $participants[0]['participant_type'] === 'team' && $participants[0]['team_id']) {
            $team_stmt = $pdo->prepare("
                SELECT t.team_id,
                       m1.nickname as member1_name,
                       m2.nickname as member2_name,
                       m3.nickname as member3_name,
                       m4.nickname as member4_name
                FROM teams t
                LEFT JOIN members m1 ON t.member1_id = m1.member_id
                LEFT JOIN members m2 ON t.member2_id = m2.member_id
                LEFT JOIN members m3 ON t.member3_id = m3.member_id
                LEFT JOIN members m4 ON t.member4_id = m4.member_id
                WHERE t.team_id = ?");
            $team_stmt->execute([$participants[0]['team_id']]);
            $team_data = $team_stmt->fetch(PDO::FETCH_ASSOC);
            if ($team_data) {
                for ($i = 1; $i <= 4; $i++) {
                    if (!empty($team_data["member{$i}_name"])) {
                        $team_members[] = $team_data["member{$i}_name"];
                    }
                }
            }
        }
    } else {
        $error = 'Cooperative game result not found';
    }
} else {
    $error = 'Invalid result ID';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Game Details - Board Game StatApp</title>
    <link rel="stylesheet" href="css/styles.css">

    <script src="js/dark-mode.js"></script>
    <style>
        .game-hero {
            display: flex;
            gap: var(--spacing-6);
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: var(--spacing-6);
            margin-bottom: var(--spacing-8);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-md);
            align-items: center;
        }
        .game-hero__image-container {
            flex-shrink: 0;
            width: 150px;
            height: 150px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 3px solid var(--color-background);
            background: var(--color-surface-muted);
        }
        .game-hero__image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .game-hero__image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-xs);
            color: var(--color-text-soft);
            background: linear-gradient(135deg, var(--color-surface-muted), var(--color-border));
            text-align: center;
            padding: var(--spacing-2);
        }
        .game-hero__content {
            flex-grow: 1;
        }
        .game-hero__label {
            font-size: var(--font-size-xs);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--color-text-muted);
            font-weight: var(--font-weight-bold);
            margin-bottom: var(--spacing-1);
            display: block;
        }
        .game-hero__title {
            font-size: var(--font-size-2xl);
            margin-bottom: var(--spacing-2);
            color: var(--color-heading);
        }
        .game-hero__subtitle {
            font-size: var(--font-size-sm);
            color: var(--color-text-soft);
        }

        @media (max-width: 36rem) {
            .game-hero {
                flex-direction: column;
                text-align: center;
                gap: var(--spacing-4);
            }
            .game-hero__image-container {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php
    // Render sidebar navigation
    if ($result) {
        NavigationHelper::renderSidebar('results', $club_id, $result['club_name']);
    } else {
        NavigationHelper::renderSidebar('results');
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader($result ? $result['game_name'] . ' - Cooperative Play' : 'Cooperative Play Details'); ?>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($result): ?>
            <div class="game-hero">
                <div class="game-hero__image-container">
                    <?php if (!empty($result['game_image'])): ?>
                        <img src="images/game_images/<?php echo htmlspecialchars($result['game_image']); ?>" alt="<?php echo htmlspecialchars($result['game_name']); ?>" class="game-hero__image">
                    <?php else: ?>
                        <div class="game-hero__image-placeholder">No Image</div>
                    <?php endif; ?>
                </div>
                <div class="game-hero__content">
                    <span class="game-hero__label">Cooperative Game Result for</span>
                    <h1 class="game-hero__title"><?php echo htmlspecialchars($result['game_name']); ?></h1>
                    <div class="game-hero__subtitle">Played at <?php echo htmlspecialchars($result['club_name']); ?></div>
                </div>
            </div>

            <div class="card game-play-details">
                <div class="section-header" style="margin-bottom: var(--spacing-6); border-bottom: 2px solid var(--color-border); padding-bottom: var(--spacing-2);">
                    <h2 style="margin: 0;">Cooperative Play Details</h2>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Outcome:</div>
                    <div class="detail-value">
                        <span class="outcome-badge outcome-<?php echo $result['outcome']; ?>">
                            <?php echo strtoupper($result['outcome']); ?>
                        </span>
                    </div>
                </div>

                <?php if ($result['score'] !== null): ?>
                <div class="detail-row">
                    <div class="detail-label">Score:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['score']); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($result['difficulty'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Difficulty:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['difficulty']); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($result['scenario'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Scenario/Mission:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['scenario']); ?></div>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <div class="detail-label">Participants:</div>
                    <div class="detail-value">
                        <?php if (!empty($participants) && $participants[0]['participant_type'] === 'team'): ?>
                            <strong><?php echo htmlspecialchars($participants[0]['team_name']); ?></strong>
                            <?php if (!empty($team_members)): ?>
                                <br><span class="text-muted">(<?php echo htmlspecialchars(implode(', ', $team_members)); ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php
                            $member_names = array_map(function($p) {
                                return htmlspecialchars($p['member_name']);
                            }, $participants);
                            echo implode(', ', $member_names);
                            ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Number of Participants:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['num_participants']); ?></div>
                </div>

                <?php if (!empty($result['notes'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Notes:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($result['notes'])); ?></div>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <div class="detail-label">Played At:</div>
                    <div class="detail-value"><?php echo date('F j, Y, g:i a', strtotime($result['played_at'])); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Duration:</div>
                    <div class="detail-value">
                        <?php
                        if (!empty($result['duration'])) {
                            $hours = floor($result['duration'] / 60);
                            $minutes = $result['duration'] % 60;
                            $duration = '';
                            if ($hours > 0) {
                                $duration .= $hours . ' hr ';
                            }
                            if ($minutes > 0 || $hours == 0) {
                                $duration .= $minutes . ' min';
                            }
                            echo htmlspecialchars($duration);
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="js/sidebar.js"></script>
    <script src="js/empty-states.js"></script>
</body>
</html>
