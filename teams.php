<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

// Fetch existing teams for this club
$stmt = $pdo->prepare("
    SELECT t.team_id, t.team_name, 
           GROUP_CONCAT(m.full_name ORDER BY m.full_name ASC SEPARATOR ', ') as members
    FROM teams t
    LEFT JOIN team_members tm ON t.team_id = tm.team_id
    LEFT JOIN members m ON tm.member_id = m.member_id
    WHERE t.club_id = ?
    GROUP BY t.team_id
    ORDER BY t.team_name
");
$stmt->execute([$_SESSION['club_id']]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Team Management</h1>
        <h2><?php echo htmlspecialchars($_SESSION['club_name']); ?></h2>
    </div>

    <div class="container">
        <div class="team-list">
            <?php foreach ($teams as $team): ?>
            <div class="team-item">
                <a href="edit_team.php?id=<?php echo $team['team_id']; ?>" class="btn btn--secondary">Edit</a>
                <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                <div class="team-members">Members: <?php echo htmlspecialchars($team['members']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="add_team.php" class="btn">Add New Team</a>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>