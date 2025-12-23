<?php
/**
 * Navigation Helper
 * Provides reusable navigation components for breadcrumbs, context bars, and navigation menus
 */

class NavigationHelper {
    
    /**
     * Render breadcrumb navigation
     * @param array $items Array of ['label' => 'Link Text', 'url' => 'page.php'] or just 'Label' for current page
     */
    public static function renderBreadcrumbs($items) {
        if (empty($items)) return '';
        
        echo '<nav class="breadcrumb" aria-label="Breadcrumb">';
        
        foreach ($items as $index => $item) {
            $isLast = ($index === count($items) - 1);
            
            echo '<div class="breadcrumb__item">';
            
            if ($isLast) {
                // Current page - no link
                if (is_array($item)) {
                    echo '<span class="breadcrumb__current">' . htmlspecialchars($item['label']) . '</span>';
                } else {
                    echo '<span class="breadcrumb__current">' . htmlspecialchars($item) . '</span>';
                }
            } else {
                // Link to previous page
                if (is_array($item)) {
                    echo '<a href="' . htmlspecialchars($item['url']) . '" class="breadcrumb__link">';
                    echo htmlspecialchars($item['label']);
                    echo '</a>';
                }
            }
            
            echo '</div>';
        }
        
        echo '</nav>';
    }
    
