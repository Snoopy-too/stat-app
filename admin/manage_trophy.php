<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header('Location: login.php');
    exit();
}

$security = new SecurityUtils($pdo);
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['trophy_image'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_trophy.php?club_id=" . $club_id);
        exit();
    }

    $file = $_FILES['trophy_image'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $uploadError = null;

    // Validate file size first
    if ($file['size'] > $maxSize) {
        $uploadError = 'File is too large. Maximum size is 5MB.';
    } else {
        // Validate file extension (secondary check)
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowedExtensions)) {
            $uploadError = 'Invalid file extension. Only JPG, PNG and GIF are allowed.';
        } else {
            // Use finfo to get actual MIME type from file content (primary check)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actualMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($actualMime, $allowedMimes)) {
                $uploadError = 'Invalid file type detected. Only JPG, PNG and GIF images are allowed.';
            } else {
                // Additional validation: verify it's actually a valid image
                $imageInfo = @getimagesize($file['tmp_name']);
                if ($imageInfo === false) {
                    $uploadError = 'File is not a valid image.';
                }
            }
        }
    }

    // If no errors, process upload
    if ($uploadError === null) {
        $upload_dir = '../images/trophies/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = 'trophy_' . $club_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Get old image path
            $stmt = $pdo->prepare('SELECT champ_image FROM clubs WHERE club_id = ?');
            $stmt->execute([$club_id]);
            $old_image = $stmt->fetch(PDO::FETCH_ASSOC)['champ_image'];

            // Update database with new image path
            $relative_path = 'images/trophies/' . $new_filename;
            $stmt = $pdo->prepare('UPDATE clubs SET champ_image = ? WHERE club_id = ?');
            $stmt->execute([$relative_path, $club_id]);

            // Delete old image if exists
            if ($old_image && file_exists('../' . $old_image)) {
                unlink('../' . $old_image);
            }
            $_SESSION['success'] = 'Trophy image updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to upload file. Please try again.';
        }
    } else {
        $_SESSION['error'] = $uploadError;
    }
}

// Get current club info
$stmt = $pdo->prepare('SELECT club_name, champ_image FROM clubs WHERE club_id = ?');
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header('Location: index.php');
    exit();
}

// Generate CSRF token for form
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trophy Image - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php NavigationHelper::renderAdminSidebar('champions', $club_id, $club['club_name']); ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader('Manage Trophy Image', htmlspecialchars($club['club_name'])); ?>
    </div>
    
    <div class="container container--narrow">
        <?php display_session_message('error'); ?>
        <?php display_session_message('success'); ?>
        
        <div class="card">
            <h2>Current Trophy Image</h2>
            <div class="text-center">
                <?php if ($club['champ_image']): ?>
                    <img src="../<?php echo htmlspecialchars($club['champ_image']); ?>" alt="Trophy" class="trophy-image" onerror="this.src='../images/placeholder-trophy.svg'">
                <?php else: ?>
                    <img src="../images/placeholder-trophy.svg" alt="No Trophy" class="trophy-image">
                    <p class="text-muted">No trophy image set</p>
                <?php endif; ?>
            </div>
        </div>
        
        <form action="manage_trophy.php?club_id=<?php echo $club_id; ?>" method="post" enctype="multipart/form-data" class="stack">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group">
                <label for="trophy_image">Upload New Trophy Image:</label>
                <input type="file" id="trophy_image" name="trophy_image" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                <span class="field-hint">Allowed formats: JPG, PNG, GIF. Max size 5MB.</span>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Update Trophy Image</button>
                <a href="dashboard.php" class="btn btn--subtle">Cancel</a>
            </div>
        </form>
    </div>
    <script src="../js/sidebar.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
</body>
</html>
