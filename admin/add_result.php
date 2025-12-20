<?php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

// Ensure game_result_losers table exists
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
    } catch (PDOException $e2) {
        // Ignore error if table creation fails, might be permissions
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: add_result.php?club_id=" . $club_id . "&game_id=" . $game_id);
        exit();
    }

    $error = null; // Initialize error variable
    $game_type = $_POST['game_type'] ?? 'ranked';
    $winner_id = $_POST['winner_id'] ?? null;
    $second_place_id = $_POST['second_place_id'] ?? null;
    $duration = $_POST['duration'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $played_at = $_POST['played_at'] ?? null;
    $additional_places = isset($_POST['additional_places']) ? array_filter($_POST['additional_places']) : []; // Filter out empty values
    $losers = isset($_POST['losers']) ? array_filter($_POST['losers']) : [];

    file_put_contents('../debug_log.txt', "Validating inputs... Game Type: $game_type\n", FILE_APPEND);

    // Requirement 1: Check if second place is selected (only for ranked games)
    if ($game_type === 'ranked' && empty($second_place_id)) {
        $error = 'Please select a member for second place. If playing solo, create a member named "none" in the Manage Members page.';
    } elseif ($game_type === 'winner_losers' && empty($losers)) {
        $error = 'Please select at least one loser.';
    } else {
        // Requirement 2: Check for unique entries across all places
        if ($game_type === 'ranked') {
            $all_selected_members = array_filter(array_merge([$winner_id, $second_place_id], $additional_places));
        } else {
            $all_selected_members = array_filter(array_merge([$winner_id], $losers));
        }
        
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
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();
            
            // Generate a unique session ID for this game result
            $session_id = uniqid('game_', true);
            
            // Insert a single game result record
            $stmt = $pdo->prepare('INSERT INTO game_results (game_id, session_id, member_id, position, played_at, duration, notes, num_players, winner, place_2, place_3, place_4, place_5, place_6, place_7, place_8) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            
            // Calculate total number of players
            $num_players = 1; // Winner
            if ($game_type === 'ranked') {
                $num_players += 1; // Second place
                $num_players += count(array_filter($additional_places));
            } else {
                $num_players += count($losers);
            }
            
            // Initialize places array with nulls
            $places = array_fill(0, 7, null);
            if ($game_type === 'ranked') {
                if ($second_place_id) $places[0] = $second_place_id;
                
                // Fill in additional places
                $place_index = 1; // Start at index 1 since index 0 is for second place
                foreach ($additional_places as $member_id) {
                    if ($member_id && $place_index < 7) { // Ensure we don't exceed place_8
                        $places[$place_index] = $member_id;
                        $place_index++;
                    }
                }
            }
            
            // Fix date format
            $formatted_played_at = str_replace('T', ' ', $played_at);
            if (strlen($formatted_played_at) == 16) $formatted_played_at .= ':00';
            
            // Insert single record with all places
            $stmt->execute([
                $game_id,
                $session_id,
                $winner_id,
                1, // position
                $formatted_played_at,
                $duration,
                $notes,
                $num_players,
                $winner_id, // winner
                $places[0], // place_2
                $places[1], // place_3
                $places[2], // place_4
                $places[3], // place_5
                $places[4], // place_6
                $places[5], // place_7
                $places[6]  // place_8
            ]);
            
            $result_id = $pdo->lastInsertId();
            
            // Insert losers if applicable
            if ($game_type === 'winner_losers') {
                if (!empty($losers)) {
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
    <title>Add Game Result - <?php echo htmlspecialchars($game['game_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $club_id); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Add Game Result', htmlspecialchars($game['game_name'])); ?>
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
                    <label>Game Type:</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="game_type" value="ranked" checked onchange="toggleGameType()"> Ranked (1st, 2nd, 3rd...)
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="game_type" value="winner_losers" onchange="toggleGameType()"> Winner vs Losers
                        </label>
                    </div>
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
                
                <div id="ranked-section">
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
                </div>

                <div id="losers-section" style="display: none;">
                    <label>Losers:</label>
                    <div id="losers-container"></div>
                    <div class="form-group">
                        <button type="button" id="add-loser" class="btn">Add Loser</button>
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
    // Function to update disabled options across all dropdowns
    function updateDisabledOptions() {
        const dropdowns = document.querySelectorAll('select[name^="winner_id"], select[name^="second_place_id"], select[name^="additional_places"], select[name^="losers"]');
        const selectedValues = Array.from(dropdowns).map(select => select.value).filter(value => value !== '');
        
        dropdowns.forEach(dropdown => {
            Array.from(dropdown.options).forEach(option => {
                if (option.value === '') return; // Skip the placeholder option
                option.disabled = selectedValues.includes(option.value) && option.value !== dropdown.value;
            });
        });
    }

    function toggleGameType() {
        const gameType = document.querySelector('input[name="game_type"]:checked').value;
        const rankedSection = document.getElementById('ranked-section');
        const losersSection = document.getElementById('losers-section');
        
        if (gameType === 'ranked') {
            rankedSection.style.display = 'block';
            losersSection.style.display = 'none';
            // Clear losers
            document.getElementById('losers-container').innerHTML = '';
        } else {
            rankedSection.style.display = 'none';
            losersSection.style.display = 'block';
            // Clear ranked inputs
            document.getElementById('second_place_id').value = '';
            document.getElementById('additional-places').innerHTML = '';
            placeCount = 2;
            
            // Add first loser field if empty
            if (document.getElementById('losers-container').children.length === 0) {
                addLoserField();
            }
        }
        updateDisabledOptions();
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

    function addLoserField() {
        const container = document.getElementById('losers-container');
        const loserDiv = document.createElement('div');
        loserDiv.className = 'form-group';
        loserDiv.innerHTML = `
            <div class="cluster items-start gap-md">
                <div class="w-100">
                    <select name="losers[]" class="form-control" required>
                        <option value="">Select Loser</option>
                        ${Array.from(document.getElementById('winner_id').options)
                            .map(opt => `<option value="${opt.value}">${opt.text}</option>`).join('')}
                    </select>
                </div>
                <button type="button" class="btn btn--secondary remove-loser mt-0">Remove</button>
            </div>
        `;
        
        container.appendChild(loserDiv);
        
        loserDiv.querySelector('select').addEventListener('change', updateDisabledOptions);
        updateDisabledOptions();
        
        loserDiv.querySelector('.remove-loser').addEventListener('click', function() {
            loserDiv.remove();
            updateDisabledOptions();
        });
    }

    document.getElementById('add-loser').addEventListener('click', addLoserField);
    
    function getOrdinalSuffix(i) {
        const j = i % 10,
              k = i % 100;
        if (j == 1 && k != 11) return 'st';
        if (j == 2 && k != 12) return 'nd';
        if (j == 3 && k != 13) return 'rd';
        return 'th';
    }
    
    function updatePlayerSelections() {
        const allSelects = document.querySelectorAll('select[id^="place_"], #winner_id, #second_place_id, select[name^="losers"]');
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
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
