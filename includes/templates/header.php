<?php
/**
 * Shared Header Template Partial
 * 
 * Available variables:
 * - $pageTitle (string) Page title for <title> tag
 * - $bodyClass (string) Class(es) for <body> element
 * - $basePath  (string) Relative path prefix to root, e.g. '' or '../'
 * - $extraHead (string) Additional raw HTML to include in <head>
 */

if (!isset($basePath)) {
    // Auto-detect if executing inside a subfolder like 'admin/'
    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = (strpos($currentScript, '/admin/') !== false) ? '../' : '';
}

$pageTitle = $pageTitle ?? 'Board Game Club StatApp';
$bodyClass = $bodyClass ?? '';
$htmlAttributes = $htmlAttributes ?? '';
?>
<!DOCTYPE html>
<html lang="en" <?php echo $htmlAttributes; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $basePath; ?>favicon.svg?v=4">
    <link rel="alternate icon" type="image/x-icon" href="<?php echo $basePath; ?>favicon.ico?v=4">
    <link rel="stylesheet" href="<?php echo $basePath; ?>css/styles.css">
    <script src="<?php echo $basePath; ?>js/dark-mode.js"></script>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">
