<?php
session_start();
require_once 'config/database.php';

// Get result ID from URL parameter
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : 0;

// Fetch team game result details
$result = null;
$error = '';

if ($result_id > 0) {
    $stmt = $pdo->prepare("SELECT tgr.*, g.game_name, g.game_id, g.game_image, c.club_name,
        (SELECT team_name FROM teams WHERE team_id = tgr.winner) as winner_name,
        (SELECT team_name FROM teams WHERE team_id = tgr.place_2) as second_name,
        (SELECT team_name FROM teams WHERE team_id = tgr.place_3) as third_name,
        (SELECT team_name FROM teams WHERE team_id = tgr.place_4) as fourth_name,
        (SELECT team_name FROM teams WHERE team_id = tgr.place_5) as fifth_name,
        (SELECT team_name FROM teams WHERE team_id = tgr.place_6) as sixth_name,
        (SELECT team_name FROM teams WHERE team_id = tgr.place_7) as seventh_name,
        (SELECT team_name FROM teams WHERE team_id = tgr.place_8) as eighth_name
        FROM team_game_results tgr 
        JOIN games g ON tgr.game_id = g.game_id 
        JOIN clubs c ON g.club_id = c.club_id 
        WHERE tgr.result_id = ?");
    $stmt->execute([$result_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $error = 'Team game result not found';
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
    <title>Team Game Play Details - Board Game StatApp</title>
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
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
        <a href="game_details.php?id=<?php echo $result ? $result['game_id'] : ''; ?>" class="btn">Back to Game Details</a>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($result): ?>
            <div class="game-hero">
                <div class="game-hero__image-container">
                    <?php if ($result['game_image']): ?>
                        <img src="images/game_images/<?php echo htmlspecialchars($result['game_image']); ?>" alt="<?php echo htmlspecialchars($result['game_name']); ?>" class="game-hero__image">
                    <?php else: ?>
                        <div class="game-hero__image-placeholder">No Image</div>
                    <?php endif; ?>
                </div>
                <div class="game-hero__content">
                    <span class="game-hero__label">Team Match Result for</span>
                    <h1 class="game-hero__title"><?php echo htmlspecialchars($result['game_name']); ?></h1>
                    <div class="game-hero__subtitle">Played at <?php echo htmlspecialchars($result['club_name']); ?></div>
                </div>
            </div>

            <div class="card game-play-details">
                <div class="section-header" style="margin-bottom: var(--spacing-6); border-bottom: 2px solid var(--color-border); padding-bottom: var(--spacing-2);">
                    <h2 style="margin: 0;">Team Play Details</h2>
                </div>

                <?php
                $positions = [
                    ['Winner', 'winner_name', 'winner-name winner-name-badge position-1 winner-name'],
                    ['Second Place', 'second_name', 'position-2'],
                    ['Third Place', 'third_name', 'position-3'],
                    ['Fourth Place', 'fourth_name', 'position-4'],
                    ['Fifth Place', 'fifth_name', 'position-5'],
                    ['Sixth Place', 'sixth_name', 'position-6'],
                    ['Seventh Place', 'seventh_name', 'position-7'],
                    ['Eighth Place', 'eighth_name', 'position-8']
                ];

                foreach ($positions as $pos) {
                    if (!empty($result[$pos[1]])) {
                        ?>
                        <div class="detail-row">
                            <div class="detail-label"><?php echo $pos[0]; ?>:</div>
                            <div class="detail-value">
                                <span class="position-badge <?php echo $pos[2]; ?>">
                                    <?php echo htmlspecialchars($result[$pos[1]]); ?>
                                </span>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>

                <?php if (!empty($result['notes'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Notes:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($result['notes'])); ?></div>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <div class="detail-label">Number of Teams:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['num_teams']); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Played At:</div>
                    <div class="detail-value"><?php echo date('F j, Y, g:i a', strtotime($result['played_at'])); ?></div>
                </div>

                <?php if (!empty($result['duration'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Duration:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['duration']); ?> minutes</div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>