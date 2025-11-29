# Navigation Quick Reference Guide

Quick reference for using the NavigationHelper class in your pages.

---

## 1. Setup

```php
<?php
// Add at the top of your file (after other requires)
require_once 'includes/NavigationHelper.php';  // Public pages
// OR
require_once '../includes/NavigationHelper.php';  // Admin pages
```

---

## 2. Breadcrumbs

### Simple Breadcrumbs
```php
<?php
// In <body> tag, before header
NavigationHelper::renderBreadcrumbs([
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'Parent', 'url' => 'parent.php'],
    'Current Page'  // Last item is a string (not clickable)
]);
?>
```

### With Dynamic Data
```php
<?php
NavigationHelper::renderBreadcrumbs([
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => $club['club_name'], 'url' => 'club_stats.php?id=' . $club_id],
    ['label' => 'Games', 'url' => 'club_game_list.php?id=' . $club_id],
    htmlspecialchars($game['game_name'])
]);
?>
```

---

## 3. Header Title

### Clickable Header (recommended)
```php
<div class="header">
    <?php NavigationHelper::renderHeaderTitle('Page Title', 'Optional Subtitle', 'index.php'); ?>
    <div class="header-actions">
        <!-- Your buttons here -->
    </div>
</div>
```

### Non-Clickable Header
```php
<?php NavigationHelper::renderHeaderTitle('Page Title', 'Subtitle', 'index.php', false); ?>
```

---

## 4. Navigation Menus

### Public Navigation
```php
<?php
// After header, before container
NavigationHelper::renderPublicNav('current_page', $club_id);
?>
```

**Page IDs:**
- `'home'` - Home page
- `'club_stats'` - Club stats page
- `'games'` - Games page
- `'results'` - Results page
- `'game_days'` - Game days page

### Admin Navigation
```php
<?php
NavigationHelper::renderAdminNav('current_page', $club_id);
?>
```

**Page IDs:**
- `'dashboard'` - Dashboard
- `'clubs'` - Manage clubs
- `'members'` - Manage members
- `'games'` - Manage games
- `'champions'` - Manage champions
- `'teams'` - Manage teams
- `'account'` - Account settings

---

## 5. Context Bar

```php
<?php
NavigationHelper::renderContextBar(
    'Label',           // e.g., 'Currently viewing'
    'Value',           // e.g., $club['club_name']
    'Link Text',       // e.g., 'View all clubs' (optional)
    'link_url.php'     // URL for the link (optional)
);
?>
```

### Examples
```php
// With link
NavigationHelper::renderContextBar('Managing members for', $club_name, 'View all clubs', 'manage_clubs.php');

// Without link
NavigationHelper::renderContextBar('Currently viewing', $member_name, null, null);
```

---

## 6. Quick Actions

```php
<?php
NavigationHelper::renderQuickActions([
    ['label' => 'Back to Games', 'url' => 'games.php', 'icon' => '←'],
    ['label' => 'Home', 'url' => 'index.php', 'icon' => '🏠', 'style' => 'btn--ghost']
]);
?>
```

---

## 7. Complete Page Template

### Public Page
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

// Your page logic here
$club_id = $_GET['id'] ?? 0;
// ... fetch data ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Title - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <?php
    // Breadcrumbs
    NavigationHelper::renderBreadcrumbs([
        ['label' => 'Home', 'url' => 'index.php'],
        ['label' => $club_name, 'url' => 'club_stats.php?id=' . $club_id],
        'Current Page'
    ]);
    ?>
    
    <!-- Header -->
    <div class="header">
        <?php NavigationHelper::renderHeaderTitle('Board Game Club StatApp', 'Page Subtitle', 'index.php'); ?>
        <div class="header-actions">
            <a href="parent.php" class="btn btn--secondary btn--small">← Back</a>
            <a href="index.php" class="btn btn--ghost btn--small">🏠 Home</a>
        </div>
    </div>
    
    <?php
    // Navigation menu
    NavigationHelper::renderPublicNav('current_page', $club_id);
    
    // Context bar
    NavigationHelper::renderContextBar('Viewing', $club_name, 'View all clubs', 'index.php');
    ?>
    
    <!-- Your content -->
    <div class="container">
        <!-- Page content here -->
    </div>
    
    <!-- Scripts -->
    <script src="js/mobile-menu.js"></script>
</body>
</html>
```

### Admin Page
```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';
require_once '../includes/NavigationHelper.php';

