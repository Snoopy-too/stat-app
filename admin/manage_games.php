<?php
declare(strict_types=1);
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
ensure_game_type_column_exists($pdo);
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

$demo = isset($_GET['demo']) || isset($_GET['preview']);
if (!$demo && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;
$user_clubs = [];

try {
    $stmt = $pdo->query("SELECT club_id, club_name FROM clubs ORDER BY club_name ASC");
    $user_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$club_id && !empty($_SESSION['current_club_id'])) {
    $club_id = (int)$_SESSION['current_club_id'];
}
if (!$club_id && !empty($_SESSION['club_id'])) {
    $club_id = (int)$_SESSION['club_id'];
}
if (!$club_id && !empty($user_clubs)) {
    $club_id = (int)$user_clubs[0]['club_id'];
}
if ($club_id > 0) {
    $_SESSION['current_club_id'] = $club_id;
    $_SESSION['club_id'] = $club_id;
}

$club_name = 'Meeple & Dice Club';
if ($club_id && !$demo) {
    try {
        $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
        $stmt->execute([$club_id]);
        $club_name = $stmt->fetchColumn() ?: 'Meeple & Dice Club';
    } catch (Exception $e) {}
}

$csrf_token = $security->generateCSRFToken();

// Handle game creation/deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['game_id'])) {
        $del_game_id = (int)$_POST['game_id'];
        $post_club_id = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : (int)$club_id;

        try {
            $stmt = $pdo->prepare("
                SELECT (SELECT COUNT(*) FROM game_results WHERE game_id = ?) +
                       (SELECT COUNT(*) FROM team_game_results WHERE game_id = ?) +
                       (SELECT COUNT(*) FROM cooperative_game_results WHERE game_id = ?) AS total_plays
            ");
            $stmt->execute([$del_game_id, $del_game_id, $del_game_id]);
            $total_plays = (int)$stmt->fetchColumn();

            if ($total_plays > 0) {
                $_SESSION['error'] = "Cannot delete game because it has existing match results.";
            } else {
                $stmt = $pdo->prepare("SELECT game_image FROM games WHERE game_id = ? AND club_id = ?");
                $stmt->execute([$del_game_id, $post_club_id]);
                $img_name = $stmt->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM games WHERE game_id = ? AND club_id = ?");
                $stmt->execute([$del_game_id, $post_club_id]);

                if ($img_name && !filter_var($img_name, FILTER_VALIDATE_URL)) {
                    $img_path = '../images/game_images/' . $img_name;
                    if (file_exists($img_path)) {
                        $check_img = $pdo->prepare("SELECT COUNT(*) FROM games WHERE game_image = ?");
                        $check_img->execute([$img_name]);
                        if ((int)$check_img->fetchColumn() === 0) {
                            @unlink($img_path);
                        }
                    }
                }

                $_SESSION['success'] = "Game deleted successfully!";
            }
        } catch (Throwable $e) {
            $_SESSION['error'] = "Error deleting game: " . $e->getMessage();
        }
        header("Location: manage_games.php" . ($post_club_id ? "?club_id=$post_club_id" : ""));
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create' && !empty($_POST['game_name'])) {
        $post_club_id = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : (int)$club_id;
        if ($post_club_id > 0) {
            $game_image = null;
            $uploadError = null;
            if (isset($_FILES['game_image']) && $_FILES['game_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['game_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['game_image'];
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    $maxSize = 1 * 1024 * 1024;
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $actualMime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($extension, $allowedExtensions) || !in_array($actualMime, $allowedMimes)) {
                        $uploadError = "Invalid file type. Only JPG, PNG, and GIF allowed.";
                    } elseif ($file['size'] > $maxSize) {
                        $uploadError = "File is too large. Max size is 1MB.";
                    } else {
                        $uploadDir = '../images/game_images/';
                        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                        $filename = 'game_' . uniqid() . '_' . time() . '.' . $extension;
                        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                            require_once '../includes/ImageHelper.php';
                            ImageHelper::optimizeImage($uploadDir . $filename, $uploadDir . $filename);
                            $game_image = $filename;
                        } else {
                            $uploadError = "Failed to move uploaded file.";
                        }
                    }
                }
            }

            // Handle image URL link (if no file uploaded)
            if (empty($uploadError) && empty($game_image) && !empty($_POST['image_url'])) {
                $imageUrl = trim($_POST['image_url']);
                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $game_image = $imageUrl;
                } else {
                    $uploadError = "Invalid image URL format. Please enter a valid HTTP or HTTPS URL.";
                }
            }

            if ($uploadError) {
                $_SESSION['error'] = $uploadError;
            } else {
                $stmt = $pdo->prepare("INSERT INTO games (club_id, game_name, min_players, max_players, game_image, game_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $post_club_id,
                    trim($_POST['game_name']),
                    (int)($_POST['min_players'] ?? 1),
                    (int)($_POST['max_players'] ?? 4),
                    $game_image,
                    $_POST['game_type'] ?? 'winner_losers'
                ]);
                $_SESSION['success'] = "Game added successfully!";
                header("Location: club_new_results.php?club_id={$post_club_id}");
                exit();
            }
        }
        header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
        exit();
    }
}

