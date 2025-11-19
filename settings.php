<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

// Get current club settings
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
$stmt->execute([$_SESSION['club_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $club_name = trim($_POST['club_name']);
    $description = trim($_POST['description']);
    $meeting_day = $_POST['meeting_day'];
    $meeting_time = $_POST['meeting_time'];
    $location = trim($_POST['location']);
    
    try {
        $stmt = $pdo->prepare("UPDATE clubs 
                              SET club_name = ?, description = ?, meeting_day = ?, 
                                  meeting_time = ?, location = ?
                              WHERE club_id = ?");
        $stmt->execute([$club_name, $description, $meeting_day, $meeting_time, 
                       $location, $_SESSION['club_id']]);
        
        $_SESSION['club_name'] = $club_name;
        $_SESSION['success'] = "Club settings updated successfully!";
        header("Location: settings.php");
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update club settings. Please try again.";
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Settings - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Club Settings</h1>
        <h2><?php echo htmlspecialchars($_SESSION['club_name']); ?></h2>
    </div>

    <div class="container">
        <div class="settings-container">
            <form method="POST">
                <div class="form-group">
                    <label for="club_name">Club Name:</label>
                    <input type="text" id="club_name" name="club_name" required
                           value="<?php echo htmlspecialchars($club['club_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($club['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="meeting_day">Meeting Day:</label>
                    <select id="meeting_day" name="meeting_day">
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
                    <input type="time" id="meeting_time" name="meeting_time"
                           value="<?php echo htmlspecialchars($club['meeting_time']); ?>">
                </div>
                <div class="form-group">
                    <label for="location">Meeting Location:</label>
                    <input type="text" id="location" name="location"
                           value="<?php echo htmlspecialchars($club['location']); ?>">
                </div>
                <button type="submit" class="btn">Save Changes</button>
            </form>

            <div class="danger-zone">
                <h3>Danger Zone</h3>
                <p>Once you delete your club, there is no going back. Please be certain.</p>
                <a href="delete_club.php" class="btn btn--danger"
                   onclick="return confirm('Are you sure you want to delete your club? This action cannot be undone!')">
                    Delete Club
                </a>
            </div>
        </div>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>