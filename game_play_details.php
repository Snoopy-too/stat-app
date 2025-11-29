<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

// Get result ID from URL parameter
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : 0;

// Fetch game result details
$result = null;
$error = '';
$club_id = null;
$game_id = null;

if ($result_id > 0) {
    $stmt = $pdo->prepare("SELECT gr.*, g.game_name, g.game_id, c.club_name, c.club_id,
        (SELECT nickname FROM members WHERE member_id = gr.winner) as winner_name,
        (SELECT nickname FROM members WHERE member_id = gr.place_2) as second_name,
        (SELECT nickname FROM members WHERE member_id = gr.place_3) as third_name,
        (SELECT nickname FROM members WHERE member_id = gr.place_4) as fourth_name,
        (SELECT nickname FROM members WHERE member_id = gr.place_5) as fifth_name,
        (SELECT nickname FROM members WHERE member_id = gr.place_6) as sixth_name,
        (SELECT nickname FROM members WHERE member_id = gr.place_7) as seventh_name,
        (SELECT nickname FROM members WHERE member_id = gr.place_8) as eighth_name
        FROM game_results gr 
        JOIN games g ON gr.game_id = g.game_id 
        JOIN clubs c ON g.club_id = c.club_id 
        WHERE gr.result_id = ?");
    $stmt->execute([$result_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $club_id = $result['club_id'];
        $game_id = $result['game_id'];
        
        // Fetch losers if any
        $loser_stmt = $pdo->prepare("SELECT m.nickname FROM game_result_losers grl JOIN members m ON grl.member_id = m.member_id WHERE grl.result_id = ? ORDER BY m.nickname");
        $loser_stmt->execute([$result_id]);
        $losers = $loser_stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $error = 'Game result not found';
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
    <title>Game Play Details - Board Game StatApp</title>
    <link rel="stylesheet" href="css/styles.css">

    <script src="js/dark-mode.js"></script>
</head>
<body>
    <?php
    // Render breadcrumbs
    if ($result) {
        NavigationHelper::renderBreadcrumbs([
            ['label' => 'Home', 'url' => 'index.php'],
            ['label' => $result['club_name'], 'url' => 'club_stats.php?id=' . $club_id],
            ['label' => $result['game_name'], 'url' => 'game_details.php?id=' . $game_id],
            'Play Details'
        ]);
    }
    ?>
    
    <div class="header">
        <?php NavigationHelper::renderHeaderTitle('Board Game Club StatApp', 'Game Play Details', 'index.php'); ?>
        <div class="header-actions">
            <a href="game_details.php?id=<?php echo $game_id; ?>" class="btn btn--secondary btn--small">← Back to Game</a>
            <a href="club_stats.php?id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small">Club Stats</a>
            <a href="index.php" class="btn btn--ghost btn--small">🏠 Home</a>
        </div>
    </div>
    
    <?php
    // Render navigation and context bar if we have data
    if ($result) {
        NavigationHelper::renderMobileCardNav('', $club_id);
        NavigationHelper::renderPublicNav('', $club_id);
        NavigationHelper::renderContextBar('Viewing result for', $result['game_name'], 'View all results', 'club_game_results.php?id=' . $club_id);
    }
    ?>

    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($result): ?>
            <div class="game-play-details">
                <h2>Game Play Details</h2>
                
                <div class="detail-row">
                    <div class="detail-label">Game:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['game_name']); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Club:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['club_name']); ?></div>
                </div>

                <?php
                // Display ranked positions if they exist
                $positions = [
                    ['Winner', 'winner_name', 'position-1'],
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
                
                // Display losers if any
                if (!empty($losers)) {
                    ?>
                    <div class="detail-row">
                        <div class="detail-label">Losers:</div>
                        <div class="detail-value">
                            <div class="losers-list">
                                <?php foreach ($losers as $loser): ?>
                                    <span class="badge badge--neutral"><?php echo htmlspecialchars($loser); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>

                <?php if (!empty($result['notes'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Notes:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($result['notes'])); ?></div>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <div class="detail-label">Number of Players:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($result['num_players']); ?></div>
                </div>

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
    <script src="js/mobile-menu.js"></script>
    <script src="js/form-loading.js"></script>
    <script src="js/confirmations.js"></script>
    <script src="js/form-validation.js"></script>
    <script src="js/empty-states.js"></script>
    <script src="js/multi-step-form.js"></script>
    <script src="js/breadcrumbs.js"></script>
</body>
</html>
