<?php
/**
 * Built-in PHP Development Server Router
 * Handles routing for stat-app to emulate .htaccess rules
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = urldecode($uri);

// If the requested resource exists as a file or directory, serve it directly
if ($path !== '/' && file_exists(__DIR__ . $path)) {
    return false;
}
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Match slug pattern from .htaccess: RewriteRule ^([a-zA-Z0-9-]+)$ club_stats.php?slug=$1 [L,QSA]
$slug = ltrim($uri, '/');
if (!empty($slug) && preg_match('/^[a-zA-Z0-9-]+$/', $slug) && file_exists(__DIR__ . '/club_stats.php')) {
    $_GET['slug'] = $slug;
    require __DIR__ . '/club_stats.php';
    exit;
}

// Fallback to index.php
if (file_exists(__DIR__ . '/index.php')) {
    require __DIR__ . '/index.php';
    exit;
}

return false;
