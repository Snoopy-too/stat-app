<?php
require_once __DIR__ . '/config/session.php';

// Redirect admin users directly to their account
if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
    $clubId = $_SESSION['current_club_id'] ?? $_SESSION['club_id'] ?? null;
    if ($clubId) {
        header('Location: admin/club_new_results.php?club_id=' . (int)$clubId);
    } else {
        header('Location: admin/select_club.php');
    }
    exit;
}

require_once 'config/database.php';

// Get club info if user is logged in
$club_name = "Board Game Club";
$activeTheme = 'midnight';
if (!empty($_COOKIE['tfd_theme'])) {
    $activeTheme = $_COOKIE['tfd_theme'];
}

if (isset($_SESSION['club_id'])) {
    $stmt = $pdo->prepare("SELECT club_name, theme FROM clubs WHERE club_id = ?");
    $stmt->execute([$_SESSION['club_id']]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($club) {
        $club_name = $club['club_name'];
        if (empty($_COOKIE['tfd_theme']) && !empty($club['theme'])) {
            $activeTheme = $club['theme'];
        }
    }
}

if (!empty($_GET['theme'])) {
    $activeTheme = $_GET['theme'];
}
if ($activeTheme === 'tabletop') {
    $activeTheme = 'casino';
}
if ($activeTheme === 'dark') {
    $activeTheme = 'arcade';
}
if (!in_array($activeTheme, ['light', 'arcade', 'midnight', 'casino'])) {
    $activeTheme = 'midnight';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($activeTheme); ?>" data-club-theme="<?php echo htmlspecialchars($activeTheme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StatApp - Track Your Board Game Club Stats</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg?v=4">
    <link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=4">
    <script>
    (function() {
        try {
            var stored = localStorage.getItem('tfd-theme-preference') || localStorage.getItem('tfd-theme') || localStorage.getItem('stat-app-theme') || localStorage.getItem('app-theme-preference');
            var theme = stored;
            if (!theme || theme === 'auto' || theme === 'system') {
                var m = document.cookie.match(/(?:^|;\s*)tfd_theme=([^;]*)/);
                if (m) theme = decodeURIComponent(m[1]);
            }
            if (theme === 'tabletop') theme = 'casino';
            if (theme === 'dark') theme = 'arcade';
            if (!theme || theme === 'auto' || theme === 'system') {
                theme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'arcade';
            }
            if (['light', 'arcade', 'midnight', 'casino'].includes(theme)) {
                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.setAttribute('data-club-theme', theme);
            }
        } catch(e) {}
    })();
    </script>
    <link rel="stylesheet" href="https://theflyingdutchmen.games/stylesheets/tfd-nav.css">
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/i18n.js"></script>
</head>
<body>
    <!-- Shared Top Navigation Bar (Managed by tfd-navbar.js) -->
    <header id="tfd-navbar" class="tfd-navbar" data-active="stats"></header>

    <!-- Hero Section -->
    <section class="landing-hero">
        <!-- Background Wave & Grid Contour Overlays -->
        <div class="hero-contour-waves"></div>

        <div class="landing-hero-content">
            <h1><span data-i18n="landing.heroTitle1">Track Every Victory.</span><br><span class="highlight" data-i18n="landing.heroTitle2">Celebrate Every Champion.</span></h1>
            <p class="landing-hero-subtitle" data-i18n="landing.heroSubtitle">
                The all-in-one platform for board game clubs to manage members, track game results, and crown champions.
            </p>

            <div class="landing-hero-stats" style="display:inline-flex;align-items:center;gap:0.75rem;margin:1rem 0 1.5rem;flex-wrap:wrap;justify-content:center;">
              <div class="stat-item">
                  <span class="stat-item-value">100%</span>
                  <span class="stat-item-label" data-i18n="landing.freeToUse">Free to Use</span>
              </div>
              <span class="stat-divider">•</span>
              <div class="stat-item">
                  <span class="stat-item-value">Unlimited</span>
                  <span class="stat-item-label" data-i18n="landing.unlimited">Games &amp; Members</span>
              </div>
            </div>

        </div>
    </section>

    <!-- Features Section -->
    <section class="landing-features">
        <div class="landing-section-header">
            <h2 data-i18n="landing.featuresHeading">Built for Board Game Enthusiasts</h2>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <div class="feature-icon feature-icon--blue">&#127922;</div>
                    <h3 data-i18n="club.games">Game Library</h3>
                </div>
                <p data-i18n="landing.featResultsDesc">Build your club's game collection. Track player counts, play counts, and see top table favorites.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <div class="feature-icon feature-icon--purple">&#127942;</div>
                    <h3 data-i18n="landing.featChampionsTitle">Game Results &amp; Champions</h3>
                </div>
                <p data-i18n="landing.featChampionsDesc">Log every play session with winners, placements, and duration while crowning champions in your hall of fame.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <div class="feature-icon feature-icon--green">&#128101;</div>
                    <h3 data-i18n="landing.featMembersTitle">Member Management</h3>
                </div>
                <p data-i18n="landing.featMembersDesc">Keep your roster organized with player nicknames, join dates, and individual head-to-head performance stats.</p>
            </div>
        </div>
    </section>

    <!-- Screenshot Gallery -->
    <section class="landing-gallery" id="preview">
        <div class="gallery-container">
            <div class="landing-section-header">
                <h2>See What's Inside</h2>
                <p>Click to open the interactive app preview carousel</p>
            </div>
            <div class="gallery-single-wrapper">
                <div class="gallery-item gallery-item--single" id="singlePreviewTrigger" role="button" tabindex="0" aria-label="Open Interactive App Preview Modal">
                    <div class="gallery-item-preview">
                        <iframe id="singlePreviewIframe" src="admin/club_new_results.php?demo=1&amp;theme=<?php echo htmlspecialchars($activeTheme); ?>" title="Interactive App Preview" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        <div class="preview-overlay-badge">
                            <span>🔍 Click to launch preview</span>
                        </div>
                    </div>
                    <div class="gallery-item-caption">Interactive App Preview &mdash; Click to explore all features</div>
                </div>
            </div>
        </div>
    </section>


    <!-- Search Section -->
    <section class="landing-search" id="clubs">
        <div class="search-container">
            <div class="landing-section-header">
                <h2 data-i18n="landing.findYourClub">Find Your Club</h2>
                <p data-i18n="landing.explorePublicClubs">Explore public clubs and view their stats</p>
            </div>
            <input type="text" id="clubSearch" placeholder="Search by club name or city..." data-i18n-placeholder="landing.searchPlaceholder" class="form-control">
            <div id="searchResults" class="club-list"></div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <p>&copy; <?php echo date('Y'); ?> StatApp. Built for board game lovers.</p>
    </footer>

    <!-- Preview Modal -->
    <div class="preview-modal-overlay" id="previewModal" aria-hidden="true">
        <div class="preview-modal-container">
            <div class="preview-modal-header">
                <h3 id="previewModalTitle">Preview</h3>
                <button type="button" class="preview-modal-close" id="previewModalClose" aria-label="Close">&times;</button>
            </div>
            <div class="preview-modal-body">
                <button type="button" class="modal-nav-btn modal-nav-btn--prev" id="modalPrev" aria-label="Previous slide">&#10094;</button>
                <iframe id="previewModalIframe" src="" title="Preview Modal" loading="lazy"></iframe>
                <button type="button" class="modal-nav-btn modal-nav-btn--next" id="modalNext" aria-label="Next slide">&#10095;</button>
            </div>
        </div>
    </div>

    <script>
    function getCurrentLandingTheme() {
        var theme = document.documentElement.getAttribute('data-club-theme') || 
                    document.documentElement.getAttribute('data-theme') || 
                    localStorage.getItem('tfd-theme-preference') || 
                    localStorage.getItem('stat-app-theme') || 
                    'midnight';
        if (theme === 'tabletop') theme = 'casino';
        if (theme === 'dark') theme = 'arcade';
        return theme;
    }

    function syncIframeTheme(theme) {
        if (!theme) theme = getCurrentLandingTheme();
        document.querySelectorAll('.landing-gallery iframe, .preview-modal-body iframe').forEach(iframe => {
            try {
                if (iframe.contentDocument && iframe.contentDocument.documentElement) {
                    iframe.contentDocument.documentElement.setAttribute('data-theme', theme);
                    iframe.contentDocument.documentElement.setAttribute('data-club-theme', theme);
                }
            } catch (e) {}
        });
    }

    // Keep landing page data-club-theme in sync with data-theme when user changes theme
    function syncLandingTheme(theme) {
        if (!theme) return;
        if (theme === 'tabletop') theme = 'casino';
        if (theme === 'dark') theme = 'arcade';
        if (['light', 'arcade', 'midnight', 'casino'].includes(theme)) {
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-club-theme', theme);
            syncIframeTheme(theme);
        }
    }
    window.addEventListener('tfd-theme-change', (e) => {
        if (e.detail && e.detail.theme) syncLandingTheme(e.detail.theme);
    });
    window.addEventListener('themechange', (e) => {
        if (e.detail && e.detail.theme) syncLandingTheme(e.detail.theme);
    });
    window.addEventListener('storage', (e) => {
        if (e.key === 'tfd-theme-preference' || e.key === 'stat-app-theme' || e.key === 'tfd-theme') {
            syncLandingTheme(e.newValue);
        }
    });

    // Modal preview behavior (modal carousel)
    const previewSlides = [
        { title: 'Add New Result', url: 'admin/club_new_results.php?demo=1' },
        { title: 'Results', url: 'admin/manage_results.php?demo=1' },
        { title: 'Members', url: 'admin/manage_members.php?demo=1' },
        { title: 'Teams', url: 'admin/manage_teams.php?demo=1' },
        { title: 'Champions', url: 'admin/manage_champions.php?demo=1' },
        { title: 'Games', url: 'admin/manage_games.php?demo=1' }
    ];
    let currentSlideIndex = 0;

    const previewModal = document.getElementById('previewModal');
    const previewModalIframe = document.getElementById('previewModalIframe');
    const previewModalTitle = document.getElementById('previewModalTitle');
    const previewModalClose = document.getElementById('previewModalClose');
    const modalPrev = document.getElementById('modalPrev');
    const modalNext = document.getElementById('modalNext');
    const singlePreviewTrigger = document.getElementById('singlePreviewTrigger');

    function updateModalSlide(index) {
        if (!previewModalIframe) return;
        currentSlideIndex = (index + previewSlides.length) % previewSlides.length;
        const slide = previewSlides[currentSlideIndex];
        const theme = getCurrentLandingTheme();
        const separator = slide.url.includes('?') ? '&' : '?';
        previewModalIframe.src = `${slide.url}${separator}theme=${encodeURIComponent(theme)}`;
        if (previewModalTitle) {
            previewModalTitle.textContent = `${slide.title} (${currentSlideIndex + 1} of ${previewSlides.length})`;
        }
    }

    function preventModalScroll(e) {
        e.preventDefault();
    }

    function openPreviewModal(slideIndex = 0) {
        if (!previewModal) return;
        updateModalSlide(slideIndex);
        previewModal.classList.add('is-active');
        previewModal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('modal-open');
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        previewModal.addEventListener('wheel', preventModalScroll, { passive: false });
        previewModal.addEventListener('touchmove', preventModalScroll, { passive: false });
    }

    function closePreviewModal() {
        if (!previewModal || !previewModalIframe) return;
        previewModal.classList.remove('is-active');
        previewModal.setAttribute('aria-hidden', 'true');
        previewModalIframe.src = '';
        document.documentElement.classList.remove('modal-open');
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        previewModal.removeEventListener('wheel', preventModalScroll);
        previewModal.removeEventListener('touchmove', preventModalScroll);
    }

    if (singlePreviewTrigger) {
        singlePreviewTrigger.addEventListener('click', () => openPreviewModal(0));
        singlePreviewTrigger.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openPreviewModal(0);
            }
        });
    }

    if (modalPrev) {
        modalPrev.addEventListener('click', (e) => {
            e.stopPropagation();
            updateModalSlide(currentSlideIndex - 1);
        });
    }

    if (modalNext) {
        modalNext.addEventListener('click', (e) => {
            e.stopPropagation();
            updateModalSlide(currentSlideIndex + 1);
        });
    }

    if (previewModalClose) {
        previewModalClose.addEventListener('click', closePreviewModal);
    }
    if (previewModal) {
        previewModal.addEventListener('click', (e) => {
            if (e.target === previewModal) closePreviewModal();
        });
    }
    document.addEventListener('keydown', (e) => {
        if (previewModal && previewModal.classList.contains('is-active')) {
            if (e.key === 'Escape') {
                closePreviewModal();
            } else if (e.key === 'ArrowLeft') {
                updateModalSlide(currentSlideIndex - 1);
            } else if (e.key === 'ArrowRight') {
                updateModalSlide(currentSlideIndex + 1);
            }
        }
    });

    document.querySelectorAll('.landing-gallery iframe, .preview-modal-body iframe').forEach(iframe => {
        iframe.addEventListener('load', () => syncIframeTheme(getCurrentLandingTheme()));
    });

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
                        const url = club.slug ? `club_game_results.php?slug=${encodeURIComponent(club.slug)}` : `club_game_results.php?id=${club.club_id}`;
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
    <script src="https://theflyingdutchmen.games/javascripts/tfd-navbar.js"></script>
    <script src="js/mobile-menu.js"></script>
</body>
</html>
