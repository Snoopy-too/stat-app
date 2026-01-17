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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StatApp - Track Your Board Game Club Stats</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
    <style>
        /* Landing Page Styles */
        .landing-hero {
            background: linear-gradient(135deg, #1e293b 0%, #1e3a5f 50%, #4f46e5 100%) !important;
            color: white !important;
            padding: 6rem 2rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .landing-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }

        .landing-hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }

        .landing-hero h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.1;
        }

        .landing-hero .highlight {
            background: linear-gradient(90deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .landing-hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            color: #cbd5e1 !important;
        }

        .landing-hero-cta {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .landing-hero .btn--large {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            border-radius: 50px;
        }

        .landing-hero .btn--glass {
            background: rgba(255,255,255,0.15) !important;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.5) !important;
            color: #ffffff !important;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .landing-hero .btn--glass:hover {
            background: rgba(255,255,255,0.3) !important;
            border-color: rgba(255,255,255,0.8) !important;
        }

        /* Features Section */
        .landing-features {
            padding: 5rem 2rem;
            background: var(--color-bg);
        }

        .landing-section-header {
            text-align: center;
            margin-bottom: 3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .landing-section-header h2 {
            font-size: 2rem;
            color: var(--color-text);
            margin-bottom: 0.5rem;
        }

        .landing-section-header p {
            color: var(--color-text-muted);
            font-size: 1.1rem;
            text-align: center !important;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--color-surface);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid var(--color-border);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.25rem;
        }

        .feature-icon--blue { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
        .feature-icon--purple { background: linear-gradient(135deg, #ede9fe, #ddd6fe); }
        .feature-icon--green { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
        .feature-icon--orange { background: linear-gradient(135deg, #fed7aa, #fdba74); }

        .feature-card h3 {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            color: var(--color-text);
        }

        .feature-card p {
            color: var(--color-text-muted);
            line-height: 1.6;
        }

        /* Screenshot Gallery */
        .landing-gallery {
            padding: 5rem 2rem;
            background: linear-gradient(180deg, var(--color-bg) 0%, var(--color-surface) 100%);
        }

        .gallery-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .gallery-wrapper {
            position: relative;
        }

        .gallery-scroll {
            display: flex;
            gap: 1.5rem;
            overflow-x: auto;
            padding: 1rem 3rem 2rem;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            scroll-behavior: smooth;
        }

        .gallery-scroll::-webkit-scrollbar {
            display: none;
        }

        .gallery-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 120px;
            border: none;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            font-size: 2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            z-index: 10;
            opacity: 0;
        }

        .gallery-wrapper:hover .gallery-nav-btn {
            opacity: 1;
        }

        .gallery-nav-btn:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        .gallery-nav-btn:active {
            transform: translateY(-50%) scale(0.95);
        }

        .gallery-nav-btn--prev {
            left: 0;
            border-radius: 0 8px 8px 0;
        }

        .gallery-nav-btn--next {
            right: 0;
            border-radius: 8px 0 0 8px;
        }

        .gallery-item {
            flex: 0 0 auto;
            width: min(90vw, 500px);
            scroll-snap-align: center;
        }

        .gallery-item img {
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            border: 1px solid var(--color-border);
            transition: transform 0.3s ease;
        }

        .gallery-item:hover img {
            transform: scale(1.02);
        }

        .gallery-item-caption {
            text-align: center;
            margin-top: 1rem;
            font-weight: 500;
            color: var(--color-text);
        }

        /* Stats Banner */
        .landing-stats {
            background: #1e293b;
            color: white;
            padding: 3rem 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 2rem;
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .stat-item-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #60a5fa;
        }

        .stat-item-label {
            font-size: 0.95rem;
            color: #cbd5e1;
            margin-top: 0.25rem;
            font-weight: 500;
        }

        /* CTA Section */
        .landing-cta {
            padding: 5rem 2rem;
            text-align: center;
            background: var(--color-surface);
        }

        .landing-cta h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--color-text);
        }

        .landing-cta p {
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 1.1rem;
            text-align: center !important;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Search Section */
        .landing-search {
            padding: 4rem 2rem;
            background: var(--color-bg);
        }

        .search-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .search-container .form-control {
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            border-radius: 50px;
            border: 2px solid var(--color-border);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .search-container .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .club-list {
            margin-top: 1.5rem;
        }

        .club-item {
            background: var(--color-surface);
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
            border: 1px solid var(--color-border);
            transition: box-shadow 0.2s ease;
        }

        .club-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .club-item h3 {
            margin: 0 0 0.25rem;
        }

        .club-item h3 a {
            color: var(--color-primary);
            text-decoration: none;
        }

        .club-item h3 a:hover {
            text-decoration: underline;
        }

        .club-item p {
            margin: 0;
            color: var(--color-text-muted);
            font-size: 0.9rem;
        }

        /* Header Override for Landing */
        .landing-header {
            background: transparent !important;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            border-bottom: none !important;
        }

        .landing-header h1 {
            color: #ffffff !important;
        }

        .landing-header .btn--secondary {
            background: rgba(255,255,255,0.2) !important;
            border: 2px solid rgba(255,255,255,0.5) !important;
            color: #ffffff !important;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .landing-header .btn--secondary:hover {
            background: rgba(255,255,255,0.35) !important;
            border-color: rgba(255,255,255,0.8) !important;
        }

        /* Footer */
        .landing-footer {
            background: #1e293b;
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .landing-footer p {
            color: #94a3b8;
            margin: 0;
        }

        @media (max-width: 768px) {
            .landing-hero {
                padding: 6rem 1.5rem 3rem;
            }

            .landing-hero h1 {
                font-size: 2rem;
            }

            .landing-hero-subtitle {
                font-size: 1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .gallery-item {
                width: min(85vw, 350px);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header landing-header">
        <div class="header-title-group">
            <h1 style="font-size: 1.25rem;">StatApp</h1>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn--icon theme-toggle" data-theme-toggle aria-label="Toggle dark mode">
                <span class="theme-toggle__icon" data-theme-icon></span>
            </button>
            <?php if (isset($_SESSION['is_super_admin'])): ?>
                <a href="admin/dashboard.php" class="btn btn--secondary btn--small">Dashboard</a>
            <?php else: ?>
                <a href="admin/login.php" class="btn btn--secondary btn--small">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="landing-hero">
        <div class="landing-hero-content">
            <h1>Track Every Victory.<br><span class="highlight">Celebrate Every Champion.</span></h1>
            <p class="landing-hero-subtitle">
                The all-in-one platform for board game clubs to manage members, track game results,
                and crown champions. Simple, beautiful, and built for communities like yours.
            </p>
            <div class="landing-hero-cta">
                <a href="register.php" class="btn btn--large">Start Your Club Free</a>
                <a href="#preview" class="btn btn--large btn--glass">See It In Action</a>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="landing-features">
        <div class="landing-section-header">
            <h2>Everything Your Club Needs</h2>
            <p>Powerful tools wrapped in a simple, intuitive interface</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon feature-icon--blue">&#127922;</div>
                <h3>Game Library</h3>
                <p>Build your club's game collection. Track player counts, total plays, and see which games hit the table most.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon feature-icon--purple">&#127942;</div>
                <h3>Champion Tracking</h3>
                <p>Crown your champions and preserve their legacy. Add notes, dates, and build a hall of fame for your club.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon feature-icon--green">&#128101;</div>
                <h3>Member Management</h3>
                <p>Keep your roster organized with nicknames, join dates, and individual stats for every member.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon feature-icon--orange">&#128202;</div>
                <h3>Game Results</h3>
                <p>Log every play session with winners, placements, duration, and notes. Build a complete history of your game nights.</p>
            </div>
        </div>
    </section>

    <!-- Screenshot Gallery -->
    <section class="landing-gallery" id="preview">
        <div class="gallery-container">
            <div class="landing-section-header">
                <h2>See What's Inside</h2>
                <p>A peek at your future dashboard</p>
            </div>
            <div class="gallery-wrapper">
                <button class="gallery-nav-btn gallery-nav-btn--prev" id="galleryPrev" aria-label="Previous">&#10094;</button>
                <div class="gallery-scroll" id="galleryScroll">
                    <div class="gallery-item">
                        <img src="images/sample UI images/Screenshot 2025-12-15 162837.png" alt="Admin Dashboard">
                        <div class="gallery-item-caption">Your Command Center</div>
                    </div>
                    <div class="gallery-item">
                        <img src="images/sample UI images/Screenshot 2025-12-15 163416.png" alt="Games List">
                        <div class="gallery-item-caption">Game Library</div>
                    </div>
                    <div class="gallery-item">
                        <img src="images/sample UI images/Screenshot 2025-12-15 163534.png" alt="Game Result Details">
                        <div class="gallery-item-caption">Detailed Results</div>
                    </div>
                    <div class="gallery-item">
                        <img src="images/sample UI images/Screenshot 2025-12-15 163324.png" alt="Champion History">
                        <div class="gallery-item-caption">Champion History</div>
                    </div>
                    <div class="gallery-item">
                        <img src="images/sample UI images/Screenshot 2025-12-15 163935.png" alt="Team Management">
                        <div class="gallery-item-caption">Team Management</div>
                    </div>
                    <div class="gallery-item">
                        <img src="images/sample UI images/Screenshot 2025-12-15 163036.png" alt="Club Management">
                        <div class="gallery-item-caption">Multi-Club Support</div>
                    </div>
                </div>
                <button class="gallery-nav-btn gallery-nav-btn--next" id="galleryNext" aria-label="Next">&#10095;</button>
            </div>
        </div>
    </section>

    <!-- Stats Banner -->
    <section class="landing-stats">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-item-value">100%</div>
                <div class="stat-item-label">Free to Use</div>
            </div>
            <div class="stat-item">
                <div class="stat-item-value">Unlimited</div>
                <div class="stat-item-label">Games & Members</div>
            </div>
            <div class="stat-item">
                <div class="stat-item-value">5 min</div>
                <div class="stat-item-label">Setup Time</div>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="landing-search">
        <div class="search-container">
            <div class="landing-section-header">
                <h2>Find a Club</h2>
                <p>Explore public clubs and their stats</p>
            </div>
            <input type="text" id="clubSearch" placeholder="Search for a club..." class="form-control">
            <div id="searchResults" class="club-list"></div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="landing-cta">
        <h2>Ready to Level Up Your Game Nights?</h2>
        <p>Join clubs already tracking their stats with StatApp.</p>
        <a href="register.php" class="btn btn--large">Start Your Club Free</a>
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

    const scrollAmount = 520; // Approximate width of one item + gap

    prevBtn.addEventListener('click', () => {
        gallery.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    });

    nextBtn.addEventListener('click', () => {
        gallery.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    });

    // Club search
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
                `).join('') : '<p style="text-align:center;color:var(--color-text-muted);">No clubs found</p>';

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
