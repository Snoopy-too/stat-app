<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = !empty($is_admin);
$prefix = $isAdmin ? '../' : '';

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/NavigationHelper.php';
require_once dirname(__DIR__) . '/SecurityUtils.php';
require_once dirname(__DIR__) . '/services/ClubService.php';

ensure_game_type_column_exists($pdo);

if ($isAdmin) {
    $demo = isset($_GET['demo']) || isset($_GET['preview']);
    if (!$demo && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
        header("Location: login.php");
        exit();
    }
}

$security = new SecurityUtils($pdo);
$csrf_token = $security->generateCSRFToken();

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$slug = $_GET['slug'] ?? '';

$clubService = new ClubService($pdo);
$club = null;

if ($club_id || !empty($slug)) {
    $club = $clubService->getClubDetails($club_id, $slug);
}

if (!$club && $isAdmin) {
    if (!empty($_SESSION['current_club_id'])) {
        $club_id = (int)$_SESSION['current_club_id'];
        $club = $clubService->getClubDetails($club_id);
    }
}

if (!$club) {
    $stmt = $pdo->query("SELECT club_id FROM clubs ORDER BY club_id ASC LIMIT 1");
    $firstId = $stmt ? (int)$stmt->fetchColumn() : 0;
    if ($firstId > 0) {
        $club_id = $firstId;
        $club = $clubService->getClubDetails($club_id);
    }
}

if (!$club) {
    header("Location: " . ($isAdmin ? "login.php" : "index.php"));
    exit();
}

$club_id = (int)$club['club_id'];
$club_name = $club['club_name'] ?? 'Club Games';

if ($isAdmin && $club_id > 0) {
    $_SESSION['current_club_id'] = $club_id;
    $_SESSION['club_id'] = $club_id;
}

// Handle Game creation/deletion for Admin
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_games.php?club_id=" . $club_id);
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
                            require_once dirname(__DIR__) . '/ImageHelper.php';
                            ImageHelper::optimizeImage($uploadDir . $filename, $uploadDir . $filename);
                            $game_image = $filename;
                        } else {
                            $uploadError = "Failed to move uploaded file.";
                        }
                    }
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
        header("Location: manage_games.php?club_id=" . $club_id);
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

$baseUrl = ($isAdmin ? 'manage_games.php?club_id=' : 'club_game_list.php?id=') . $club_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games - <?php echo htmlspecialchars($club_name); ?></title>
    <link rel="stylesheet" href="<?php echo $prefix; ?>css/styles.css">
    <script src="<?php echo $prefix; ?>js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php
    if ($isAdmin) {
        NavigationHelper::renderAdminSidebar('games', $club_id, $club_name);
    } else {
        NavigationHelper::renderSidebar('games', $club_id, $club_name);
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage ' . $club_name . ' Games (' . count($games) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php if ($isAdmin): ?>
            <?php display_session_message('success'); ?>
            <?php display_session_message('error'); ?>

            <div id="add-game-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add a Game</h3>
                <form method="POST" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="game_name">Game Name <span style="color:var(--color-error,#ef4444);">*</span></label>
                            <input type="text" id="game_name" name="game_name" required placeholder="Game Name" class="form-control">
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
                            <label for="min_players">Min Players</label>
                            <input type="number" id="min_players" name="min_players" value="1" min="1" max="99" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="max_players">Max Players</label>
                            <input type="number" id="max_players" name="max_players" value="4" min="1" max="99" class="form-control">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:1.25rem;">
                        <label for="game_image">Game Image</label>
                        <input type="file" id="game_image" name="game_image" accept="image/jpeg,image/png,image/gif" class="form-control">
                    </div>
                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <button type="submit" class="btn btn--primary">Save Game</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddGameForm()">Cancel</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php
            TableHelper::renderGamesTable($games, [
                'is_admin' => $isAdmin,
                'base_url' => $baseUrl,
                'sort' => $sort,
                'order' => $order,
                'search' => $search,
                'club_id' => $club_id
            ]);
            ?>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <script>
            function toggleAddGameForm() {
                const wrapper = document.getElementById('add-game-form-wrapper');
                const btn = document.getElementById('add-game-btn');
                if (!wrapper) return;
                const isHidden = wrapper.style.display === 'none';
                wrapper.style.display = isHidden ? 'block' : 'none';
                if (btn) btn.style.visibility = isHidden ? 'hidden' : 'visible';
            }
        </script>
    <?php endif; ?>
    <script src="<?php echo $prefix; ?>js/sidebar.js"></script>
    <script src="<?php echo $prefix; ?>js/form-loading.js"></script>
</body>
</html>
