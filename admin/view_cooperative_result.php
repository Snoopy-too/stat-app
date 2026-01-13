<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/NavigationHelper.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

// Get result_id from URL
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : null;

if (!$result_id) {
    $_SESSION['error'] = "Invalid result ID provided.";
    header("Location: dashboard.php");
    exit();
}

// Get cooperative result details with game and club information
$stmt = $pdo->prepare("
    SELECT cgr.*, g.game_name, g.club_id, c.club_name
    FROM cooperative_game_results cgr
    JOIN games g ON cgr.game_id = g.game_id
    JOIN clubs c ON g.club_id = c.club_id
    WHERE cgr.result_id = ?");
$stmt->execute([$result_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error'] = "Cooperative result not found.";
    header("Location: dashboard.php");
    exit();
}

// Fetch participants
$participant_stmt = $pdo->prepare("
    SELECT crp.*, m.nickname as member_name, t.team_name
    FROM cooperative_result_participants crp
    LEFT JOIN members m ON crp.member_id = m.member_id
    LEFT JOIN teams t ON crp.team_id = t.team_id
    WHERE crp.result_id = ?
    ORDER BY m.nickname, t.team_name");
$participant_stmt->execute([$result_id]);
$participants = $participant_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team members if participant is a team
$team_members = [];
if (!empty($participants) && $participants[0]['participant_type'] === 'team' && $participants[0]['team_id']) {
    $team_stmt = $pdo->prepare("
        SELECT t.team_id,
               m1.nickname as member1_name,
               m2.nickname as member2_name,
               m3.nickname as member3_name,
               m4.nickname as member4_name
        FROM teams t
        LEFT JOIN members m1 ON t.member1_id = m1.member_id
        LEFT JOIN members m2 ON t.member2_id = m2.member_id
        LEFT JOIN members m3 ON t.member3_id = m3.member_id
        LEFT JOIN members m4 ON t.member4_id = m4.member_id
        WHERE t.team_id = ?");
    $team_stmt->execute([$participants[0]['team_id']]);
    $team_data = $team_stmt->fetch(PDO::FETCH_ASSOC);
    if ($team_data) {
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($team_data["member{$i}_name"])) {
                $team_members[] = $team_data["member{$i}_name"];
            }
        }
    }
}

// Convert duration to hours and minutes
$hours = floor($result['duration'] / 60);
$minutes = $result['duration'] % 60;
$duration = '';
if ($hours > 0) {
    $duration .= $hours . ' hr ';
}
if ($minutes > 0 || $hours == 0) {
    $duration .= $minutes . ' min';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Result Details - <?php echo htmlspecialchars($result['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $result['club_id'], $result['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Cooperative Result Details', htmlspecialchars($result['game_name'])); ?>
        <div class="header-actions">
            <a href="edit_cooperative_result.php?result_id=<?php echo $result_id; ?>" class="btn btn--small">Edit</a>
            <button type="button" class="btn btn--danger btn--small" onclick="confirmDeletion(event, <?php echo $result_id; ?>)">Delete</button>
        </div>
    </div>

    <div class="container">
        <?php display_session_message('error'); ?>
        <?php display_session_message('success'); ?>

        <div class="card">
            <h2>Game Information</h2>
            <p><strong>Club:</strong> <?php echo htmlspecialchars($result['club_name']); ?></p>
            <p><strong>Date Played:</strong> <?php echo date('M j, Y', strtotime($result['played_at'])); ?></p>
            <p><strong>Duration:</strong> <?php echo $duration; ?></p>
            <?php if ($result['notes']): ?>
                <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($result['notes'])); ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Cooperative Result</h2>
            <table class="data-table">
                <tbody>
                    <tr>
                        <td><strong>Outcome</strong></td>
                        <td>
                            <span class="outcome-badge outcome-<?php echo $result['outcome']; ?>">
                                <?php echo strtoupper($result['outcome']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($result['score'] !== null): ?>
                    <tr>
                        <td><strong>Score</strong></td>
                        <td><?php echo htmlspecialchars($result['score']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($result['difficulty']): ?>
                    <tr>
                        <td><strong>Difficulty</strong></td>
                        <td><?php echo htmlspecialchars($result['difficulty']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($result['scenario']): ?>
                    <tr>
                        <td><strong>Scenario/Mission</strong></td>
                        <td><?php echo htmlspecialchars($result['scenario']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Participants</strong></td>
                        <td>
                            <?php if (!empty($participants) && $participants[0]['participant_type'] === 'team'): ?>
                                <strong><?php echo htmlspecialchars($participants[0]['team_name']); ?></strong>
                                <?php if (!empty($team_members)): ?>
                                    <br><span class="text-muted">(<?php echo htmlspecialchars(implode(', ', $team_members)); ?>)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php
                                $member_names = array_map(function($p) {
                                    return htmlspecialchars($p['member_name']);
                                }, $participants);
                                echo implode(', ', $member_names);
                                ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Number of Participants</strong></td>
                        <td><?php echo $result['num_participants']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script>
        function confirmDeletion(event, resultId) {
            showConfirmDialog(event, {
                title: 'Delete Cooperative Result?',
                message: 'Are you sure you want to delete this cooperative game result? This action cannot be undone.',
                confirmText: 'Delete Result',
                type: 'danger',
                onConfirm: () => {
                    window.location.href = 'delete_cooperative_result.php?result_id=' + resultId;
                }
            });
        }
    </script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
