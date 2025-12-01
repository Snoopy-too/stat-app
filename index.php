<?php
session_start();
// Test webhook deployment trigger
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
    <title>The Flying Dutchmen StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>The Flying Dutchmen StatApp</h1>
            <p class="header-subtitle">Stats for Board Game Clubs</p>
        </div>
        <div class="header-actions">
            <?php if (isset($_SESSION['is_super_admin'])): ?>
                <a href="admin/dashboard.php" class="btn btn--secondary btn--small">Admin Dashboard</a>
            <?php else: ?>
                <a href="admin/login.php" class="btn btn--secondary btn--small">Login</a>
                <a href="register.php" class="btn btn--small">Register</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="container container--narrow">
        <div class="card hero-card">
            <div class="card-header">
                <div>
                    <h2>Welcome to The Flying Dutchmen StatApp</h2>
                    <p class="card-subtitle">Everything your club needs to track plays, celebrate champions, and grow community.</p>
                </div>
            </div>
            <p>From small weekly meetups to large competitive leagues, our tools keep your members, games, and results organized in one intuitive dashboard.</p>
            <ul>
                <li>📊 Comprehensive game statistics and play tracking</li>
                <li>👥 Member management and champion history</li>
                <li>🎲 Curated game library with team support</li>
                <li>📈 Insightful analytics for every club night</li>
            </ul>
            <div class="hero-actions">
                <a href="register.php" class="btn">Start Your Club</a>
                <?php if (!isset($_SESSION['is_super_admin'])): ?>
                    <a href="admin/login.php" class="btn btn--ghost">Club Admin Login</a>
                <?php else: ?>
                    <a href="admin/dashboard.php" class="btn btn--ghost">Go to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card search-card">
            <div class="card-header">
                <div>
                    <h2>Find a Board Game Club</h2>
                    <p class="card-subtitle card-subtitle--muted">Browse public clubs to explore their stats and champions.</p>
                </div>
            </div>
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
    <script src="js/mobile-menu.js"></script>
</body>
</html>
