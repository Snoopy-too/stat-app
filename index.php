<?php
session_start();
require_once 'config/database.php';

// Get club info if user is logged in
$club_name = "Board Game Club";
if (isset($_SESSION['club_id'])) {
    $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
    $stmt->execute([$_SESSION['club_id']]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($club) {
        $club_name = $club['club_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
        <h2>Stats for <span id="clubName"><?php echo htmlspecialchars($club_name); ?></span></h2>
        <?php if (isset($_SESSION['is_super_admin'])): ?>
            <a href="admin/dashboard.php" class="button">Admin Dashboard</a>
        <?php else: ?>
            <div class="auth-links">
                <a href="admin/login.php" class="button">Login</a>
                <a href="register.php" class="button">Register</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="container">
        <div class="sales-pitch card">
            <h2>Welcome to Board Game StatApp!</h2>
            <p>Track your board gaming journey like never before! Our platform offers:</p>
            <ul>
                <li>📊 Comprehensive game statistics and play tracking</li>
                <li>👥 Member management for your gaming club</li>
                <li>🎲 Game library organization</li>
                <li>📈 Detailed play history and analytics</li>
            </ul>
            <p>Join today and take your board gaming experience to the next level!</p>
        </div>

        <div class="search-section card">
            <h2>Find a Board Game Club</h2>
            <div class="search-box">
                <input type="text" id="clubSearch" placeholder="Search for clubs..." class="form-control">
            </div>
            <div id="searchResults" class="club-list">
                <!-- Club search results will appear here -->
            </div>
        </div>
    </div>

    <script>
    document.getElementById('clubSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim();
        if (searchTerm.length < 2) {
            document.getElementById('searchResults').innerHTML = '';
            return;
        }

        fetch(`search_clubs.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(clubs => {
                const resultsHtml = clubs.length ? clubs.map(club => `
                    <div class="club-item">
                        <h3><a href="club_stats.php?id=${club.club_id}">${club.club_name}</a></h3>
                        ${club.description ? `<p>${club.description.substring(0, 100)}...</p>` : ''}
                    </div>
                `).join('') : '<p>No clubs found</p>';
                
                document.getElementById('searchResults').innerHTML = resultsHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('searchResults').innerHTML = '<p>Error searching clubs</p>';
            });
    });
    </script>
</body>
</html>