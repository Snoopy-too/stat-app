<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);

// Get club_id from URL
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;

// Validate club_id exists if provided
if ($club_id) {
    $stmt = $pdo->prepare("SELECT club_id FROM clubs WHERE club_id = ?");
    $stmt->execute([$club_id]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Invalid club ID provided.";
        header("Location: dashboard.php");
        exit();
    }
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
            $game_image = null;
            
            // Handle image upload
            if (isset($_FILES['game_image']) && $_FILES['game_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['game_image'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                $maxSize = 2 * 1024 * 1024; // 2MB for game images
                
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $actualMime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (in_array($extension, $allowedExtensions) && in_array($actualMime, $allowedMimes) && $file['size'] <= $maxSize) {
                    $uploadDir = '../images/game_images/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $filename = 'game_' . uniqid() . '_' . time() . '.' . $extension;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        $game_image = $filename;
                    }
                }
            }

            $stmt = $pdo->prepare("INSERT INTO games (club_id, game_name, min_players, max_players, game_image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $club_id,
                trim($_POST['game_name']),
                $_POST['min_players'],
                $_POST['max_players'],
                $game_image
            ]);
            $_SESSION['success'] = "Game added successfully!";
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
        .admin-form-shell {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-4);
        }
        .upload-zone {
            border: 2px dashed var(--color-border-strong);
            border-radius: var(--radius-lg);
            padding: var(--spacing-6);
            text-align: center;
            transition: all var(--transition-fast);
            background: var(--color-surface-muted);
            cursor: pointer;
            position: relative;
        }
        .upload-zone:hover {
            border-color: var(--color-primary);
            background: rgba(var(--color-primary-rgb), 0.05);
        }
        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .upload-zone__icon {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-2);
            display: block;
        }
        .upload-zone__text {
            display: block;
            font-weight: var(--font-weight-medium);
            color: var(--color-heading);
        }
        .upload-zone__hint {
            font-size: var(--font-size-xs);
            color: var(--color-text-soft);
        }
        .modern-card {
            border: none;
            box-shadow: var(--shadow-md);
            border-radius: var(--radius-xl);
            padding: var(--spacing-6);
            background: var(--color-surface);
        }
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
            line-height: 48px;
            text-align: center;
            font-size: 1.25rem;
        }
        @media (max-width: 48rem) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php
    // Render breadcrumbs
    if ($club_id) {
        NavigationHelper::renderBreadcrumbs([
            ['label' => 'Dashboard', 'url' => 'dashboard.php'],
            ['label' => 'Clubs', 'url' => 'manage_clubs.php'],
            'Manage Games'
        ]);
    } else {
        NavigationHelper::renderBreadcrumbs([
            ['label' => 'Dashboard', 'url' => 'dashboard.php'],
            'Manage Games'
        ]);
    }
    ?>
    
    <div class="header">
        <div class="header-title-group">
            <?php 
            $subtitle = $club_name ? $club_name : 'All clubs';
            NavigationHelper::renderHeaderTitle('Manage Games', $subtitle, 'dashboard.php', false); 
            ?>
        </div>
        <div class="header-actions">
            <?php if ($club_id): ?>
                <a href="../club_game_list.php?id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small" target="_blank" title="View on public site">👁️ Preview</a>
                <a href="dashboard.php" class="btn btn--secondary btn--small">🏠 Dashboard</a>
                <a href="manage_clubs.php" class="btn btn--secondary btn--small">← Back to Clubs</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn--secondary btn--small">← Back to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Render admin navigation
    NavigationHelper::renderAdminNav('games', $club_id);
    if ($club_id && $club_name) {
        NavigationHelper::renderContextBar('Managing games for', $club_name, 'View all clubs', 'manage_clubs.php');
    }
    ?>
    
    <div class="container">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <?php if ($club_id): ?>
        <div class="admin-form-shell">
            <div class="modern-card">
                <div class="section-header">
                    <h2>Add New Game</h2>
                </div>
                <form method="POST" class="form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="game_name" class="form-label">Game Name</label>
                        <input type="text" name="game_name" id="game_name" placeholder="Enter game title..." required class="form-control">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="min_players" class="form-label">Min Players</label>
                            <input type="number" name="min_players" id="min_players" placeholder="e.g. 1" required min="1" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="max_players" class="form-label">Max Players</label>
                            <input type="number" name="max_players" id="max_players" placeholder="e.g. 4" required min="1" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Game Image</label>
                        <div class="upload-zone" id="upload-zone">
                            <span class="upload-zone__icon">🖼️</span>
                            <span class="upload-zone__text">Click to upload or drag & drop</span>
                            <span class="upload-zone__hint">JPG, PNG, GIF (Max 2MB)</span>
                            <input type="file" name="game_image" id="game_image" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div id="file-name-display" style="margin-top: 10px; font-size: 0.9rem; color: var(--color-primary); font-weight: 500;"></div>
                    </div>

                    <div style="margin-top: var(--spacing-6); display: flex; justify-content: flex-end;">
                        <input type="hidden" name="action" value="create">
                        <button type="submit" class="btn btn--primary btn--large">Create Game</button>
                    </div>
                </form>
            </div>
        </div>
        <div style="margin-bottom: var(--spacing-8);"></div>
        <?php endif; ?>

        <script>
            document.getElementById('game_image')?.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name;
                const display = document.getElementById('file-name-display');
                if (fileName) {
                    display.textContent = 'Selected: ' + fileName;
                    document.getElementById('upload-zone').style.borderColor = 'var(--color-primary)';
                } else {
                    display.textContent = '';
                    document.getElementById('upload-zone').style.borderColor = '';
                }
            });
        </script>

        <div class="card">
            <div class="filters">
                <form method="GET" class="search-form">
                    <?php if ($club_id): ?>
                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <input type="text" name="search" placeholder="Search games..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <button type="submit" class="btn">Filter</button>
                        <a href="?<?php echo $club_id ? 'club_id=' . $club_id : ''; ?>" class="btn">Reset</a>
                    </div>
                </form>
            </div>

            <h2>Games List</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if (!$club_id): ?><th>Club</th><?php endif; ?>
                        <th style="width: 50px; text-align: left;">Image</th>
                        <th style="text-align: left;">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'game_name','order'=>$sort==='game_name'&&$order==='asc'?'desc':'asc'])); ?>" class="sort-link">
                                Game Name <?php echo $sort==='game_name'?($order==='asc'?'^':'v'):''; ?>
                            </a>
                        </th>
                        <th>Players</th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'created_at','order'=>$sort==='created_at'&&$order==='asc'?'desc':'asc'])); ?>" class="sort-link">
                                Added <?php echo $sort==='created_at'?($order==='asc'?'^':'v'):''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'total_plays','order'=>$sort==='total_plays'&&$order==='asc'?'desc':'asc'])); ?>" class="sort-link">
                                Total Plays <?php echo $sort==='total_plays'?($order==='asc'?'^':'v'):''; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game): ?>
                        <tr>
                            <?php if (!$club_id): ?><td data-label="Club"><?php echo htmlspecialchars($game['club_name']); ?></td><?php endif; ?>
                            <td data-label="Image">
                                <?php if ($game['game_image']): ?>
                                    <img src="../images/game_images/<?php echo htmlspecialchars($game['game_image']); ?>" alt="" class="game-thumbnail">
                                <?php else: ?>
                                    <div class="game-thumbnail" title="No image uploaded">🖼️</div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Game Name" style="text-align: left;"><?php echo htmlspecialchars($game['game_name']); ?></td>
                            <td data-label="Players"><?php echo $game['min_players'] . '-' . $game['max_players']; ?></td>
                            <td data-label="Added"><?php echo date('M j, Y', strtotime($game['created_at'])); ?></td>
                            <td data-label="Total Plays"><?php echo $game['total_plays']; ?></td>
                            <td>
                                <a href="edit_game.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game['game_id']; ?>" 
                                   class="btn">Edit</a>
                                <a href="results.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game['game_id']; ?>" 
                                   class="btn">Results</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
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
    // Also save on form submit (search/filter)
    var forms = document.querySelectorAll('.search-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', saveScrollPosition);
    });
})();
</script>
</html>