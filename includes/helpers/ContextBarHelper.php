<?php
/**
 * Context Bar Helper
 * Renders page headers, context bars, compact headers, and quick action buttons.
 */

class ContextBarHelper {
    /**
     * Render context bar showing what club/game/member the user is viewing
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
     * Render clickable header title
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
        
        echo '</div>';
    }

    /**
     * Render compact header for admin views
     */
    public static function renderCompactHeader($title, $subtitle = '', $actions = []) {
        echo '<div class="compact-header-content">';
        echo '<div>';
        echo '<h1 class="compact-header__title">' . htmlspecialchars($title) . '</h1>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render quick action buttons
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
}