// Auth check
if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// Your page logic here
$club_id = $_GET['club_id'] ?? 0;
// ... fetch data ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <?php
    // Breadcrumbs
    NavigationHelper::renderBreadcrumbs([
        ['label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['label' => 'Parent', 'url' => 'parent.php'],
        'Current Page'
    ]);
    ?>
    
    <!-- Header -->
    <div class="header">
        <div class="header-title-group">
            <?php NavigationHelper::renderHeaderTitle('Admin Page Title', $club_name, 'dashboard.php', false); ?>
        </div>
        <div class="header-actions">
            <a href="../club_stats.php?id=<?php echo $club_id; ?>" class="btn btn--ghost btn--small" target="_blank" title="View on public site">👁️ Preview</a>
            <a href="dashboard.php" class="btn btn--secondary btn--small">🏠 Dashboard</a>
            <a href="parent.php" class="btn btn--secondary btn--small">← Back</a>
        </div>
    </div>
    
    <?php
    // Admin navigation
    NavigationHelper::renderAdminNav('current_page', $club_id);
    
    // Context bar
    if ($club_id && $club_name) {
        NavigationHelper::renderContextBar('Managing', $club_name, 'View all clubs', 'manage_clubs.php');
    }
    ?>
    
    <!-- Your content -->
    <div class="container">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>
        
        <!-- Page content here -->
    </div>
    
    <!-- Scripts -->
    <script src="../js/mobile-menu.js"></script>
</body>
</html>
```

---

## 8. Helper Methods

### Get Names from Database
```php
// Get club name
$club_name = NavigationHelper::getClubName($pdo, $club_id);

// Get game name
$game_name = NavigationHelper::getGameName($pdo, $game_id);

// Get member nickname
$member_name = NavigationHelper::getMemberNickname($pdo, $member_id);
```

---

## 9. Common Patterns

### Preview Button (Admin→Public)
```html
<a href="../club_stats.php?id=<?php echo $club_id; ?>" 
   class="btn btn--ghost btn--small" 
   target="_blank" 
   title="View on public site">👁️ Preview</a>
```

### Dashboard Quick Link
```html
<a href="dashboard.php" class="btn btn--secondary btn--small">🏠 Dashboard</a>
```

### Back with Icon
```html
<a href="parent.php" class="btn btn--secondary btn--small">← Back to Parent</a>
```

---

## 10. Icons Reference

- 🏠 - Home
- 📊 - Dashboard/Stats
- 🎲 - Games
- 👥 - Members/Users
- 🏆 - Champions/Results
- 📅 - Calendar/Game Days
- ⚙️ - Settings/Account
- 👁️ - Preview/View
- ← - Back
- 🎯 - Clubs/Target
- 🤝 - Teams

---

## 11. CSS Classes

### Button Styles
- `btn` - Primary button
- `btn--secondary` - Secondary button
- `btn--ghost` - Ghost/transparent button
- `btn--subtle` - Subtle button
- `btn--small` - Small size
- `btn--xsmall` - Extra small size

### Navigation Active State
```php
class="nav-link <?php echo $current_page === 'page_id' ? 'active' : ''; ?>"
```

---

## 12. Mobile Considerations

Breadcrumbs automatically:
- Hide intermediate items on mobile
- Show ellipsis (...) if items are hidden
- Always show first and last items

Navigation menus:
- Horizontally scrollable on mobile
- Touch-friendly spacing

---

## 13. Accessibility

All navigation components include:
- ARIA labels
- Semantic HTML
- Keyboard navigation support
- Focus indicators

---

## 14. Troubleshooting

### Breadcrumbs not showing
- Make sure you call `renderBreadcrumbs()` inside `<body>` tag
- Check that NavigationHelper is included
- Verify CSS file is loaded (breadcrumbs.css)

### Navigation menu broken
- Verify $club_id is set (or null for non-club pages)
- Check page ID matches defined IDs
- Ensure CSS styles.css is loaded

### Preview links not working
- Check absolute paths (`../` for admin pages)
- Verify target page accepts ID parameter
- Check database has valid IDs

---

## 15. Best Practices

✅ **DO:**
- Include breadcrumbs on pages 2+ levels deep
- Use clickable header titles
- Provide multiple navigation options
- Add preview links in admin pages
- Use context bars for clarity

❌ **DON'T:**
- Duplicate navigation (breadcrumb + nav menu is fine)
- Hard-code navigation (use helpers)
- Forget mobile responsiveness
- Skip accessibility features

---

**Questions?** See `NAVIGATION_IMPLEMENTATION.md` for full details.
