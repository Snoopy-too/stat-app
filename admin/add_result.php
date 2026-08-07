<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SecurityUtils.php';
require_once __DIR__ . '/../includes/NavigationHelper.php';
require_once __DIR__ . '/../includes/helpers.php';
ensure_game_type_column_exists($pdo);

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header('Location: login.php');
    exit();
}

$security = new SecurityUtils($pdo);
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : 0;
$type_param = $_GET['type'] ?? $_POST['type'] ?? null;
$is_edit = ($result_id > 0);

$existing_result = null;
$result_format = 'winner_losers';

if ($is_edit) {
    if ($type_param === 'team' || $type_param === 'teams') {
        try {
            $stmt = $pdo->prepare("SELECT tgr.*, 'teams' as result_format, g.game_name, g.club_id FROM team_game_results tgr JOIN games g ON tgr.game_id = g.game_id WHERE tgr.result_id = ?");
            $stmt->execute([$result_id]);
            $existing_result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_result) $result_format = 'teams';
        } catch (Throwable $e) {}
    } elseif ($type_param === 'cooperative') {
        try {
            $stmt = $pdo->prepare("SELECT cgr.*, 'cooperative' as result_format, g.game_name, g.club_id FROM cooperative_game_results cgr JOIN games g ON cgr.game_id = g.game_id WHERE cgr.result_id = ?");
            $stmt->execute([$result_id]);
            $existing_result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_result) $result_format = 'cooperative';
        } catch (Throwable $e) {}
    }

    if (!$existing_result) {
        try {
            $stmt = $pdo->prepare("SELECT gr.*, g.game_name, g.club_id FROM game_results gr JOIN games g ON gr.game_id = g.game_id WHERE gr.result_id = ?");
            $stmt->execute([$result_id]);
            $existing_result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_result) {
                $result_format = ($existing_result['place_2'] !== null) ? 'ranked' : 'winner_losers';
            }
        } catch (Throwable $e) {}
    }

    if (!$existing_result) {
        try {
            $stmt = $pdo->prepare("SELECT tgr.*, g.game_name, g.club_id FROM team_game_results tgr JOIN games g ON tgr.game_id = g.game_id WHERE tgr.result_id = ?");
            $stmt->execute([$result_id]);
            $existing_result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_result) $result_format = 'teams';
        } catch (Throwable $e) {}
    }

    if (!$existing_result) {
        try {
            $stmt = $pdo->prepare("SELECT cgr.*, g.game_name, g.club_id FROM cooperative_game_results cgr JOIN games g ON cgr.game_id = g.game_id WHERE cgr.result_id = ?");
            $stmt->execute([$result_id]);
            $existing_result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_result) $result_format = 'cooperative';
        } catch (Throwable $e) {}
    }

    if (!$existing_result) {
        $_SESSION['error'] = "Result record not found.";
        header("Location: results.php");
        exit();
    }

    $club_id = (int)$existing_result['club_id'];
    $game_id = (int)$existing_result['game_id'];
} else {
    $club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
    $game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
}

