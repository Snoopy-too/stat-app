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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header d-flex flex-column flex-md-row justify-content-between align-items-center p-3">
        <div class="d-flex flex-column flex-md-row justify-content-around align-items-center flex-grow-1">
            <h1>Board Game Club StatApp</h1>
            <h5>Stats for <span id="clubName"><?php echo htmlspecialchars($club_name); ?></span></h2>
        </div>
        <?php if (isset($_SESSION['is_super_admin'])): ?>
            <a href="admin/dashboard.php" class="button btn">Admin Dashboard</a>
        <?php else: ?>
            <div class="auth-links d-flex flex-column flex-sm-row align-items-center mt-2 mt-md-0">
                <a href="admin/login.php" class="button btn mb-2 mb-sm-0 me-sm-2">Login</a>
                <a href="register.php" class="button btn">Register</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="container py-4">
        <div class="card sales-pitch mb-4">
            <div class="card-body">
                <h2 class="card-title">Welcome to Board Game StatApp!</h2>
                <p>Track your board gaming journey like never before! Our platform offers:</p>
                <ul class="list-unstyled">
                    <li>📊 Comprehensive game statistics and play tracking</li>
                    <li>👥 Member management for your gaming club</li>
                    <li>🎲 Game library organization</li>
                    <li>📈 Detailed play history and analytics</li>
                </ul>
                <p>Join today and take your board gaming experience to the next level!</p>
            </div>
        </div>

        <div class="card search-section">
            <div class="card-body">
                <h2 class="card-title">Find a Board Game Club</h2>
                <div class="search-box mb-3">
                    <input type="text" id="clubSearch" placeholder="Search for clubs..." class="form-control">
                </div>
                <div id="searchResults" class="club-list list-group">
                    <!-- Club search results will appear here -->
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('clubSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim();
        const searchResultsDiv = document.getElementById('searchResults');
        
        if (searchTerm.length < 2) {
            searchResultsDiv.innerHTML = '';
            return;
        }

        fetch(`search_clubs.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(clubs => {
                let resultsHtml = '';
                if (clubs.length) {
                    resultsHtml = clubs.map(club => `
                        <a href="club_stats.php?id=${club.club_id}" class="list-group-item list-group-item-action club-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">${club.club_name}</h5>
                            </div>
                            ${club.description ? `<p class="mb-1 small">${club.description.substring(0, 100)}...</p>` : ''}
                        </a>
                    `).join('');
                } else {
                    resultsHtml = '<p class="text-muted p-2">No clubs found</p>';
                }
                searchResultsDiv.innerHTML = resultsHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                searchResultsDiv.innerHTML = '<p class="text-danger p-2">Error searching clubs</p>';
            });
    });
    </script>
</body>
</html>