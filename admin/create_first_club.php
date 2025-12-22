<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';

// Redirect if not logged in
if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// Redirect multi_club admins to dashboard (they don't need this page)
if (isset($_SESSION['admin_type']) && $_SESSION['admin_type'] !== 'single_club') {
    header("Location: dashboard.php");
    exit();
}

$security = new SecurityUtils($pdo);

// Check if admin already has a club - if so, redirect to dashboard
$clubCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE admin_id = ?");
$clubCheckStmt->execute([$_SESSION['admin_id']]);
if ($clubCheckStmt->fetchColumn() > 0) {
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: create_first_club.php");
        exit();
    }

    if (!empty($_POST['club_name'])) {
        $club_name = trim($_POST['club_name']);
        $slug = trim($_POST['slug'] ?? '');
        $slug = $slug === '' ? null : $slug;

        if (!preg_match('/^[a-zA-Z0-9\s_-]+$/', $club_name)) {
            $_SESSION['error'] = "Club name can only contain letters, numbers, spaces, dashes and underscores.";
        } elseif ($slug !== null && !preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
            $_SESSION['error'] = "Slug can only contain letters, numbers, and hyphens.";
        } elseif ($slug !== null && in_array(strtolower($slug), ['admin', 'api', 'index', 'login', 'logout', 'dashboard', 'config', 'includes', 'css', 'js', 'images', 'uploads'])) {
            $_SESSION['error'] = "This slug is reserved and cannot be used.";
        } else {
            try {
                $pdo->beginTransaction();

                // Create the club
                $stmt = $pdo->prepare("INSERT INTO clubs (club_name, slug) VALUES (?, ?)");
                $stmt->execute([$club_name, $slug]);
                $new_club_id = $pdo->lastInsertId();

                // Assign the creator as the owner
                $stmt = $pdo->prepare("INSERT INTO club_admins (club_id, admin_id, role) VALUES (?, ?, 'owner')");
                $stmt->execute([$new_club_id, $_SESSION['admin_id']]);

                $pdo->commit();

                $_SESSION['success'] = "Your club has been created successfully!";
                header("Location: dashboard.php");
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) {
                    $_SESSION['error'] = "This slug is already in use. Please choose a different one.";
                } else {
                    error_log("Failed to create club: " . $e->getMessage());
                    $_SESSION['error'] = "Failed to create club. Please try again.";
                }
            }
        }
    } else {
        $_SESSION['error'] = "Please enter a club name.";
    }
}

// Generate CSRF token
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Club - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
        </div>
    </div>

    <div class="container container--narrow">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="card">
            <h2>Create Your Club</h2>
            <p style="margin-bottom: 1.5rem; color: var(--text-light);">
                Welcome to StatApp! To get started, create your club below. This will be your permanent club where you can track members, games, and statistics.
            </p>
            <form method="POST" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="club_name">Club Name:</label>
                    <input type="text" id="club_name" name="club_name" placeholder="Enter your club name" required class="form-control" pattern="[a-zA-Z0-9 _\-]+" title="Only letters, numbers, spaces, dashes and underscores are allowed" autofocus>
                </div>
                <div class="form-group">
                    <label for="slug">Club URL Slug (optional):</label>
                    <input type="text" id="slug" name="slug" placeholder="e.g., myclub" class="form-control" pattern="[a-zA-Z0-9\-]+" title="Only letters, numbers, and hyphens allowed">
                    <small style="display:block; margin-top:0.5rem; color:var(--text-light);">
                        Leave empty to use ID-based URL. If set, your club will be accessible at domain.com/slug
                    </small>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn--block">Create My Club</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/form-loading.js"></script>
    <script src="../js/form-validation.js"></script>
</body>
</html>
