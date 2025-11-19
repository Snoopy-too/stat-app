<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header('Location: login.php');
    exit();
}

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['trophy_image'])) {
    $file = $_FILES['trophy_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    
    if (!in_array($file['type'], $allowed_types)) {
        $error = 'Invalid file type. Only JPG, PNG and GIF images are allowed.';
    } else {
        $upload_dir = '../images/trophies/';
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
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
            
            if ($stmt->execute()) {
                // Delete old image if exists
                if ($old_image && file_exists('../' . $old_image)) {
                    unlink('../' . $old_image);
                }
                $success = 'Trophy image updated successfully!';
            } else {
                $error = 'Failed to update database.';
                unlink($upload_path); // Remove uploaded file if database update fails
            }
        } else {
            $error = 'Failed to upload file.';
        }
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
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Manage Trophy Image</h1>
            <p class="header-subtitle"><?php echo htmlspecialchars($club['club_name']); ?></p>
        </div>
        <a href="dashboard.php" class="btn btn--secondary">Back to Dashboard</a>
    </div>
    
    <div class="container container--narrow">
        <?php if (isset($error)): ?>
            <div class="message message--error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="message message--success"><?php echo $success; ?></div>
        <?php endif; ?>
        
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
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
</html>
