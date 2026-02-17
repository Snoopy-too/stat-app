<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);

// Get club_id from URL
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;

// Validate club_id exists if provided and verify admin access
if ($club_id) {
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM club_admins 
        WHERE club_id = ? AND admin_id = ?
    ");
    $stmt->execute([$club_id, $_SESSION['admin_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Unauthorized club access.";
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
                            $game_image = $filename;
                        } else {
                            $uploadError = "Failed to move uploaded file. Check folder permissions.";
                        }
                    }
                } else {
                    $uploadError = "Upload error: " . $_FILES['game_image']['error'];
                }
            }

            if ($uploadError) {
                $_SESSION['error'] = $uploadError;
            } else {
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
        .upload-zone.has-preview {
            border-style: solid;
            padding: var(--spacing-2);
        }
        .upload-zone.has-preview .upload-zone__icon,
        .upload-zone.has-preview .upload-zone__text,
        .upload-zone.has-preview .upload-zone__hint {
            display: none;
        }
        .upload-zone__preview-img {
            max-width: 100%;
            max-height: 250px;
            border-radius: var(--radius-md);
            display: block;
            margin: 0 auto;
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
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('games', $club_id, $club_name); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Games', $club_name ? $club_name : 'All clubs'); ?>
        <?php if ($club_id): ?>
        <div class="header-actions">
            <a href="../club_game_list.php?id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small" target="_blank" title="View on public site">👁️ Preview</a>
        </div>
        <?php endif; ?>
    </div>
    
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
                            <span class="upload-zone__hint">JPG, PNG, GIF (Max 1MB, 600px recommended)</span>
                            <input type="file" name="game_image" id="game_image" accept="image/jpeg,image/png,image/gif">
                        </div>
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
                const file = e.target.files[0];
                const uploadZone = document.getElementById('upload-zone');
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        let preview = uploadZone.querySelector('.upload-zone__preview-img');
                        if (!preview) {
                            preview = document.createElement('img');
                            preview.className = 'upload-zone__preview-img';
                            uploadZone.prepend(preview);
                        }
                        preview.src = event.target.result;
                        uploadZone.classList.add('has-preview');
                    };
                    reader.readAsDataURL(file);
                    uploadZone.style.borderColor = 'var(--color-primary)';
                } else {
                    const preview = uploadZone.querySelector('.upload-zone__preview-img');
                    if (preview) preview.remove();
                    uploadZone.classList.remove('has-preview');
                    uploadZone.style.borderColor = '';
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
                                    <img src="../images/game_images/<?php echo htmlspecialchars($game['game_image']); ?>" alt="" class="game-thumbnail" loading="lazy">
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
                                       class="btn btn--small">Edit</a>
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
                                        <button class="btn btn--small btn--ghost" 
                                                style="color: var(--color-text-soft);"
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
    // Also save on form submit (search/filter)
    var forms = document.querySelectorAll('.search-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', saveScrollPosition);
    });
})();
</script>
</html>