    /**
     * Render context bar showing what club/game/member the user is viewing
     * @param string $label The context label (e.g., "Club", "Game", "Member")
     * @param string $value The value to display
     * @param string $linkText Optional link text
     * @param string $linkUrl Optional link URL
     */
    public static function renderContextBar($label, $value, $linkText = null, $linkUrl = null) {
        echo '<div class="context-bar">';
        echo '<span class="context-label">' . htmlspecialchars($label) . ':</span>';
        echo '<span class="context-value"><strong>' . htmlspecialchars($value) . '</strong></span>';
        
        if ($linkText && $linkUrl) {
            echo '<a href="' . htmlspecialchars($linkUrl) . '" class="context-link">' . htmlspecialchars($linkText) . '</a>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render mobile-friendly card navigation (shows on mobile, hidden on desktop)
     * @param string $currentPage The current page identifier
     * @param int|null $clubId Optional club ID for context-specific links
     */
    public static function renderMobileCardNav($currentPage = '', $clubId = null) {
        echo '<nav class="mobile-card-nav" aria-label="Mobile navigation">';
        
        // Home card - always show
        echo '<a href="index.php" class="nav-card' . ($currentPage === 'home' ? ' nav-card--active' : '') . '">';
        echo '<div class="nav-card__icon">🏠</div>';
        echo '<div class="nav-card__label">Home</div>';
        echo '</a>';
        
        // Club-specific cards
        if ($clubId) {
            echo '<a href="club_stats.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'club_stats' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon">📊</div>';
            echo '<div class="nav-card__label">Club Stats</div>';
            echo '</a>';
            
            echo '<a href="club_game_list.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'games' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon">🎲</div>';
            echo '<div class="nav-card__label">Games</div>';
            echo '</a>';
            
            echo '<a href="club_game_results.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'results' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon">🏆</div>';
            echo '<div class="nav-card__label">Results</div>';
            echo '</a>';
            
            echo '<a href="game_days.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'game_days' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon">📅</div>';
            echo '<div class="nav-card__label">Game Days</div>';
            echo '</a>';
            
            echo '<a href="club_champions.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'champions' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon">🏆</div>';
            echo '<div class="nav-card__label">Champions</div>';
            echo '</a>';
        }
        
        echo '</nav>';
    }
    
    /**
     * Render public navigation menu
     * @param string $currentPage The current page identifier
     * @param int|null $clubId Optional club ID for context-specific links
     */
    public static function renderPublicNav($currentPage = '', $clubId = null) {
        echo '<nav class="main-nav" aria-label="Main navigation">';

        echo '<a href="index.php" class="nav-link ' . ($currentPage === 'home' ? 'active' : '') . '">Home</a>';

        if ($clubId) {
            echo '<a href="club_stats.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'club_stats' ? 'active' : '') . '">Club Stats</a>';

            echo '<a href="club_game_list.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'games' ? 'active' : '') . '">Games</a>';

            echo '<a href="club_game_results.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'results' ? 'active' : '') . '">Results</a>';

            echo '<a href="game_days.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'game_days' ? 'active' : '') . '">Game Days</a>';

            echo '<a href="club_champions.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'champions' ? 'active' : '') . '">Champions</a>';
        }

        echo '</nav>';
    }
    
    /**
     * Render admin navigation menu
     * @param string $currentPage The current page identifier
     * @param int|null $clubId Optional club ID for context-specific links
     */
    public static function renderAdminNav($currentPage = '', $clubId = null) {
        echo '<nav class="admin-nav" aria-label="Admin navigation">';

        echo '<a href="dashboard.php" class="nav-link ' . ($currentPage === 'dashboard' ? 'active' : '') . '">Dashboard</a>';

        echo '<a href="manage_clubs.php" class="nav-link ' . ($currentPage === 'clubs' ? 'active' : '') . '">Clubs</a>';

        if ($clubId) {
            echo '<a href="manage_members.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'members' ? 'active' : '') . '">Members</a>';

            echo '<a href="manage_games.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'games' ? 'active' : '') . '">Games</a>';

            echo '<a href="manage_champions.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'champions' ? 'active' : '') . '">Champions</a>';

            echo '<a href="club_teams.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'teams' ? 'active' : '') . '">Teams</a>';
        }

        echo '<a href="account.php" class="nav-link ' . ($currentPage === 'account' ? 'active' : '') . '">Account</a>';

        echo '</nav>';
    }
    
    /**
     * Render clickable header title
     * @param string $title The page title
     * @param string $subtitle Optional subtitle
     * @param string $homeUrl The URL to link to (usually index.php or dashboard.php)
     * @param bool $makeClickable Whether to make the title clickable
     */
    public static function renderHeaderTitle($title, $subtitle = '', $homeUrl = 'index.php', $makeClickable = true) {
        echo '<div class="header-title-group">';
        
        if ($makeClickable) {
            echo '<a href="' . htmlspecialchars($homeUrl) . '" class="header-title-link">';
            echo '<h1>' . htmlspecialchars($title) . '</h1>';
            echo '</a>';
        } else {
            echo '<h1>' . htmlspecialchars($title) . '</h1>';
        }
        
        if ($subtitle) {
            echo '<p class="header-subtitle">' . htmlspecialchars($subtitle) . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render quick action buttons
     * @param array $actions Array of ['label' => 'Text', 'url' => 'page.php', 'style' => 'btn--secondary']
     */
    public static function renderQuickActions($actions) {
        if (empty($actions)) return '';
        
        echo '<div class="quick-actions">';
        
        foreach ($actions as $action) {
            $buttonClass = isset($action['style']) ? $action['style'] : 'btn--ghost';
            $icon = isset($action['icon']) ? '<span class="nav-icon">' . $action['icon'] . '</span>' : '';
            
            echo '<a href="' . htmlspecialchars($action['url']) . '" class="btn ' . $buttonClass . ' btn--small">';
            echo $icon . htmlspecialchars($action['label']);
            echo '</a>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get club name by ID
     * @param PDO $pdo Database connection
     * @param int $clubId Club ID
     * @return string Club name or empty string
     */
    public static function getClubName($pdo, $clubId) {
        $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
        $stmt->execute([$clubId]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);
        return $club ? $club['club_name'] : '';
    }
    
    /**
     * Get game name by ID
     * @param PDO $pdo Database connection
     * @param int $gameId Game ID
     * @return string Game name or empty string
     */
    public static function getGameName($pdo, $gameId) {
        $stmt = $pdo->prepare("SELECT game_name FROM games WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        return $game ? $game['game_name'] : '';
    }
    
    /**
     * Get member nickname by ID
     * @param PDO $pdo Database connection
     * @param int $memberId Member ID
     * @return string Member nickname or empty string
     */
    public static function getMemberNickname($pdo, $memberId) {
        $stmt = $pdo->prepare("SELECT nickname FROM members WHERE member_id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        return $member ? $member['nickname'] : '';
    }

    /**
     * Render sidebar navigation for public pages
     * @param string $currentPage The current page identifier
     * @param int|null $clubId Optional club ID for context-specific links
     * @param string|null $clubName Optional club name for display
     * @param string|null $clubLogo Optional club logo filename
     */
    public static function renderSidebar($currentPage = '', $clubId = null, $clubName = null, $clubLogo = null) {
        // Output critical responsive styles
        echo '<style>
            .sidebar{position:fixed!important;top:0!important;left:0!important;width:260px!important;height:100vh!important;background:#fff!important;border-right:1px solid #e2e8f0!important;display:flex!important;flex-direction:column!important;z-index:1100!important;overflow-y:auto!important;transition:transform .3s ease!important;box-shadow:2px 0 8px rgba(0,0,0,.05)!important}
            .has-sidebar .header{margin-left:260px!important;width:calc(100% - 260px)!important}
            .has-sidebar .container{margin-left:260px!important}
            .sidebar-toggle{display:none!important}
            .sidebar__close{display:none!important}
            .sidebar-overlay{display:none!important;position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;background:rgba(15,23,42,.5)!important;z-index:1050!important}
            @media(max-width:768px){
                .sidebar{transform:translateX(-100%)!important;width:280px!important;box-shadow:4px 0 20px rgba(0,0,0,.15)!important}
                .sidebar.sidebar--open{transform:translateX(0)!important}
                .sidebar__close{display:flex!important}
                .sidebar-toggle{display:flex!important}
                .has-sidebar .header,.has-sidebar .container{margin-left:0!important;width:100%!important;max-width:100%!important}
                body.sidebar-open{overflow:hidden!important}
                .sidebar-overlay.sidebar-overlay--visible{display:block!important;opacity:1!important}
            }
        </style>';

        echo '<aside class="sidebar" aria-label="Main navigation">';

        // Close button (mobile)
        echo '<button class="sidebar__close" aria-label="Close menu" style="display:none;position:absolute;top:1rem;right:1rem;width:32px;height:32px;background:#f1f5f9;border:none;border-radius:0.375rem;cursor:pointer;font-size:1.25rem;align-items:center;justify-content:center;">&times;</button>';

        // Header with app logo
        echo '<div class="sidebar__header" style="padding:1.5rem 1rem;border-bottom:1px solid #e2e8f0;flex-shrink:0;">';
        echo '<a href="index.php" class="sidebar__logo" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;color:#1e293b;font-weight:700;font-size:1.125rem;">';
        echo '<span class="sidebar__logo-icon" style="width:36px;height:36px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:0.75rem;display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:white;">🎲</span>';
        echo '<span>StatApp</span>';
        echo '</a>';
        echo '</div>';

        // Navigation
        echo '<nav class="sidebar__nav" style="flex:1;padding:1rem 0;">';

        // Main section - always show
        echo '<div class="sidebar__section" style="margin-bottom:0.5rem;">';
        $activeStyle = 'display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;margin:0.25rem 0.5rem;text-decoration:none;font-size:0.875rem;font-weight:500;border-radius:0.75rem;';
        $normalLinkStyle = $activeStyle . 'color:#475569;';
        $activeLinkStyle = $activeStyle . 'color:#6366f1;background:#e0e7ff;font-weight:600;';

        echo '<a href="index.php" class="sidebar__link' . ($currentPage === 'home' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'home' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;">🏠</span>';
        echo '<span>Home</span>';
        echo '</a>';

        // Search/Find club link (when no club selected)
        if (!$clubId) {
            echo '<a href="index.php#clubs" class="sidebar__link' . ($currentPage === 'search' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'search' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;">🔍</span>';
            echo '<span>Find a Club</span>';
            echo '</a>';
        }
        echo '</div>';

        // Club-specific navigation (only when viewing a club)
        if ($clubId) {
            echo '<div class="sidebar__divider" style="height:1px;background:#e2e8f0;margin:0.75rem 1rem;"></div>';
            echo '<div class="sidebar__section" style="margin-bottom:0.5rem;">';
            echo '<div class="sidebar__section-title" style="padding:0.5rem 1rem;font-size:0.75rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Club</div>';

            echo '<a href="club_stats.php?id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'club_stats' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'club_stats' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;">📊</span>';
            echo '<span>Club Stats</span>';
            echo '</a>';

            echo '<a href="club_game_list.php?id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'games' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'games' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;">🎲</span>';
            echo '<span>Games</span>';
            echo '</a>';

            echo '<a href="club_game_results.php?id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'results' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'results' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;">🏆</span>';
            echo '<span>Results</span>';
            echo '</a>';

            echo '<a href="game_days.php?id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'game_days' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'game_days' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;">📅</span>';
            echo '<span>Game Days</span>';
            echo '</a>';

            echo '<a href="club_champions.php?id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'champions' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'champions' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;">👑</span>';
            echo '<span>Champions</span>';
            echo '</a>';

            echo '</div>';
        }

        echo '</nav>';

        // Footer with club info (if viewing a club)
        if ($clubId && $clubName) {
            echo '<div class="sidebar__footer" style="padding:1rem;border-top:1px solid #e2e8f0;flex-shrink:0;">';
            echo '<a href="club_stats.php?id=' . (int)$clubId . '" class="sidebar__club-info" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;background:#f1f5f9;border-radius:0.75rem;text-decoration:none;color:#0f172a;">';

            if ($clubLogo) {
                echo '<img src="images/club_logos/' . htmlspecialchars($clubLogo) . '" alt="" class="sidebar__club-logo" style="width:40px;height:40px;border-radius:0.375rem;object-fit:cover;">';
            } else {
                echo '<div class="sidebar__club-logo-placeholder" style="width:40px;height:40px;border-radius:0.375rem;background:linear-gradient(135deg,#e0e7ff,#ddd6fe);display:flex;align-items:center;justify-content:center;font-size:1.25rem;">🎯</div>';
            }

            echo '<div>';
            echo '<div class="sidebar__club-label" style="font-size:0.75rem;color:#94a3b8;">Viewing</div>';
            echo '<div class="sidebar__club-name" style="font-size:0.875rem;font-weight:600;color:#1e293b;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . htmlspecialchars($clubName) . '</div>';
            echo '</div>';
            echo '</a>';
            echo '</div>';
        }

        echo '</aside>';

        // Overlay for mobile
        echo '<div class="sidebar-overlay" aria-hidden="true" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.5);z-index:1050;"></div>';
    }

    /**
     * Render sidebar toggle button for mobile
     */
    public static function renderSidebarToggle() {
        // display:none on desktop, display:flex on mobile (handled by CSS in renderSidebar)
        echo '<button class="sidebar-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar" style="align-items:center;justify-content:center;width:40px;height:40px;background:transparent;border:1px solid #e2e8f0;border-radius:0.75rem;color:#0f172a;cursor:pointer;flex-shrink:0;">';
        echo '<span class="sidebar-toggle__icon" style="width:20px;height:20px;display:flex;flex-direction:column;justify-content:center;gap:4px;">';
        echo '<span class="sidebar-toggle__bar" style="width:100%;height:2px;background:currentColor;border-radius:1px;"></span>';
        echo '<span class="sidebar-toggle__bar" style="width:100%;height:2px;background:currentColor;border-radius:1px;"></span>';
        echo '<span class="sidebar-toggle__bar" style="width:100%;height:2px;background:currentColor;border-radius:1px;"></span>';
        echo '</span>';
        echo '</button>';
    }

    /**
     * Render compact header for pages with sidebar
     * @param string $title Page title
     * @param string $subtitle Optional subtitle
     */
    public static function renderCompactHeader($title, $subtitle = '') {
        echo '<div class="header-title-group" style="flex:1;">';
        echo '<h1 style="margin:0;font-size:1.25rem;">' . htmlspecialchars($title) . '</h1>';
        if ($subtitle) {
            echo '<p class="header-subtitle" style="margin:0;font-size:0.875rem;color:#64748b;">' . htmlspecialchars($subtitle) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render admin sidebar navigation
     * @param string $currentPage The current page identifier
     * @param int|null $clubId Optional club ID for context-specific links
     * @param string|null $clubName Optional club name for display
     */
    public static function renderAdminSidebar($currentPage = '', $clubId = null, $clubName = null) {
        // Output critical responsive styles for admin sidebar
        echo '<style>
            .sidebar{position:fixed!important;top:0!important;left:0!important;width:260px!important;height:100vh!important;background:#1e293b!important;border-right:1px solid #334155!important;display:flex!important;flex-direction:column!important;z-index:1100!important;overflow-y:auto!important;transition:transform .3s ease!important;box-shadow:2px 0 8px rgba(0,0,0,.1)!important}
            .has-sidebar .header{margin-left:260px!important;width:calc(100% - 260px)!important}
            .has-sidebar .container{margin-left:260px!important}
            .sidebar-toggle{display:none!important;background:rgba(255,255,255,0.1)!important;border:1px solid rgba(255,255,255,0.25)!important;color:#f1f5f9!important}
            .sidebar__close{display:none!important}
            .sidebar-overlay{display:none!important;position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;background:rgba(15,23,42,.5)!important;z-index:1050!important}
            @media(max-width:768px){
                .sidebar{transform:translateX(-100%)!important;width:280px!important;box-shadow:4px 0 20px rgba(0,0,0,.25)!important}
                .sidebar.sidebar--open{transform:translateX(0)!important}
                .sidebar__close{display:flex!important}
                .sidebar-toggle{display:flex!important}
                .has-sidebar .header,.has-sidebar .container{margin-left:0!important;width:100%!important;max-width:100%!important}
                body.sidebar-open{overflow:hidden!important}
                .sidebar-overlay.sidebar-overlay--visible{display:block!important;opacity:1!important}
            }
        </style>';

        echo '<aside class="sidebar" aria-label="Admin navigation">';

        // Close button (mobile)
        echo '<button class="sidebar__close" aria-label="Close menu" style="display:none;position:absolute;top:1rem;right:1rem;width:32px;height:32px;background:rgba(255,255,255,0.1);border:none;border-radius:0.375rem;cursor:pointer;font-size:1.25rem;color:#94a3b8;align-items:center;justify-content:center;">&times;</button>';

        // Header with app logo
        echo '<div class="sidebar__header" style="padding:1.5rem 1rem;border-bottom:1px solid #334155;flex-shrink:0;">';
        echo '<a href="dashboard.php" class="sidebar__logo" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;color:#f1f5f9;font-weight:700;font-size:1.125rem;">';
        echo '<span class="sidebar__logo-icon" style="width:36px;height:36px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:0.75rem;display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:white;">🎲</span>';
        echo '<span>StatApp Admin</span>';
        echo '</a>';
        echo '</div>';

        // Navigation
        echo '<nav class="sidebar__nav" style="flex:1;padding:1rem 0;">';

        // Link styles for admin (dark theme)
        $activeStyle = 'display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;margin:0.25rem 0.5rem;text-decoration:none;font-size:0.875rem;font-weight:500;border-radius:0.75rem;';
        $normalLinkStyle = $activeStyle . 'color:#94a3b8;';
        $activeLinkStyle = $activeStyle . 'color:#a5b4fc;background:rgba(99,102,241,0.15);font-weight:600;';
        $iconStyle = 'width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;';

        // Main section - always show
        echo '<div class="sidebar__section" style="margin-bottom:0.5rem;">';

        echo '<a href="dashboard.php" class="sidebar__link' . ($currentPage === 'dashboard' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'dashboard' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">📊</span>';
        echo '<span>Dashboard</span>';
        echo '</a>';

        // Only show Clubs link for multi_club admins
        $adminType = $_SESSION['admin_type'] ?? 'multi_club';
        if ($adminType !== 'single_club') {
            echo '<a href="manage_clubs.php" class="sidebar__link' . ($currentPage === 'clubs' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'clubs' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">🏠</span>';
            echo '<span>Clubs</span>';
            echo '</a>';
        } else {
            // For single_club admins, show Edit Club link
            global $pdo;
            if (isset($pdo) && isset($_SESSION['admin_id'])) {
                $singleClubStmt = $pdo->prepare("SELECT club_id FROM club_admins WHERE admin_id = ? LIMIT 1");
                $singleClubStmt->execute([$_SESSION['admin_id']]);
                $singleClubId = $singleClubStmt->fetchColumn();
                if ($singleClubId) {
                    echo '<a href="edit_club.php?id=' . (int)$singleClubId . '" class="sidebar__link' . ($currentPage === 'edit_club' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'edit_club' ? $activeLinkStyle : $normalLinkStyle) . '">';
                    echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">✏️</span>';
                    echo '<span>Edit Club</span>';
                    echo '</a>';
                }
            }
        }

        echo '</div>';

        // Quick Actions section - always show
        echo '<div class="sidebar__divider" style="height:1px;background:#334155;margin:0.75rem 1rem;"></div>';
        echo '<div class="sidebar__section" style="margin-bottom:0.5rem;">';
        echo '<div class="sidebar__section-title" style="padding:0.5rem 1rem;font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Quick Actions</div>';

        echo '<a href="new_result.php" class="sidebar__link' . ($currentPage === 'new_result' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'new_result' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">➕</span>';
        echo '<span>New Result</span>';
        echo '</a>';

        echo '</div>';

        // Club-specific navigation (only when managing a specific club)
        if ($clubId) {
            echo '<div class="sidebar__divider" style="height:1px;background:#334155;margin:0.75rem 1rem;"></div>';
            echo '<div class="sidebar__section" style="margin-bottom:0.5rem;">';
            echo '<div class="sidebar__section-title" style="padding:0.5rem 1rem;font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Club Management</div>';

            echo '<a href="manage_members.php?club_id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'members' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'members' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">👥</span>';
            echo '<span>Members</span>';
            echo '</a>';

            echo '<a href="manage_games.php?club_id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'games' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'games' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">🎲</span>';
            echo '<span>Games</span>';
            echo '</a>';

            echo '<a href="manage_champions.php?club_id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'champions' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'champions' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">🏆</span>';
            echo '<span>Champions</span>';
            echo '</a>';

            echo '<a href="club_teams.php?club_id=' . (int)$clubId . '" class="sidebar__link' . ($currentPage === 'teams' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'teams' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">👨‍👩‍👧‍👦</span>';
            echo '<span>Teams</span>';
            echo '</a>';

            echo '</div>';
        }

        // Account section
        echo '<div class="sidebar__divider" style="height:1px;background:#334155;margin:0.75rem 1rem;"></div>';
        echo '<div class="sidebar__section" style="margin-bottom:0.5rem;">';

        echo '<a href="account.php" class="sidebar__link' . ($currentPage === 'account' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'account' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">⚙️</span>';
        echo '<span>Account</span>';
        echo '</a>';

        echo '<a href="logout.php" class="sidebar__link" style="' . $normalLinkStyle . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '">🚪</span>';
        echo '<span>Logout</span>';
        echo '</a>';

        echo '</div>';

        echo '</nav>';

        // Footer with club info (if managing a specific club)
        if ($clubId && $clubName) {
            echo '<div class="sidebar__footer" style="padding:1rem;border-top:1px solid #334155;flex-shrink:0;">';
            echo '<a href="manage_clubs.php" class="sidebar__club-info" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;background:rgba(255,255,255,0.05);border-radius:0.75rem;text-decoration:none;color:#f1f5f9;">';
            echo '<div class="sidebar__club-logo-placeholder" style="width:40px;height:40px;border-radius:0.375rem;background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.3));display:flex;align-items:center;justify-content:center;font-size:1.25rem;">🎯</div>';
            echo '<div>';
            echo '<div class="sidebar__club-label" style="font-size:0.75rem;color:#64748b;">Managing</div>';
            echo '<div class="sidebar__club-name" style="font-size:0.875rem;font-weight:600;color:#f1f5f9;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . htmlspecialchars($clubName) . '</div>';
            echo '</div>';
            echo '</a>';
            echo '</div>';
        }

        echo '</aside>';

        // Overlay for mobile
        echo '<div class="sidebar-overlay" aria-hidden="true"></div>';
    }
}
