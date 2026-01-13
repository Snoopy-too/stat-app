<?php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

// Ensure user is logged in and has appropriate admin access
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header('Location: login.php');
    exit();
}

$security = new SecurityUtils($pdo);
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : 0;

if (!$result_id) {
    $_SESSION['error'] = "Invalid result ID provided.";
    header("Location: dashboard.php");
    exit();
}

// Fetch the cooperative result
$stmt = $pdo->prepare("
    SELECT cgr.*, g.game_name, g.club_id, g.game_id
    FROM cooperative_game_results cgr
    JOIN games g ON cgr.game_id = g.game_id
    WHERE cgr.result_id = ?");
$stmt->execute([$result_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error'] = "Cooperative result not found.";
    header("Location: dashboard.php");
    exit();
}

$club_id = $result['club_id'];
$game_id = $result['game_id'];

// Fetch active members for the dropdown
$stmt = $pdo->prepare('SELECT m.member_id as id, m.nickname as name
    FROM members m
    WHERE m.club_id = ? AND m.status = "active"
    ORDER BY m.nickname ASC');
$stmt->execute([$club_id]);
$members = $stmt->fetchAll();

// Fetch teams for the dropdown
$stmt = $pdo->prepare('SELECT t.team_id as id, t.team_name as name
    FROM teams t
    WHERE t.club_id = ?
    ORDER BY t.team_name ASC');
$stmt->execute([$club_id]);
$teams = $stmt->fetchAll();

// Fetch current participants
$participant_stmt = $pdo->prepare("SELECT * FROM cooperative_result_participants WHERE result_id = ?");
$participant_stmt->execute([$result_id]);
$current_participants = $participant_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine participant type from existing data
$participant_type = 'members';
$selected_team_id = null;
$selected_member_ids = [];

if (!empty($current_participants)) {
    if ($current_participants[0]['participant_type'] === 'team') {
        $participant_type = 'team';
        $selected_team_id = $current_participants[0]['team_id'];
    } else {
        foreach ($current_participants as $p) {
            $selected_member_ids[] = $p['member_id'];
        }
    }
}

// Check if difficulty is custom (not in predefined list)
$predefined_difficulties = ['Easy', 'Normal', 'Hard', 'Heroic', 'Expert', 'Nightmare'];
$is_custom_difficulty = $result['difficulty'] && !in_array($result['difficulty'], $predefined_difficulties);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: edit_cooperative_result.php?result_id=" . $result_id);
        exit();
    }

    $error = null;
    $outcome = $_POST['outcome'] ?? null;
    $score = !empty($_POST['score']) ? (int)$_POST['score'] : null;
    $difficulty = !empty($_POST['difficulty']) ? $_POST['difficulty'] : null;
    $custom_difficulty = !empty($_POST['custom_difficulty']) ? $_POST['custom_difficulty'] : null;
    $scenario = !empty($_POST['scenario']) ? $_POST['scenario'] : null;
    $duration = $_POST['duration'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $played_at = $_POST['played_at'] ?? null;
    $new_participant_type = $_POST['participant_type'] ?? 'members';
    $participants = isset($_POST['participants']) ? array_filter($_POST['participants']) : [];
    $team_id = $_POST['team_id'] ?? null;

    // Use custom difficulty if selected
    if ($difficulty === 'custom' && $custom_difficulty) {
        $difficulty = $custom_difficulty;
    } elseif ($difficulty === 'custom') {
        $difficulty = null;
    }

    // Validation
    if (empty($outcome)) {
        $error = 'Please select an outcome (Win or Loss).';
    } elseif (empty($duration)) {
        $error = 'Please enter the duration of the game.';
    } elseif ($new_participant_type === 'members' && empty($participants)) {
        $error = 'Please select at least one participant.';
    } elseif ($new_participant_type === 'team' && empty($team_id)) {
        $error = 'Please select a team.';
    } else {
        if ($new_participant_type === 'members' && count($participants) !== count(array_unique($participants))) {
            $error = 'Duplicate participants selected. Each member can only be added once.';
        }
    }

    if ($error === null) {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();

            // Calculate number of participants
            if ($new_participant_type === 'members') {
                $num_participants = count($participants);
            } else {
                $team_stmt = $pdo->prepare('SELECT member1_id, member2_id, member3_id, member4_id FROM teams WHERE team_id = ?');
                $team_stmt->execute([$team_id]);
                $team_data = $team_stmt->fetch();
                $num_participants = 0;
                if ($team_data) {
                    for ($i = 1; $i <= 4; $i++) {
                        if (!empty($team_data["member{$i}_id"])) {
                            $num_participants++;
                        }
                    }
                }
            }

            // Fix date format
            $formatted_played_at = str_replace('T', ' ', $played_at);
            if (strlen($formatted_played_at) == 16) $formatted_played_at .= ':00';

            // Update cooperative result
            $stmt = $pdo->prepare('UPDATE cooperative_game_results SET outcome = ?, score = ?, difficulty = ?, scenario = ?, num_participants = ?, played_at = ?, duration = ?, notes = ? WHERE result_id = ?');
            $stmt->execute([
                $outcome,
                $score,
                $difficulty,
                $scenario,
                $num_participants,
                $formatted_played_at,
                $duration,
                $notes,
                $result_id
            ]);

            // Delete existing participants
            $delete_stmt = $pdo->prepare('DELETE FROM cooperative_result_participants WHERE result_id = ?');
            $delete_stmt->execute([$result_id]);

            // Insert new participants
            $participant_stmt = $pdo->prepare('INSERT INTO cooperative_result_participants (result_id, participant_type, member_id, team_id) VALUES (?, ?, ?, ?)');

            if ($new_participant_type === 'members') {
                foreach ($participants as $member_id) {
                    $participant_stmt->execute([$result_id, 'member', $member_id, null]);
                }
            } else {
                $participant_stmt->execute([$result_id, 'team', null, $team_id]);
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Cooperative game result has been successfully updated.';
            header('Location: view_cooperative_result.php?result_id=' . $result_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating result: ' . $e->getMessage();
        }
    }
}

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();

// Format played_at for datetime-local input
$played_at_value = date('Y-m-d\TH:i', strtotime($result['played_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Cooperative Result - <?php echo htmlspecialchars($result['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Edit Cooperative Result', htmlspecialchars($result['game_name'])); ?>
    </div>
    <div class="container">
        <div class="card">
            <?php if (isset($error)): ?>
                <div class="message message--error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="played_at">Date Played:</label>
                    <input type="datetime-local" id="played_at" name="played_at" required class="form-control" value="<?php echo $played_at_value; ?>">
                </div>

                <div class="form-group">
                    <label>Outcome:</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="outcome" value="win" required <?php echo $result['outcome'] === 'win' ? 'checked' : ''; ?>> Win
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="outcome" value="loss" <?php echo $result['outcome'] === 'loss' ? 'checked' : ''; ?>> Loss
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="score">Score (optional):</label>
                    <input type="number" id="score" name="score" min="0" class="form-control" value="<?php echo $result['score'] !== null ? htmlspecialchars($result['score']) : ''; ?>" placeholder="Enter score if applicable">
                </div>

                <div class="form-group">
                    <label for="difficulty">Difficulty (optional):</label>
                    <select id="difficulty" name="difficulty" class="form-control" onchange="toggleCustomDifficulty()">
                        <option value="">-- Select Difficulty --</option>
                        <option value="Easy" <?php echo $result['difficulty'] === 'Easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="Normal" <?php echo $result['difficulty'] === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                        <option value="Hard" <?php echo $result['difficulty'] === 'Hard' ? 'selected' : ''; ?>>Hard</option>
                        <option value="Heroic" <?php echo $result['difficulty'] === 'Heroic' ? 'selected' : ''; ?>>Heroic</option>
                        <option value="Expert" <?php echo $result['difficulty'] === 'Expert' ? 'selected' : ''; ?>>Expert</option>
                        <option value="Nightmare" <?php echo $result['difficulty'] === 'Nightmare' ? 'selected' : ''; ?>>Nightmare</option>
                        <option value="custom" <?php echo $is_custom_difficulty ? 'selected' : ''; ?>>Custom...</option>
                    </select>
                </div>

                <div class="form-group" id="custom-difficulty-group" style="display: <?php echo $is_custom_difficulty ? 'block' : 'none'; ?>;">
                    <label for="custom_difficulty">Custom Difficulty:</label>
                    <input type="text" id="custom_difficulty" name="custom_difficulty" class="form-control" value="<?php echo $is_custom_difficulty ? htmlspecialchars($result['difficulty']) : ''; ?>" placeholder="Enter custom difficulty">
                </div>

                <div class="form-group">
                    <label for="scenario">Scenario/Mission (optional):</label>
                    <input type="text" id="scenario" name="scenario" class="form-control" value="<?php echo htmlspecialchars($result['scenario'] ?? ''); ?>" placeholder="Enter scenario or mission name">
                </div>

                <div class="form-group">
                    <label>Participant Type:</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="participant_type" value="members" <?php echo $participant_type === 'members' ? 'checked' : ''; ?> onchange="toggleParticipantType()"> Individual Members
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="participant_type" value="team" <?php echo $participant_type === 'team' ? 'checked' : ''; ?> onchange="toggleParticipantType()"> Team
                        </label>
                    </div>
                </div>

                <div id="members-section" style="display: <?php echo $participant_type === 'members' ? 'block' : 'none'; ?>;">
                    <label>Participants:</label>
                    <div id="participants-container"></div>
                    <div class="form-group">
                        <button type="button" id="add-participant" class="btn">Add Participant</button>
                    </div>
                </div>

                <div id="team-section" style="display: <?php echo $participant_type === 'team' ? 'block' : 'none'; ?>;">
                    <div class="form-group">
                        <label for="team_id">Team:</label>
                        <select id="team_id" name="team_id" class="form-control">
                            <option value="">Select Team</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" <?php echo $selected_team_id == $team['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="duration">Duration (minutes):</label>
                    <input type="number" id="duration" name="duration" min="1" class="form-control" required value="<?php echo htmlspecialchars($result['duration']); ?>">
                </div>

                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($result['notes'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Save Changes</button>
                    <a href="view_cooperative_result.php?result_id=<?php echo $result_id; ?>"
                       class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/script.js"></script>

    <script>
    const memberOptions = <?php echo json_encode($members); ?>;
    const selectedMemberIds = <?php echo json_encode($selected_member_ids); ?>;

    function toggleCustomDifficulty() {
        const difficulty = document.getElementById('difficulty').value;
        const customGroup = document.getElementById('custom-difficulty-group');
        customGroup.style.display = difficulty === 'custom' ? 'block' : 'none';
        if (difficulty !== 'custom') {
            document.getElementById('custom_difficulty').value = '';
        }
    }

    function toggleParticipantType() {
        const participantType = document.querySelector('input[name="participant_type"]:checked').value;
        const membersSection = document.getElementById('members-section');
        const teamSection = document.getElementById('team-section');

        if (participantType === 'members') {
            membersSection.style.display = 'block';
            teamSection.style.display = 'none';
            document.getElementById('team_id').value = '';
            if (document.getElementById('participants-container').children.length === 0) {
                addParticipantField();
            }
        } else {
            membersSection.style.display = 'none';
            teamSection.style.display = 'block';
            document.getElementById('participants-container').innerHTML = '';
        }
        updateDisabledOptions();
    }

    function updateDisabledOptions() {
        const dropdowns = document.querySelectorAll('select[name^="participants"]');
        const selectedValues = Array.from(dropdowns).map(select => select.value).filter(value => value !== '');

        dropdowns.forEach(dropdown => {
            Array.from(dropdown.options).forEach(option => {
                if (option.value === '') return;
                option.disabled = selectedValues.includes(option.value) && option.value !== dropdown.value;
            });
        });
    }

    function addParticipantField(selectedId = null) {
        const container = document.getElementById('participants-container');
        const participantDiv = document.createElement('div');
        participantDiv.className = 'form-group';
        participantDiv.innerHTML = `
            <div class="cluster items-start gap-md">
                <div class="w-100">
                    <select name="participants[]" class="form-control" required>
                        <option value="">Select Participant</option>
                        ${memberOptions.map(m => `<option value="${m.id}" ${selectedId == m.id ? 'selected' : ''}>${m.name}</option>`).join('')}
                    </select>
                </div>
                <button type="button" class="btn btn--secondary remove-participant mt-0">Remove</button>
            </div>
        `;

        container.appendChild(participantDiv);

        participantDiv.querySelector('select').addEventListener('change', updateDisabledOptions);
        updateDisabledOptions();

        participantDiv.querySelector('.remove-participant').addEventListener('click', function() {
            participantDiv.remove();
            updateDisabledOptions();
        });
    }

    document.getElementById('add-participant').addEventListener('click', function() {
        addParticipantField();
    });

    // Initialize with existing participants
    if (selectedMemberIds.length > 0) {
        selectedMemberIds.forEach(id => addParticipantField(id));
    } else if (document.querySelector('input[name="participant_type"]:checked').value === 'members') {
        addParticipantField();
    }
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
