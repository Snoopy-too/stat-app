<?php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';

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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: add_result.php?club_id=" . $club_id . "&game_id=" . $game_id);
        exit();
    }

    $error = null; // Initialize error variable
    $winner_id = $_POST['winner_id'] ?? null;
    $second_place_id = $_POST['second_place_id'] ?? null;
    $duration = $_POST['duration'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $played_at = $_POST['played_at'] ?? null;
    $additional_places = isset($_POST['additional_places']) ? array_filter($_POST['additional_places']) : []; // Filter out empty values

    // Requirement 1: Check if second place is selected
    if (empty($second_place_id)) {
        $error = 'Please select a member for second place. If playing solo, create a member named "none" in the Manage Members page.';
    } else {
        // Requirement 2: Check for unique entries across all places
        $all_selected_members = array_filter([$winner_id, $second_place_id] + $additional_places);
        if (count($all_selected_members) !== count(array_unique($all_selected_members))) {
            $error = 'Duplicate members selected. Each member can only occupy one place.';
        }
    }

    // Requirement 3: Check if duration is provided
    if ($error === null && empty($duration)) {
        $error = 'Please enter the duration of the game.';
    }

    // Proceed only if there are no errors
    if ($error === null) {
        try {
        $pdo->beginTransaction();
        
        // Generate a unique session ID for this game result
        $session_id = uniqid('game_', true);
        
        // Insert a single game result record
        $stmt = $pdo->prepare('INSERT INTO game_results (game_id, session_id, member_id, position, played_at, duration, notes, num_players, winner, place_2, place_3, place_4, place_5, place_6, place_7, place_8) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        // Calculate total number of players
        $num_players = 2; // Start with winner and second place
        $num_players += count(array_filter($additional_places));
        
        // Initialize places array with nulls
        $places = array_fill(0, 7, null);
        if ($second_place_id) $places[0] = $second_place_id;
        
        // Fill in additional places
        $place_index = 1; // Start at index 1 since index 0 is for second place
        foreach ($additional_places as $member_id) {
            if ($member_id && $place_index < 7) { // Ensure we don't exceed place_8
                $places[$place_index] = $member_id;
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
            $num_players,
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
        $_SESSION['success_message'] = 'Game result has been successfully saved.';
        header('Location: results.php?game_id=' . $game_id . '&club_id=' . $club_id . '&success=1');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error saving result: ' . $e->getMessage();
    }
}
} // Closing brace for if ($_SERVER['REQUEST_METHOD'] === 'POST')

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
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Add Game Result</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($game['game_name']); ?></p>
        </div>
        <a href="results.php?game_id=<?php echo $game_id; ?>&club_id=<?php echo $club_id; ?>" class="btn btn--secondary">Back to Results</a>
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
                    <label for="winner_id">Winner:</label>
                    <select id="winner_id" name="winner_id" required class="form-control">
                        <option value="">Select Winner</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="second_place_id">Second Place:</label>
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
        
        // Add change event listener to the new select
        placeDiv.querySelector('select').addEventListener('change', updateDisabledOptions);
        updateDisabledOptions();
        
        placeDiv.querySelector('.remove-place').addEventListener('click', function() {
            placeDiv.remove();
            placeCount--;
            updateDisabledOptions();
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
    
    function updatePlayerSelections() {
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
    document.getElementById('winner_id').addEventListener('change', updatePlayerSelections);
    document.getElementById('second_place_id').addEventListener('change', updatePlayerSelections);
    </script>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>
