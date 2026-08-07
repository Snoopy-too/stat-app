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

if ($demo || !$user_clubs) {
    $club_id = 1;
    $user_clubs = [['club_id' => 1, 'club_name' => 'Meeple & Dice Club']];
}

// Handle game creation/deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
        exit();
    }

    if (isset($_POST['bulk_action']) && !empty($_POST['selected_games'])) {
        $selected_games = array_map('intval', $_POST['selected_games']);
        $bulk_action = $_POST['bulk_action'];

        if ($bulk_action === 'bulk_delete') {
            $password = $_POST['password'] ?? '';
            if (!verify_admin_password($password, $pdo)) {
                $_SESSION['error'] = "Incorrect password. Bulk delete cancelled.";
                header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
                exit();
            }
            $deleted_count = 0;
            $skipped_count = 0;

            foreach ($selected_games as $del_game_id) {
                if ($club_id) {
                    $stmt = $pdo->prepare("SELECT 1 FROM games WHERE game_id = ? AND club_id = ?");
                    $stmt->execute([$del_game_id, $club_id]);
                    if (!$stmt->fetch()) {
                        continue;
                    }
                }

                $stmt = $pdo->prepare("
                    SELECT 
                    (SELECT COUNT(*) FROM game_results WHERE game_id = ?) + 
                    (SELECT COUNT(*) FROM team_game_results WHERE game_id = ?) as total_plays
                ");
                $stmt->execute([$del_game_id, $del_game_id]);
                $count = $stmt->fetchColumn();

                if ($count > 0) {
                    $skipped_count++;
                } else {
                    $stmt = $pdo->prepare("SELECT game_image FROM games WHERE game_id = ?");
                    $stmt->execute([$del_game_id]);
                    $old_image = $stmt->fetchColumn();

                    $stmt = $pdo->prepare("DELETE FROM games WHERE game_id = ?");
                    if ($stmt->execute([$del_game_id])) {
                        if ($old_image) {
                            $image_path = '../images/game_images/' . $old_image;
                            if (file_exists($image_path)) {
                                @unlink($image_path);
                            }
                        }
                        $deleted_count++;
                    }
                }
            }

            if ($deleted_count > 0 && $skipped_count === 0) {
                $_SESSION['success'] = "$deleted_count selected game(s) deleted successfully!";
            } elseif ($deleted_count > 0 && $skipped_count > 0) {
                $_SESSION['success'] = "$deleted_count game(s) deleted. $skipped_count game(s) skipped due to existing match results.";
            } else {
                $_SESSION['error'] = "Could not delete selected game(s) because they have associated match results.";
            }
        }
        header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
        exit();
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create' && !empty($_POST['game_name'])) {
            $post_club_id = isset($_POST['club_id']) ? (int)$_POST['club_id'] : $club_id;
            if ($post_club_id > 0) {
                $game_image = null;
                $uploadError = null;
                if (isset($_FILES['game_image']) && $_FILES['game_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['game_image']['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES['game_image'];
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                        $maxSize = 1 * 1024 * 1024; // 1MB
                        
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
                            if (!file_exists($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            $filename = 'game_' . uniqid() . '_' . time() . '.' . $extension;
                            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                                require_once '../includes/ImageHelper.php';
                                ImageHelper::optimizeImage($uploadDir . $filename, $uploadDir . $filename);
                                $game_image = $filename;
                            } else {
                                $uploadError = "Failed to move uploaded file. Check folder permissions.";
                            }
                        }
                    }
                }
                
                if (empty($uploadError) && !empty($_POST['image_url'])) {
                    $imageUrl = trim($_POST['image_url']);
                    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                        $game_image = $imageUrl;
                    } else {
                        $uploadError = "Invalid image URL format.";
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
                }
            } else {
                $_SESSION['error'] = "Please select a club for the game.";
            }
            header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
            exit();
        } elseif ($_POST['action'] === 'delete' && isset($_POST['game_id'])) {
            $del_game_id = (int)$_POST['game_id'];
            
            // Verify game belongs to club if club_id is set
            if ($club_id) {
                $stmt = $pdo->prepare("SELECT 1 FROM games WHERE game_id = ? AND club_id = ?");
                $stmt->execute([$del_game_id, $club_id]);
                if (!$stmt->fetch()) {
                    $_SESSION['error'] = "Unauthorized game access.";
                    header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
                    exit();
                }
            }
            
            // Double check results count for safety
            $stmt = $pdo->prepare("
                SELECT 
                (SELECT COUNT(*) FROM game_results WHERE game_id = ?) + 
                (SELECT COUNT(*) FROM team_game_results WHERE game_id = ?) as total_plays
            ");
            $stmt->execute([$del_game_id, $del_game_id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $_SESSION['error'] = "Deletion Restricted: This game has associated match results. Please ensure all related records have been removed prior to deleting the game entry.";
            } else {
                // Get image name to delete file
                $stmt = $pdo->prepare("SELECT game_image FROM games WHERE game_id = ?");
                $stmt->execute([$del_game_id]);
                $old_image = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("DELETE FROM games WHERE game_id = ?");
                if ($stmt->execute([$del_game_id])) {
                    if ($old_image) {
                        $image_path = '../images/game_images/' . $old_image;
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }
                    }
                    $_SESSION['success'] = "Game deleted successfully!";
                } else {
                    $_SESSION['error'] = "An error occurred while attempting to delete the game.";
                }
            }
        }
        header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
        exit();
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'game_name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';
// Define valid sort columns
$valid_sort_columns = ['game_name', 'created_at', 'total_plays'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'game_name';
$order = ($order === 'desc') ? 'desc' : 'asc';
// Build the query
$query = "SELECT g.*, c.club_name, 
          (COALESCE(gr.total, 0) + COALESCE(tgr.total, 0)) as total_plays 
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
          ) tgr ON g.game_id = tgr.game_id"; // Adjusted subqueries for accurate counting
$params = [];
if ($club_id) {
    $query .= " WHERE g.club_id = ?";
    $params[] = $club_id;
}
$query .= " GROUP BY g.game_id, c.club_id, c.club_name, g.game_name, g.min_players, g.max_players, g.created_at";
if ($sort === 'total_plays') {
    $query .= " ORDER BY total_plays $order, g.game_name ASC";
} else {
    $query .= " ORDER BY g.$sort $order";
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($demo) {
    $games = [
        ['game_id' => 1, 'game_name' => 'Catan', 'min_players' => 3, 'max_players' => 4, 'total_plays' => 42, 'club_name' => 'Meeple & Dice Club', 'created_at' => date('Y-m-d H:i:s'), 'game_image' => null],
        ['game_id' => 2, 'game_name' => 'Wingspan', 'min_players' => 1, 'max_players' => 5, 'total_plays' => 35, 'club_name' => 'Meeple & Dice Club', 'created_at' => date('Y-m-d H:i:s'), 'game_image' => null],
        ['game_id' => 3, 'game_name' => 'Ticket to Ride', 'min_players' => 2, 'max_players' => 5, 'total_plays' => 28, 'club_name' => 'Meeple & Dice Club', 'created_at' => date('Y-m-d H:i:s'), 'game_image' => null],
        ['game_id' => 4, 'game_name' => 'Codenames', 'min_players' => 2, 'max_players' => 8, 'total_plays' => 54, 'club_name' => 'Meeple & Dice Club', 'created_at' => date('Y-m-d H:i:s'), 'game_image' => null]
    ];
}

// Get club name if club_id is set
$club_name = '';
if ($demo) {
    $club_name = 'Meeple & Dice Club';
} else if ($club_id) {
    $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    $club_name = $club ? $club['club_name'] : '';
}

// Generate CSRF token for forms
$csrf_token = $security->generateCSRFToken();
?>

<?php
$themeParam = $_GET['theme'] ?? $_GET['club_theme'] ?? ($demo ? 'arcade' : '');
$htmlThemeAttrs = $themeParam ? 'data-club-theme="' . htmlspecialchars($themeParam) . '" data-theme="dark" data-theme-locked="true"' : '';
?>
<!DOCTYPE html>
<html lang="en" <?php echo $htmlThemeAttrs; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Games - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <style>
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-6);
            border-bottom: 2px solid var(--color-border);
            padding-bottom: var(--spacing-3);
        }
        .section-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--color-heading);
        }
        .game-thumbnail {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-border);
            display: block;
            background: var(--color-surface-muted);
            overflow: hidden;
            position: relative;
        }
        .game-thumbnail--skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, 
                rgba(17, 24, 39, 0.03) 25%, 
                rgba(17, 24, 39, 0.06) 37%, 
                rgba(17, 24, 39, 0.03) 63%);
            background-size: 400% 100%;
            animation: skeleton-loading 2s ease infinite;
        }
        @keyframes skeleton-loading {
            0% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @media (max-width: 48rem) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-2);
            }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Games' . ($club_name ? ' (' . $club_name . ')' : '')); ?>
    </div>
    
    <div class="container">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <h2>Games (<?php echo count($games); ?>)</h2>
            </div>

            <?php 
            $showAddForm = (isset($_GET['action']) && $_GET['action'] === 'add') || (isset($_POST['action']) && $_POST['action'] === 'create');
            ?>
            <div id="add-game-form-wrapper" style="<?php echo $showAddForm ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem; color:var(--color-heading);">Add New Game</h3>
                <form method="POST" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="create">
                    <?php if ($club_id): ?>
                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <?php endif; ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="game_name">Game Name <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <input type="text" id="game_name" name="game_name" required class="form-control" placeholder="e.g. Catan">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="min_players">Min Players</label>
                            <input type="number" id="min_players" name="min_players" value="1" min="1" max="99" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="max_players">Max Players</label>
                            <input type="number" id="max_players" name="max_players" value="4" min="1" max="99" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="game_type">Game Type <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <select name="game_type" id="game_type" class="form-control">
                                <option value="winner_losers">Winner/Losers</option>
                                <option value="ranked">Ranked (1st, 2nd, 3rd...)</option>
                                <option value="teams">Teams</option>
                                <option value="cooperative">Cooperative</option>
                            </select>
                        </div>
                        <?php if (!$club_id): ?>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="game_club_id">Club <span style="color:var(--color-error,#ef4444); font-weight:bold;">*</span></label>
                            <select name="club_id" id="game_club_id" required class="form-control">
                                <option value="">Select Club</option>
                                <?php foreach ($user_clubs as $c): ?>
                                    <option value="<?php echo $c['club_id']; ?>"><?php echo htmlspecialchars($c['club_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Game Image</label>
                            <div class="upload-zone" id="upload-zone" style="border: 2px dashed var(--color-border); border-radius: 8px; padding: 1rem; text-align: center; background: var(--color-surface); cursor: pointer; position: relative;">
                                <span class="upload-zone__icon" style="font-size: 1.5rem; display: block; margin-bottom: 0.25rem;">🖼️</span>
                                <span class="upload-zone__text" style="font-size: 0.9rem; color: var(--color-text);">Click to upload or drag & drop file</span>
                                <span class="upload-zone__hint" style="font-size: 0.75rem; color: var(--color-text-muted); display: block; margin-top: 0.25rem;">JPG, PNG, GIF (Max 1MB)</span>
                                <input type="file" name="game_image" id="game_image" accept="image/jpeg,image/png,image/gif" style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="image_url">Or Image Link / URL</label>
                            <input type="url" id="image_url" name="image_url" placeholder="https://..." class="form-control">
                            <small style="color: var(--color-text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">Paste a direct web link to an image file</small>
                        </div>
                    </div>

                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <button type="submit" class="btn btn--primary">Save Game</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddGameForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="card-toolbar">
                <button type="button" class="btn btn--primary" id="toggle-add-game-btn" onclick="toggleAddGameForm()" style="<?php echo $showAddForm ? 'visibility:hidden;' : ''; ?>">
                    <span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>Add a Game
                </button>
                <form method="GET" class="toolbar-group toolbar-group--grow" id="filter-form">
                    <?php if ($club_id): ?>
                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo strtolower($order); ?>">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="Search games..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <a href="?<?php echo $club_id ? 'club_id=' . $club_id : ''; ?>" class="btn btn--subtle btn--small">Reset</a>
                    </div>
                </form>
                <form method="POST" class="toolbar-group" id="bulk-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <?php if ($club_id): ?>
                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <?php endif; ?>
                    <select name="bulk_action" id="bulk-action-select" class="form-control form-control--sm" onchange="executeBulkAction(this)">
                        <option value="">Bulk Actions</option>
                        <option value="bulk_delete">Delete Selected</option>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all" class="form-check-input"></th>
                        <?php if (!$club_id): ?><th>Club</th><?php endif; ?>
                        <th style="width: 50px; text-align: left;">Image</th>
                        <th style="text-align: left;">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'game_name','order'=>($sort==='game_name'&&strtolower($order)==='asc')?'desc':'asc'])); ?>" class="table-sort-link sort-link">
                                <span>Game Name</span>
                                <?php if ($sort === 'game_name'): ?>
                                    <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Players</th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'created_at','order'=>($sort==='created_at'&&strtolower($order)==='asc')?'desc':'asc'])); ?>" class="table-sort-link sort-link">
                                <span>Added</span>
                                <?php if ($sort === 'created_at'): ?>
                                    <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'total_plays','order'=>($sort==='total_plays'&&strtolower($order)==='asc')?'desc':'asc'])); ?>" class="table-sort-link sort-link">
                                <span>Total Plays</span>
                                <?php if ($sort === 'total_plays'): ?>
                                    <span class="table-sort-link__icon"><?php echo strtolower($order) === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="noSearchMatch" style="display: none;">
                        <td colspan="<?php echo $club_id ? '7' : '8'; ?>" class="text-center text-muted" style="padding: 1.5rem;">No games match your search.</td>
                    </tr>
                    <?php if (empty($games)): ?>
                        <tr>
                            <td colspan="<?php echo $club_id ? '7' : '8'; ?>" class="text-center text-muted" style="padding: 1.5rem;">No games created yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($games as $game): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_games[]" form="bulk-form"
                                       value="<?php echo $game['game_id']; ?>" class="form-check-input game-checkbox">
                            </td>
                            <?php if (!$club_id): ?><td data-label="Club"><?php echo htmlspecialchars($game['club_name']); ?></td><?php endif; ?>
                            <td data-label="Image">
                                <?php if (!empty($game['game_image'])): ?>
                                    <img src="<?php echo htmlspecialchars(get_game_image_url($game['game_image'], '../')); ?>" alt="" class="game-thumbnail" loading="lazy" onerror="this.onerror=null; this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-block';">
                                    <div class="game-thumbnail game-thumbnail--skeleton" style="display:none;" title="No image uploaded">🎲</div>
                                <?php else: ?>
                                    <div class="game-thumbnail game-thumbnail--skeleton" title="No image uploaded">🎲</div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Game Name" style="text-align: left;"><?php echo htmlspecialchars($game['game_name']); ?></td>
                            <td data-label="Players"><?php echo $game['min_players'] . '-' . $game['max_players']; ?></td>
                            <td data-label="Added"><?php echo date('M j, Y', strtotime($game['created_at'])); ?></td>
                            <td data-label="Total Plays"><?php echo $game['total_plays']; ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="edit_game.php?club_id=<?php echo $game['club_id']; ?>&game_id=<?php echo $game['game_id']; ?>" 
                                       class="btn btn--small btn--secondary">View/Edit</a>
                                    <a href="results.php?club_id=<?php echo $game['club_id']; ?>&game_id=<?php echo $game['game_id']; ?>" 
                                       class="btn btn--small btn--subtle">Results</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