// Fetch Games
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'game_name';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';

$valid_sort_columns = ['game_name', 'game_type', 'total_plays', 'last_played'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'game_name';
$order = ($order === 'desc') ? 'desc' : 'asc';

$query = "SELECT g.*, c.club_name, 
          (COALESCE(gr.total, 0) + COALESCE(tgr.total, 0) + COALESCE(cgr.total, 0)) as total_plays,
          (
              SELECT MAX(played_at) FROM (
                  SELECT game_id, played_at FROM game_results
                  UNION ALL
                  SELECT game_id, played_at FROM team_game_results
                  UNION ALL
                  SELECT game_id, played_at FROM cooperative_game_results
              ) all_res WHERE all_res.game_id = g.game_id
          ) as last_played
          FROM games g 
          JOIN clubs c ON g.club_id = c.club_id
          LEFT JOIN (
              SELECT game_id, COUNT(result_id) as total 
              FROM game_results 
              GROUP BY game_id
          ) gr ON g.game_id = gr.game_id
          LEFT JOIN (
              SELECT game_id, COUNT(result_id) as total 
              FROM team_game_results 
              GROUP BY game_id
          ) tgr ON g.game_id = tgr.game_id
          LEFT JOIN (
              SELECT game_id, COUNT(result_id) as total 
              FROM cooperative_game_results 
              GROUP BY game_id
          ) cgr ON g.game_id = cgr.game_id
          WHERE g.club_id = ?";

$params = [$club_id];
if ($search !== '') {
    $query .= " AND (g.game_name LIKE ? OR g.game_type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " GROUP BY g.game_id, c.club_id, c.club_name, g.game_name, g.min_players, g.max_players, g.game_type";

if ($sort === 'total_plays') {
    $query .= " ORDER BY total_plays $order, g.game_name ASC";
} elseif ($sort === 'last_played') {
    $query .= " ORDER BY last_played $order, g.game_name ASC";
} else {
    $query .= " ORDER BY g.$sort $order";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($demo) {
    $games = get_demo_data('games');
}

$baseUrl = 'manage_games.php?club_id=' . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Games - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Games (' . count($games) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div id="add-game-form-wrapper" style="<?php echo ((isset($_POST['action']) && $_POST['action'] === 'create') || (isset($_GET['action']) && $_GET['action'] === 'add')) ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
            <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add a Game</h3>
            <form method="POST" enctype="multipart/form-data" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="game_name">Game Name <span style="color:var(--color-error,#ef4444);">*</span></label>
                        <input type="text" id="game_name" name="game_name" required placeholder="Game Name" class="form-control"<?php echo ((isset($_POST['action']) && $_POST['action'] === 'create') || (isset($_GET['action']) && $_GET['action'] === 'add')) ? ' autofocus' : ''; ?>>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="game_type">Game Type</label>
                        <select id="game_type" name="game_type" class="form-control">
                            <option value="winner_losers">Winner / Losers</option>
                            <option value="ranked">Ranked (1st, 2nd, etc.)</option>
                            <option value="teams">Teams</option>
                            <option value="cooperative">Cooperative</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Players (Min &ndash; Max)</label>
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <input type="number" id="min_players" name="min_players" value="1" min="1" max="99" placeholder="Min" class="form-control" style="flex:1;">
                            <span style="color:var(--color-text-muted); font-weight:bold;">&ndash;</span>
                            <input type="number" id="max_players" name="max_players" value="4" min="1" max="99" placeholder="Max" class="form-control" style="flex:1;">
                        </div>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Game Image File</label>
                        <div class="upload-zone" id="upload-zone" style="border: 2px dashed var(--color-border); border-radius: 8px; padding: 1rem; text-align: center; background: var(--color-surface); cursor: pointer; position: relative;">
                            <span class="upload-zone__icon" style="font-size: 1.5rem; display: block; margin-bottom: 0.25rem;">🖼️</span>
                            <span class="upload-zone__text" style="font-size: 0.85rem; color: var(--color-text); font-weight: 500;">Click to upload or drag &amp; drop file</span>
                            <span class="upload-zone__hint" style="font-size: 0.75rem; color: var(--color-text-muted); display: block; margin-top: 0.25rem;">JPG, PNG, GIF (Max 1MB)</span>
                            <input type="file" name="game_image" id="game_image" accept="image/jpeg,image/png,image/gif" style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="image_url" class="form-label">Or Image Link / URL</label>
                        <input type="url" name="image_url" id="image_url" placeholder="https://..." class="form-control">
                        <small style="color: var(--color-text-muted); font-size: 0.75rem; display: block; margin-top: 0.35rem;">Paste a direct web link to an image file</small>
                    </div>
                </div>
                <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                    <button type="submit" class="btn btn--primary">Save Game</button>
                    <button type="button" class="btn btn--subtle" onclick="toggleAddGameForm()">Cancel</button>
                </div>
            </form>
        </div>

        <?php
        TableHelper::renderGamesTable($games, [
            'is_admin' => true,
            'base_url' => $baseUrl,
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'club_id' => $club_id
        ]);
        ?>
    </div>

    <script>
        function toggleAddGameForm() {
            const wrapper = document.getElementById('add-game-form-wrapper');
            const btn = document.getElementById('add-game-btn');
            if (!wrapper) return;
            const isHidden = wrapper.style.display === 'none';
            wrapper.style.display = isHidden ? 'block' : 'none';
            if (btn) btn.style.visibility = isHidden ? 'hidden' : 'visible';
        }
        document.getElementById('game_image')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const textEl = document.querySelector('#upload-zone .upload-zone__text');
            if (fileName && textEl) {
                textEl.textContent = 'Selected: ' + fileName;
            }
        });
    </script>
    <?php if (!empty($demo)): ?>
    <style>
    html, body, body * {
        pointer-events: none !important;
        user-select: none !important;
        cursor: default !important;
    }
    img {
        display: none !important;
    }
    *:hover, *:active, *:focus, *:focus-within {
        background: inherit !important;
        background-color: inherit !important;
        color: inherit !important;
        border-color: inherit !important;
        box-shadow: none !important;
        transform: none !important;
        transition: none !important;
        animation: none !important;
        outline: none !important;
        opacity: inherit !important;
    }
    .sidebar__nav a:hover, .sidebar__nav a:active, .sidebar__nav a:focus,
    .data-table tr:hover, .data-table tr:active, .data-table td:hover,
    .btn:hover, .btn:active, .btn:focus, button:hover, button:active,
    .form-control:hover, .form-control:active, .form-control:focus,
    a:hover, a:active, a:focus {
        background: transparent !important;
        background-color: transparent !important;
        color: inherit !important;
        box-shadow: none !important;
        transform: none !important;
    }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('a, button, input, select, textarea, details, summary').forEach(el => {
            el.setAttribute('tabindex', '-1');
            if (el.tagName === 'BUTTON' || el.tagName === 'INPUT' || el.tagName === 'SELECT') {
                el.setAttribute('disabled', 'disabled');
            }
        });
    });
    document.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); }, true);
    document.addEventListener('mouseover', function(e) { e.stopPropagation(); }, true);
    document.addEventListener('mouseenter', function(e) { e.stopPropagation(); }, true);
    document.addEventListener('mouseleave', function(e) { e.stopPropagation(); }, true);
    </script>
    <?php endif; ?>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
</body>
</html>