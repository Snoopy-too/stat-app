<?php
/**
 * Navigation Helper Facade
 * Provides a unified API for navigation components while delegating to specialized helper classes.
 */

require_once __DIR__ . '/helpers/BreadcrumbHelper.php';
require_once __DIR__ . '/helpers/ContextBarHelper.php';
require_once __DIR__ . '/helpers/SidebarHelper.php';

class NavigationHelper {

    public static function renderBreadcrumbs($items) {
        BreadcrumbHelper::render($items);
    }

    public static function renderContextBar($label, $value, $linkText = null, $linkUrl = null) {
        ContextBarHelper::renderContextBar($label, $value, $linkText, $linkUrl);
    }

    public static function renderHeaderTitle($title, $subtitle = '', $homeUrl = 'index.php', $makeClickable = true) {
        ContextBarHelper::renderHeaderTitle($title, $subtitle, $homeUrl, $makeClickable);
    }

    public static function renderCompactHeader($title, $subtitle = '', $actions = []) {
        ContextBarHelper::renderCompactHeader($title, $subtitle, $actions);
    }

    public static function renderQuickActions($actions) {
        ContextBarHelper::renderQuickActions($actions);
    }

    public static function renderMobileCardNav($currentPage = '', $clubId = null) {
        SidebarHelper::renderMobileCardNav($currentPage, $clubId);
    }

    public static function renderPublicNav($currentPage = '', $clubId = null) {
        SidebarHelper::renderPublicNav($currentPage, $clubId);
    }

    public static function renderAdminNav($currentPage = '', $clubId = null) {
        SidebarHelper::renderAdminNav($currentPage, $clubId);
    }

    public static function renderSidebar($currentPage = '', $clubId = null, $clubName = null, $clubLogo = null) {
        SidebarHelper::renderSidebar($currentPage, $clubId, $clubName, $clubLogo);
    }

    public static function renderSidebarToggle() {
        SidebarHelper::renderSidebarToggle();
    }

    public static function renderAdminSidebar($currentPage = '', $clubId = null, $clubName = null, $clubLogo = null) {
        SidebarHelper::renderAdminSidebar($currentPage, $clubId, $clubName, $clubLogo);
    }

    public static function getClubName($pdo, $clubId) {
        $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
        $stmt->execute([$clubId]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);
        return $club ? $club['club_name'] : '';
    }

    public static function getGameName($pdo, $gameId) {
        $stmt = $pdo->prepare("SELECT game_name FROM games WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        return $game ? $game['game_name'] : '';
    }

    public static function getMemberNickname($pdo, $memberId) {
        $stmt = $pdo->prepare("SELECT nickname FROM members WHERE member_id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        return $member ? $member['nickname'] : '';
    }
}
