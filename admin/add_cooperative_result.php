<?php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

// Ensure cooperative tables exist
try {
    $pdo->query("SELECT 1 FROM cooperative_game_results LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cooperative_game_results (
            result_id INT AUTO_INCREMENT PRIMARY KEY,
            game_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            outcome ENUM('win','loss') NOT NULL,
            score INT DEFAULT NULL,
            difficulty VARCHAR(100) DEFAULT NULL,
            scenario VARCHAR(255) DEFAULT NULL,
            num_participants INT DEFAULT NULL,
            played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            duration INT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cooperative_result_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            result_id INT NOT NULL,
            participant_type ENUM('member','team') NOT NULL,
            member_id INT DEFAULT NULL,
            team_id INT DEFAULT NULL,
            FOREIGN KEY (result_id) REFERENCES cooperative_game_results(result_id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
            FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (PDOException $e2) {
        // Ignore error if table creation fails
    }
}

// Clear any existing success messages
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}

// Ensure user is logged in and has appropriate admin access
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header('Location: login.php');
    exit();
}

$security = new SecurityUtils($pdo);
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;

// Fetch game details
$stmt = $pdo->prepare('SELECT * FROM games WHERE game_id = ? AND club_id = ?');
$stmt->execute([$game_id, $club_id]);
$game = $stmt->fetch();

if (!$game) {
    header('Location: manage_games.php');
    exit();
}

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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: add_cooperative_result.php?club_id=" . $club_id . "&game_id=" . $game_id);
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
    $participant_type = $_POST['participant_type'] ?? 'members';
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
    } elseif ($participant_type === 'members' && empty($participants)) {
        $error = 'Please select at least one participant.';
    } elseif ($participant_type === 'team' && empty($team_id)) {
        $error = 'Please select a team.';
    } else {
        // Check for duplicate participants if using members
        if ($participant_type === 'members' && count($participants) !== count(array_unique($participants))) {
            $error = 'Duplicate participants selected. Each member can only be added once.';
        }
    }

    // Proceed only if there are no errors
    if ($error === null) {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();

            // Generate a unique session ID for this game result
            $session_id = uniqid('coop_', true);

            // Calculate number of participants
            if ($participant_type === 'members') {
                $num_participants = count($participants);
            } else {
                // Count team members
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

            // Insert cooperative result
            $stmt = $pdo->prepare('INSERT INTO cooperative_game_results (game_id, session_id, outcome, score, difficulty, scenario, num_participants, played_at, duration, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $game_id,
                $session_id,
                $outcome,
                $score,
                $difficulty,
                $scenario,
                $num_participants,
                $formatted_played_at,
                $duration,
                $notes
            ]);

            $result_id = $pdo->lastInsertId();

            // Insert participants
            $participant_stmt = $pdo->prepare('INSERT INTO cooperative_result_participants (result_id, participant_type, member_id, team_id) VALUES (?, ?, ?, ?)');

            if ($participant_type === 'members') {
                foreach ($participants as $member_id) {
                    $participant_stmt->execute([$result_id, 'member', $member_id, null]);
                }
            } else {
                $participant_stmt->execute([$result_id, 'team', null, $team_id]);
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Cooperative game result has been successfully saved.';
            header('Location: results.php?game_id=' . $game_id . '&club_id=' . $club_id . '&success=1');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error saving result: ' . $e->getMessage();
        }
    }
}

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Cooperative Result - <?php echo htmlspecialchars($game['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Add Cooperative Result', htmlspecialchars($game['game_name'])); ?>
    </div>
    <div class="container">
        <div class="card">
            <?php if (isset($error)): ?>
                <div class="message message--error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="message message--success"><?php echo $_SESSION['success_message']; ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="played_at">Date Played:</label>
                    <input type="datetime-local" id="played_at" name="played_at" required class="form-control">
                </div>

                <div class="form-group">
                    <label>Outcome:</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="outcome" value="win" required> Win
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="outcome" value="loss"> Loss
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="score">Score (optional):</label>
                    <input type="number" id="score" name="score" min="0" class="form-control" placeholder="Enter score if applicable">
                </div>

                <div class="form-group">
                    <label for="difficulty">Difficulty (optional):</label>
                    <select id="difficulty" name="difficulty" class="form-control" onchange="toggleCustomDifficulty()">
                        <option value="">-- Select Difficulty --</option>
                        <option value="Easy">Easy</option>
                        <option value="Normal">Normal</option>
                        <option value="Hard">Hard</option>
                        <option value="Heroic">Heroic</option>
                        <option value="Expert">Expert</option>
                        <option value="Nightmare">Nightmare</option>
                        <option value="custom">Custom...</option>
                    </select>
                </div>

                <div class="form-group" id="custom-difficulty-group" style="display: none;">
                    <label for="custom_difficulty">Custom Difficulty:</label>
                    <input type="text" id="custom_difficulty" name="custom_difficulty" class="form-control" placeholder="Enter custom difficulty">
                </div>

                <div class="form-group">
                    <label for="scenario">Scenario/Mission (optional):</label>
                    <input type="text" id="scenario" name="scenario" class="form-control" placeholder="Enter scenario or mission name">
                </div>

                <div class="form-group">
                    <label>Participant Type:</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="participant_type" value="members" checked onchange="toggleParticipantType()"> Individual Members
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="participant_type" value="team" onchange="toggleParticipantType()"> Team
                        </label>
                    </div>
                </div>

                <div id="members-section">
                    <label>Participants:</label>
                    <div id="participants-container"></div>
                    <div class="form-group">
                        <button type="button" id="add-participant" class="btn">Add Participant</button>
                    </div>
                </div>

                <div id="team-section" style="display: none;">
                    <div class="form-group">
                        <label for="team_id">Team:</label>
                        <select id="team_id" name="team_id" class="form-control">
                            <option value="">Select Team</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>">
                                    <?php echo htmlspecialchars($team['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="duration">Duration (minutes):</label>
                    <input type="number" id="duration" name="duration" min="1" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Save Result</button>
                    <a href="results.php?game_id=<?php echo $game_id; ?>&club_id=<?php echo $club_id; ?>"
                       class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/script.js"></script>

    <script>
    // Member options for dynamic dropdowns
    const memberOptions = <?php echo json_encode($members); ?>;

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
            // Add first participant if empty
            if (document.getElementById('participants-container').children.length === 0) {
                addParticipantField();
            }
        } else {
            membersSection.style.display = 'none';
            teamSection.style.display = 'block';
            // Clear participants
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

    function addParticipantField() {
        const container = document.getElementById('participants-container');
        const participantDiv = document.createElement('div');
        participantDiv.className = 'form-group';
        participantDiv.innerHTML = `
            <div class="cluster items-start gap-md">
                <div class="w-100">
                    <select name="participants[]" class="form-control" required>
                        <option value="">Select Participant</option>
                        ${memberOptions.map(m => `<option value="${m.id}">${m.name}</option>`).join('')}
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

    document.getElementById('add-participant').addEventListener('click', addParticipantField);

    // Set default date to user's current local time
    const now = new Date();
    const timezoneOffset = now.getTimezoneOffset();
    now.setMinutes(now.getMinutes() - timezoneOffset);
    const localDateTime = now.toISOString().slice(0, 16);
    document.getElementById('played_at').value = localDateTime;

    // Add first participant field on load
    addParticipantField();
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
