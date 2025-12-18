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
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// Get club info and verify admin access
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM clubs c 
    JOIN club_admins ca ON c.club_id = ca.club_id 
    WHERE c.club_id = ? AND ca.admin_id = ?
");
$stmt->execute([$club_id, $_SESSION['admin_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: manage_logo.php?club_id=" . $club_id);
        exit();
    }

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $maxSize = 1 * 1024 * 1024; // 1MB

        $uploadError = null;

        // Validate file size first
        if ($file['size'] > $maxSize) {
            $uploadError = "File is too large. Maximum size is 1MB.";
        } else {
            // Validate file extension (secondary check)
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                $uploadError = "Invalid file extension. Only JPG, PNG and GIF are allowed.";
            } else {
                // Use finfo to get actual MIME type from file content (primary check)
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $actualMime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($actualMime, $allowedMimes)) {
                    $uploadError = "Invalid file type detected. Only JPG, PNG and GIF images are allowed.";
                } else {
                    // Additional validation: verify it's actually a valid image
                    $imageInfo = @getimagesize($file['tmp_name']);
                    if ($imageInfo === false) {
                        $uploadError = "File is not a valid image.";
                    }
                }
            }
        }

        // If no errors, process upload
        if ($uploadError === null) {
            $uploadDir = '../images/club_logos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Delete old logo if exists
            if ($club['logo_image'] && file_exists($uploadDir . $club['logo_image'])) {
                unlink($uploadDir . $club['logo_image']);
            }

            // Generate unique filename
            $filename = 'club_' . $club_id . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Update database
                $stmt = $pdo->prepare("UPDATE clubs SET logo_image = ? WHERE club_id = ?");
                $stmt->execute([$filename, $club_id]);
                $_SESSION['success'] = "Club logo updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to upload file. Please try again.";
            }
        } else {
            $_SESSION['error'] = $uploadError;
        }
    } elseif (isset($_POST['remove_logo']) && $club['logo_image']) {
        $uploadDir = '../images/club_logos/';
        if (file_exists($uploadDir . $club['logo_image'])) {
            unlink($uploadDir . $club['logo_image']);
        }
        
        $stmt = $pdo->prepare("UPDATE clubs SET logo_image = NULL WHERE club_id = ?");
        $stmt->execute([$club_id]);
        $_SESSION['success'] = "Club logo removed successfully!";
    }
    
    header("Location: manage_logo.php?club_id=" . $club_id);
    exit();
}

// Generate CSRF token for forms
$csrf_token = $security->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Club Logo - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Manage Club Logo</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($club['club_name']); ?></p>
        </div>
        <a href="manage_clubs.php" class="btn btn--secondary">Back to Clubs</a>
    </div>
    
    <div class="container container--narrow">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="card">
            <h2><?php echo htmlspecialchars($club['club_name']); ?> - Logo Management</h2>
            
            <div class="logo-container">
                <?php if ($club['logo_image']): ?>
                    <img src="../images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="Club Logo" class="logo-preview">
                    <form method="POST" class="stack stack--sm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="remove_logo" value="1">
                        <button type="submit" class="btn btn--danger" onclick="return confirm('Are you sure you want to remove the logo?')">Remove Logo</button>
                    </form>
                <?php else: ?>
                    <p>No logo uploaded yet</p>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="logo">Upload New Logo:</label>
                    <input type="file" name="logo" id="logo" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                    <span class="field-hint">Maximum file size: 1MB. Allowed formats: JPG, PNG, GIF.</span>
                </div>
                <button type="submit" class="btn">Upload Logo</button>
            </form>
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
</html>
