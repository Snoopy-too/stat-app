<?php
/**
 * Built-in PHP Development Server Router
 * Handles routing for stat-app to emulate .htaccess rules
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rawurldecode($uri);

// If the requested resource exists as a file, serve it directly
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

// If the requested resource exists as a directory with an index.php, serve it directly
if ($path !== '/' && is_dir(__DIR__ . $path) && is_file(__DIR__ . $path . '/index.php')) {
    return false;
}

// If it has a static file extension or belongs to static asset directories, do NOT fall back to index.php or slug routing
if (preg_match('/\.(jpe?g|png|gif|webp|svg|ico|css|js|woff2?|ttf|eot|otf|map|json|txt|xml|pdf|mp3|mp4|webm)$/i', $path) ||
    preg_match('#^/(images|uploads|css|js|fonts|data|logs)/#i', $path)) {
    http_response_code(404);
    return false;
}

// Clean slug (trim leading and trailing slashes)
$cleanSlug = trim($path, '/');

// Normalize slug trailing slash if requested with trailing slash e.g. /my-club/ -> /my-club
if (substr($path, -1) === '/' && !empty($cleanSlug) && preg_match('/^[a-zA-Z0-9_-]+$/', $cleanSlug)) {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: /' . $cleanSlug . $queryString, true, 301);
    exit;
}

// Match slug pattern from .htaccess: RewriteRule ^([a-zA-Z0-9_-]+)$ club_game_results.php?slug=$1 [L,QSA]
if (!empty($cleanSlug) && preg_match('/^[a-zA-Z0-9_-]+$/', $cleanSlug) && is_file(__DIR__ . '/club_game_results.php')) {
    $_GET['slug'] = $cleanSlug;
    require __DIR__ . '/club_game_results.php';
    exit;
}

// Root request serves index.php
if ($path === '/' || $path === '/index.php' || $path === '') {
    if (is_file(__DIR__ . '/index.php')) {
        require __DIR__ . '/index.php';
        exit;
    }
}

// Any other non-existent route should return 404, never redirect or fall back to index.php
http_response_code(404);
return false;

