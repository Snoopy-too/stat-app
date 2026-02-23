<?php
declare(strict_types=1);
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : null;

if (!$result_id) {
    $_SESSION['error'] = "Invalid result ID provided.";
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: edit_result.php?result_id=" . $result_id);
        exit();
    }

    try {
        // Validate and sanitize input
        $played_at = $_POST['played_at'];
        $duration = (int)$_POST['duration'];
        $notes = trim($_POST['notes']);
        $member_id = (int)$_POST['member_id'];
        $game_type = $_POST['game_type'] ?? 'ranked';

        if ($game_type === 'winner_losers') {
            // Winner + Losers format
            $loser_ids = isset($_POST['losers']) ? array_filter($_POST['losers']) : [];

            // Check for duplicate players
            $all_players = array_merge([$member_id], array_map('intval', $loser_ids));
            if (count($all_players) !== count(array_unique($all_players))) {
                $_SESSION['error'] = "A player cannot be both winner and loser, or appear multiple times.";
                header("Location: edit_result.php?result_id=" . $result_id);
                exit();
            }

            if (empty($loser_ids)) {
                $_SESSION['error'] = "Please select at least one loser.";
                header("Location: edit_result.php?result_id=" . $result_id);
                exit();
            }

            $pdo->beginTransaction();

            // Update the game result (clear any ranked places)
            $stmt = $pdo->prepare("
                UPDATE game_results
                SET played_at = ?, duration = ?, notes = ?,
                    member_id = ?, num_players = ?,
                    place_2 = NULL, place_3 = NULL, place_4 = NULL,
                    place_5 = NULL, place_6 = NULL, place_7 = NULL, place_8 = NULL
                WHERE result_id = ?");

            $num_players = 1 + count($loser_ids);
            $stmt->execute([$played_at, $duration, $notes, $member_id, $num_players, $result_id]);

            // Delete existing losers and insert new ones
            $stmt = $pdo->prepare("DELETE FROM game_result_losers WHERE result_id = ?");
            $stmt->execute([$result_id]);

            $stmt = $pdo->prepare("INSERT INTO game_result_losers (result_id, member_id) VALUES (?, ?)");
            foreach ($loser_ids as $loser_id) {
                $stmt->execute([$result_id, (int)$loser_id]);
            }

            $pdo->commit();
        } else {
            // Ranked format
            $place_2 = !empty($_POST['place_2']) ? (int)$_POST['place_2'] : null;
            $place_3 = !empty($_POST['place_3']) ? (int)$_POST['place_3'] : null;
            $place_4 = !empty($_POST['place_4']) ? (int)$_POST['place_4'] : null;
            $place_5 = !empty($_POST['place_5']) ? (int)$_POST['place_5'] : null;
            $place_6 = !empty($_POST['place_6']) ? (int)$_POST['place_6'] : null;
            $place_7 = !empty($_POST['place_7']) ? (int)$_POST['place_7'] : null;
            $place_8 = !empty($_POST['place_8']) ? (int)$_POST['place_8'] : null;

            // Check for duplicate players
            $player_positions = array_filter([$member_id, $place_2, $place_3, $place_4, $place_5, $place_6, $place_7, $place_8]);
            if (count($player_positions) !== count(array_unique($player_positions))) {
                $_SESSION['error'] = "A player cannot occupy more than one position.";
                header("Location: edit_result.php?result_id=" . $result_id);
                exit();
            }

            // Update the game result
            $stmt = $pdo->prepare("
                UPDATE game_results
                SET played_at = ?, duration = ?, notes = ?,
                    member_id = ?, place_2 = ?, place_3 = ?,
                    place_4 = ?, place_5 = ?, place_6 = ?,
                    place_7 = ?, place_8 = ?
                WHERE result_id = ?");

            $stmt->execute([
                $played_at, $duration, $notes,
                $member_id, $place_2, $place_3,
                $place_4, $place_5, $place_6,
                $place_7, $place_8, $result_id
            ]);
        }

        $_SESSION['success'] = "Game result updated successfully.";
        header("Location: view_result.php?result_id=" . $result_id);
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error updating game result: " . $e->getMessage();
    }
}

// Get game result details with game and club information
$stmt = $pdo->prepare("
    SELECT gr.*, g.game_name, g.club_id, c.club_name
    FROM game_results gr
    JOIN games g ON gr.game_id = g.game_id
    JOIN clubs c ON g.club_id = c.club_id
    WHERE gr.result_id = ?");
$stmt->execute([$result_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error'] = "Result not found.";
    header("Location: dashboard.php");
    exit();
}

// Check if this result uses winner+losers format (has entries in game_result_losers table)
$stmt = $pdo->prepare("SELECT member_id FROM game_result_losers WHERE result_id = ?");
$stmt->execute([$result_id]);
$losers = $stmt->fetchAll(PDO::FETCH_COLUMN);
$is_winner_losers = !empty($losers);

// Get all members for the club
$stmt = $pdo->prepare("SELECT member_id, nickname FROM members WHERE club_id = ? ORDER BY nickname");
$stmt->execute([$result['club_id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Game Result - <?php echo htmlspecialchars($result['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $result['club_id'], $result['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Edit Game Result', htmlspecialchars($result['game_name'])); ?>
    </div>
    
    <style>
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: var(--spacing-3);
        max-height: 300px;
        overflow-y: auto;
        padding: var(--spacing-3);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface-muted);
    }
    
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: var(--spacing-2);
        padding: var(--spacing-2);
        border-radius: var(--radius-sm);
        transition: background-color var(--transition-fast);
    }
    
    .checkbox-item:hover {
        background-color: var(--color-surface);
    }

    .checkbox-item input:disabled + label {
        color: var(--color-text-muted);
        text-decoration: line-through;
        cursor: not-allowed;
    }
    </style>
    
    <div class="container">
        <?php display_session_message('error'); ?>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group">
                <label for="played_at">Date Played:</label>
                <input type="date" id="played_at" name="played_at" class="form-control" value="<?php echo date('Y-m-d', strtotime($result['played_at'])); ?>" required>
            </div>

            <div class="form-group">
                <label for="duration">Duration (minutes):</label>
                <input type="number" id="duration" name="duration" class="form-control" value="<?php echo $result['duration']; ?>" required min="1">
            </div>

            <div class="form-group">
                <label for="notes">Notes:</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($result['notes']); ?></textarea>
            </div>

            <input type="hidden" name="game_type" value="<?php echo $is_winner_losers ? 'winner_losers' : 'ranked'; ?>">

            <div class="form-group">
                <label for="member_id">Winner:</label>
                <select id="member_id" name="member_id" class="form-control" required>
                    <option value="">Select Winner</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?php echo $member['member_id']; ?>" <?php echo $result['member_id'] == $member['member_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['nickname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($is_winner_losers): ?>
                <!-- Winner + Losers format -->
                <div id="losers-section">
                    <label class="form-label">Select Losers:</label>
                    <div id="losers-checkbox-list" class="checkbox-grid">
                        <?php foreach ($members as $member): ?>
                            <div class="form-check checkbox-item">
                                <input type="checkbox" name="losers[]" id="loser_<?php echo $member['member_id']; ?>" 
                                       value="<?php echo $member['member_id']; ?>" 
                                       class="form-check-input loser-checkbox"
                                       <?php echo in_array($member['member_id'], $losers) ? 'checked' : ''; ?>>
                                <label for="loser_<?php echo $member['member_id']; ?>" class="form-check-label">
                                    <?php echo htmlspecialchars($member['nickname']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text mt-2">Select all members who lost this game. The winner cannot be selected as a loser.</div>
                </div>
            <?php else: ?>
                <!-- Ranked format -->
                <?php
                function getOrdinalSuffix($i) {
                    $j = $i % 10;
                    $k = $i % 100;
                    if ($j == 1 && $k != 11) return 'st';
                    if ($j == 2 && $k != 12) return 'nd';
                    if ($j == 3 && $k != 13) return 'rd';
                    return 'th';
                }
                ?>
                <?php for ($i = 2; $i <= 8; $i++): ?>
                    <div class="form-group">
                        <label for="place_<?php echo $i; ?>"><?php echo $i . getOrdinalSuffix($i); ?> Place:</label>
                        <select id="place_<?php echo $i; ?>" name="place_<?php echo $i; ?>" class="form-control">
                            <option value="">Select Player</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>" <?php echo $result['place_' . $i] == $member['member_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['nickname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endfor; ?>
            <?php endif; ?>

            <div class="form-group">
                <button type="submit" class="btn">Update Result</button>
                <a href="view_result.php?result_id=<?php echo $result_id; ?>" class="btn btn--secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php if ($is_winner_losers): ?>
    <script>
        // Update disabled options across all dropdowns to prevent duplicates
        function updateDisabledOptions() {
            const winnerId = document.getElementById('member_id').value;
            
            // Get all selected values in ranked section (if they exist)
            const selectedRankedIds = [winnerId];
            for (let i = 2; i <= 8; i++) {
                const el = document.getElementById('place_' + i);
                if (el && el.value) selectedRankedIds.push(el.value);
            }

            // Update ranked dropdowns
            for (let i = 2; i <= 8; i++) {
                const el = document.getElementById('place_' + i);
                if (el) {
                    Array.from(el.options).forEach(option => {
                        if (option.value) {
                            option.disabled = selectedRankedIds.includes(option.value) && option.value !== el.value;
                        }
                    });
                }
            }
            
            const winnerSelect = document.getElementById('member_id');
            if (winnerSelect) {
                Array.from(winnerSelect.options).forEach(option => {
                    if (option.value) {
                        option.disabled = selectedRankedIds.includes(option.value) && option.value !== winnerSelect.value;
                    }
                });
            }

            // Update loser checkboxes
            const loserCheckboxes = document.querySelectorAll('.loser-checkbox');
            loserCheckboxes.forEach(checkbox => {
                if (winnerId && checkbox.value === winnerId) {
                    checkbox.disabled = true;
                    checkbox.checked = false;
                } else {
                    checkbox.disabled = false;
                }
            });
        }

        // Set up winner dropdown change listener
        document.getElementById('member_id').addEventListener('change', updateDisabledOptions);

        // Set up ranked dropdown listeners
        for (let i = 2; i <= 8; i++) {
            const el = document.getElementById('place_' + i);
            if (el) el.addEventListener('change', updateDisabledOptions);
        }

        // Initialize disabled state on page load
        updateDisabledOptions();
    </script>
    <?php endif; ?>

    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>