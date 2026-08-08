<?php
session_start();

// Redirect admin users directly to their account
if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
    header('Location: admin/account.php');
    exit;
}

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
<html lang="en" data-club-theme="light" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StatApp - Track Your Board Game Club Stats</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg?v=4">
    <link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=4">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- Header -->
    <header class="landing-header">
        <a href="index.php" class="logo-brand">
            <span>🎲</span> StatApp
        </a>
    </header>

    <!-- Hero Section -->
    <section class="landing-hero">
        <!-- Background Wave & Grid Contour Overlays -->
        <div class="hero-contour-waves"></div>

        <div class="landing-hero-content">
            <h1>Track Every Victory.<br><span class="highlight">Celebrate Every Champion.</span></h1>
            <p class="landing-hero-subtitle">
                The all-in-one platform for board game clubs to manage members, track game results, and crown champions.

              <div class="stat-item">
                  <span class="stat-item-value">100%</span>
                  <span class="stat-item-label">Free to Use</span>
              </div>
              <span class="stat-divider">•</span>
              <div class="stat-item">
                  <span class="stat-item-value">Unlimited</span>
                  <span class="stat-item-label">Games &amp; Members</span>
              </div>
              <span class="stat-divider">•</span>
              <div class="stat-item">
                  <span class="stat-item-value">5 min</span>
                  <span class="stat-item-label">Quick Setup</span>
              </div>

            </p>

            <div class="landing-hero-cta">
                <?php if (isset($_SESSION['is_super_admin'])): ?>
                    <a href="admin/account.php" class="btn btn--secondary">Go to Account</a>
                <?php else: ?>
                    <a href="admin/login.php" class="btn btn--secondary">Login</a>
                <?php endif; ?>
                <a href="register.php" class="btn btn--primary">Register</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="landing-features">
        <div class="landing-section-header">
            <h2>Everything Your Club Needs</h2>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <div class="feature-icon feature-icon--blue">&#127922;</div>
                    <h3>Game Library</h3>
                </div>
                <p>Build your club's game collection. Track player counts, play counts, and see top table favorites.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <div class="feature-icon feature-icon--purple">&#127942;</div>
                    <h3>Game Results &amp; Champions</h3>
                </div>
                <p>Log every play session with winners, placements, and duration while crowning champions in your hall of fame.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <div class="feature-icon feature-icon--green">&#128101;</div>
                    <h3>Member Management</h3>
                </div>
                <p>Keep your roster organized with player nicknames, join dates, and individual head-to-head performance stats.</p>
            </div>
        </div>
    </section>

    <!-- Screenshot Gallery -->
    <section class="landing-gallery" id="preview">
        <div class="gallery-container">
            <div class="landing-section-header">
                <h2>See What's Inside</h2>
            </div>
            <div class="gallery-wrapper">
                <button class="gallery-nav-btn gallery-nav-btn--prev" id="galleryPrev" aria-label="Previous">&#10094;</button>
                <div class="gallery-scroll" id="galleryScroll">
                    <div class="gallery-item">
                        <div class="gallery-item-preview">
                            <iframe src="admin/manage_teams.php?demo=1&theme=arcade" title="Teams" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        </div>
                        <div class="gallery-item-caption">Teams</div>
                    </div>
                    <div class="gallery-item">
                        <div class="gallery-item-preview">
                            <iframe src="admin/manage_members.php?demo=1&theme=arcade" title="Members" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        </div>
                        <div class="gallery-item-caption">Members</div>
                    </div>
                    <div class="gallery-item">
                        <div class="gallery-item-preview">
                            <iframe src="admin/manage_champions.php?demo=1&theme=arcade" title="Champions" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        </div>
                        <div class="gallery-item-caption">Champions</div>
                    </div>
                    <div class="gallery-item">
                        <div class="gallery-item-preview">
                            <iframe src="admin/manage_games.php?demo=1&theme=arcade" title="Games" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        </div>
                        <div class="gallery-item-caption">Games</div>
                    </div>
                </div>
                <button class="gallery-nav-btn gallery-nav-btn--next" id="galleryNext" aria-label="Next">&#10095;</button>
            </div>
        </div>
    </section>


    <!-- Search Section -->
    <section class="landing-search">
        <div class="search-container">
            <div class="landing-section-header">
                <h2>Find a Club</h2>
                <p>Explore public clubs and view their stats</p>
            </div>
            <input type="text" id="clubSearch" placeholder="Search for a club..." class="form-control">
            <div id="searchResults" class="club-list"></div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <p>&copy; <?php echo date('Y'); ?> StatApp. Built for board game lovers.</p>
    </footer>

    <script>
    // Gallery navigation
    const gallery = document.getElementById('galleryScroll');
    const prevBtn = document.getElementById('galleryPrev');
    const nextBtn = document.getElementById('galleryNext');

    if (gallery && prevBtn && nextBtn) {
        const scrollAmount = 380;
        prevBtn.addEventListener('click', () => {
            gallery.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
        nextBtn.addEventListener('click', () => {
            gallery.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });
    }

    // Club search
    const searchInput = document.getElementById('clubSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.trim();
            if (searchTerm.length < 2) {
                document.getElementById('searchResults').innerHTML = '';
                return;
            }

            fetch(`search_clubs.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(clubs => {
                    const resultsHtml = clubs.length ? clubs.map(club => {
                        const url = club.slug ? `club_stats.php?slug=${encodeURIComponent(club.slug)}` : `club_stats.php?id=${club.club_id}`;
                        return `
                            <a href="${url}" class="club-item" style="display: block; text-decoration: none;">
                                <h3 style="margin: 0 0 0.2rem; font-size: 1rem; color: var(--color-primary); font-weight: 600;">${club.club_name}</h3>
                                ${club.description ? `<p style="margin: 0; color: var(--color-text-muted); font-size: 0.85rem;">${club.description.substring(0, 100)}...</p>` : ''}
                            </a>
                        `;
                    }).join('') : '<p style="text-align:center;color:var(--color-text-muted);font-size:0.9rem;margin:0.5rem 0;">No clubs found</p>';

                    document.getElementById('searchResults').innerHTML = resultsHtml;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('searchResults').innerHTML = '<p style="text-align:center;color:var(--color-text-muted);font-size:0.9rem;">Error searching clubs</p>';
                });
        });
    }
    </script>
    <script src="js/mobile-menu.js"></script>
</body>
</html>
