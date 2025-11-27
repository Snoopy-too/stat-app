<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';

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
        header("Location: edit_team_result.php?result_id=" . $result_id);
        exit();
    }

    try {
        // Validate and sanitize input
        $played_at = $_POST['played_at'];
        $duration = (int)$_POST['duration'];
        $notes = trim($_POST['notes']);
        $winner = (int)$_POST['winner'];
        $place_2 = !empty($_POST['place_2']) ? (int)$_POST['place_2'] : null;
        $place_3 = !empty($_POST['place_3']) ? (int)$_POST['place_3'] : null;
        $place_4 = !empty($_POST['place_4']) ? (int)$_POST['place_4'] : null;

        // Check for duplicate teams
        $team_positions = array_filter([$winner, $place_2, $place_3, $place_4]);
        if (count($team_positions) !== count(array_unique($team_positions))) {
            $_SESSION['error'] = "A team cannot occupy more than one position.";
            header("Location: edit_team_result.php?result_id=" . $result_id);
            exit();
        }

        // Update the team game result
        $stmt = $pdo->prepare("
            UPDATE team_game_results 
            SET played_at = ?, duration = ?, notes = ?,
                winner = ?, place_2 = ?, place_3 = ?, place_4 = ?
            WHERE result_id = ?");

        $stmt->execute([
            $played_at, $duration, $notes,
            $winner, $place_2, $place_3, $place_4,
            $result_id
        ]);

        $_SESSION['success'] = "Team game result updated successfully.";
        header("Location: view_team_result.php?result_id=" . $result_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating team game result: " . $e->getMessage();
    }
}

// Get team game result details with game and club information
$stmt = $pdo->prepare("
    SELECT tgr.*, g.game_name, g.club_id, c.club_name 
    FROM team_game_results tgr
    JOIN games g ON tgr.game_id = g.game_id
    JOIN clubs c ON g.club_id = c.club_id
    WHERE tgr.result_id = ?");
$stmt->execute([$result_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error'] = "Result not found.";
    header("Location: dashboard.php");
    exit();
}

// Get all teams for the club
$stmt = $pdo->prepare("SELECT team_id, team_name FROM teams WHERE club_id = ? ORDER BY team_name");
$stmt->execute([$result['club_id']]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Team Game Result - <?php echo htmlspecialchars($result['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Edit Team Game Result - <?php echo htmlspecialchars($result['game_name']); ?></h1>
        <a href="view_team_result.php?result_id=<?php echo $result_id; ?>" class="btn">Back to Result</a>
    </div>
    
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

            <div class="form-group">
                <label for="winner">Winner (1st Place):</label>
                <select id="winner" name="winner" class="form-control" required>
                    <option value="">Select Winner Team</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?php echo $team['team_id']; ?>" <?php echo $result['winner'] == $team['team_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($team['team_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php 
            $places = [
                2 => '2nd',
                3 => '3rd',
                4 => '4th'
            ];
            
            foreach ($places as $num => $label): ?>
                <div class="form-group">
                    <label for="place_<?php echo $num; ?>"><?php echo $label; ?> Place:</label>
                    <select id="place_<?php echo $num; ?>" name="place_<?php echo $num; ?>" class="form-control">
                        <option value="">Select Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['team_id']; ?>" <?php echo $result['place_' . $num] == $team['team_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($team['team_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <div class="form-group">
                <button type="submit" class="btn">Update Result</button>
            </div>
        </form>
    </div>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>