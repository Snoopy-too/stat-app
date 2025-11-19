<?php
session_start();
require_once '../config/database.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : null;

if (!$result_id) {
    $_SESSION['error'] = "Invalid result ID provided.";
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $played_at = $_POST['played_at'];
        $duration = (int)$_POST['duration'];
        $notes = trim($_POST['notes']);
        $member_id = (int)$_POST['member_id'];
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

        $_SESSION['success'] = "Game result updated successfully.";
        header("Location: view_result.php?result_id=" . $result_id);
        exit();
    } catch (PDOException $e) {
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

// Get all members for the club
$stmt = $pdo->prepare("SELECT member_id, nickname FROM members WHERE club_id = ? ORDER BY nickname");
$stmt->execute([$result['club_id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Game Result - <?php echo htmlspecialchars($result['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Edit Game Result - <?php echo htmlspecialchars($result['game_name']); ?></h1>
        <a href="view_result.php?result_id=<?php echo $result_id; ?>" class="btn">Back to Result</a>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="POST" class="form-card">
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
                <label for="member_id">Winner (1st Place):</label>
                <select id="member_id" name="member_id" class="form-control" required>
                    <option value="">Select Winner</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?php echo $member['member_id']; ?>" <?php echo $result['member_id'] == $member['member_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['nickname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php for ($i = 2; $i <= 8; $i++): ?>
                <div class="form-group">
                    <label for="place_<?php echo $i; ?>"><?php echo $i; ?>nd Place:</label>
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

            <div class="form-group">
                <button type="submit" class="btn">Update Result</button>
            </div>
        </form>
    </div>
</body>
</html>