// Fetch game details
$stmt = $pdo->prepare('SELECT * FROM games WHERE game_id = ? AND club_id = ?');
$stmt->execute([$game_id, $club_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game && !$is_edit) {
    header('Location: manage_games.php');
    exit();
}

$club_name = NavigationHelper::getClubName($pdo, $club_id);

// Fetch all games for switcher dropdown
$stmt = $pdo->prepare('SELECT game_id, game_name FROM games WHERE club_id = ? ORDER BY game_name ASC');
$stmt->execute([$club_id]);
$all_club_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active members
$stmt = $pdo->prepare('SELECT m.member_id as id, m.nickname as name FROM members m WHERE m.club_id = ? AND m.status = "active" ORDER BY m.nickname ASC');
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active teams
$stmt = $pdo->prepare('SELECT t.team_id as id, t.team_name as name FROM teams t WHERE t.club_id = ? ORDER BY t.team_name ASC');
$stmt->execute([$club_id]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load supplementary data for edit mode
$edit_losers = [];
$edit_coop_players = [];
if ($is_edit) {
    if ($result_format === 'winner_losers' || $result_format === 'ranked') {
        try {
            $stmt = $pdo->prepare("SELECT member_id FROM game_result_losers WHERE result_id = ?");
            $stmt->execute([$result_id]);
            $edit_losers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {}
    } elseif ($result_format === 'cooperative') {
        try {
            $stmt = $pdo->prepare("SELECT member_id FROM cooperative_result_participants WHERE result_id = ? AND participant_type = 'member'");
            $stmt->execute([$result_id]);
            $edit_coop_players = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {}
    }
}

// Process form submission (Delete, Update, or Insert)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: add_result.php?" . ($is_edit ? "result_id={$result_id}" : "club_id={$club_id}&game_id={$game_id}"));
        exit();
    }

    // Handle Delete Action
    if ($is_edit && isset($_POST['action']) && $_POST['action'] === 'delete') {
        try {
            if ($result_format === 'teams') {
                $pdo->prepare("DELETE FROM team_game_results WHERE result_id = ?")->execute([$result_id]);
            } elseif ($result_format === 'cooperative') {
                $pdo->prepare("DELETE FROM cooperative_game_results WHERE result_id = ?")->execute([$result_id]);
            } else {
                $pdo->prepare("DELETE FROM game_results WHERE result_id = ?")->execute([$result_id]);
            }
            $_SESSION['success'] = "Result deleted successfully!";
            header("Location: results.php?club_id=" . $club_id);
            exit();
        } catch (Throwable $e) {
            $_SESSION['error'] = "Error deleting result: " . $e->getMessage();
        }
    }

    $error = null;
    $game_type = $_POST['game_type'] ?? $game['game_type'] ?? 'winner_losers';
    $winner_id = !empty($_POST['winner_id']) ? (int)$_POST['winner_id'] : null;
    $second_place_id = !empty($_POST['second_place_id']) ? (int)$_POST['second_place_id'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $played_at = $_POST['played_at'] ?? date('Y-m-d H:i:s');
    $additional_places = isset($_POST['additional_places']) ? array_filter(array_map('intval', $_POST['additional_places'])) : [];
    $losers = isset($_POST['losers']) ? array_filter(array_map('intval', $_POST['losers'])) : [];
    $coop_outcome = $_POST['coop_outcome'] ?? 'win';
    $coop_players = isset($_POST['coop_players']) ? array_filter(array_map('intval', $_POST['coop_players'])) : [];
    $team_winner_id = !empty($_POST['team_winner_id']) ? (int)$_POST['team_winner_id'] : null;
    $team_losers = isset($_POST['team_losers']) ? array_filter(array_map('intval', $_POST['team_losers'])) : [];

    $duration_hours = isset($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : 2;
    $duration_minutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : 0;
    $duration = ($duration_hours * 60) + $duration_minutes;

    if ($game_type === 'ranked') {
        if (empty($winner_id)) {
            $error = 'Please select a winner.';
        } elseif (empty($second_place_id)) {
            $error = 'Please select a member for second place.';
        }
    } elseif ($game_type === 'winner_losers') {
        if (empty($winner_id)) {
            $error = 'Please select a winner.';
        }
    } elseif ($game_type === 'cooperative') {
        if (empty($coop_players)) {
            $error = 'Please select at least one player for the cooperative game.';
        }
    } elseif ($game_type === 'teams') {
        if (empty($team_winner_id)) {
            $error = 'Please select a winning team.';
        }
    }

    if ($error === null && $duration <= 0) {
        $error = 'Please select a valid duration for the game.';
    }

    if ($error === null) {
        try {
            $pdo->beginTransaction();
            $formatted_played_at = str_replace('T', ' ', $played_at);
            if (strlen($formatted_played_at) == 16) $formatted_played_at .= ':00';

            if ($is_edit) {
                // Execute UPDATE
                if ($game_type === 'cooperative') {
                    $stmt = $pdo->prepare('UPDATE cooperative_game_results SET outcome = ?, num_participants = ?, played_at = ?, duration = ?, notes = ? WHERE result_id = ?');
                    $stmt->execute([$coop_outcome, count($coop_players), $formatted_played_at, $duration, $notes, $result_id]);

                    $pdo->prepare("DELETE FROM cooperative_result_participants WHERE result_id = ?")->execute([$result_id]);
                    $insert_stmt = $pdo->prepare("INSERT INTO cooperative_result_participants (result_id, participant_type, member_id) VALUES (?, 'member', ?)");
                    foreach ($coop_players as $pid) {
                        $insert_stmt->execute([$result_id, $pid]);
                    }
                } elseif ($game_type === 'teams') {
                    $stmt = $pdo->prepare('UPDATE team_game_results SET winner = ?, place_2 = ?, played_at = ?, duration = ?, notes = ? WHERE result_id = ?');
                    $stmt->execute([$team_winner_id, reset($team_losers) ?: null, $formatted_played_at, $duration, $notes, $result_id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE game_results SET member_id = ?, winner = ?, place_2 = ?, played_at = ?, duration = ?, notes = ? WHERE result_id = ?');
                    $stmt->execute([$winner_id, $winner_id, $second_place_id, $formatted_played_at, $duration, $notes, $result_id]);

                    if ($game_type === 'winner_losers') {
                        $pdo->prepare("DELETE FROM game_result_losers WHERE result_id = ?")->execute([$result_id]);
                        $insert_stmt = $pdo->prepare("INSERT INTO game_result_losers (result_id, member_id) VALUES (?, ?)");
                        foreach ($losers as $lid) {
                            $insert_stmt->execute([$result_id, $lid]);
                        }
                    }
                }
                $pdo->commit();
                $_SESSION['success'] = 'Result updated successfully!';
                header('Location: add_result.php?result_id=' . $result_id . '&type=' . $game_type);
                exit();
            } else {
                // Execute INSERT
                $session_id = uniqid('game_', true);
                if ($game_type === 'cooperative') {
                    $stmt = $pdo->prepare('INSERT INTO cooperative_game_results (game_id, session_id, outcome, num_participants, played_at, duration, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$game_id, $session_id, $coop_outcome, count($coop_players), $formatted_played_at, $duration, $notes]);
                    $new_id = $pdo->lastInsertId();

                    $insert_stmt = $pdo->prepare("INSERT INTO cooperative_result_participants (result_id, participant_type, member_id) VALUES (?, 'member', ?)");
                    foreach ($coop_players as $pid) {
                        $insert_stmt->execute([$new_id, $pid]);
                    }
                } elseif ($game_type === 'teams') {
                    $stmt = $pdo->prepare('INSERT INTO team_game_results (game_id, session_id, winner, place_2, played_at, duration, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$game_id, $session_id, $team_winner_id, reset($team_losers) ?: null, $formatted_played_at, $duration, $notes]);
                } else {
                    $places = array_pad(array_merge([$winner_id, $second_place_id], $additional_places), 8, null);
                    $stmt = $pdo->prepare('INSERT INTO game_results (game_id, session_id, member_id, winner, place_2, place_3, place_4, place_5, place_6, place_7, place_8, played_at, duration, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $game_id,
                        $session_id,
                        $winner_id,
                        $winner_id,
                        $places[1],
                        $places[2],
                        $places[3],
                        $places[4],
                        $places[5],
                        $places[6],
                        $places[7],
                        $formatted_played_at,
                        $duration,
                        $notes
                    ]);
                    $new_id = $pdo->lastInsertId();

                    if ($game_type === 'winner_losers' && !empty($losers)) {
                        $insert_stmt = $pdo->prepare("INSERT INTO game_result_losers (result_id, member_id) VALUES (?, ?)");
                        foreach ($losers as $lid) {
                            $insert_stmt->execute([$new_id, $lid]);
                        }
                    }
                }
                $pdo->commit();
                $_SESSION['success'] = 'Game result saved successfully!';
                header('Location: results.php?club_id=' . $club_id . '&game_id=' . $game_id);
                exit();
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Error saving result: ' . $e->getMessage();
        }
    }
}

// Format default values for pre-population
$default_game_type = $_POST['game_type'] ?? ($is_edit ? $result_format : ($game['game_type'] ?? 'winner_losers'));
$default_played_at = $is_edit && !empty($existing_result['played_at']) ? date('Y-m-d\TH:i', strtotime((string)$existing_result['played_at'])) : date('Y-m-d\TH:i');
$default_duration_hours = $is_edit ? floor((int)($existing_result['duration'] ?? 0) / 60) : 2;
$default_duration_minutes = $is_edit ? ((int)($existing_result['duration'] ?? 0) % 60) : 0;
$default_winner_id = $is_edit ? (int)($existing_result['winner'] ?? $existing_result['member_id'] ?? 0) : 0;
$default_place_2_id = $is_edit ? (int)($existing_result['place_2'] ?? 0) : 0;
$default_notes = $is_edit ? ($existing_result['notes'] ?? '') : '';
$default_coop_outcome = $is_edit ? strtolower($existing_result['outcome'] ?? 'win') : 'win';

$csrf_token = $security->generateCSRFToken();
$page_title = $is_edit ? 'Edit Game Result' : 'Add Game Result';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo htmlspecialchars($game['game_name'] ?? 'Game'); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <style>
        .required-marker { color: var(--color-error, #ef4444); font-weight: bold; }
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: var(--spacing-3, 0.75rem);
            padding: var(--spacing-3, 0.75rem);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md, 8px);
            background: var(--color-surface-muted);
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-2, 0.5rem);
            padding: var(--spacing-2, 0.5rem);
            border-radius: var(--radius-sm, 4px);
            transition: background-color var(--transition-fast, 0.15s);
            cursor: pointer;
            user-select: none;
        }
        .checkbox-item:hover {
            background-color: var(--color-surface);
        }
    </style>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('results', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader($page_title . ' (' . htmlspecialchars($club_name) . ')', htmlspecialchars($game['game_name'] ?? 'Game')); ?>
    </div>

    <div class="container">
        <div class="card">
            <?php if (isset($error)): ?>
                <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php display_session_message('error'); ?>
            <?php display_session_message('success'); ?>

            <form method="POST" class="stack" id="result-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($default_game_type); ?>">

                <?php if (!$is_edit && !empty($all_club_games)): ?>
                    <div class="form-group">
                        <label for="game_id_select" class="form-label">Game: <span class="required-marker">*</span></label>
                        <select id="game_id_select" class="form-control" onchange="if(this.value) window.location.href='add_result.php?club_id=<?php echo $club_id; ?>&game_id=' + this.value;">
                            <?php foreach ($all_club_games as $cg): ?>
                                <option value="<?php echo $cg['game_id']; ?>" <?php echo ($cg['game_id'] == $game_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cg['game_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Game:</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($game['game_name'] ?? ''); ?>" disabled style="background: var(--color-surface-muted);">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="played_at" class="form-label">Date Played: <span class="required-marker">*</span></label>
                    <input type="datetime-local" id="played_at" name="played_at" value="<?php echo $default_played_at; ?>" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Duration: <span class="required-marker">*</span></label>
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <div style="flex: 1; display: flex; align-items: center; gap: 0.35rem;">
                            <select name="duration_hours" id="duration_hours" class="form-control" style="flex: 1;">
                                <?php for ($h = 0; $h <= 12; $h++): ?>
                                    <option value="<?php echo $h; ?>" <?php echo ($h === (int)$default_duration_hours) ? 'selected' : ''; ?>><?php echo $h; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span style="font-size: 0.875rem; color: var(--color-text-muted);">hrs</span>
                        </div>
                        <div style="flex: 1; display: flex; align-items: center; gap: 0.35rem;">
                            <select name="duration_minutes" id="duration_minutes" class="form-control" style="flex: 1;">
                                <?php for ($m = 0; $m <= 50; $m += 5): ?>
                                    <option value="<?php echo $m; ?>" <?php echo ($m === (int)$default_duration_minutes) ? 'selected' : ''; ?>><?php echo $m; ?></option>
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
                            <input type="radio" name="game_type" value="winner_losers" class="form-check-input" <?php echo ($default_game_type === 'winner_losers') ? 'checked' : ''; ?> onchange="toggleGameType()"> Winner/Losers
                        </label>
                        <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="game_type" value="ranked" class="form-check-input" <?php echo ($default_game_type === 'ranked') ? 'checked' : ''; ?> onchange="toggleGameType()"> Ranked (1st, 2nd, 3rd...)
                        </label>
                        <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="game_type" value="teams" class="form-check-input" <?php echo ($default_game_type === 'teams') ? 'checked' : ''; ?> onchange="toggleGameType()"> Teams
                        </label>
                        <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="game_type" value="cooperative" class="form-check-input" <?php echo ($default_game_type === 'cooperative') ? 'checked' : ''; ?> onchange="toggleGameType()"> Cooperative
                        </label>
                    </div>
                </div>

                <div class="form-group" id="winner-section">
                    <label for="winner_id" class="form-label">Winner: <span class="required-marker">*</span></label>
                    <select id="winner_id" name="winner_id" class="form-control">
                        <option value="">Select Winner</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>" <?php echo ((int)$member['id'] === $default_winner_id) ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $member['id']; ?>" <?php echo ((int)$member['id'] === $default_place_2_id) ? 'selected' : ''; ?>>
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
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                        <label class="form-label" style="margin:0;">Select Losers: <span class="required-marker">*</span></label>
                        <div style="display:flex; gap:0.5rem;">
                            <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.loser-checkbox', true)">Select All</button>
                            <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.loser-checkbox', false)">Uncheck All</button>
                        </div>
                    </div>
                    <div id="losers-checkbox-list" class="checkbox-grid">
                        <?php foreach ($members as $member): ?>
                            <label for="loser_<?php echo $member['id']; ?>" class="form-check checkbox-item">
                                <input type="checkbox" name="losers[]" id="loser_<?php echo $member['id']; ?>" value="<?php echo $member['id']; ?>" class="form-check-input loser-checkbox" <?php echo ($is_edit ? in_array($member['id'], $edit_losers) : true) ? 'checked' : ''; ?>>
                                <span class="form-check-label"><?php echo htmlspecialchars($member['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="cooperative-section" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Outcome: <span class="required-marker">*</span></label>
                        <div class="radio-group" style="display:flex; gap:1.5rem;">
                            <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                <input type="radio" name="coop_outcome" value="win" class="form-check-input" <?php echo ($default_coop_outcome === 'win') ? 'checked' : ''; ?>> Victory
                            </label>
                            <label class="radio-label" style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                <input type="radio" name="coop_outcome" value="loss" class="form-check-input" <?php echo ($default_coop_outcome === 'loss') ? 'checked' : ''; ?>> Defeat
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                            <label class="form-label" style="margin:0;">Select Players: <span class="required-marker">*</span></label>
                            <div style="display:flex; gap:0.5rem;">
                                <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.coop-checkbox', true)">Select All</button>
                                <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.coop-checkbox', false)">Uncheck All</button>
                            </div>
                        </div>
                        <div id="coop-checkbox-list" class="checkbox-grid">
                            <?php foreach ($members as $member): ?>
                                <label for="coop_player_<?php echo $member['id']; ?>" class="form-check checkbox-item">
                                    <input type="checkbox" name="coop_players[]" id="coop_player_<?php echo $member['id']; ?>" value="<?php echo $member['id']; ?>" class="form-check-input coop-checkbox" <?php echo ($is_edit ? in_array($member['id'], $edit_coop_players) : true) ? 'checked' : ''; ?>>
                                    <span class="form-check-label"><?php echo htmlspecialchars($member['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
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
                                    <option value="<?php echo $team['id']; ?>" <?php echo ($is_edit && (int)$team['id'] === $default_winner_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                                <label class="form-label" style="margin:0;">Select Losing Team(s): <span class="required-marker">*</span></label>
                                <div style="display:flex; gap:0.5rem;">
                                    <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.team-loser-checkbox', true)">Select All</button>
                                    <button type="button" class="btn btn--subtle btn--small" style="padding:0.2rem 0.5rem; font-size:0.8rem;" onclick="toggleAllCheckboxes('.team-loser-checkbox', false)">Uncheck All</button>
                                </div>
                            </div>
                            <div id="team-losers-checkbox-list" class="checkbox-grid">
                                <?php foreach ($teams as $team): ?>
                                    <label for="team_loser_<?php echo $team['id']; ?>" class="form-check checkbox-item">
                                        <input type="checkbox" name="team_losers[]" id="team_loser_<?php echo $team['id']; ?>" value="<?php echo $team['id']; ?>" class="form-check-input team-loser-checkbox" <?php echo ($is_edit && (int)$team['id'] === $default_place_2_id) ? 'checked' : ''; ?>>
                                        <span class="form-check-label"><?php echo htmlspecialchars($team['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="notes" class="form-label">Notes:</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($default_notes); ?></textarea>
                </div>

                <div style="display:flex; gap:0.5rem; margin-top: 1rem; align-items:center; flex-wrap:wrap;">
                    <button type="submit" class="btn btn--primary"><?php echo $is_edit ? 'Save Changes' : 'Save Result'; ?></button>
                    <a href="results.php?club_id=<?php echo $club_id; ?>" class="btn btn--subtle">Back to Results</a>
                    <?php if ($is_edit): ?>
                        <button type="button" class="btn btn--danger" style="margin-left: auto;" onclick="if(confirm('Are you sure you want to delete this result?')) document.getElementById('delete-form').submit()">Delete Result</button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($is_edit): ?>
                <form method="POST" id="delete-form" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($default_game_type); ?>">
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleAllCheckboxes(selector, checkedState) {
        document.querySelectorAll(selector).forEach(cb => {
            const item = cb.closest('.checkbox-item');
            if (!item || item.style.display !== 'none') {
                if (cb.checked !== checkedState) {
                    cb.checked = checkedState;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });
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
        } else {
            // winner_losers
            winnerSection.style.display = 'block';
            rankedSection.style.display = 'none';
            losersSection.style.display = 'block';
            coopSection.style.display = 'none';
            if (teamsSection) teamsSection.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleGameType();

        const isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
        const form = document.getElementById('result-form');
        const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

        if (isEdit && form && submitBtn) {
            function getFormState() {
                const formData = new FormData(form);
                const state = [];
                for (let [key, value] of formData.entries()) {
                    if (key !== 'csrf_token' && key !== 'action') {
                        state.push(key + '=' + value);
                    }
                }
                return state.sort().join('&');
            }

            const initialState = getFormState();
            submitBtn.disabled = true;

            const checkChanges = function() {
                submitBtn.disabled = (getFormState() === initialState);
            };

            form.addEventListener('input', checkChanges);
            form.addEventListener('change', checkChanges);
        }
    });
    </script>
    <script src="../js/sidebar.js"></script>
</body>
</html>
