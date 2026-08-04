<?php
session_start();

// Redirect admin users directly to their dashboard
if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
    header('Location: admin/dashboard.php');
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
    <style>
        /* Modern Compact Landing Page Styles */
        :root {
            --landing-hero-bg: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 40%, #f1f5f9 100%);
            --landing-hero-text: var(--color-text);
            --landing-hero-subtitle: var(--color-text-muted);
            --landing-card-bg: var(--color-surface);
            --landing-card-border: var(--color-border);
            --landing-card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --landing-stat-bg: var(--color-surface-muted);
            --landing-header-bg: rgba(255, 255, 255, 0.85);
            --landing-header-border: var(--color-border);
        }

        :root[data-theme="dark"] {
            --landing-hero-bg: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #0f172a 100%);
            --landing-hero-text: #f8fafc;
            --landing-hero-subtitle: #94a3b8;
            --landing-card-bg: #1e293b;
            --landing-card-border: #334155;
            --landing-card-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            --landing-stat-bg: #1e293b;
            --landing-header-bg: rgba(15, 23, 42, 0.85);
            --landing-header-border: #334155;
        }

        /* Header */
        .landing-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--landing-header-bg);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--landing-header-border);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .landing-header .logo-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .landing-header .header-actions {
            display: flex !important;
            align-items: center !important;
            gap: 0.75rem !important;
            max-height: none !important;
            opacity: 1 !important;
            overflow: visible !important;
            width: auto !important;
            flex-direction: row !important;
            margin: 0 !important;
        }

        /* Hero Section */
        .landing-hero {
            background: var(--landing-hero-bg);
            padding: 2.75rem 1.5rem 2.25rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid var(--landing-card-border);
            transition: background 0.3s ease;
        }

        .landing-hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%236366f1' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.7;
            pointer-events: none;
        }

        .landing-hero-content {
            position: relative;
            z-index: 1;
            max-width: 680px;
            margin: 0 auto;
        }

        .landing-hero h1 {
            font-size: clamp(1.75rem, 3.5vw, 2.5rem);
            font-weight: 800;
            margin-bottom: 0.75rem;
            line-height: 1.2;
            color: var(--landing-hero-text);
            letter-spacing: -0.02em;
        }

        .landing-hero .highlight {
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .landing-hero-subtitle {
            font-size: 1.05rem;
            margin: 0 auto 1.5rem;
            line-height: 1.5;
            color: var(--landing-hero-subtitle);
            max-width: 580px;
        }

        .landing-hero-cta {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .landing-hero .btn {
            padding: 0.65rem 1.6rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: var(--radius-pill);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            min-width: 140px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .landing-hero .btn:hover {
            transform: translateY(-2px);
        }

        /* Features Section */
        .landing-features {
            padding: 2.25rem 1.5rem;
            background: var(--color-background);
            transition: background 0.3s ease;
        }

        .landing-section-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .landing-section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-heading);
            margin-bottom: 0.25rem;
        }

        .landing-section-header p {
            color: var(--color-text-muted);
            font-size: 0.95rem;
            margin: 0;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
            max-width: 1100px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--landing-card-bg);
            border-radius: var(--radius-lg);
            padding: 1.35rem 1.25rem;
            box-shadow: var(--landing-card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.3s ease;
            border: 1px solid var(--landing-card-border);
            display: flex;
            flex-direction: column;
        }

        .feature-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .feature-icon-wrapper {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }

        .feature-icon--blue { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
        .feature-icon--purple { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
        .feature-icon--green { background: rgba(16, 185, 129, 0.12); color: #10b981; }

        .feature-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            color: var(--color-heading);
        }

        .feature-card p {
            color: var(--color-text-muted);
            line-height: 1.5;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Screenshot Gallery */
        .landing-gallery {
            padding: 2.25rem 1.5rem;
            background: var(--landing-card-bg);
            border-top: 1px solid var(--landing-card-border);
            border-bottom: 1px solid var(--landing-card-border);
            transition: background 0.3s ease;
        }

        .gallery-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .gallery-wrapper {
            position: relative;
        }

        .gallery-scroll {
            display: flex;
            gap: 1.25rem;
            overflow-x: auto;
            padding: 0.5rem 0.5rem 1rem;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scroll-behavior: smooth;
        }

        .gallery-nav-btn {
            position: absolute;
            top: 45%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid var(--landing-card-border);
            background: var(--landing-card-bg);
            color: var(--color-text);
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
            z-index: 10;
        }

        .gallery-nav-btn:hover {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        .gallery-nav-btn--prev { left: -18px; }
        .gallery-nav-btn--next { right: -18px; }

        .gallery-item {
            flex: 0 0 auto;
            width: min(82vw, 360px);
            scroll-snap-align: center;
        }

        .gallery-item-preview {
            width: 100%;
            height: 220px;
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-md);
            box-shadow: var(--landing-card-shadow);
            border: 1px solid var(--landing-card-border);
            background: var(--landing-card-bg);
            transition: transform 0.25s ease;
        }

        .gallery-item:hover .gallery-item-preview {
            transform: scale(1.02);
        }

        .gallery-item-preview iframe {
            width: 1200px;
            height: 733px;
            border: 0;
            transform: scale(0.3);
            transform-origin: 0 0;
            pointer-events: none;
        }

        .gallery-item-caption {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-text);
        }

        /* Stats Section */
        .landing-stats {
            background: var(--landing-stat-bg);
            padding: 0.65rem 1rem;
            border-bottom: 1px solid var(--landing-card-border);
            transition: background 0.3s ease;
        }

        .stats-grid {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.25rem;
            max-width: 760px;
            margin: 0 auto;
            text-align: center;
        }

        .stat-item {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }

        .stat-item-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--color-primary);
        }

        .stat-item-label {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            font-weight: 500;
        }

        .stat-divider {
            color: var(--color-text-soft);
            font-size: 0.65rem;
            opacity: 0.6;
        }

        @media (max-width: 640px) {
            .stats-grid {
                flex-wrap: wrap;
                gap: 0.4rem 0.85rem;
            }
            .stat-divider {
                display: none;
            }
        }

        /* Search Section */
        .landing-search {
            padding: 2.25rem 1.5rem;
            background: var(--color-background);
            transition: background 0.3s ease;
        }

        .search-container {
            max-width: 540px;
            margin: 0 auto;
        }

        .search-container .form-control {
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            border-radius: var(--radius-pill);
            border: 1px solid var(--landing-card-border);
            background: var(--landing-card-bg);
            color: var(--color-text);
            box-shadow: var(--landing-card-shadow);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            width: 100%;
        }

        .search-container .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-focus-ring);
        }

        .club-list {
            margin-top: 1rem;
        }

        .club-item {
            background: var(--landing-card-bg);
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
            border: 1px solid var(--landing-card-border);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .club-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .club-item h3 {
            margin: 0 0 0.2rem;
            font-size: 1rem;
        }

        .club-item h3 a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 600;
        }

        .club-item p {
            margin: 0;
            color: var(--color-text-muted);
            font-size: 0.85rem;
        }

        /* Footer */
        .landing-footer {
            background: var(--landing-card-bg);
            border-top: 1px solid var(--landing-card-border);
            padding: 1.25rem 1.5rem;
            text-align: center;
            transition: background 0.3s ease;
        }

        .landing-footer p {
            color: var(--color-text-muted);
            font-size: 0.85rem;
            margin: 0;
        }

        @media (max-width: 640px) {
            .landing-hero {
                padding: 2rem 1rem 1.75rem;
            }
            .landing-features, .landing-gallery, .landing-search {
                padding: 1.75rem 1rem;
            }
            .gallery-nav-btn {
                display: none;
            }
        }
    </style>
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
        <div class="landing-hero-content">
            <h1>Track Every Victory.<br><span class="highlight">Celebrate Every Champion.</span></h1>
            <p class="landing-hero-subtitle">
                The all-in-one platform for board game clubs to manage members, track game results, and crown champions with ease.

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
                    <a href="admin/dashboard.php" class="btn btn--secondary">Go to Dashboard</a>
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
                            <iframe src="admin/club_teams.php?demo=1" title="Teams" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        </div>
                        <div class="gallery-item-caption">Teams</div>
                    </div>
                    <div class="gallery-item">
                        <div class="gallery-item-preview">
                            <iframe src="admin/manage_members.php?demo=1" title="Members" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        </div>
                        <div class="gallery-item-caption">Members</div>
                    </div>
                    <div class="gallery-item">
                        <div class="gallery-item-preview">
                            <iframe src="admin/manage_champions.php?demo=1" title="Champions" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        </div>
                        <div class="gallery-item-caption">Champions</div>
                    </div>
                    <div class="gallery-item">
                        <div class="gallery-item-preview">
                            <iframe src="admin/manage_games.php?demo=1" title="Games" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
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
                    const resultsHtml = clubs.length ? clubs.map(club => `
                        <div class="club-item">
                            <h3><a href="club_stats.php?id=${club.club_id}">${club.club_name}</a></h3>
                            ${club.description ? `<p>${club.description.substring(0, 100)}...</p>` : ''}
                        </div>
                    `).join('') : '<p style="text-align:center;color:var(--color-text-muted);font-size:0.9rem;margin:0.5rem 0;">No clubs found</p>';

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
