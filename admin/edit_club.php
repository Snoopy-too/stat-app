<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get club details
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: edit_club.php?id=" . $club_id);
        exit();
    }

    $club_name = trim($_POST['club_name']);
    $slug = trim($_POST['slug']);
    $slug = $slug === '' ? null : $slug; // Convert empty string to null for unique constraint
    $description = trim($_POST['description']);
    $meeting_day = $_POST['meeting_day'];
    $meeting_time = $_POST['meeting_time'];
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    
    // Validate slug
    if ($slug !== null && !preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
        $_SESSION['error'] = "Slug can only contain letters, numbers, and hyphens.";
    } elseif ($slug !== null && in_array(strtolower($slug), ['admin', 'api', 'index', 'login', 'logout', 'dashboard', 'config', 'includes', 'css', 'js', 'images', 'uploads'])) {
        $_SESSION['error'] = "This slug is reserved and cannot be used.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE clubs 
                SET club_name = ?, slug = ?, description = ?, meeting_day = ?, 
                    meeting_time = ?, location = ?, status = ?
                WHERE club_id = ?
            ");
            $stmt->execute([
                $club_name, $slug, $description, $meeting_day, 
                $meeting_time, $location, $status, $club_id
            ]);
            
            $_SESSION['success'] = "Club updated successfully!";
            header("Location: view_club.php?id=" . $club_id);
            exit();
            
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $_SESSION['error'] = "This slug is already in use. Please choose a different one.";
            } else {
                $_SESSION['error'] = "Failed to update club. Please try again.";
            }
        }
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$statuses = ['active', 'suspended', 'inactive'];

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Club - Super Admin</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Edit Club</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($club['club_name']); ?></p>
        </div>
        <a href="view_club.php?id=<?php echo $club_id; ?>" class="btn btn--secondary">Cancel</a>
    </div>

    <div class="container container--narrow">
        <div class="card">
            <?php display_session_message('error'); ?>

            <form method="POST" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="club_name">Club Name:</label>
                    <input type="text" id="club_name" name="club_name" class="form-control" required
                           value="<?php echo htmlspecialchars($club['club_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="slug">Club URL Slug (optional):</label>
                    <input type="text" id="slug" name="slug" class="form-control"
                           pattern="[a-zA-Z0-9\-]+" title="Only letters, numbers, and hyphens allowed"
                           value="<?php echo htmlspecialchars($club['slug'] ?? ''); ?>">
                    <small style="display:block; margin-top:0.5rem; color:var(--text-light);">
                        Leave empty to use ID-based URL. If set, club will be accessible at domain.com/slug
                    </small>
                    <?php if (!empty($club['slug'])): ?>
                        <div style="margin-top:1rem; padding:1rem; background:var(--bg-secondary); border-radius:0.5rem;">
                            <strong style="display:block; margin-bottom:0.5rem;">Current Vanity URL:</strong>
                            <div style="display:flex; gap:0.5rem; align-items:center;">
                                <code id="vanity-url" style="flex:1; padding:0.5rem; background:var(--bg-primary); border-radius:0.25rem; font-size:0.9rem;">
                                    <?php 
                                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                                    $host = $_SERVER['HTTP_HOST'];
                                    $base_path = dirname(dirname($_SERVER['PHP_SELF']));
                                    echo htmlspecialchars($protocol . '://' . $host . $base_path . '/' . $club['slug']); 
                                    ?>
                                </code>
                                <button type="button" class="btn btn--small btn--subtle" onclick="copyVanityUrl()">Copy</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($club['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="meeting_day">Meeting Day:</label>
                    <select id="meeting_day" name="meeting_day" class="form-control">
                        <?php foreach ($days as $day): ?>
                            <option value="<?php echo $day; ?>"
                                <?php echo ($day == $club['meeting_day']) ? 'selected' : ''; ?>>
                                <?php echo $day; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="meeting_time">Meeting Time:</label>
                    <input type="time" id="meeting_time" name="meeting_time" class="form-control"
                           value="<?php echo htmlspecialchars($club['meeting_time']); ?>">
                </div>
                <div class="form-group">
                    <label for="location">Meeting Location:</label>
                    <input type="text" id="location" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($club['location']); ?>">
                </div>
                <div class="form-group">
                    <label for="status">Club Status:</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>"
                                <?php echo ($status == $club['status']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Save Changes</button>
                    <a href="view_club.php?id=<?php echo $club_id; ?>" class="btn btn--subtle">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        function copyVanityUrl() {
            const urlElement = document.getElementById('vanity-url');
            const url = urlElement.textContent.trim();
            
            navigator.clipboard.writeText(url).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.classList.add('btn--success');
                
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('btn--success');
                }, 2000);
            }).catch(err => {
                alert('Failed to copy URL: ' + err);
            });
        }
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
