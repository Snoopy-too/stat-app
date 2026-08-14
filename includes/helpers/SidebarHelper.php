<?php
/**
 * Sidebar & Navigation Helper
 * Handles rendering of public and admin sidebars, mobile nav cards, and navigation links.
 */

class SidebarHelper {

    /**
     * Render mobile-friendly card navigation
     */
    public static function renderMobileCardNav($currentPage = '', $clubId = null) {
        echo '<nav class="mobile-card-nav" aria-label="Mobile navigation">';
        
        echo '<a href="index.php" class="nav-card' . ($currentPage === 'home' ? ' nav-card--active' : '') . '">';
        echo '<div class="nav-card__icon"><span class="material-symbols-outlined">home</span></div>';
        echo '<div class="nav-card__label">Home</div>';
        echo '</a>';
        
        if ($clubId) {
            echo '<a href="club_members.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'members' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon"><span class="material-symbols-outlined">group</span></div>';
            echo '<div class="nav-card__label">Members</div>';
            echo '</a>';

            echo '<a href="club_teams.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'teams' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon"><span class="material-symbols-outlined">groups</span></div>';
            echo '<div class="nav-card__label">Teams</div>';
            echo '</a>';

            echo '<a href="club_champions.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'champions' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon"><span class="material-symbols-outlined">emoji_events</span></div>';
            echo '<div class="nav-card__label">Champions</div>';
            echo '</a>';

            echo '<a href="club_game_list.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'games' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon"><span class="material-symbols-outlined">casino</span></div>';
            echo '<div class="nav-card__label">Games</div>';
            echo '</a>';
            
            echo '<a href="club_game_results.php?id=' . (int)$clubId . '" class="nav-card' . ($currentPage === 'results' ? ' nav-card--active' : '') . '">';
            echo '<div class="nav-card__icon"><span class="material-symbols-outlined">leaderboard</span></div>';
            echo '<div class="nav-card__label">Results</div>';
            echo '</a>';
        }
        
        echo '</nav>';
    }

    /**
     * Render public navigation menu
     */
    public static function renderPublicNav($currentPage = '', $clubId = null) {
        echo '<nav class="main-nav" aria-label="Main navigation">';
        echo '<a href="index.php" class="nav-link ' . ($currentPage === 'home' ? 'active' : '') . '">Home</a>';

        if ($clubId) {
            echo '<a href="club_members.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'members' ? 'active' : '') . '">Members</a>';
            echo '<a href="club_teams.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'teams' ? 'active' : '') . '">Teams</a>';
            echo '<a href="club_champions.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'champions' ? 'active' : '') . '">Champions</a>';
            echo '<a href="club_game_list.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'games' ? 'active' : '') . '">Games</a>';
            echo '<a href="club_game_results.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'results' ? 'active' : '') . '">Results</a>';
        }

        echo '</nav>';
    }

    /**
     * Render admin navigation menu
     */
    public static function renderAdminNav($currentPage = '', $clubId = null) {
        echo '<nav class="admin-nav" aria-label="Admin navigation">';
        echo '<a href="account.php" class="nav-link ' . ($currentPage === 'account' ? 'active' : '') . '">Account</a>';

        if ($clubId) {
            echo '<a href="manage_members.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'members' ? 'active' : '') . '">Members</a>';
            echo '<a href="manage_teams.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'teams' ? 'active' : '') . '">Teams</a>';
            echo '<a href="manage_champions.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'champions' ? 'active' : '') . '">Champions</a>';
            echo '<a href="manage_games.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'games' ? 'active' : '') . '">Games</a>';
            echo '<a href="manage_results.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'results' ? 'active' : '') . '">Results</a>';
        }

        echo '</nav>';
    }

