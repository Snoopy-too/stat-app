<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

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
    $club_name = trim($_POST['club_name']);
    $description = trim($_POST['description']);
    $meeting_day = $_POST['meeting_day'];
    $meeting_time = $_POST['meeting_time'];
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE clubs 
            SET club_name = ?, description = ?, meeting_day = ?, 
                meeting_time = ?, location = ?, status = ?
            WHERE club_id = ?
        ");
        $stmt->execute([
            $club_name, $description, $meeting_day, 
            $meeting_time, $location, $status, $club_id
        ]);
        
        $_SESSION['success'] = "Club updated successfully!";
        header("Location: view_club.php?id=" . $club_id);
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Failed to update club. Please try again.";
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$statuses = ['active', 'suspended', 'inactive'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Club - Super Admin</title>
    <style>
        body {
            background-color: #f0f0f0;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .container {
            width: 80%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .edit-form {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Edit Club</h1>
        <h2><?php echo htmlspecialchars($club['club_name']); ?></h2>
    </div>

    <div class="container">
        <div class="edit-form">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="club_name">Club Name:</label>
                    <input type="text" id="club_name" name="club_name" required
                           value="<?php echo htmlspecialchars($club['club_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($club['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="meeting_day">Meeting Day:</label>
                    <select id="meeting_day" name="meeting_day">
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
                    <input type="time" id="meeting_time" name="meeting_time"
                           value="<?php echo htmlspecialchars($club['meeting_time']); ?>">
                </div>
                <div class="form-group">
                    <label for="location">Meeting Location:</label>
                    <input type="text" id="location" name="location"
                           value="<?php echo htmlspecialchars($club['location']); ?>">
                </div>
                <div class="form-group">
                    <label for="status">Club Status:</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>"
                                <?php echo ($status == $club['status']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="button">Save Changes</button>
                <a href="view_club.php?id=<?php echo $club_id; ?>" class="button">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>