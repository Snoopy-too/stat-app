<?php
declare(strict_types=1);
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

// Ensure game_result_losers and cooperative tables exist
try {
    $pdo->query("SELECT 1 FROM game_result_losers LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS game_result_losers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            result_id INT NOT NULL,
            member_id INT NOT NULL,
            FOREIGN KEY (result_id) REFERENCES game_results(result_id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (PDOException $e2) {}
}

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
    } catch (PDOException $e2) {}
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

$club_name = NavigationHelper::getClubName($pdo, $club_id);

// Fetch active members for the dropdown
$stmt = $pdo->prepare('SELECT m.member_id as id, m.nickname as name
    FROM members m
    WHERE m.club_id = ? AND m.status = "active"
    ORDER BY m.nickname ASC');
$stmt->execute([$club_id]);
$members = $stmt->fetchAll();

// Fetch active teams for team game type
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
        header("Location: add_result.php?club_id=" . $club_id . "&game_id=" . $game_id);
        exit();
    }

    $error = null; // Initialize error variable
    $game_type = $_POST['game_type'] ?? 'winner_losers';
    $winner_id = $_POST['winner_id'] ?? null;
    $second_place_id = $_POST['second_place_id'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $played_at = $_POST['played_at'] ?? null;
    $additional_places = isset($_POST['additional_places']) ? array_filter($_POST['additional_places']) : [];
    $losers = isset($_POST['losers']) ? array_filter($_POST['losers']) : [];
    $coop_outcome = $_POST['coop_outcome'] ?? 'win';
    $coop_players = isset($_POST['coop_players']) ? array_filter($_POST['coop_players']) : [];
    $team_winner_id = $_POST['team_winner_id'] ?? null;
    $team_losers = isset($_POST['team_losers']) ? array_filter($_POST['team_losers']) : [];

    // Parse duration from hours and minutes selects (default 2h 0m = 120 mins)
    $duration_hours = isset($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : 2;
    $duration_minutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : 0;
    $duration = ($duration_hours * 60) + $duration_minutes;

    // Requirement validations
    if ($game_type === 'ranked') {
        if (empty($second_place_id)) {
            $error = 'Please select a member for second place.';
        } else {
            $all_selected_members = array_filter(array_merge([$winner_id, $second_place_id], $additional_places));
            if (count($all_selected_members) !== count(array_unique($all_selected_members))) {
                $error = 'Duplicate members selected. Each member can only occupy one place.';
            }
        }
    } elseif ($game_type === 'winner_losers') {
        if (empty($winner_id)) {
            $error = 'Please select a winner.';
        } elseif (empty($losers)) {
            $error = 'Please select at least one loser.';
        } else {
            $all_selected_members = array_filter(array_merge([$winner_id], $losers));
            if (count($all_selected_members) !== count(array_unique($all_selected_members))) {
                $error = 'Duplicate members selected. The winner cannot also be a loser.';
            }
        }
    } elseif ($game_type === 'cooperative') {
        if (empty($_POST['coop_outcome'])) {
            $error = 'Please select a cooperative outcome (Victory or Defeat).';
        } elseif (empty($coop_players)) {
            $error = 'Please select at least one player for the cooperative game.';
        }
    } elseif ($game_type === 'teams') {
        if (empty($team_winner_id)) {
            $error = 'Please select a winning team.';
        } elseif (empty($team_losers)) {
            $error = 'Please select at least one losing team.';
        } else {
            if (in_array($team_winner_id, $team_losers)) {
                $error = 'The winning team cannot also be a losing team.';
            }
        }
    }

    if ($error === null && $duration <= 0) {
        $error = 'Please enter a valid duration for the game.';
    }
    
    // Proceed only if there are no errors
    if ($error === null) {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();
            
            $session_id = uniqid('game_', true);
            $formatted_played_at = str_replace('T', ' ', $played_at);
            if (strlen($formatted_played_at) == 16) $formatted_played_at .= ':00';

            if ($game_type === 'cooperative') {
                // Insert into cooperative_game_results
                $stmt = $pdo->prepare('INSERT INTO cooperative_game_results (game_id, session_id, outcome, num_participants, played_at, duration, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $game_id,
                    $session_id,
                    $coop_outcome,
                    count($coop_players),
                    $formatted_played_at,
                    $duration,
                    $notes
                ]);
                $result_id = $pdo->lastInsertId();

                // Insert cooperative participants
                $part_stmt = $pdo->prepare('INSERT INTO cooperative_result_participants (result_id, participant_type, member_id) VALUES (?, "member", ?)');
                foreach ($coop_players as $m_id) {
                    $part_stmt->execute([$result_id, $m_id]);
                }
            } elseif ($game_type === 'teams') {
                // Insert into team_game_results
                $num_teams = 1 + count($team_losers);
                $places = array_fill(0, 7, null);
                $t_index = 0;
                foreach ($team_losers as $t_id) {
                    if ($t_index < 7) {
                        $places[$t_index] = $t_id;
                        $t_index++;
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO team_game_results (game_id, session_id, team_id, position, played_at, duration, notes, num_teams, winner, place_2, place_3, place_4, place_5, place_6, place_7, place_8) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $game_id,
                    $session_id,
                    $team_winner_id,
                    1,
                    $formatted_played_at,
                    $duration,
                    $notes,
                    $num_teams,
                    $team_winner_id,
                    $places[0],
                    $places[1],
                    $places[2],
                    $places[3],
                    $places[4],
                    $places[5],
                    $places[6]
                ]);
            } else {
                // Insert into game_results (ranked / winner_losers)
                $stmt = $pdo->prepare('INSERT INTO game_results (game_id, session_id, member_id, position, played_at, duration, notes, num_players, winner, place_2, place_3, place_4, place_5, place_6, place_7, place_8) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                
                $num_players = 1;
                if ($game_type === 'ranked') {
                    $num_players += 1 + count(array_filter($additional_places));
                } else {
                    $num_players += count($losers);
                }
                
                $places = array_fill(0, 7, null);
                if ($game_type === 'ranked') {
                    if ($second_place_id) $places[0] = $second_place_id;
                    $place_index = 1;
                    foreach ($additional_places as $member_id) {
                        if ($member_id && $place_index < 7) {
                            $places[$place_index] = $member_id;
                            $place_index++;
                        }
                    }
                }
                
                $stmt->execute([
                    $game_id,
                    $session_id,
                    $winner_id,
                    1,
                    $formatted_played_at,
                    $duration,
                    $notes,
                    $num_players,
                    $winner_id,
                    $places[0],
                    $places[1],
                    $places[2],
                    $places[3],
                    $places[4],
                    $places[5],
                    $places[6]
                ]);
                
                $result_id = $pdo->lastInsertId();
                
                if ($game_type === 'winner_losers' && !empty($losers)) {
                    $loser_stmt = $pdo->prepare("INSERT INTO game_result_losers (result_id, member_id) VALUES (?, ?)");
                    foreach ($losers as $loser_id) {
                        $loser_stmt->execute([$result_id, $loser_id]);
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Game result has been successfully saved.';
            header('Location: results.php?game_id=' . $game_id . '&club_id=' . $club_id . '&success=1');
            exit();
        } catch (Throwable $e) {
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
    <title>Add Game Result - <?php echo htmlspecialchars($game['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <style>
        .required-marker { color: var(--color-error, #ef4444); font-weight: bold; }
    </style>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('new_result', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Add Game Result (' . $club_name . ')', htmlspecialchars($game['game_name'])); ?>
    </div>
    
    <style>
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: var(--spacing-3);
        padding: var(--spacing-3);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface-muted);
    }
    
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: var(--spacing-2);
        padding: var(--spacing-2);
        border-radius: var(--radius-sm);
        transition: background-color var(--transition-fast);
        cursor: pointer;
        user-select: none;
    }
    
    .checkbox-item:hover {
        background-color: var(--color-surface);
    }

    .checkbox-item input:disabled + label {
        color: var(--color-text-muted);
        text-decoration: line-through;
        cursor: not-allowed;
    }
    </style>
    <div class="container">
        <div class="card">
            <?php if (isset($error)): ?>
                <div class="message message--error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="message message--success"><?php echo $_SESSION['success_message']; ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <form method="POST" class="stack" id="result-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div id="validation-errors" class="message message--error" style="display: none;"></div>
                <div class="form-group">
                    <label for="played_at" class="form-label">Date Played: <span class="required-marker">*</span></label>
                    <input type="datetime-local" id="played_at" name="played_at" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Duration: <span class="required-marker">*</span></label>
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <div style="flex: 1; display: flex; align-items: center; gap: 0.35rem;">
                            <select name="duration_hours" id="duration_hours" class="form-control" style="flex: 1;">
                                <?php for ($h = 0; $h <= 12; $h++): ?>
                                    <option value="<?php echo $h; ?>" <?php echo ($h === 2) ? 'selected' : ''; ?>><?php echo $h; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span style="font-size: 0.875rem; color: var(--color-text-muted);">hrs</span>
                        </div>
                        <div style="flex: 1; display: flex; align-items: center; gap: 0.35rem;">
                            <select name="duration_minutes" id="duration_minutes" class="form-control" style="flex: 1;">
                                <?php for ($m = 0; $m <= 50; $m += 10): ?>
                                    <option value="<?php echo $m; ?>" <?php echo ($m === 0) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span style="font-size: 0.875rem; color: var(--color-text-muted);">mins</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Game Type:</label>
                    <div class="radio-group">
                        <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="game_type" value="winner_losers" class="form-check-input" checked onchange="toggleGameType()"> Winner vs. Losers
                        </label>
                        <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="game_type" value="ranked" class="form-check-input" onchange="toggleGameType()"> Ranked (1st, 2nd, 3rd...)
                        </label>
                        <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="game_type" value="teams" class="form-check-input" onchange="toggleGameType()"> Teams
                        </label>
                        <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="game_type" value="cooperative" class="form-check-input" onchange="toggleGameType()"> Cooperative
                        </label>
                    </div>
                </div>
                
                <div class="form-group" id="winner-section">
                    <label for="winner_id" class="form-label">Winner: <span class="required-marker">*</span></label>
                    <select id="winner_id" name="winner_id" class="form-control">
                        <option value="">Select Winner</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="ranked-section" style="display: none;">
                    <div class="form-group">
                        <label for="second_place_id" class="form-label">Second Place: <span class="required-marker">*</span></label>
                        <select id="second_place_id" name="second_place_id" class="form-control">
                            <option value="">Select Second Place</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="additional-places"></div>
                    
                    <div class="form-group">
                        <button type="button" id="add-place" class="btn">Add Place</button>
                    </div>
                </div>

                <div id="losers-section">
                    <label class="form-label">Select Losers: <span class="required-marker">*</span></label>
                    <div id="losers-checkbox-list" class="checkbox-grid">
                        <?php foreach ($members as $member): ?>
                            <label for="loser_<?php echo $member['id']; ?>" class="form-check checkbox-item">
                                <input type="checkbox" name="losers[]" id="loser_<?php echo $member['id']; ?>" value="<?php echo $member['id']; ?>" class="form-check-input loser-checkbox" checked>
                                <span class="form-check-label"><?php echo htmlspecialchars($member['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text mt-2">Select all members who lost this game.</div>
                </div>

                <div id="cooperative-section" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Outcome: <span class="required-marker">*</span></label>
                        <div class="radio-group">
                            <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                <input type="radio" name="coop_outcome" value="win" class="form-check-input"> Victory
                            </label>
                            <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                <input type="radio" name="coop_outcome" value="loss" class="form-check-input"> Defeat
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Select Players: <span class="required-marker">*</span></label>
                        <div id="coop-checkbox-list" class="checkbox-grid">
                            <?php foreach ($members as $member): ?>
                                <label for="coop_player_<?php echo $member['id']; ?>" class="form-check checkbox-item">
                                    <input type="checkbox" name="coop_players[]" id="coop_player_<?php echo $member['id']; ?>" value="<?php echo $member['id']; ?>" class="form-check-input coop-checkbox" checked>
                                    <span class="form-check-label"><?php echo htmlspecialchars($member['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="help-text mt-2">Select all members who played in this cooperative game.</div>
                    </div>
                </div>

                <div id="teams-section" style="display: none;">
                    <?php if (empty($teams)): ?>
                        <div class="message message--warning" style="margin-bottom: 1rem;">
                            No teams found for this club. Please <a href="club_teams.php?club_id=<?php echo $club_id; ?>">create teams</a> first.
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="team_winner_id" class="form-label">Winning Team: <span class="required-marker">*</span></label>
                            <select id="team_winner_id" name="team_winner_id" class="form-control">
                                <option value="">Select Winning Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Select Losing Team(s): <span class="required-marker">*</span></label>
                            <div id="team-losers-checkbox-list" class="checkbox-grid">
                                <?php foreach ($teams as $team): ?>
                                    <label for="team_loser_<?php echo $team['id']; ?>" class="form-check checkbox-item">
                                        <input type="checkbox" name="team_losers[]" id="team_loser_<?php echo $team['id']; ?>" value="<?php echo $team['id']; ?>" class="form-check-input team-loser-checkbox">
                                        <span class="form-check-label"><?php echo htmlspecialchars($team['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="help-text mt-2">Select all teams that lost this game.</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="notes" class="form-label">Notes:</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Save Result</button>
                    <a href="club_new_results.php?club_id=<?php echo $club_id; ?>" 
                       class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../js/script.js"></script>
    
    <script>
    // Function to update disabled options across all dropdowns
    function updateDisabledOptions() {
        updatePlayerSelections();
    }

    function toggleGameType() {
        const gameType = document.querySelector('input[name="game_type"]:checked').value;
        const winnerSection = document.getElementById('winner-section');
        const rankedSection = document.getElementById('ranked-section');
        const losersSection = document.getElementById('losers-section');
        const coopSection = document.getElementById('cooperative-section');
        const teamsSection = document.getElementById('teams-section');
        
        if (gameType === 'ranked') {
            winnerSection.style.display = 'block';
            rankedSection.style.display = 'block';
            losersSection.style.display = 'none';
            coopSection.style.display = 'none';
            if (teamsSection) teamsSection.style.display = 'none';
        } else if (gameType === 'cooperative') {
            winnerSection.style.display = 'none';
            rankedSection.style.display = 'none';
            losersSection.style.display = 'none';
            coopSection.style.display = 'block';
            if (teamsSection) teamsSection.style.display = 'none';
        } else if (gameType === 'teams') {
            winnerSection.style.display = 'none';
            rankedSection.style.display = 'none';
            losersSection.style.display = 'none';
            coopSection.style.display = 'none';
            if (teamsSection) teamsSection.style.display = 'block';
        } else { // winner_losers
            winnerSection.style.display = 'block';
            rankedSection.style.display = 'none';
            losersSection.style.display = 'block';
            coopSection.style.display = 'none';
            if (teamsSection) teamsSection.style.display = 'none';
            
            // Clear ranked inputs
            document.getElementById('second_place_id').value = '';
            document.getElementById('additional-places').innerHTML = '';
            placeCount = 2;
        }
        updatePlayerSelections();
        updateTeamSelections();
    }

    function updateTeamSelections() {
        const teamWinnerSelect = document.getElementById('team_winner_id');
        if (!teamWinnerSelect) return;
        const teamWinnerId = teamWinnerSelect.value;
        const teamLoserCheckboxes = document.querySelectorAll('.team-loser-checkbox');
        teamLoserCheckboxes.forEach(checkbox => {
            const parentItem = checkbox.closest('.checkbox-item');
            if (teamWinnerId && checkbox.value === teamWinnerId) {
                checkbox.checked = false;
                if (parentItem) parentItem.style.display = 'none';
            } else {
                if (parentItem) parentItem.style.display = 'flex';
            }
        });
    }

    // Add change event listeners to all dropdowns
    document.getElementById('winner_id').addEventListener('change', updatePlayerSelections);
    document.getElementById('second_place_id').addEventListener('change', updatePlayerSelections);
    if (document.getElementById('team_winner_id')) {
        document.getElementById('team_winner_id').addEventListener('change', updateTeamSelections);
    }
    
    // Initialize the disabled state
    updatePlayerSelections();
    updateTeamSelections();
    
    // Set default date to user's current local time
    const now = new Date();
    const timezoneOffset = now.getTimezoneOffset();
    now.setMinutes(now.getMinutes() - timezoneOffset);
    const localDateTime = now.toISOString().slice(0, 16);
    document.getElementById('played_at').value = localDateTime;
    
    let placeCount = 2;
    const maxPlaces = 8;
    
    document.getElementById('add-place').addEventListener('click', function() {
        if (placeCount >= maxPlaces) {
            alert('Maximum of 8 places allowed');
            return;
        }
        
        placeCount++;
        const container = document.getElementById('additional-places');
        const placeDiv = document.createElement('div');
        placeDiv.className = 'form-group';
        placeDiv.innerHTML = `
            <div class="cluster items-start gap-md">
                <div class="w-100">
                    <label for="place_${placeCount}">${placeCount}${getOrdinalSuffix(placeCount)} Place:</label>
                    <select id="place_${placeCount}" name="additional_places[]" class="form-control">
                        <option value="">Select Player</option>
                        ${Array.from(document.getElementById('winner_id').options)
                            .map(opt => `<option value="${opt.value}">${opt.text}</option>`).join('')}
                    </select>
                </div>
                <button type="button" class="btn btn--secondary remove-place mt-3">Remove</button>
            </div>
        `;
        
        container.appendChild(placeDiv);
        
        placeDiv.querySelector('select').addEventListener('change', updatePlayerSelections);
        updatePlayerSelections();
        
        placeDiv.querySelector('.remove-place').addEventListener('click', function() {
            placeDiv.remove();
            placeCount--;
            updatePlayerSelections();
        });
    });

    function getOrdinalSuffix(i) {
        const j = i % 10, k = i % 100;
        if (j == 1 && k != 11) return 'st';
        if (j == 2 && k != 12) return 'nd';
        if (j == 3 && k != 13) return 'rd';
        return 'th';
    }
    
    function updatePlayerSelections() {
        const winnerId = document.getElementById('winner_id').value;
        const secondPlaceId = document.getElementById('second_place_id').value;
        const additionalPlacesSelects = document.querySelectorAll('select[name^="additional_places"]');
        
        const selectedRankedIds = [winnerId, secondPlaceId];
        additionalPlacesSelects.forEach(select => {
            if (select.value) selectedRankedIds.push(select.value);
        });
        
        const allRankedSelects = [
            document.getElementById('winner_id'),
            document.getElementById('second_place_id'),
            ...Array.from(additionalPlacesSelects)
        ];
        
        allRankedSelects.forEach(select => {
            Array.from(select.options).forEach(option => {
                if (option.value) {
                    option.disabled = selectedRankedIds.includes(option.value) && option.value !== select.value;
                }
            });
        });

        const loserCheckboxes = document.querySelectorAll('.loser-checkbox');
        loserCheckboxes.forEach(checkbox => {
            const parentItem = checkbox.closest('.checkbox-item');
            if (winnerId && checkbox.value === winnerId) {
                checkbox.checked = false;
                if (parentItem) parentItem.style.display = 'none';
            } else {
                if (parentItem) parentItem.style.display = 'flex';
            }
        });
    }

    // Form validation
    document.getElementById('result-form').addEventListener('submit', function(e) {
        const missingFields = [];
        const errorDiv = document.getElementById('validation-errors');
        const gameType = document.querySelector('input[name="game_type"]:checked').value;

        // Check Date Played
        if (!document.getElementById('played_at').value) {
            missingFields.push('Date Played');
        }

        // Check game type specific fields
        if (gameType === 'ranked') {
            if (!document.getElementById('winner_id').value) missingFields.push('Winner');
            if (!document.getElementById('second_place_id').value) missingFields.push('Second Place');
        } else if (gameType === 'cooperative') {
            const coopOutcome = document.querySelector('input[name="coop_outcome"]:checked');
            if (!coopOutcome) missingFields.push('Outcome (Victory or Defeat)');
            const coopCheckboxes = document.querySelectorAll('.coop-checkbox:checked');
            if (coopCheckboxes.length === 0) missingFields.push('At least one Player');
        } else if (gameType === 'teams') {
            if (!document.getElementById('team_winner_id') || !document.getElementById('team_winner_id').value) missingFields.push('Winning Team');
            const teamLoserCheckboxes = document.querySelectorAll('.team-loser-checkbox:checked');
            if (teamLoserCheckboxes.length === 0) missingFields.push('At least one Losing Team');
        } else { // winner_losers
            if (!document.getElementById('winner_id').value) missingFields.push('Winner');
            const loserCheckboxes = document.querySelectorAll('.loser-checkbox:checked');
            if (loserCheckboxes.length === 0) missingFields.push('At least one Loser');
        }

        // Check Duration
        const durH = parseInt(document.getElementById('duration_hours').value || '0', 10);
        const durM = parseInt(document.getElementById('duration_minutes').value || '0', 10);
        if (durH === 0 && durM === 0) {
            missingFields.push('Duration (must be > 0)');
        }

        // Show errors or submit
        if (missingFields.length > 0) {
            e.preventDefault();
            errorDiv.innerHTML = '<strong>Please fill in the following required fields:</strong><br>' + missingFields.join(', ') + '.';
            errorDiv.style.display = 'block';
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            errorDiv.style.display = 'none';
        }
    });
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