    /**
     * Render sidebar navigation for public pages
     */
    public static function renderSidebar($currentPage = '', $clubId = null, $clubName = null, $clubLogo = null) {
        global $pdo;

        $clubTheme = null;

        if (!$clubId && !empty($_SESSION['current_club_id'])) {
            $clubId = (int)$_SESSION['current_club_id'];
        }
        if (!$clubId && !empty($_SESSION['club_id'])) {
            $clubId = (int)$_SESSION['club_id'];
        }

        if ($clubId && isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT club_name, logo_image, theme FROM clubs WHERE club_id = ?");
                $stmt->execute([$clubId]);
                $cData = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cData) {
                    if (empty($clubName)) {
                        $clubName = $cData['club_name'];
                    }
                    if ($clubLogo === null) {
                        $clubLogo = $cData['logo_image'];
                    }
                    $clubTheme = $cData['theme'] ?? 'midnight';
                }
            } catch (Throwable $e) {}
        }

        if (!empty($_GET['theme'])) {
            $clubTheme = $_GET['theme'];
        }

        if ($clubTheme) {
            echo '<script>if(!document.documentElement.hasAttribute("data-theme-locked")){document.documentElement.setAttribute("data-club-theme", ' . json_encode($clubTheme) . ');}</script>';
        }

        $memberCount = null;
        $teamCount = null;
        $championCount = null;
        $gameCount = null;
        $resultCount = null;

        if ($clubId && isset($pdo)) {
            try {
                $cStmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM members WHERE club_id = ?) as member_count,
                        (SELECT COUNT(*) FROM teams WHERE club_id = ?) as team_count,
                        (SELECT COUNT(*) FROM champions WHERE club_id = ?) as champion_count,
                        (SELECT COUNT(*) FROM games WHERE club_id = ?) as game_count,
                        (
                            SELECT COUNT(*) FROM (
                                SELECT g.game_id FROM games g INNER JOIN game_results gr ON g.game_id = gr.game_id WHERE g.club_id = ?
                                UNION ALL
                                SELECT g.game_id FROM games g INNER JOIN team_game_results tgr ON g.game_id = tgr.game_id WHERE g.club_id = ?
                                UNION ALL
                                SELECT g.game_id FROM games g INNER JOIN cooperative_game_results cgr ON g.game_id = cgr.game_id WHERE g.club_id = ?
                            ) as all_results
                        ) as result_count
                ");
                $cStmt->execute([$clubId, $clubId, $clubId, $clubId, $clubId, $clubId, $clubId]);
                $counts = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($counts) {
                    $memberCount = (int)$counts['member_count'];
                    $teamCount = (int)$counts['team_count'];
                    $championCount = (int)$counts['champion_count'];
                    $gameCount = (int)$counts['game_count'];
                    $resultCount = (int)$counts['result_count'];
                }
            } catch (Throwable $e) {}
        }

        $clubQuery = $clubId ? '?id=' . (int)$clubId : '';
        $displayName = !empty($clubName) ? $clubName : 'StatApp';

        echo '<style>
            .sidebar{position:fixed!important;top:0!important;left:0!important;width:260px!important;height:100vh!important;background:var(--sidebar-bg, var(--color-surface, #1e293b))!important;border-right:1px solid var(--sidebar-border, var(--color-border, #334155))!important;color:var(--color-text, #f8fafc)!important;display:flex!important;flex-direction:column!important;z-index:1100!important;overflow-y:auto!important;transition:transform .3s ease, background .3s ease, border-color .3s ease!important;box-shadow:2px 0 8px rgba(0,0,0,.1)!important}
            .has-sidebar .header{margin-left:260px!important;width:calc(100% - 260px)!important}
            .has-sidebar .container{margin-left:calc(260px + max(1rem, (100% - 260px - var(--container-max, 75rem)) / 2))!important;margin-right:max(1rem, (100% - 260px - var(--container-max, 75rem)) / 2)!important;width:auto!important;max-width:calc(100% - 260px - 2rem)!important;}
            .has-sidebar .container--narrow{margin-left:calc(260px + max(1rem, (100% - 260px - 42rem) / 2))!important;margin-right:max(1rem, (100% - 260px - 42rem) / 2)!important;width:auto!important;}
            .has-sidebar .container--medium{margin-left:calc(260px + max(1rem, (100% - 260px - 64rem) / 2))!important;margin-right:max(1rem, (100% - 260px - 64rem) / 2)!important;width:auto!important;}
            .has-sidebar .container--wide{margin-left:calc(260px + max(1rem, (100% - 260px - var(--container-wide, 85rem)) / 2))!important;margin-right:max(1rem, (100% - 260px - var(--container-wide, 85rem)) / 2)!important;width:auto!important;}
            .sidebar-toggle{display:none!important;background:rgba(255,255,255,0.1)!important;border:1px solid rgba(255,255,255,0.25)!important;color:#f1f5f9!important}
            .sidebar__close{display:none!important}
            .sidebar-overlay{display:none!important;position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;background:rgba(15,23,42,.5)!important;z-index:1050!important}
            @media(max-width:768px), (max-height:500px){
                .sidebar{transform:translateX(-100%)!important;width:280px!important;box-shadow:4px 0 20px rgba(0,0,0,.25)!important}
                .sidebar.sidebar--open{transform:translateX(0)!important}
                .sidebar__close{display:flex!important}
                .sidebar-toggle{display:flex!important}
                .has-sidebar .header,.has-sidebar .container,.has-sidebar .container--narrow,.has-sidebar .container--medium,.has-sidebar .container--wide{margin-left:0!important;margin-right:0!important;width:100%!important;max-width:100%!important}
                body.sidebar-open{overflow:hidden!important}
                .sidebar-overlay.sidebar-overlay--visible{display:block!important;opacity:1!important}
            }
            .sidebar__link:hover:not(.sidebar__link--logout) {
                background: var(--color-surface, rgba(255,255,255,0.15)) !important;
                border-color: var(--color-border-strong, rgba(255,255,255,0.3)) !important;
                color: var(--color-heading, #ffffff) !important;
            }
            .sidebar__link--active:hover:not(.sidebar__link--logout) {
                background: var(--color-primary-soft, rgba(99,102,241,0.35)) !important;
                border-color: var(--color-primary, #6366f1) !important;
                color: var(--color-heading, #ffffff) !important;
                filter: brightness(1.15) !important;
            }
        </style>';

        echo '<aside class="sidebar" style="position:fixed;top:0;left:0;bottom:0;width:260px;background:var(--sidebar-bg, var(--color-surface, #1e293b));border-right:1px solid var(--sidebar-border, var(--color-border, #334155));color:var(--color-text, #f8fafc);z-index:1050;display:flex;flex-direction:column;box-shadow:4px 0 12px rgba(0,0,0,0.15);transition:transform 0.3s ease, background 0.3s ease, border-color 0.3s ease;">';
        echo '<button class="sidebar__close" aria-label="Close menu" style="display:none;position:absolute;top:1rem;right:1rem;width:32px;height:32px;background:rgba(255,255,255,0.1);border:none;border-radius:0.375rem;cursor:pointer;font-size:1.25rem;color:var(--color-text-soft, #94a3b8);align-items:center;justify-content:center;">&times;</button>';

        $activeStyle = 'display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0.85rem;margin:0.25rem 0.5rem;text-decoration:none;font-size:0.875rem;font-weight:500;border-radius:0.5rem;transition:all 0.2s ease;';
        $normalLinkStyle = $activeStyle . 'color:var(--color-text, #f8fafc);background:var(--color-surface-muted, rgba(255,255,255,0.08));border:1px solid var(--color-border, #334155);';
        $activeLinkStyle = $activeStyle . 'color:var(--color-primary, #a5b4fc);background:var(--color-primary-soft, rgba(99,102,241,0.25));border:1px solid var(--color-primary, #6366f1);font-weight:600;';
        $iconStyle = 'width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;';

        echo '<div class="sidebar__header" style="padding:1.25rem 0.5rem 1rem 0.5rem;border-bottom:1px solid var(--color-border, #334155);flex-shrink:0;">';
        echo '<a href="index.php" class="sidebar__logo" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;color:var(--color-heading, #f1f5f9);font-weight:700;font-size:1.125rem;padding:0.25rem 0.5rem;margin-bottom:0.75rem;">';
        $isDemoMode = isset($_GET['demo']) || isset($_GET['preview']);
        if (!empty($clubLogo) && !$isDemoMode) {
            $logoUrl = function_exists('get_club_logo_url') ? get_club_logo_url($clubLogo, '') : (preg_match('~^https?://~i', $clubLogo) ? $clubLogo : 'images/club_logos/' . $clubLogo);
            echo '<img src="' . htmlspecialchars($logoUrl) . '" alt="" style="width:36px;height:36px;border-radius:0.75rem;object-fit:cover;flex-shrink:0;" onerror="this.onerror=null; this.style.display=\'none\'; if(this.nextElementSibling) this.nextElementSibling.style.display=\'flex\';">';
            echo '<span class="sidebar__logo-icon" style="display:none;width:36px;height:36px;background:linear-gradient(135deg,var(--color-primary, #6366f1),var(--color-accent, #8b5cf6));border-radius:0.75rem;align-items:center;justify-content:center;font-size:1.25rem;color:white;flex-shrink:0;"><span class="material-symbols-outlined" style="font-size:1.4rem;">casino</span></span>';
        } else {
            echo '<span class="sidebar__logo-icon" style="width:36px;height:36px;background:linear-gradient(135deg,var(--color-primary, #6366f1),var(--color-accent, #8b5cf6));border-radius:0.75rem;display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:white;flex-shrink:0;"><span class="material-symbols-outlined" style="font-size:1.4rem;">casino</span></span>';
        }
        echo '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . htmlspecialchars($displayName) . '</span>';
        echo '</a>';

        echo '<div style="display:flex;align-items:center;gap:0.35rem;margin:0 0.5rem;">';
        echo '<a href="index.php" class="sidebar__link' . ($currentPage === 'home' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'home' ? $activeLinkStyle : $normalLinkStyle) . ';flex:1;margin:0;">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">home</span></span>';
        echo '<span>Home</span>';
        echo '</a>';
        echo '</div>';
        echo '</div>';

        echo '<nav class="sidebar__nav" style="flex:1;padding:1rem 0;overflow-y:auto;">';

        if ($clubId) {
            echo '<div class="sidebar__section" style="margin-bottom:0.5rem;">';

            $mLabel = 'Members' . ($memberCount !== null ? ' (' . $memberCount . ')' : '');
            $tLabel = 'Teams' . ($teamCount !== null ? ' (' . $teamCount . ')' : '');
            $cLabel = 'Champions' . ($championCount !== null ? ' (' . $championCount . ')' : '');
            $gLabel = 'Games' . ($gameCount !== null ? ' (' . $gameCount . ')' : '');
            $rLabel = 'Results' . ($resultCount !== null ? ' (' . $resultCount . ')' : '');

            echo '<a href="club_game_results.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'results' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'results' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">leaderboard</span></span>';
            echo '<span>' . htmlspecialchars($rLabel) . '</span>';
            echo '</a>';

            echo '<a href="club_members.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'members' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'members' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">group</span></span>';
            echo '<span>' . htmlspecialchars($mLabel) . '</span>';
            echo '</a>';

            echo '<a href="club_teams.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'teams' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'teams' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">groups</span></span>';
            echo '<span>' . htmlspecialchars($tLabel) . '</span>';
            echo '</a>';

            echo '<a href="club_champions.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'champions' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'champions' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">emoji_events</span></span>';
            echo '<span>' . htmlspecialchars($cLabel) . '</span>';
            echo '</a>';

            echo '<a href="club_game_list.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'games' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'games' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">casino</span></span>';
            echo '<span>' . htmlspecialchars($gLabel) . '</span>';
            echo '</a>';

            echo '</div>';
        } else {
            echo '<div class="sidebar__section" style="margin-bottom:0.5rem;">';
            echo '<a href="index.php#clubs" class="sidebar__link' . ($currentPage === 'search' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'search' ? $activeLinkStyle : $normalLinkStyle) . '">';
            echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">search</span></span>';
            echo '<span>Find a Club</span>';
            echo '</a>';
            echo '</div>';
        }

        echo '</nav>';
        echo '</aside>';

        echo '<div class="sidebar-overlay" aria-hidden="true"></div>';
    }

    /**
     * Render sidebar toggle button for mobile
     */
    public static function renderSidebarToggle() {
        echo '<button class="sidebar-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar" style="align-items:center;justify-content:center;width:40px;height:40px;background:var(--color-surface-muted, rgba(255,255,255,0.1));border:1px solid var(--color-border, rgba(255,255,255,0.2));border-radius:0.75rem;color:var(--color-heading, var(--color-text, #0f172a));cursor:pointer;flex-shrink:0;">';
        echo '<span class="sidebar-toggle__icon" style="width:20px;height:20px;display:flex;flex-direction:column;justify-content:center;gap:4px;">';
        echo '<span class="sidebar-toggle__bar" style="width:100%;height:2px;background:currentColor;border-radius:1px;"></span>';
        echo '<span class="sidebar-toggle__bar" style="width:100%;height:2px;background:currentColor;border-radius:1px;"></span>';
        echo '<span class="sidebar-toggle__bar" style="width:100%;height:2px;background:currentColor;border-radius:1px;"></span>';
        echo '</span>';
        echo '</button>';
    }

    /**
     * Render admin sidebar navigation
     */
    public static function renderAdminSidebar($currentPage = '', $clubId = null, $clubName = null, $clubLogo = null) {
        echo '<style>
            .sidebar{position:fixed!important;top:0!important;left:0!important;width:260px!important;height:100vh!important;background:var(--sidebar-bg, var(--color-surface, #1e293b))!important;border-right:1px solid var(--sidebar-border, var(--color-border, #334155))!important;color:var(--color-text, #f8fafc)!important;display:flex!important;flex-direction:column!important;z-index:1100!important;overflow-y:auto!important;transition:transform .3s ease, background .3s ease, border-color .3s ease!important;box-shadow:2px 0 8px rgba(0,0,0,.1)!important}
            .has-sidebar .header{margin-left:260px!important;width:calc(100% - 260px)!important}
            .has-sidebar .container{margin-left:calc(260px + max(1rem, (100% - 260px - var(--container-max, 75rem)) / 2))!important;margin-right:max(1rem, (100% - 260px - var(--container-max, 75rem)) / 2)!important;width:auto!important;max-width:calc(100% - 260px - 2rem)!important;}
            .has-sidebar .container--narrow{margin-left:calc(260px + max(1rem, (100% - 260px - 42rem) / 2))!important;margin-right:max(1rem, (100% - 260px - 42rem) / 2)!important;width:auto!important;}
            .has-sidebar .container--medium{margin-left:calc(260px + max(1rem, (100% - 260px - 64rem) / 2))!important;margin-right:max(1rem, (100% - 260px - 64rem) / 2)!important;width:auto!important;}
            .has-sidebar .container--wide{margin-left:calc(260px + max(1rem, (100% - 260px - var(--container-wide, 85rem)) / 2))!important;margin-right:max(1rem, (100% - 260px - var(--container-wide, 85rem)) / 2)!important;width:auto!important;}
            .sidebar-toggle{display:none!important;background:rgba(255,255,255,0.1)!important;border:1px solid rgba(255,255,255,0.25)!important;color:#f1f5f9!important}
            .sidebar__close{display:none!important}
            .sidebar-overlay{display:none!important;position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;background:rgba(15,23,42,.5)!important;z-index:1050!important}
            @media(max-width:768px), (max-height:500px){
                .sidebar{transform:translateX(-100%)!important;width:280px!important;box-shadow:4px 0 20px rgba(0,0,0,.25)!important}
                .sidebar.sidebar--open{transform:translateX(0)!important}
                .sidebar__close{display:flex!important}
                .sidebar-toggle{display:flex!important}
                .has-sidebar .header,.has-sidebar .container,.has-sidebar .container--narrow,.has-sidebar .container--medium,.has-sidebar .container--wide{margin-left:0!important;margin-right:0!important;width:100%!important;max-width:100%!important}
                body.sidebar-open{overflow:hidden!important}
                .sidebar-overlay.sidebar-overlay--visible{display:block!important;opacity:1!important}
            }
            .sidebar__link:hover:not(.sidebar__link--logout) {
                background: var(--color-surface, rgba(255,255,255,0.15)) !important;
                border-color: var(--color-border-strong, rgba(255,255,255,0.3)) !important;
                color: var(--color-heading, #ffffff) !important;
            }
            .sidebar__link--active:hover:not(.sidebar__link--logout) {
                background: var(--color-primary-soft, rgba(99,102,241,0.35)) !important;
                border-color: var(--color-primary, #6366f1) !important;
                color: var(--color-heading, #ffffff) !important;
                filter: brightness(1.15) !important;
            }
            .sidebar__theme-toggle:hover{background:rgba(255,255,255,0.1)!important}
        </style>';

        global $pdo;

        $clubTheme = null;

        if (!$clubId && !empty($_SESSION['current_club_id'])) {
            $clubId = (int)$_SESSION['current_club_id'];
        }
        if (!$clubId && !empty($_SESSION['club_id'])) {
            $clubId = (int)$_SESSION['club_id'];
        }

        // Auto-fetch default club if not provided to ensure consistent active context
        if (!$clubId && isset($pdo) && isset($_SESSION['admin_id'])) {
            try {
                $stmt = $pdo->prepare("SELECT c.club_id, c.club_name, c.logo_image, c.theme FROM clubs c JOIN club_admins ca ON c.club_id = ca.club_id WHERE ca.admin_id = ? ORDER BY c.club_name ASC LIMIT 1");
                $stmt->execute([$_SESSION['admin_id']]);
                $defaultClub = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($defaultClub) {
                    $clubId = (int)$defaultClub['club_id'];
                    if (!$clubName) {
                        $clubName = $defaultClub['club_name'];
                    }
                    if ($clubLogo === null) {
                        $clubLogo = $defaultClub['logo_image'];
                    }
                    $clubTheme = $defaultClub['theme'] ?? 'midnight';
                }
            } catch (Throwable $e) {}
        } elseif ($clubId && isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT club_name, logo_image, theme FROM clubs WHERE club_id = ?");
                $stmt->execute([$clubId]);
                $cData = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cData) {
                    if (empty($clubName)) {
                        $clubName = $cData['club_name'];
                    }
                    if ($clubLogo === null) {
                        $clubLogo = $cData['logo_image'];
                    }
                    $clubTheme = $cData['theme'] ?? 'midnight';
                }
            } catch (Throwable $e) {}
        }

        if (!empty($_GET['theme'])) {
            $clubTheme = $_GET['theme'];
        }

        if ($clubTheme) {
            echo '<script>if(!document.documentElement.hasAttribute("data-theme-locked")){document.documentElement.setAttribute("data-club-theme", ' . json_encode($clubTheme) . ');}</script>';
        }

        $memberCount = null;
        $teamCount = null;
        $championCount = null;
        $gameCount = null;
        $resultCount = null;

        if ($clubId && isset($pdo)) {
            try {
                $cStmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM members WHERE club_id = ?) as member_count,
                        (SELECT COUNT(*) FROM teams t JOIN members m ON t.member1_id = m.member_id WHERE m.club_id = ?) as team_count,
                        (SELECT COUNT(*) FROM champions WHERE club_id = ?) as champion_count,
                        (SELECT COUNT(*) FROM games WHERE club_id = ?) as game_count,
                        (
                            (SELECT COUNT(*) FROM game_results gr JOIN games g ON gr.game_id = g.game_id WHERE g.club_id = ?) +
                            (SELECT COUNT(*) FROM team_game_results tgr JOIN games g ON tgr.game_id = g.game_id WHERE g.club_id = ?) +
                            (SELECT COUNT(*) FROM cooperative_game_results cgr JOIN games g ON cgr.game_id = g.game_id WHERE g.club_id = ?)
                        ) as result_count
                ");
                $cStmt->execute([$clubId, $clubId, $clubId, $clubId, $clubId, $clubId, $clubId]);
                $counts = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($counts) {
                    $memberCount = (int)$counts['member_count'];
                    $teamCount = (int)$counts['team_count'];
                    $championCount = (int)$counts['champion_count'];
                    $gameCount = (int)$counts['game_count'];
                    $resultCount = (int)$counts['result_count'];
                }
            } catch (Throwable $e) {}
        }

        $clubQuery = $clubId ? '?club_id=' . (int)$clubId : '';
        $displayName = !empty($clubName) ? $clubName : 'StatApp Admin';

        echo '<aside class="sidebar" style="position:fixed;top:0;left:0;bottom:0;width:260px;background:var(--sidebar-bg, var(--color-surface, #1e293b));border-right:1px solid var(--sidebar-border, var(--color-border, #334155));color:var(--color-text, #f8fafc);z-index:1050;display:flex;flex-direction:column;box-shadow:4px 0 12px rgba(0,0,0,0.15);transition:transform 0.3s ease, background 0.3s ease, border-color 0.3s ease;">';
        echo '<button class="sidebar__close" aria-label="Close menu" style="display:none;position:absolute;top:1rem;right:1rem;width:32px;height:32px;background:rgba(255,255,255,0.1);border:none;border-radius:0.375rem;cursor:pointer;font-size:1.25rem;color:var(--color-text-soft, #94a3b8);align-items:center;justify-content:center;">&times;</button>';

        $activeStyle = 'display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0.85rem;margin:0.25rem 0.5rem;text-decoration:none;font-size:0.875rem;font-weight:500;border-radius:0.5rem;transition:all 0.2s ease;';
        $normalLinkStyle = $activeStyle . 'color:var(--color-text, #f8fafc);background:var(--color-surface-muted, rgba(255,255,255,0.08));border:1px solid var(--color-border, #334155);';
        $activeLinkStyle = $activeStyle . 'color:var(--color-primary, #a5b4fc);background:var(--color-primary-soft, rgba(99,102,241,0.25));border:1px solid var(--color-primary, #6366f1);font-weight:600;';
        $iconStyle = 'width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:1rem;';

        echo '<div class="sidebar__header" style="padding:1rem 0.75rem;border-bottom:1px solid var(--color-border, #334155);flex-shrink:0;">';
        echo '<a href="account.php" class="sidebar__logo" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;color:var(--color-heading, #f1f5f9);font-weight:700;font-size:1.125rem;padding:0.25rem 0.25rem;">';
        $isDemoMode = isset($_GET['demo']) || isset($_GET['preview']);
        if (!empty($clubLogo) && !$isDemoMode) {
            $logoUrl = function_exists('get_club_logo_url') ? get_club_logo_url($clubLogo, '../') : (preg_match('~^https?://~i', $clubLogo) ? $clubLogo : '../images/club_logos/' . $clubLogo);
            echo '<img src="' . htmlspecialchars($logoUrl) . '" alt="" style="width:36px;height:36px;border-radius:0.75rem;object-fit:cover;flex-shrink:0;" onerror="this.onerror=null; this.style.display=\'none\'; if(this.nextElementSibling) this.nextElementSibling.style.display=\'flex\';">';
            echo '<span class="sidebar__logo-icon" style="display:none;width:36px;height:36px;background:linear-gradient(135deg,var(--color-primary, #6366f1),var(--color-accent, #8b5cf6));border-radius:0.75rem;align-items:center;justify-content:center;font-size:1.25rem;color:white;flex-shrink:0;"><span class="material-symbols-outlined" style="font-size:1.4rem;">casino</span></span>';
        } else {
            echo '<span class="sidebar__logo-icon" style="width:36px;height:36px;background:linear-gradient(135deg,var(--color-primary, #6366f1),var(--color-accent, #8b5cf6));border-radius:0.75rem;display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:white;flex-shrink:0;"><span class="material-symbols-outlined" style="font-size:1.4rem;">casino</span></span>';
        }
        echo '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . htmlspecialchars($displayName) . '</span>';
        echo '</a>';
        echo '</div>';

        echo '<nav class="sidebar__nav" style="flex:1;padding:0.75rem 0.5rem;overflow-y:auto;">';

        // Single Row Action Bar: +New Result | Gear (Settings) | Logout
        echo '<div style="display:flex;align-items:center;gap:0.35rem;margin:0 0.25rem 0.75rem 0.25rem;">';
        
        $newResultActive = in_array($currentPage, ['new_result', 'add_result'], true);
        $newResultStyle = 'flex:1;display:flex;align-items:center;justify-content:center;gap:0.35rem;padding:0.55rem 0.6rem;font-size:0.85rem;border-radius:0.5rem;text-decoration:none;transition:all 0.2s ease;';
        if ($newResultActive) {
            $newResultStyle .= 'color:var(--color-primary, #a5b4fc);background:var(--color-primary-soft, rgba(99,102,241,0.25));border:1px solid var(--color-primary, #6366f1);font-weight:600;';
        } else {
            $newResultStyle .= 'color:var(--color-text, #f8fafc);background:var(--color-surface-muted, rgba(255,255,255,0.08));border:1px solid var(--color-border, #334155);font-weight:500;';
        }
        echo '<a href="club_new_results.php' . $clubQuery . '" class="sidebar__link' . ($newResultActive ? ' sidebar__link--active' : '') . '" style="' . $newResultStyle . '" title="Add Result">';
        echo '<span>Add Result</span>';
        echo '</a>';

        $accountActive = ($currentPage === 'account');
        $gearStyle = 'display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:0.5rem;text-decoration:none;transition:all 0.2s ease;flex-shrink:0;';
        if ($accountActive) {
            $gearStyle .= 'background:var(--color-primary-soft, rgba(99,102,241,0.25));color:var(--color-primary, #a5b4fc);border:1px solid var(--color-primary, #6366f1);';
        } else {
            $gearStyle .= 'background:var(--color-surface-muted, rgba(255,255,255,0.08));color:var(--color-text, #f8fafc);border:1px solid var(--color-border, #334155);';
        }
        echo '<a href="account.php" class="sidebar__link' . ($accountActive ? ' sidebar__link--active' : '') . '" style="' . $gearStyle . '" title="Settings & Account">';
        echo '<span class="material-symbols-outlined" style="font-size:1.25rem;">settings</span>';
        echo '</a>';

        $logoutStyle = 'display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:0.5rem;text-decoration:none;color:#f87171;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);transition:background 0.2s;flex-shrink:0;';
        echo '<a href="logout.php" class="sidebar__link sidebar__link--logout" style="' . $logoutStyle . '" title="Logout">';
        echo '<span class="material-symbols-outlined" style="font-size:1.25rem;">logout</span>';
        echo '</a>';
        echo '</div>';

        // Navigation Items (No hr divider)
        $mLabel = 'Members' . ($memberCount !== null ? ' (' . $memberCount . ')' : '');
        $tLabel = 'Teams' . ($teamCount !== null ? ' (' . $teamCount . ')' : '');
        $cLabel = 'Champions' . ($championCount !== null ? ' (' . $championCount . ')' : '');
        $gLabel = 'Games' . ($gameCount !== null ? ' (' . $gameCount . ')' : '');
        $rLabel = 'Results' . ($resultCount !== null ? ' (' . $resultCount . ')' : '');

        echo '<a href="manage_results.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'results' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'results' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">leaderboard</span></span>';
        echo '<span>' . htmlspecialchars($rLabel) . '</span>';
        echo '</a>';

        echo '<a href="manage_members.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'members' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'members' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">group</span></span>';
        echo '<span>' . htmlspecialchars($mLabel) . '</span>';
        echo '</a>';

        echo '<a href="manage_teams.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'teams' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'teams' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">groups</span></span>';
        echo '<span>' . htmlspecialchars($tLabel) . '</span>';
        echo '</a>';

        echo '<a href="manage_champions.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'champions' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'champions' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">emoji_events</span></span>';
        echo '<span>' . htmlspecialchars($cLabel) . '</span>';
        echo '</a>';

        echo '<a href="manage_games.php' . $clubQuery . '" class="sidebar__link' . ($currentPage === 'games' ? ' sidebar__link--active' : '') . '" style="' . ($currentPage === 'games' ? $activeLinkStyle : $normalLinkStyle) . '">';
        echo '<span class="sidebar__link-icon" style="' . $iconStyle . '"><span class="material-symbols-outlined">casino</span></span>';
        echo '<span>' . htmlspecialchars($gLabel) . '</span>';
        echo '</a>';

        echo '</nav>';
        echo '</aside>';

        echo '<div class="sidebar-overlay" aria-hidden="true"></div>';

        if (!empty($_SESSION['is_impersonating'])) {
            $adminUsername = htmlspecialchars($_SESSION['admin_username'] ?? '');
            echo '<div style="background:#fef3c7;border-bottom:2px solid #f59e0b;padding:0.6rem 1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;font-size:0.9rem;position:sticky;top:0;z-index:1040;margin-left:260px;" class="impersonation-banner">';
            echo '<span>⚠️ You are viewing as <strong>' . $adminUsername . '</strong> (impersonation mode).</span>';
            echo '<a href="../super_admin/return_to_super_admin.php" style="font-weight:600;color:#92400e;text-decoration:underline;">← Return to Super Admin Panel</a>';
            echo '</div>';
            
            echo '<style>
                @media(max-width:768px) {
                    .impersonation-banner { margin-left: 0 !important; }
                }
            </style>';
        }
    }
}
