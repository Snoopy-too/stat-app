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
     * Render public navigation menu
     * @param string $currentPage The current page identifier
     * @param int|null $clubId Optional club ID for context-specific links
     */
    public static function renderPublicNav($currentPage = '', $clubId = null) {
        echo '<nav class="main-nav" aria-label="Main navigation">';
        
        echo '<a href="index.php" class="nav-link ' . ($currentPage === 'home' ? 'active' : '') . '">';
        echo '<span class="nav-icon">🏠</span>Home</a>';
        
        if ($clubId) {
            echo '<a href="club_stats.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'club_stats' ? 'active' : '') . '">';
            echo '<span class="nav-icon">📊</span>Club Stats</a>';
            
            echo '<a href="club_game_list.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'games' ? 'active' : '') . '">';
            echo '<span class="nav-icon">🎲</span>Games</a>';
            
            echo '<a href="club_game_results.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'results' ? 'active' : '') . '">';
            echo '<span class="nav-icon">🏆</span>Results</a>';
            
            echo '<a href="game_days.php?id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'game_days' ? 'active' : '') . '">';
            echo '<span class="nav-icon">📅</span>Game Days</a>';
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
        
        echo '<a href="dashboard.php" class="nav-link ' . ($currentPage === 'dashboard' ? 'active' : '') . '">';
        echo '<span class="nav-icon">📊</span>Dashboard</a>';
        
        echo '<a href="manage_clubs.php" class="nav-link ' . ($currentPage === 'clubs' ? 'active' : '') . '">';
        echo '<span class="nav-icon">🎯</span>Clubs</a>';
        
        if ($clubId) {
            echo '<a href="manage_members.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'members' ? 'active' : '') . '">';
            echo '<span class="nav-icon">👥</span>Members</a>';
            
            echo '<a href="manage_games.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'games' ? 'active' : '') . '">';
            echo '<span class="nav-icon">🎲</span>Games</a>';
            
            echo '<a href="manage_champions.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'champions' ? 'active' : '') . '">';
            echo '<span class="nav-icon">🏆</span>Champions</a>';
            
            echo '<a href="club_teams.php?club_id=' . (int)$clubId . '" class="nav-link ' . ($currentPage === 'teams' ? 'active' : '') . '">';
            echo '<span class="nav-icon">🤝</span>Teams</a>';
        }
        
        echo '<a href="account.php" class="nav-link ' . ($currentPage === 'account' ? 'active' : '') . '">';
        echo '<span class="nav-icon">⚙️</span>Account</a>';
        
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
}
