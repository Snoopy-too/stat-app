<?php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

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

// Fetch active teams for the dropdown
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
        header("Location: add_team_result.php?club_id=" . $club_id . "&game_id=" . $game_id);
        exit();
    }

    if (empty($_POST['winner_id']) || empty($_POST['second_place_id'])) {
        $error = 'Both winner and second place teams must be selected.';
    } else {
        $winner_id = $_POST['winner_id'];
        $second_place_id = $_POST['second_place_id'];
        $duration = $_POST['duration'];
        $notes = $_POST['notes'];
        $played_at = $_POST['played_at'];
        $additional_places = isset($_POST['additional_places']) ? $_POST['additional_places'] : [];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Generate a unique session ID for this game result
        $session_id = uniqid('team_game_', true);
        
        // Insert a single team game result record
        $stmt = $pdo->prepare('INSERT INTO team_game_results (game_id, session_id, team_id, position, played_at, duration, notes, num_teams, winner, place_2, place_3, place_4, place_5, place_6, place_7, place_8) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        // Calculate total number of teams
        $num_teams = 2; // Start with winner and second place
        $num_teams += count(array_filter($additional_places));
        
        // Initialize places array with nulls
        $places = array_fill(0, 7, null);
        if ($second_place_id) $places[0] = $second_place_id;
        
        // Fill in additional places
        $place_index = 1; // Start at index 1 since index 0 is for second place
        foreach ($additional_places as $team_id) {
            if ($team_id && $place_index < 7) { // Ensure we don't exceed place_8
                $places[$place_index] = $team_id;
                $place_index++;
            }
        }
        
        // Insert single record with all places
        $stmt->execute([
            $game_id,
            $session_id,
            $winner_id,
            1, // position
            $played_at,
            $duration,
            $notes,
            $num_teams,
            $winner_id, // winner
            $second_place_id, // place_2
            $places[1], // place_3
            $places[2], // place_4
            $places[3], // place_5
            $places[4], // place_6
            $places[5], // place_7
            $places[6]  // place_8
        ]);
        
        $pdo->commit();
        $_SESSION['success_message'] = 'Team game result has been successfully saved to the database.';
        header('Location: results.php?game_id=' . $game_id . '&club_id=' . $club_id . '&success=1');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error saving team game result: ' . $e->getMessage();
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
    <title>Add Team Game Result - <?php echo htmlspecialchars($game['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Add Team Game Result', htmlspecialchars($game['game_name'])); ?>
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
                    <label for="winner_id">Winner Team:</label>
                    <select id="winner_id" name="winner_id" required class="form-control">
                        <option value="">Select Winner Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>">
                                <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="second_place_id">Second Place Team:</label>
                    <select id="second_place_id" name="second_place_id" class="form-control">
                        <option value="">Select Second Place Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>">
                                <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="additional-places"></div>
                
                <div class="form-group">
                    <button type="button" id="add-place" class="btn">Add Place</button>
                </div>
                
                <div class="form-group">
                    <label for="duration">Duration (minutes):</label>
                    <input type="number" id="duration" name="duration" min="1" class="form-control">
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
    // Function to update disabled options across all dropdowns
    function updateDisabledOptions() {
        const dropdowns = document.querySelectorAll('select[name^="winner_id"], select[name^="second_place_id"], select[name^="additional_places"]');
        const selectedValues = Array.from(dropdowns).map(select => select.value).filter(value => value !== '');
        
        dropdowns.forEach(dropdown => {
            Array.from(dropdown.options).forEach(option => {
                if (option.value === '') return; // Skip the placeholder option
                option.disabled = selectedValues.includes(option.value) && option.value !== dropdown.value;
            });
        });
    }

    // Add change event listeners to all dropdowns
    document.getElementById('winner_id').addEventListener('change', updateDisabledOptions);
    document.getElementById('second_place_id').addEventListener('change', updateDisabledOptions);
    
    // Initialize the disabled state
    updateDisabledOptions();
    
    // Set default date to today
    document.getElementById('played_at').value = new Date().toISOString().slice(0, 16);
    
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
                    <label for="place_${placeCount}">${placeCount}${getOrdinalSuffix(placeCount)} Place Team:</label>
                    <select id="place_${placeCount}" name="additional_places[]" class="form-control">
                        <option value="">Select Team</option>
                        ${Array.from(document.getElementById('winner_id').options)
                            .map(opt => `<option value="${opt.value}">${opt.text}</option>`).join('')}
                    </select>
                </div>
                <button type="button" class="btn btn--secondary remove-place mt-3">Remove</button>
            </div>
        `;
        
        container.appendChild(placeDiv);
        updateTeamSelections();
        
        placeDiv.querySelector('.remove-place').addEventListener('click', function() {
            placeDiv.remove();
            placeCount--;
            updateTeamSelections();
        });
    });
    
    function getOrdinalSuffix(i) {
        const j = i % 10,
              k = i % 100;
        if (j == 1 && k != 11) return 'st';
        if (j == 2 && k != 12) return 'nd';
        if (j == 3 && k != 13) return 'rd';
        return 'th';
    }
    
    function updateTeamSelections() {
        const allSelects = document.querySelectorAll('select[id^="place_"], #winner_id, #second_place_id');
        const selectedValues = Array.from(allSelects).map(select => select.value).filter(Boolean);
        
        allSelects.forEach(select => {
            Array.from(select.options).forEach(option => {
                if (option.value) {
                    option.disabled = selectedValues.includes(option.value) && option.value !== select.value;
                }
            });
        });
    }
    
    // Add event listeners for existing dropdowns
    document.getElementById('winner_id').addEventListener('change', updateTeamSelections);
    document.getElementById('second_place_id').addEventListener('change', updateTeamSelections);
    </script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
