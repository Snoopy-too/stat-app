<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$pageTitle = 'Dashboard - Board Game Club StatApp';
require_once 'includes/templates/header.php';
?>

    <div class="header">
        <h1>Board Game Club StatApp</h1>
        <div class="welcome-text">
            Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?>!<br>
            Club: <?php echo htmlspecialchars($_SESSION['club_name'] ?? ''); ?>
        </div>
    </div>
    <div class="menu-container">
        <a href="history.php" class="menu-button">Add/Edit History</a>
        <a href="games.php" class="menu-button">Add/Edit Game List</a>
        <a href="members.php" class="menu-button">Add/Edit Members</a>
        <a href="teams.php" class="menu-button">Add/Edit Teams</a>
        <a href="champions.php" class="menu-button">Manage Champions</a>
        <a href="logout.php" class="logout-link">Logout</a>
    </div>

<?php require_once 'includes/templates/footer.php'; ?>