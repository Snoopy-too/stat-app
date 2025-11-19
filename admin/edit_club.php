<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get club details
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $club_name = trim($_POST['club_name']);
    $description = trim($_POST['description']);
    $meeting_day = $_POST['meeting_day'];
    $meeting_time = $_POST['meeting_time'];
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE clubs 
            SET club_name = ?, description = ?, meeting_day = ?, 
                meeting_time = ?, location = ?, status = ?
            WHERE club_id = ?
        ");
        $stmt->execute([
            $club_name, $description, $meeting_day, 
            $meeting_time, $location, $status, $club_id
        ]);
        
        $_SESSION['success'] = "Club updated successfully!";
        header("Location: view_club.php?id=" . $club_id);
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update club. Please try again.";
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$statuses = ['active', 'suspended', 'inactive'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Club - Super Admin</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Edit Club</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($club['club_name']); ?></p>
        </div>
        <a href="view_club.php?id=<?php echo $club_id; ?>" class="btn btn--secondary">Cancel</a>
    </div>

    <div class="container container--narrow">
        <div class="card">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form method="POST" class="stack">
                <div class="form-group">
                    <label for="club_name">Club Name:</label>
                    <input type="text" id="club_name" name="club_name" class="form-control" required
                           value="<?php echo htmlspecialchars($club['club_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($club['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="meeting_day">Meeting Day:</label>
                    <select id="meeting_day" name="meeting_day" class="form-control">
                        <?php foreach ($days as $day): ?>
                            <option value="<?php echo $day; ?>"
                                <?php echo ($day == $club['meeting_day']) ? 'selected' : ''; ?>>
                                <?php echo $day; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="meeting_time">Meeting Time:</label>
                    <input type="time" id="meeting_time" name="meeting_time" class="form-control"
                           value="<?php echo htmlspecialchars($club['meeting_time']); ?>">
                </div>
                <div class="form-group">
                    <label for="location">Meeting Location:</label>
                    <input type="text" id="location" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($club['location']); ?>">
                </div>
                <div class="form-group">
                    <label for="status">Club Status:</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>"
                                <?php echo ($status == $club['status']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Save Changes</button>
                    <a href="view_club.php?id=<?php echo $club_id; ?>" class="btn btn--subtle">Cancel</a>
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
