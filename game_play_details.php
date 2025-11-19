<?php
session_start();
require_once 'config/database.php';

// Get result ID from URL parameter
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : 0;

// Fetch game result details
$result = null;
$error = '';

if ($result_id > 0) {
    $stmt = $pdo->prepare("SELECT gr.*, g.game_name, c.club_name,
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

    if (!$result) {
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
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Game Play Details</p>
        </div>
        <a href="game_details.php?id=<?php echo $result ? $result['game_id'] : ''; ?>" class="btn btn--secondary">Back to Game Details</a>
    </div>

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
