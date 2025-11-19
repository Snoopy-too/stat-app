<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

// Fetch existing members for this club
$stmt = $pdo->prepare("SELECT * FROM members WHERE club_id = ? ORDER BY full_name");
$stmt->execute([$_SESSION['club_id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Member Management</h1>
        <h2><?php echo htmlspecialchars($_SESSION['club_name']); ?></h2>
    </div>

    <div class="container">
        <div class="member-list">
            <?php foreach ($members as $member): ?>
            <div class="member-item">
                <div class="member-info">
                    <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                    <?php if (!empty($member['username'])): ?>
                        <br><small><?php echo htmlspecialchars($member['username']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="member-actions">
                    <a href="edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn">Edit</a>
                    <a href="view_stats.php?id=<?php echo $member['member_id']; ?>" class="btn">Stats</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="add_member.php" class="btn">Add New Member</a>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>