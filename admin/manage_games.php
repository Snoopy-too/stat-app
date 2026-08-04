<?php
declare(strict_types=1);
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
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

if (!$user_clubs) {
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
                    $stmt = $pdo->prepare("INSERT INTO games (club_id, game_name, min_players, max_players, game_image) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $post_club_id,
                        trim($_POST['game_name']),
                        (int)($_POST['min_players'] ?? 1),
                        (int)($_POST['max_players'] ?? 4),
                        $game_image
                    ]);
                    $_SESSION['success'] = "Game added successfully!";
                }
            } else {
                $_SESSION['error'] = "Please select a club for the game.";
            }
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
if ($search) {
    $query .= ($club_id ? " AND" : " WHERE") . " g.game_name LIKE ?";
    $params[] = "%$search%";
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

if ($demo && empty($games)) {
    $games = [
        ['game_id' => 1, 'game_name' => 'Catan', 'min_players' => 3, 'max_players' => 4, 'total_plays' => 42, 'club_name' => 'Meeple & Dice Club', 'game_image' => null],
        ['game_id' => 2, 'game_name' => 'Wingspan', 'min_players' => 1, 'max_players' => 5, 'total_plays' => 35, 'club_name' => 'Meeple & Dice Club', 'game_image' => null],
        ['game_id' => 3, 'game_name' => 'Ticket to Ride', 'min_players' => 2, 'max_players' => 5, 'total_plays' => 28, 'club_name' => 'Meeple & Dice Club', 'game_image' => null],
        ['game_id' => 4, 'game_name' => 'Codenames', 'min_players' => 2, 'max_players' => 8, 'total_plays' => 54, 'club_name' => 'Meeple & Dice Club', 'game_image' => null]
    ];
}

// Get club name if club_id is set
$club_name = '';
if ($club_id) {
    $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    $club_name = $club ? $club['club_name'] : '';
}

// Generate CSRF token for forms
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
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
        <div class="header-actions">
            <button type="button" class="btn btn--primary btn--small" onclick="toggleAddGameForm()"><span style="color: white; font-weight: bold; margin-right: 0.35rem;">+</span>Add a Game</button>
            <?php if ($club_id): ?>
                <a href="../club_game_list.php?id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small" target="_blank" title="View on public site">👁️ Preview</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="container">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="card">
            <div class="card-header">
                <h2>Games (<?php echo count($games); ?>)</h2>
            </div>

            <div id="add-game-form-wrapper" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? '' : 'display:none;'; ?> margin: 1rem 0 1.25rem 0; padding: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg, 0.75rem); background: var(--color-surface-muted);">
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
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="game_image">Game Image <small class="text-muted">(file)</small></label>
                            <input type="file" id="game_image" name="game_image" accept="image/jpeg,image/png,image/gif" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="image_url">Or Image URL</label>
                            <input type="url" id="image_url" name="image_url" placeholder="https://..." class="form-control">
                        </div>
                    </div>
                    <div class="form-group" style="display:flex; gap:0.5rem; margin-bottom:0;">
                        <button type="submit" class="btn btn--primary">Save Game</button>
                        <button type="button" class="btn btn--subtle" onclick="toggleAddGameForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="card-toolbar">
                <button type="button" class="btn btn--primary" id="toggle-add-game-btn" onclick="toggleAddGameForm()" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? 'visibility:hidden;' : ''; ?>">
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
            </div>

            <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
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
                    <?php if (empty($games)): ?>
                        <tr>
                            <td colspan="<?php echo $club_id ? '7' : '8'; ?>" class="text-center text-muted" style="padding: 1.5rem;">No games created yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($games as $game): ?>
                        <tr>
                            <?php if (!$club_id): ?><td data-label="Club"><?php echo htmlspecialchars($game['club_name']); ?></td><?php endif; ?>
                            <td data-label="Image">
                                <?php if ($game['game_image']): ?>
                                    <img src="<?php echo htmlspecialchars(get_game_image_url($game['game_image'], '../')); ?>" alt="" class="game-thumbnail" loading="lazy">
                                <?php else: ?>
                                    <div class="game-thumbnail game-thumbnail--skeleton" title="No image uploaded"></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Game Name" style="text-align: left;"><?php echo htmlspecialchars($game['game_name']); ?></td>
                            <td data-label="Players"><?php echo $game['min_players'] . '-' . $game['max_players']; ?></td>
                            <td data-label="Added"><?php echo date('M j, Y', strtotime($game['created_at'])); ?></td>
                            <td data-label="Total Plays"><?php echo $game['total_plays']; ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="edit_game.php?club_id=<?php echo $game['club_id']; ?>&game_id=<?php echo $game['game_id']; ?>" 
                                       class="btn btn--small btn--secondary">Edit</a>
                                    <a href="results.php?club_id=<?php echo $game['club_id']; ?>&game_id=<?php echo $game['game_id']; ?>" 
                                       class="btn btn--small btn--subtle">Results</a>
                                    
                                    <?php if ($game['total_plays'] == 0): ?>
                                        <form method="POST" style="display:inline;" id="delete-form-<?php echo $game['game_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="game_id" value="<?php echo $game['game_id']; ?>">
                                            <button type="button" class="btn btn--small btn--danger" 
                                                    onclick="showConfirmDialog(event, {
                                                        title: 'Delete Game?',
                                                        message: 'Are you sure you want to permanently delete \'<?php echo addslashes($game['game_name']); ?>\'? This action cannot be undone.',
                                                        confirmText: 'Delete Game',
                                                        onConfirm: () => document.getElementById('delete-form-<?php echo $game['game_id']; ?>').submit()
                                                    })">
                                                Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn--small btn--danger" 
                                                onclick="showConfirmDialog(event, {
                                                    title: 'Deletion Restricted',
                                                    message: 'This game has associated match results. Please ensure all related records have been removed prior to deleting the game entry.',
                                                    confirmText: 'Understood',
                                                    type: 'primary'
                                                })"
                                                title="Game cannot be deleted while it has match results">
                                            Delete
                                        </button>
                                    <?php endif; ?>
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
})();

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
</script>
</html>