<script>
(function() {
    // Save scroll position before navigating away (sort/filter links)
    function saveScrollPosition() {
        try {
            sessionStorage.setItem('manage_games_scroll', window.scrollY);
        } catch (e) {}
    }
    // Attach to all sort/filter links
    document.addEventListener('DOMContentLoaded', function() {
        var links = document.querySelectorAll('a.sort-link, .search-form button[type="submit"], .search-form .button');
        links.forEach(function(link) {
            link.addEventListener('click', saveScrollPosition);
        });
        // Restore scroll position
        var scroll = sessionStorage.getItem('manage_games_scroll');
        if (scroll !== null) {
            window.scrollTo(0, parseInt(scroll, 10));
            sessionStorage.removeItem('manage_games_scroll');
        }
    });
    var forms = document.querySelectorAll('.search-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', saveScrollPosition);
    });

    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        const filterGames = () => {
            const query = searchInput.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.data-table tbody tr:not(#noSearchMatch)');
            let visibleCount = 0;
            let hasOriginalRows = false;
            rows.forEach(row => {
                if (row.querySelector('.text-muted') && rows.length === 1) return;
                hasOriginalRows = true;
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            const noMatch = document.getElementById('noSearchMatch');
            if (noMatch) {
                noMatch.style.display = (visibleCount === 0 && query !== '' && hasOriginalRows) ? '' : 'none';
            }
        };
        searchInput.addEventListener('input', filterGames);
        if (searchInput.value) filterGames();
    }

    document.getElementById('select-all')?.addEventListener('change', function() {
        document.querySelectorAll('.game-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
})();

function executeBulkAction(selectEl) {
    const action = selectEl.value;
    if (!action) return;

    const selectedCheckboxes = document.querySelectorAll('.game-checkbox:checked');

    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one game.');
        selectEl.value = '';
        return;
    }

    if (action === 'bulk_delete') {
        showConfirmDialog(null, {
            title: '⚠️ Delete Selected Games?',
            message: `Are you sure you want to delete ${selectedCheckboxes.length} selected game(s)?`,
            confirmText: 'Delete Games',
            cancelText: 'Cancel',
            type: 'danger',
            warningMessage: 'Games with no recorded match results will be permanently removed.',
            requirePassword: true,
            onConfirm: (password) => {
                const bulkForm = document.getElementById('bulk-form');
                let passInput = bulkForm.querySelector('input[name="password"]');
                if (!passInput) {
                    passInput = document.createElement('input');
                    passInput.type = 'hidden';
                    passInput.name = 'password';
                    bulkForm.appendChild(passInput);
                }
                passInput.value = password;
                bulkForm.submit();
            },
            onCancel: () => {
                selectEl.value = '';
            }
        });
    }
}

function toggleAddGameForm() {
    const wrapper = document.getElementById('add-game-form-wrapper');
    const btn = document.getElementById('toggle-add-game-btn');
    if (!wrapper) return;
    if (wrapper.style.display === 'none' || wrapper.style.display === '') {
        wrapper.style.display = 'block';
        if (btn) btn.style.visibility = 'hidden';
        document.getElementById('game_name')?.focus();
    } else {
        wrapper.style.display = 'none';
        if (btn) btn.style.visibility = 'visible';
    }
}

document.getElementById('game_image')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const uploadZone = document.getElementById('upload-zone');
    if (!uploadZone) return;
    if (file) {
        uploadZone.style.borderColor = 'var(--color-primary)';
        const textSpan = uploadZone.querySelector('.upload-zone__text');
        if (textSpan) textSpan.textContent = 'Selected: ' + file.name;
    }
});
</script>
</html>