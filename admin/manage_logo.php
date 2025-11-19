<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// Get club info and verify admin ownership
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ? AND admin_id = ?");
$stmt->execute([$club_id, $_SESSION['admin_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG and GIF are allowed.";
        } elseif ($file['size'] > $maxSize) {
            $_SESSION['error'] = "File is too large. Maximum size is 5MB.";
        } else {
            $uploadDir = '../images/club_logos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Delete old logo if exists
            if ($club['logo_image'] && file_exists($uploadDir . $club['logo_image'])) {
                unlink($uploadDir . $club['logo_image']);
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Club Logo - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
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
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message--success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><?php echo htmlspecialchars($club['club_name']); ?> - Logo Management</h2>
            
            <div class="logo-container">
                <?php if ($club['logo_image']): ?>
                    <img src="../images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="Club Logo" class="logo-preview">
                    <form method="POST" class="stack stack--sm">
                        <input type="hidden" name="remove_logo" value="1">
                        <button type="submit" class="btn btn--danger" onclick="return confirm('Are you sure you want to remove the logo?')">Remove Logo</button>
                    </form>
                <?php else: ?>
                    <p>No logo uploaded yet</p>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" class="stack">
                <div class="form-group">
                    <label for="logo">Upload New Logo:</label>
                    <input type="file" name="logo" id="logo" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                    <span class="field-hint">Maximum file size: 5MB. Allowed formats: JPG, PNG, GIF.</span>
                </div>
                <button type="submit" class="btn">Upload Logo</button>
            </form>
        </div>
    </div>
</body>
</html>
