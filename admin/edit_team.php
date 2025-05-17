<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Get team info
$stmt = $pdo->prepare("SELECT * FROM teams WHERE team_id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    header("Location: club_teams.php?club_id=" . $club_id);
    exit();
}

// Fetch all members of the club
$stmt = $pdo->prepare("SELECT * FROM members WHERE club_id = ? ORDER BY member_name");
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_team'])) {
    $team_name = trim($_POST['team_name']);
    $member1 = isset($_POST['member1']) ? (int)$_POST['member1'] : null;
    $member2 = isset($_POST['member2']) ? (int)$_POST['member2'] : null;
    $member3 = isset($_POST['member3']) ? (int)$_POST['member3'] : null;
    $member4 = isset($_POST['member4']) ? (int)$_POST['member4'] : null;

    if (!empty($team_name) && $member1) {
        try {
            $pdo->beginTransaction();
            
            // Convert empty member selections to NULL
            $member2 = $member2 ?: null;
            $member3 = $member3 ?: null;
            $member4 = $member4 ?: null;
            
            // Verify all selected members belong to the same club
            $memberIds = array_filter([$member1, $member2, $member3, $member4]);
            if (!empty($memberIds)) {
                $placeholders = str_repeat('?,', count($memberIds) - 1) . '?';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE member_id IN ($placeholders) AND club_id = ?");
                $stmt->execute([...array_values($memberIds), $club_id]);
                $validMembers = $stmt->fetchColumn();
                
                if ($validMembers !== count($memberIds)) {
                    throw new PDOException('Invalid member selection. All members must belong to the same club.');
                }
            }
            
            $sql = "UPDATE teams SET team_name = ?, member1_id = ?, member2_id = ?, member3_id = ?, member4_id = ? WHERE team_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$team_name, $member1, $member2, $member3, $member4, $team_id]);
            
            $pdo->commit();
            $_SESSION['success'] = "Team updated successfully!";
            header("Location: club_teams.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error updating team: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Team name and at least one member are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Team</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit Team</h1>
            <a href="club_teams.php?club_id=<?php echo $club_id; ?>" class="button">Back to Teams</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                echo htmlspecialchars($_SESSION['error']); 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" class="form">
                <div class="form-group">
                    <label for="team_name">Team Name:</label>
                    <input type="text" id="team_name" name="team_name" value="<?php echo htmlspecialchars($team['team_name']); ?>" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="member1">Member 1 (Required):</label>
                    <select id="member1" name="member1" required class="form-control">
                        <option value="">Select Member</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $team['member1_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['member_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="member2">Member 2:</label>
                    <select id="member2" name="member2" class="form-control">
                        <option value="">Select Member</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $team['member2_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['member_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="member3">Member 3:</label>
                    <select id="member3" name="member3" class="form-control">
                        <option value="">Select Member</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $team['member3_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['member_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="member4">Member 4:</label>
                    <select id="member4" name="member4">
                        <option value="">Select Member</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $team['member4_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['member_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="update_team" class="button">Update Team</button>
            </form>
        </div>
    </div>
</body>
</html>