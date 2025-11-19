<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;

// Get game info
$stmt = $pdo->prepare("SELECT g.*, c.club_name FROM games g JOIN clubs c ON g.club_id = c.club_id WHERE g.game_id = ? AND g.club_id = ?");
$stmt->execute([$game_id, $club_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    header("Location: manage_games.php?club_id=" . $club_id);
    exit();
}

// Handle game update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $stmt = $pdo->prepare("UPDATE games SET game_name = ?, min_players = ?, max_players = ? WHERE game_id = ? AND club_id = ?");
        $stmt->execute([
            trim($_POST['game_name']),
            $_POST['min_players'],
            $_POST['max_players'],
            $game_id,
            $club_id
        ]);
        $_SESSION['success'] = "Game updated successfully!";
        header("Location: manage_games.php?club_id=" . $club_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to update game: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Game - <?php echo htmlspecialchars($game['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Edit Game - <?php echo htmlspecialchars($game['game_name']); ?></h1>
        <a href="manage_games.php?club_id=<?php echo $club_id; ?>" class="btn">Back to Games</a>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Edit Game Details</h2>
            <form method="POST" class="form">
                <div class="form-group">
                    <input type="text" name="game_name" placeholder="Game Name" value="<?php echo htmlspecialchars($game['game_name']); ?>" required class="form-control">
                </div>
                <div class="form-group">
                    <select name="min_players" class="form-control" required>
                        <option value="">Min Players</option>
                        <?php for ($i = 1; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($game['min_players'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="number" name="max_players" placeholder="Max Players" value="<?php echo $game['max_players']; ?>" required min="1" class="form-control">
                </div>
                <input type="hidden" name="action" value="update">
                <div class="form-group">
                    <button type="submit" class="btn">Update Game</button>
                </div>
            </form>
        </div>
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