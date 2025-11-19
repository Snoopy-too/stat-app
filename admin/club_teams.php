<?php
session_start();
require_once '../config/database.php';

// Ensure user is logged in and has appropriate admin access
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// Fetch club info
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_team'])) {
    $team_name = trim($_POST['team_name']);
    $member1 = isset($_POST['member1']) ? (int)$_POST['member1'] : null;
    $member2 = isset($_POST['member2']) ? (int)$_POST['member2'] : null;
    $member3 = isset($_POST['member3']) ? (int)$_POST['member3'] : null;
    $member4 = isset($_POST['member4']) ? (int)$_POST['member4'] : null;

    if (!empty($team_name) && $member1) {
        try {
            $pdo->beginTransaction();
            
            // Build dynamic SQL query based on selected members
            $fields = ['team_name', 'club_id', 'member1_id', 'member2_id', 'member3_id', 'member4_id'];
            $values = ['?', '?', '?', '?', '?', '?'];
            $params = [
                $team_name,
                $club_id,
                $member1 ?: null,
                $member2 ?: null,
                $member3 ?: null,
                $member4 ?: null
            ];
            
            $sql = "INSERT INTO teams (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $pdo->commit();
            $_SESSION['success'] = "Team created successfully!";
            header("Location: club_teams.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error creating team: " . $e->getMessage();
            header("Location: club_teams.php?club_id=" . $club_id);
            exit();
        }
    } else {
        $_SESSION['error'] = "Team name and at least one member are required.";
        header("Location: club_teams.php?club_id=" . $club_id);
        exit();
    }
}

// Fetch all members of the club
$stmt = $pdo->prepare("SELECT * FROM members WHERE club_id = ? ORDER BY member_name");
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing teams
$stmt = $pdo->prepare("
    SELECT t.*, 
           m1.member_name as member1_name,
           m2.member_name as member2_name,
           m3.member_name as member3_name,
           m4.member_name as member4_name
    FROM teams t
    LEFT JOIN members m1 ON t.member1_id = m1.member_id
    LEFT JOIN members m2 ON t.member2_id = m2.member_id
    LEFT JOIN members m3 ON t.member3_id = m3.member_id
    LEFT JOIN members m4 ON t.member4_id = m4.member_id
    WHERE m1.club_id = ?
    ORDER BY t.created_at DESC");
$stmt->execute([$club_id]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/team-validation.js"></script>
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Teams</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($club['club_name']); ?></p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn--secondary btn--small">Back to Dashboard</a>
        </div>
    </div>

    <div class="container container--wide">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message--success">
                <?php 
                echo htmlspecialchars($_SESSION['success']); 
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message--error">
                <?php 
                echo htmlspecialchars($_SESSION['error']); 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2>Create New Team</h2>
                    <p class="card-subtitle card-subtitle--muted">Build a team roster using members of this club.</p>
                </div>
            </div>
            <form method="POST" class="stack">
                <div class="grid grid--columns-2">
                    <div class="form-group">
                        <label for="team_name">Team Name</label>
                        <input type="text" id="team_name" name="team_name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="member1">Member 1 (Required)</label>
                        <select id="member1" name="member1" required class="form-control">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>">
                                    <?php echo htmlspecialchars($member['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member2">Member 2</label>
                        <select id="member2" name="member2" class="form-control">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>">
                                    <?php echo htmlspecialchars($member['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member3">Member 3</label>
                        <select id="member3" name="member3" class="form-control">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>">
                                    <?php echo htmlspecialchars($member['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member4">Member 4</label>
                        <select id="member4" name="member4" class="form-control">
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>">
                                    <?php echo htmlspecialchars($member['member_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="create_team" class="btn">Create Team</button>
                </div>
            </form>
        </div>
        <script>
        // Disable already-selected members in all dropdowns
        function updateMemberDropdowns() {
            const selects = [
                document.getElementById('member1'),
                document.getElementById('member2'),
                document.getElementById('member3'),
                document.getElementById('member4')
            ];
            // Gather selected values
            const selected = selects.map(sel => sel.value).filter(v => v !== '');
            selects.forEach(sel => {
                Array.from(sel.options).forEach(opt => {
                    if (opt.value === '') {
                        opt.disabled = false;
                    } else {
                        // Only disable if selected elsewhere and not selected in this dropdown
                        opt.disabled = selected.includes(opt.value) && sel.value !== opt.value;
                    }
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            const selects = [
                document.getElementById('member1'),
                document.getElementById('member2'),
                document.getElementById('member3'),
                document.getElementById('member4')
            ];
            selects.forEach(sel => {
                sel.addEventListener('change', updateMemberDropdowns);
            });
            updateMemberDropdowns();
        });
        </script>

        <div class="card">
            <div class="card-header card-header--stack">
                <div>
                    <h2>Existing Teams</h2>
                    <p class="card-subtitle card-subtitle--muted"><?php echo count($teams); ?> team<?php echo count($teams) === 1 ? '' : 's'; ?> on record.</p>
                </div>
            </div>
            <?php if (empty($teams)): ?>
                <p>No teams created yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Team Name</th>
                            <th>Members</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                            <tr>
                                <td data-label="Team Name"><?php echo htmlspecialchars($team['team_name']); ?></td>
                                <td data-label="Members">
                                    <ul class="member-list">
                                        <?php if ($team['member1_name']): ?>
                                            <li><?php echo htmlspecialchars($team['member1_name']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($team['member2_name']): ?>
                                            <li><?php echo htmlspecialchars($team['member2_name']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($team['member3_name']): ?>
                                            <li><?php echo htmlspecialchars($team['member3_name']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($team['member4_name']): ?>
                                            <li><?php echo htmlspecialchars($team['member4_name']); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </td>
                                <td data-label="Created"><?php echo date('M j, Y', strtotime($team['created_at'])); ?></td>
                                <td data-label="Actions" class="action-buttons">
                                    <div class="btn-group">
                                        <a href="edit_team.php?team_id=<?php echo $team['team_id']; ?>&club_id=<?php echo $club_id; ?>" class="btn btn--subtle btn--xsmall btn--pill">Edit</a>
                                        <a href="delete_team.php?team_id=<?php echo $team['team_id']; ?>&club_id=<?php echo $club_id; ?>" class="btn btn--danger btn--xsmall btn--pill" onclick="return confirm('Are you sure you want to delete this team?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
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
