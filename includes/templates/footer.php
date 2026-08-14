<?php
/**
 * Shared Footer Template Partial
 * 
 * Available variables:
 * - $basePath (string) Relative path prefix to root
 * - $extraScripts (string) Additional scripts to render before </body>
 */

if (!isset($basePath)) {
    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = (strpos($currentScript, '/admin/') !== false) ? '../' : '';
}
?>
    <script src="<?php echo $basePath; ?>js/mobile-menu.js"></script>
    <?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
